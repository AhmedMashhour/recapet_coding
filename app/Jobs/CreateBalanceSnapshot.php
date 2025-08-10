<?php

namespace App\Jobs;

use App\Models\BalanceSnapshot;
use App\Models\WalletBalanceSnapshot;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\LedgerEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateBalanceSnapshot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ?string $notes;


    public function __construct()
    {
    }


    public function handle(): void
    {

        $systemStats = $this->gatherSystemStatistics();
        $snapshot = BalanceSnapshot::query()->create([
            'snapshot_id' => Str::uuid()->toString(),
            'snapshot_time' => now(),
            'status' => 'processing',
            'notes' => "processing snapshot",
            'total_wallets' => $systemStats['total_wallets'],
            'active_wallets' => $systemStats['active_wallets'],
            'total_deposits' => $systemStats['total_deposits'],
            'total_withdrawals' => $systemStats['total_withdrawals'],
            'total_fees' => $systemStats['total_fees'],
            'calculated_balance' => 0.0,
            'total_balance' => 0.0,
            'balance_discrepancy' => 0.0,
            'wallet_balances' => 0.0,
        ]);

        try {

            $walletSnapshots = [];
            $discrepancies = [];
            $totalWalletBalance = 0;
            $totalLedgerBalance = 0;

            Wallet::chunk(100, function ($wallets) use ($snapshot, &$walletSnapshots, &$discrepancies, &$totalWalletBalance, &$totalLedgerBalance) {
                foreach ($wallets as $wallet) {
                    $walletSnapshot = $this->createWalletSnapshot($wallet, $snapshot);
                    $walletSnapshots[] = $walletSnapshot;

                    $totalWalletBalance += $walletSnapshot['wallet_balance'];
                    $totalLedgerBalance += $walletSnapshot['ledger_balance'];

                    if (abs($walletSnapshot['discrepancy']) > 0.01) {
                        $discrepancies[] = [
                            'wallet_id' => $wallet->id,
                            'wallet_number' => $wallet->wallet_number,
                            'wallet_balance' => $walletSnapshot['wallet_balance'],
                            'ledger_balance' => $walletSnapshot['ledger_balance'],
                            'discrepancy' => $walletSnapshot['discrepancy']
                        ];
                    }
                }
            });

            $expectedBalance = $systemStats['total_deposits'] - $systemStats['total_withdrawals'] - $systemStats['total_fees'];
            $balanceDiscrepancy = $totalWalletBalance - $expectedBalance;

            $snapshot->update([
                'total_balance' => $totalWalletBalance,
                'calculated_balance' => $expectedBalance,
                'balance_discrepancy' => $balanceDiscrepancy,
                'wallet_balances' => array_slice($walletSnapshots, 0, 100), // Store first 100 for quick reference
                'statistics' => [
                    'total_transactions' => $systemStats['total_transactions'],
                    'completed_transactions' => $systemStats['completed_transactions'],
                    'failed_transactions' => $systemStats['failed_transactions'],
                    'wallets_with_discrepancies' => count($discrepancies),
                    'total_ledger_balance' => $totalLedgerBalance,
                    'ledger_vs_wallet_discrepancy' => $totalWalletBalance - $totalLedgerBalance
                ],
                'discrepancies' => $discrepancies,
                'status' => 'completed'
            ]);

            Log::info('Balance snapshot completed', [
                'snapshot_id' => $snapshot->snapshot_id,
                'total_wallets' => $systemStats['total_wallets'],
                'discrepancies_found' => count($discrepancies)
            ]);

        } catch (\Exception $e) {
            Log::error('Balance snapshot failed', [
                'snapshot_id' => $snapshot->snapshot_id,
                'error' => $e->getMessage()
            ]);

            $snapshot->update([
                'status' => 'failed',
                'notes' => ($snapshot->notes ?? '') . ' Error: ' . $e->getMessage()
            ]);

            throw $e;
        }
    }

    protected function gatherSystemStatistics(): array
    {
        return [
            'total_wallets' => Wallet::count(),
            'active_wallets' => Wallet::where('status', 'active')->count(),
            'total_transactions' => Transaction::count(),
            'completed_transactions' => Transaction::where('status', 'completed')->count(),
            'failed_transactions' => Transaction::where('status', 'failed')->count(),
            'total_deposits' => DB::table('deposits')
                ->join('transactions', 'deposits.transaction_id', '=', 'transactions.transaction_id')
                ->where('transactions.status', 'completed')
                ->sum('deposits.amount'),
            'total_withdrawals' => DB::table('withdrawals')
                ->join('transactions', 'withdrawals.transaction_id', '=', 'transactions.transaction_id')
                ->where('transactions.status', 'completed')
                ->sum('withdrawals.amount'),
            'total_fees' => Transaction::where('status', 'completed')
                ->where('type', 'transfer')
                ->sum('fee')
        ];
    }


    protected function createWalletSnapshot(Wallet $wallet, BalanceSnapshot $snapshot): array
    {
        $lastLedgerEntry = LedgerEntry::query()->where('wallet_id', $wallet->id)
            ->orderBy('id', 'desc')
            ->first();

        $ledgerBalance = $lastLedgerEntry ? $lastLedgerEntry->balance_after : 0;
        $discrepancy = $wallet->balance - $ledgerBalance;

        $transactionStats = $this->getWalletTransactionStats($wallet->id);

        $walletSnapshot = WalletBalanceSnapshot::create([
            'snapshot_id' => $snapshot->id,
            'wallet_id' => $wallet->id,
            'wallet_number' => $wallet->wallet_number,
            'wallet_balance' => $wallet->balance,
            'ledger_balance' => $ledgerBalance,
            'discrepancy' => $discrepancy,
            'transaction_count' => $transactionStats['count'],
            'last_transaction_at' => $transactionStats['last_transaction_at'],
            'metadata' => [
                'status' => $wallet->status,
                'deposits' => $transactionStats['deposits'],
                'withdrawals' => $transactionStats['withdrawals'],
                'transfers_sent' => $transactionStats['transfers_sent'],
                'transfers_received' => $transactionStats['transfers_received']
            ]
        ]);

        return $walletSnapshot->toArray();
    }

    protected function getWalletTransactionStats(int $walletId): array
    {
        $stats = [
            'count' => 0,
            'last_transaction_at' => null,
            'deposits' => 0,
            'withdrawals' => 0,
            'transfers_sent' => 0,
            'transfers_received' => 0
        ];

        $deposits = DB::table('deposits')
            ->join('transactions', 'deposits.transaction_id', '=', 'transactions.transaction_id')
            ->where('deposits.wallet_id', $walletId)
            ->where('transactions.status', 'completed')
            ->select(DB::raw('COUNT(*) as count, MAX(transactions.created_at) as last_at'))
            ->first();

        if ($deposits) {
            $stats['deposits'] = $deposits->count;
            $stats['count'] += $deposits->count;
            $stats['last_transaction_at'] = $deposits->last_at;
        }

        $withdrawals = DB::table('withdrawals')
            ->join('transactions', 'withdrawals.transaction_id', '=', 'transactions.transaction_id')
            ->where('withdrawals.wallet_id', $walletId)
            ->where('transactions.status', 'completed')
            ->select(DB::raw('COUNT(*) as count, MAX(transactions.created_at) as last_at'))
            ->first();

        if ($withdrawals) {
            $stats['withdrawals'] = $withdrawals->count;
            $stats['count'] += $withdrawals->count;
            if (!$stats['last_transaction_at'] || $withdrawals->last_at > $stats['last_transaction_at']) {
                $stats['last_transaction_at'] = $withdrawals->last_at;
            }
        }

        $transfers = DB::table('transfers')
            ->join('transactions', 'transfers.transaction_id', '=', 'transactions.transaction_id')
            ->where(function ($q) use ($walletId) {
                $q->where('transfers.sender_wallet_id', $walletId)
                    ->orWhere('transfers.receiver_wallet_id', $walletId);
            })
            ->where('transactions.status', 'completed')
            ->select(
                DB::raw('SUM(CASE WHEN sender_wallet_id = ' . $walletId . ' THEN 1 ELSE 0 END) as sent'),
                DB::raw('SUM(CASE WHEN receiver_wallet_id = ' . $walletId . ' THEN 1 ELSE 0 END) as received'),
                DB::raw('MAX(transactions.created_at) as last_at')
            )
            ->first();

        if ($transfers) {
            $stats['transfers_sent'] = $transfers->sent ?? 0;
            $stats['transfers_received'] = $transfers->received ?? 0;
            $stats['count'] += ($transfers->sent ?? 0) + ($transfers->received ?? 0);
            if (!$stats['last_transaction_at'] || ($transfers->last_at && $transfers->last_at > $stats['last_transaction_at'])) {
                $stats['last_transaction_at'] = $transfers->last_at;
            }
        }

        return $stats;
    }
}
