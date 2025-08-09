<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TransactionService;
use App\Services\TransferService;
use App\Models\Wallet;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WalletStressWorker extends Command
{
    protected $signature = 'wallet:stress-worker
                            {--operations=10 : Number of operations to perform}
                            {--type=mixed : Type of operations (deposit|withdraw|transfer|mixed)}
                            {--delay=0 : Delay between operations in milliseconds}
                            {--process=0 : Process identifier}
                            {--wallets= : Comma-separated wallet IDs (optional, uses random wallets if not specified)}
                            {--wallet-count=20 : Number of random wallets to use if wallet IDs not specified}
                            {--min-balance=100 : Minimum balance for active wallets}';

    protected $description = 'Worker process for stress testing';

    protected $hidden = true;

    private TransactionService $transactionService;
    private TransferService $transferService;

    public function __construct()
    {
        parent::__construct();
        $this->transactionService = app(TransactionService::class);
        $this->transferService = app(TransferService::class);
    }

    public function handle()
    {
        $operations = (int) $this->option('operations');
        $type = $this->option('type');
        $delay = (int) $this->option('delay');
        $processId = $this->option('process');
        $walletOption = $this->option('wallets');
        $walletCount = (int) $this->option('wallet-count');
        $minBalance = (float) $this->option('min-balance');

        // Get wallets based on input
        if ($walletOption) {
            // Use specified wallet IDs
            $walletIds = array_map('trim', explode(',', $walletOption));
            $wallets = Wallet::whereIn('id', $walletIds)
                ->where('status', 'active')
                ->get();

            if ($wallets->isEmpty()) {
                $this->error("No active wallets found with IDs: {$walletOption}");
                return 1;
            }
        } else {
            // Get random active wallets
            $wallets = Wallet::where('status', 'active')
                ->where('balance', '>=', $minBalance)
                ->inRandomOrder()
                ->limit($walletCount)
                ->get();

            if ($wallets->isEmpty()) {
                $this->error("No active wallets found with minimum balance of {$minBalance}");
                return 1;
            }

            $this->info("Process {$processId}: Using {$wallets->count()} random wallets");
        }

        $successful = 0;
        $failed = 0;
        $errors = [];

        // Display progress bar in console
        $bar = $this->output->createProgressBar($operations);
        $bar->start();

        for ($i = 0; $i < $operations; $i++) {
            try {
                $this->performOperation($wallets, $type, $processId, $i);
                $successful++;
            } catch (\Exception $e) {
                $failed++;
                $errorMessage = class_basename($e) . ': ' . $e->getMessage();

                // Track error types
                if (!isset($errors[$errorMessage])) {
                    $errors[$errorMessage] = 0;
                }
                $errors[$errorMessage]++;

                // Log detailed error only for first few occurrences
                if ($errors[$errorMessage] <= 3) {
                    Log::warning("Process {$processId} operation {$i} failed", [
                        'error' => $errorMessage,
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            $bar->advance();

            if ($delay > 0) {
                usleep($delay * 1000); // Convert to microseconds
            }
        }

        $bar->finish();
        $this->newLine();

        // Display results
        $this->line("Process {$processId} completed: Successful: {$successful}, Failed: {$failed}");

        // Show error summary if there were failures
        if (!empty($errors)) {
            $this->line("Error summary:");
            foreach ($errors as $error => $count) {
                $this->line("  - {$error}: {$count} times");
            }
        }

        // Log summary to file
        Log::info("Stress test process {$processId} completed", [
            'process_id' => $processId,
            'successful' => $successful,
            'failed' => $failed,
            'errors' => $errors,
            'wallet_count' => $wallets->count()
        ]);

        return 0;
    }

    private function performOperation($wallets, $type, $processId, $operationId)
    {
        $wallet = $wallets->random();

        if ($type === 'mixed') {
            // Weighted random selection for more realistic testing
            $weights = [
                'deposit' => 30,    // 30% deposits
                'withdraw' => 30,   // 30% withdrawals
                'transfer' => 40    // 40% transfers
            ];

            $type = $this->weightedRandom($weights);
        }

        switch ($type) {
            case 'deposit':
                $amount = $this->randomAmount(50, 500);
                $this->transactionService->deposit([
                    'wallet_id' => $wallet->id,
                    'amount' => $amount,
                    'payment_method' => $this->randomPaymentMethod(),
                    'payment_reference' => "REF_P{$processId}_O{$operationId}",
                    'idempotency_key' => "stress_dep_p{$processId}_o{$operationId}_" . Str::random(8)
                ]);
                break;

            case 'withdraw':
                $currentBalance = $wallet->fresh()->balance;
                if ($currentBalance >= 100) {
                    $maxWithdraw = min(500, $currentBalance * 0.7); // Max 70% of balance
                    $amount = $this->randomAmount(20, $maxWithdraw);

                    $this->transactionService->withdraw([
                        'wallet_id' => $wallet->id,
                        'amount' => $amount,
                        'withdrawal_method' => $this->randomWithdrawalMethod(),
                        'withdrawal_reference' => "WD_P{$processId}_O{$operationId}",
                        'idempotency_key' => "stress_wd_p{$processId}_o{$operationId}_" . Str::random(8)
                    ]);
                } else {
                    throw new \Exception("Insufficient balance for withdrawal");
                }
                break;

            case 'transfer':
                $targetWallet = $wallets->where('id', '!=', $wallet->id)->random();
                if (!$targetWallet) {
                    throw new \Exception("No target wallet available for transfer");
                }

                $currentBalance = $wallet->fresh()->balance;
                if ($currentBalance >= 150) {
                    $maxTransfer = min(300, $currentBalance * 0.5); // Max 50% of balance
                    $amount = $this->randomAmount(10, $maxTransfer);

                    $this->transferService->executeTransfer(
                        $wallet->id,
                        $targetWallet->wallet_number,
                        $amount,
                        "stress_tr_p{$processId}_o{$operationId}_" . Str::random(8)
                    );
                } else {
                    throw new \Exception("Insufficient balance for transfer");
                }
                break;

            default:
                throw new \Exception("Unknown operation type: {$type}");
        }
    }

    /**
     * Generate random amount with realistic distribution
     */
    private function randomAmount($min, $max)
    {
        // Use log-normal distribution for more realistic amounts
        // Most transactions will be smaller amounts
        $logMin = log($min);
        $logMax = log($max);
        $logAmount = $logMin + (mt_rand() / mt_getrandmax()) * ($logMax - $logMin);

        return round(exp($logAmount), 2);
    }

    /**
     * Weighted random selection
     */
    private function weightedRandom(array $weights)
    {
        $totalWeight = array_sum($weights);
        $random = mt_rand(1, $totalWeight);

        foreach ($weights as $key => $weight) {
            if ($random <= $weight) {
                return $key;
            }
            $random -= $weight;
        }

        return array_key_first($weights);
    }

    /**
     * Get random payment method
     */
    private function randomPaymentMethod()
    {
        $methods = ['bank_transfer', 'credit_card', 'debit_card', 'paypal', 'stripe'];
        return $methods[array_rand($methods)];
    }

    /**
     * Get random withdrawal method
     */
    private function randomWithdrawalMethod()
    {
        $methods = ['bank_transfer', 'check', 'wire_transfer'];
        return $methods[array_rand($methods)];
    }
}
