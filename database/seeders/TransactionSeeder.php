<?php

namespace Database\Seeders;

use App\Models\Deposit;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // AI generated seeder
        $users = User::with('wallet')->get();

        $this->seedDeposits($users);

        $this->seedWithdrawals($users);

        $this->seedTransfers($users);

        $this->recalculateWalletBalances();
    }

    protected function seedDeposits($users): void
    {
        $depositCount = 0;

        foreach ($users->take(30) as $user) {
            $numDeposits = rand(1, 5);

            for ($i = 0; $i < $numDeposits; $i++) {
                DB::transaction(function () use ($user) {
                    $amount = fake()->randomFloat(2, 100, 2000);

                    $transaction = Transaction::query()->create([
                        'transaction_id' => Str::uuid(),
                        'type' => 'deposit',
                        'status' => 'completed',
                        'amount' => $amount,
                        'fee' => 0,
                        'completed_at' => fake()->dateTimeBetween('-30 days', 'now'),
                    ]);

                    $deposit = Deposit::query()->create([
                        'transaction_id' => $transaction->transaction_id,
                        'wallet_id' => $user->wallet->id,
                        'amount' => $amount,
                        'payment_method' => fake()->randomElement(['bank_transfer', 'card', 'cash']),
                        'payment_reference' => fake()->uuid(),
                    ]);

                    $this->createLedgerEntry(
                        $user->wallet,
                        'credit',
                        $amount,
                        $transaction->transaction_id,
                        'deposit',
                        $deposit->id,
                        'Deposit via ' . $deposit->payment_method
                    );
                });

                $depositCount++;
            }
        }

        $this->command->info('Created ' . $depositCount . ' deposit transactions');
    }

    /**
     * Seed withdrawal transactions
     */
    protected function seedWithdrawals($users): void
    {
        $withdrawalCount = 0;

        foreach ($users->take(20) as $user) {
            if ($user->wallet->balance < 200) {
                continue;
            }

            $numWithdrawals = rand(1, 3);

            for ($i = 0; $i < $numWithdrawals; $i++) {
                DB::transaction(function () use ($user) {
                    $maxAmount = min($user->wallet->balance * 0.3, 1000);
                    $amount = fake()->randomFloat(2, 50, $maxAmount);

                    $transaction = Transaction::query()->create([
                        'transaction_id' => Str::uuid(),
                        'type' => Transaction::TRANSACTION_TYPE_WITHDRAWAL,
                        'status' => fake()->randomElement([Transaction::TRANSACTION_STATUS_COMPLETED,Transaction::TRANSACTION_STATUS_FAILED, Transaction::TRANSACTION_STATUS_PROCESSING, Transaction::TRANSACTION_STATUS_PENDING]),
                        'amount' => $amount,
                        'fee' => 0,
                        'completed_at' => fake()->dateTimeBetween('-20 days', 'now'),
                    ]);

                    $withdrawal = Withdrawal::query()->create([
                        'transaction_id' => $transaction->transaction_id,
                        'wallet_id' => $user->wallet->id,
                        'amount' => $amount,
                        'withdrawal_method' => fake()->randomElement(['bank_transfer', 'card']),
                        'withdrawal_reference' => fake()->iban(),
                    ]);

                    if ($transaction->status === 'completed') {
                        $this->createLedgerEntry(
                            $user->wallet,
                            'debit',
                            $amount,
                            $transaction->transaction_id,
                            'withdrawal',
                            $withdrawal->id,
                            'Withdrawal via ' . $withdrawal->withdrawal_method
                        );
                    }
                });

                $withdrawalCount++;
            }
        }

        $this->command->info('Created ' . $withdrawalCount . ' withdrawal transactions');
    }

    /**
     * Seed transfer transactions
     */
    protected function seedTransfers($users): void
    {
        $transferCount = 0;
        $activeWallets = Wallet::query()->where('status', 'active')->pluck('id')->toArray();

        foreach ($users->take(40) as $user) {
            // Skip if wallet is not active or has low balance
            if (!$user->wallet->isActive() || $user->wallet->balance < 100) {
                continue;
            }

            $numTransfers = rand(1, 5);

            for ($i = 0; $i < $numTransfers; $i++) {
                // Find a random receiver (not self)
                $receiverWalletId = fake()->randomElement(
                    array_diff($activeWallets, [$user->wallet->id])
                );

                if (!$receiverWalletId) {
                    continue;
                }

                $receiverWallet = Wallet::find($receiverWalletId);

                DB::transaction(function () use ($user, $receiverWallet) {
                    $maxAmount = min($user->wallet->balance * 0.2, 500);
                    $amount = fake()->randomFloat(2, 10, $maxAmount);

                    // Calculate fee
                    $fee = $amount > 25 ? (2.50 + ($amount * 0.10)) : 2.50;
                    $totalDebit = $amount + $fee;

                    // Skip if insufficient balance
                    if ($user->wallet->balance < $totalDebit) {
                        return;
                    }

                    // Create transaction
                    $transaction = Transaction::query()->create([
                        'transaction_id' => Str::uuid(),
                        'type' => Transaction::TRANSACTION_TYPE_TRANSFER,
                        'status' => Transaction::TRANSACTION_STATUS_COMPLETED,
                        'amount' => $amount,
                        'fee' => round($fee, 2),
                        'completed_at' => fake()->dateTimeBetween('-15 days', 'now'),
                    ]);

                    $transfer = Transfer::query()->create([
                        'transaction_id' => $transaction->transaction_id,
                        'sender_wallet_id' => $user->wallet->id,
                        'receiver_wallet_id' => $receiverWallet->id,
                        'amount' => $amount,
                        'fee' => round($fee, 2),
                    ]);

                    // Create ledger entries for sender
                    $this->createLedgerEntry(
                        $user->wallet,
                        'debit',
                        $amount,
                        $transaction->transaction_id,
                        'transfer',
                        $transfer->id,
                        'Transfer to wallet ' . $receiverWallet->wallet_number
                    );

                    if ($fee > 0) {
                        $this->createLedgerEntry(
                            $user->wallet,
                            'fee',
                            $fee,
                            $transaction->transaction_id,
                            'transfer',
                            $transfer->id,
                            'Transfer fee'
                        );
                    }

                    // Create ledger entry for receiver
                    $this->createLedgerEntry(
                        $receiverWallet,
                        'credit',
                        $amount,
                        $transaction->transaction_id,
                        'transfer',
                        $transfer->id,
                        'Transfer from wallet ' . $user->wallet->wallet_number
                    );
                });

                $transferCount++;
            }
        }

        $this->command->info('Created ' . $transferCount . ' transfer transactions');
    }

    /**
     * Create a ledger entry
     */
    protected function createLedgerEntry($wallet, $type, $amount, $transactionId, $referenceType, $referenceId, $description): void
    {
        // Get current balance from latest ledger entry or wallet
        $lastEntry = LedgerEntry::query()->where('wallet_id', $wallet->id)
            ->orderBy('id', 'desc')
            ->first();

        $balanceBefore = $lastEntry ? $lastEntry->balance_after : $wallet->balance;

        $balanceAfter = match($type) {
            'credit' => $balanceBefore + $amount,
            'debit', 'fee' => $balanceBefore - $amount,
        };

        LedgerEntry::query()->create([
            'transaction_id' => $transactionId,
            'wallet_id' => $wallet->id,
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => max(0, $balanceAfter), // Ensure non-negative
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
        ]);
    }

    /**
     * Recalculate wallet balances based on ledger entries
     */
    protected function recalculateWalletBalances(): void
    {
        $wallets = Wallet::all();

        foreach ($wallets as $wallet) {
            $balance = 0;
            $entries = LedgerEntry::query()->where('wallet_id', $wallet->id)
                ->orderBy('id')
                ->get();

            foreach ($entries as $entry) {
                $balance = match($entry->type) {
                    LedgerEntry::LEDGER_TYPE_CREDIT => $balance + $entry->amount,
                    LedgerEntry::LEDGER_TYPE_DEBIT, LedgerEntry::LEDGER_TYPE_FEE => $balance - $entry->amount,
                };
            }

            $wallet->update([
                'balance' => max(0, $balance),
            ]);
        }

        $this->command->info('Recalculated balances for ' . $wallets->count() . ' wallets');
    }
}
