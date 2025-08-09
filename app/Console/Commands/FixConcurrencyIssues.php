<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Wallet;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\Deposit;
use App\Models\Withdrawal;
use App\Models\Transfer;

class FixConcurrencyIssues extends Command
{
    protected $signature = 'wallet:fix-concurrency
                            {--dry-run : Show what would be fixed without making changes}
                            {--wallet= : Fix specific wallet only}
                            {--rebuild-ledger : Rebuild entire ledger from transactions}';

    protected $description = 'Fix concurrency-related issues in wallet system';

    private $dryRun = false;
    private $fixedCount = 0;

    public function handle()
    {
        $this->dryRun = $this->option('dry-run');

        $this->info('🔧 Fixing Concurrency Issues in Wallet System');
        $this->info('============================================');

        if ($this->dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        if ($this->option('rebuild-ledger')) {
            $this->rebuildLedgerFromTransactions();
        } else {
            $this->fixLedgerGaps();
            $this->recalculateWalletBalances();
            $this->fixOrphanedTransactions();
        }

        $this->info("\n✅ Fixed {$this->fixedCount} issues");

        return 0;
    }

    /**
     * Fix ledger gaps by rebuilding the chain
     */
    private function fixLedgerGaps()
    {
        $this->info("\n1. Fixing Ledger Gaps...");

        $walletFilter = $this->option('wallet') ? ['wallet_id' => $this->option('wallet')] : [];

        $walletIds = LedgerEntry::where($walletFilter)
            ->distinct('wallet_id')
            ->pluck('wallet_id');

        $bar = $this->output->createProgressBar($walletIds->count());

        foreach ($walletIds as $walletId) {
            $this->fixWalletLedger($walletId);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Fix ledger for a specific wallet
     */
    private function fixWalletLedger($walletId)
    {
        $entries = LedgerEntry::where('wallet_id', $walletId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($entries->isEmpty()) {
            return;
        }

        $runningBalance = 0;
        $previousEntry = null;

        foreach ($entries as $entry) {
            $expectedBalanceBefore = $previousEntry ? $previousEntry->balance_after : 0;

            // Check if there's a gap
            if (abs($entry->balance_before - $expectedBalanceBefore) > 0.01) {
                $this->fixedCount++;

                if (!$this->dryRun) {
                    // Fix the gap
                    $entry->balance_before = $expectedBalanceBefore;

                    // Recalculate balance_after
                    switch ($entry->type) {
                        case 'credit':
                            $entry->balance_after = $entry->balance_before + $entry->amount;
                            break;
                        case 'debit':
                        case 'fee':
                            $entry->balance_after = max(0, $entry->balance_before - $entry->amount);
                            break;
                    }

                    $entry->save();
                } else {
                    $this->line("Would fix wallet {$walletId} ledger entry {$entry->id}: balance_before {$entry->balance_before} -> {$expectedBalanceBefore}");
                }
            }

            $runningBalance = $entry->balance_after;
            $previousEntry = $entry;
        }
    }

    /**
     * Recalculate wallet balances from ledger
     */
    private function recalculateWalletBalances()
    {
        $this->info("\n2. Recalculating Wallet Balances...");

        $walletFilter = $this->option('wallet') ? ['id' => $this->option('wallet')] : [];
        $wallets = Wallet::where($walletFilter)->get();

        $bar = $this->output->createProgressBar($wallets->count());

        foreach ($wallets as $wallet) {
            $lastLedgerEntry = LedgerEntry::where('wallet_id', $wallet->id)
                ->orderBy('id', 'desc')
                ->first();

            if ($lastLedgerEntry) {
                $correctBalance = $lastLedgerEntry->balance_after;

                if (abs($wallet->balance - $correctBalance) > 0.01) {
                    $this->fixedCount++;

                    if (!$this->dryRun) {
                        $wallet->update(['balance' => $correctBalance]);
                    } else {
                        $this->line("Would update wallet {$wallet->id} balance: {$wallet->balance} -> {$correctBalance}");
                    }
                }
            } elseif ($wallet->balance != 0) {
                // Wallet has balance but no ledger entries - need to investigate
                $this->warn("Wallet {$wallet->id} has balance {$wallet->balance} but no ledger entries!");

                // Try to rebuild from transactions
                $this->rebuildWalletLedger($wallet);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Rebuild ledger for a specific wallet from transactions
     */
    private function rebuildWalletLedger(Wallet $wallet)
    {
        $this->info("Rebuilding ledger for wallet {$wallet->id}...");

        // Get all transactions for this wallet
        $transactions = collect();

        // Deposits
        $deposits = Deposit::where('wallet_id', $wallet->id)
            ->join('transactions', 'deposits.transaction_id', '=', 'transactions.transaction_id')
            ->where('transactions.status', 'completed')
            ->orderBy('transactions.created_at')
            ->select('deposits.*', 'transactions.created_at as tx_created_at')
            ->get();

        foreach ($deposits as $deposit) {
            $transactions->push([
                'type' => 'deposit',
                'data' => $deposit,
                'created_at' => $deposit->tx_created_at
            ]);
        }

        // Withdrawals
        $withdrawals = Withdrawal::where('wallet_id', $wallet->id)
            ->join('transactions', 'withdrawals.transaction_id', '=', 'transactions.transaction_id')
            ->where('transactions.status', 'completed')
            ->orderBy('transactions.created_at')
            ->select('withdrawals.*', 'transactions.created_at as tx_created_at')
            ->get();

        foreach ($withdrawals as $withdrawal) {
            $transactions->push([
                'type' => 'withdrawal',
                'data' => $withdrawal,
                'created_at' => $withdrawal->tx_created_at
            ]);
        }

        // Transfers (sent)
        $sentTransfers = Transfer::where('sender_wallet_id', $wallet->id)
            ->join('transactions', 'transfers.transaction_id', '=', 'transactions.transaction_id')
            ->where('transactions.status', 'completed')
            ->orderBy('transactions.created_at')
            ->select('transfers.*', 'transactions.created_at as tx_created_at')
            ->get();

        foreach ($sentTransfers as $transfer) {
            $transactions->push([
                'type' => 'transfer_sent',
                'data' => $transfer,
                'created_at' => $transfer->tx_created_at
            ]);
        }

        // Transfers (received)
        $receivedTransfers = Transfer::where('receiver_wallet_id', $wallet->id)
            ->join('transactions', 'transfers.transaction_id', '=', 'transactions.transaction_id')
            ->where('transactions.status', 'completed')
            ->orderBy('transactions.created_at')
            ->select('transfers.*', 'transactions.created_at as tx_created_at')
            ->get();

        foreach ($receivedTransfers as $transfer) {
            $transactions->push([
                'type' => 'transfer_received',
                'data' => $transfer,
                'created_at' => $transfer->tx_created_at
            ]);
        }

        // Sort by created_at
        $transactions = $transactions->sortBy('created_at');

        // Rebuild ledger
        $balance = 0;

        if (!$this->dryRun) {
            // Delete existing ledger entries for this wallet
            LedgerEntry::where('wallet_id', $wallet->id)->delete();
        }

        foreach ($transactions as $tx) {
            $balanceBefore = $balance;
            $amount = $tx['data']->amount;
            $transactionId = $tx['data']->transaction_id;

            switch ($tx['type']) {
                case 'deposit':
                    $balance += $amount;
                    $this->createLedgerEntry($wallet->id, $transactionId, 'credit', $amount, $balanceBefore, $balance, 'deposit', $tx['data']->id);
                    break;

                case 'withdrawal':
                    $balance = max(0, $balance - $amount);
                    $this->createLedgerEntry($wallet->id, $transactionId, 'debit', $amount, $balanceBefore, $balance, 'withdrawal', $tx['data']->id);
                    break;

                case 'transfer_sent':
                    $balance = max(0, $balance - $amount);
                    $this->createLedgerEntry($wallet->id, $transactionId, 'debit', $amount, $balanceBefore, $balance, 'transfer', $tx['data']->id);

                    // Handle fee
                    if ($tx['data']->fee > 0) {
                        $balanceBefore = $balance;
                        $balance = max(0, $balance - $tx['data']->fee);
                        $this->createLedgerEntry($wallet->id, $transactionId, 'fee', $tx['data']->fee, $balanceBefore, $balance, 'transfer', $tx['data']->id);
                    }
                    break;

                case 'transfer_received':
                    $balance += $amount;
                    $this->createLedgerEntry($wallet->id, $transactionId, 'credit', $amount, $balanceBefore, $balance, 'transfer', $tx['data']->id);
                    break;
            }
        }

        // Update wallet balance
        if (!$this->dryRun) {
            $wallet->update(['balance' => $balance]);
        }

        $this->info("Rebuilt ledger for wallet {$wallet->id}. Final balance: {$balance}");
    }

    /**
     * Create a ledger entry
     */
    private function createLedgerEntry($walletId, $transactionId, $type, $amount, $balanceBefore, $balanceAfter, $referenceType, $referenceId)
    {
        if (!$this->dryRun) {
            LedgerEntry::create([
                'transaction_id' => $transactionId,
                'wallet_id' => $walletId,
                'type' => $type,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => "Rebuilt from {$referenceType} {$referenceId}"
            ]);
        }

        $this->fixedCount++;
    }

    /**
     * Fix orphaned transactions
     */
    private function fixOrphanedTransactions()
    {
        $this->info("\n3. Fixing Orphaned Transactions...");

        // Find completed transactions without proper records
        $orphanedTransactions = Transaction::where('status', 'completed')
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('type', 'deposit')
                        ->whereNotExists(function ($sub) {
                            $sub->select(DB::raw(1))
                                ->from('deposits')
                                ->whereColumn('deposits.transaction_id', 'transactions.transaction_id');
                        });
                })
                    ->orWhere(function ($q) {
                        $q->where('type', 'withdrawal')
                            ->whereNotExists(function ($sub) {
                                $sub->select(DB::raw(1))
                                    ->from('withdrawals')
                                    ->whereColumn('withdrawals.transaction_id', 'transactions.transaction_id');
                            });
                    })
                    ->orWhere(function ($q) {
                        $q->where('type', 'transfer')
                            ->whereNotExists(function ($sub) {
                                $sub->select(DB::raw(1))
                                    ->from('transfers')
                                    ->whereColumn('transfers.transaction_id', 'transactions.transaction_id');
                            });
                    });
            })
            ->get();

        foreach ($orphanedTransactions as $transaction) {
            $this->warn("Found orphaned {$transaction->type} transaction: {$transaction->transaction_id}");

            if (!$this->dryRun) {
                // Mark as failed since we can't recover the details
                $transaction->update(['status' => 'failed']);
                $this->fixedCount++;
            }
        }

        $this->line("Found {$orphanedTransactions->count()} orphaned transactions");
    }

    /**
     * Complete rebuild of ledger from transactions
     */
    private function rebuildLedgerFromTransactions()
    {
        $this->info("\nRebuilding Entire Ledger from Transactions...");

        if (!$this->confirm('This will delete all ledger entries and rebuild from scratch. Continue?')) {
            return;
        }

        $wallets = Wallet::all();
        $bar = $this->output->createProgressBar($wallets->count());

        foreach ($wallets as $wallet) {
            $this->rebuildWalletLedger($wallet);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }
}
