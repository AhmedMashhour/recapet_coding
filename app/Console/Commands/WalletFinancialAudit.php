<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\LedgerEntry;
use App\Models\Deposit;
use App\Models\Withdrawal;
use App\Models\Transfer;

class WalletFinancialAudit extends Command
{
    protected $signature = 'wallet:audit
                            {--fix : Attempt to fix minor discrepancies}
                            {--detailed : Show detailed breakdown of all issues}
                            {--wallet= : Audit specific wallet ID}
                            {--from= : Start date for audit (Y-m-d)}
                            {--to= : End date for audit (Y-m-d)}';

    protected $description = 'Perform comprehensive financial audit on wallet system';

    private $errors = [];
    private $warnings = [];
    private $totalDiscrepancy = 0;

    public function handle()
    {
        $this->info('🔍 Starting Financial Audit...');
        $this->line('================================');

        $startTime = microtime(true);

        // Run all audit checks
        $this->auditWalletBalances();
        $this->auditLedgerConsistency();
        $this->auditTransactionCompleteness();
        $this->auditTransferBalances();
        $this->auditFeeCalculations();
        $this->auditIdempotencyKeys();
        $this->auditOrphanedRecords();
        $this->auditSystemBalance();

        // Display results
        $this->displayAuditResults();

        $duration = microtime(true) - $startTime;
        $this->info("\n✅ Audit completed in " . number_format($duration, 2) . " seconds");

        return count($this->errors) > 0 ? 1 : 0;
    }

    /**
     * Audit 1: Verify wallet balances match ledger entries
     */
    private function auditWalletBalances()
    {
        $this->info("\n1. Auditing Wallet Balances vs Ledger...");

        $walletFilter = $this->option('wallet') ? ['id' => $this->option('wallet')] : [];
        $wallets = Wallet::where($walletFilter)->get();

        $discrepancies = 0;

        foreach ($wallets as $wallet) {
            // Get last ledger entry
            $lastLedger = LedgerEntry::where('wallet_id', $wallet->id)
                ->orderBy('id', 'desc')
                ->first();

            if (!$lastLedger) {
                // Check if wallet has any transactions
                $hasTransactions = DB::table('deposits')->where('wallet_id', $wallet->id)->exists() ||
                    DB::table('withdrawals')->where('wallet_id', $wallet->id)->exists() ||
                    DB::table('transfers')->where('sender_wallet_id', $wallet->id)->exists() ||
                    DB::table('transfers')->where('receiver_wallet_id', $wallet->id)->exists();

                if ($hasTransactions || $wallet->balance != 0) {
                    $this->errors[] = [
                        'type' => 'missing_ledger',
                        'wallet_id' => $wallet->id,
                        'message' => "Wallet has balance {$wallet->balance} but no ledger entries"
                    ];
                }
                continue;
            }

            // Compare balances
            $difference = abs($wallet->balance - $lastLedger->balance_after);
            if ($difference > 0.1) { // Allow for floating point errors
                $discrepancies++;
                $this->errors[] = [
                    'type' => 'balance_mismatch',
                    'wallet_id' => $wallet->id,
                    'wallet_balance' => $wallet->balance,
                    'ledger_balance' => $lastLedger->balance_after,
                    'difference' => $wallet->balance - $lastLedger->balance_after
                ];

                $this->totalDiscrepancy += abs($wallet->balance - $lastLedger->balance_after);

                if ($this->option('fix')) {
                    $this->fixWalletBalance($wallet, $lastLedger->balance_after);
                }
            }
        }

        $this->line("  ✓ Checked {$wallets->count()} wallets, found {$discrepancies} discrepancies");
    }

    /**
     * Audit 2: Verify ledger entry consistency
     */
    private function auditLedgerConsistency()
    {
        $this->info("\n2. Auditing Ledger Entry Consistency...");

        $walletFilter = $this->option('wallet') ? ['wallet_id' => $this->option('wallet')] : [];

        // Check ledger continuity for each wallet
        $walletIds = LedgerEntry::where($walletFilter)
            ->distinct('wallet_id')
            ->pluck('wallet_id');

        $issues = 0;

        foreach ($walletIds as $walletId) {
            $entries = LedgerEntry::where('wallet_id', $walletId)
                ->orderBy('id')
                ->get();

            $previousBalance = 0;

            foreach ($entries as $index => $entry) {
                // First entry should have balance_before = 0 or match initial wallet state
                if ($index === 0 && $entry->balance_before != 0) {
                    $this->warnings[] = [
                        'type' => 'first_entry_balance',
                        'wallet_id' => $walletId,
                        'entry_id' => $entry->id,
                        'balance_before' => $entry->balance_before
                    ];
                }

                // Check continuity
                if ($index > 0 && abs($entry->balance_before - $previousBalance) > 0.1) {
                    $issues++;
                    $this->errors[] = [
                        'type' => 'ledger_discontinuity',
                        'wallet_id' => $walletId,
                        'entry_id' => $entry->id,
                        'expected_balance_before' => $previousBalance,
                        'actual_balance_before' => $entry->balance_before,
                        'gap' => $entry->balance_before - $previousBalance
                    ];
                }

                // Verify calculation
                $expectedAfter = $this->calculateExpectedBalance($entry);
                if (abs($entry->balance_after - $expectedAfter) > 0.1) {
                    $issues++;
                    $this->errors[] = [
                        'type' => 'ledger_calculation_error',
                        'wallet_id' => $walletId,
                        'entry_id' => $entry->id,
                        'type' => $entry->type,
                        'amount' => $entry->amount,
                        'expected_after' => $expectedAfter,
                        'actual_after' => $entry->balance_after
                    ];
                }

                $previousBalance = $entry->balance_after;
            }
        }

        $this->line("  ✓ Checked {$walletIds->count()} wallets' ledgers, found {$issues} issues");
    }

    /**
     * Audit 3: Verify all transactions have corresponding records
     */
    private function auditTransactionCompleteness()
    {
        $this->info("\n3. Auditing Transaction Completeness...");

        $dateFilter = [];
        if ($this->option('from')) {
            $dateFilter[] = ['created_at', '>=', $this->option('from')];
        }
        if ($this->option('to')) {
            $dateFilter[] = ['created_at', '<=', $this->option('to')];
        }

        $transactions = Transaction::where($dateFilter)->get();
        $incomplete = 0;

        foreach ($transactions as $transaction) {
            $hasRecord = false;
            $hasLedger = false;

            switch ($transaction->type) {
                case 'deposit':
                    $hasRecord = Deposit::where('transaction_id', $transaction->transaction_id)->exists();
                    $hasLedger = LedgerEntry::where('transaction_id', $transaction->transaction_id)
                        ->where('type', 'credit')
                        ->exists();
                    break;

                case 'withdrawal':
                    $hasRecord = Withdrawal::where('transaction_id', $transaction->transaction_id)->exists();
                    $hasLedger = LedgerEntry::where('transaction_id', $transaction->transaction_id)
                        ->where('type', 'debit')
                        ->exists();
                    break;

                case 'transfer':
                    $hasRecord = Transfer::where('transaction_id', $transaction->transaction_id)->exists();
                    $debitEntry = LedgerEntry::where('transaction_id', $transaction->transaction_id)
                        ->where('type', 'debit')
                        ->exists();
                    $creditEntry = LedgerEntry::where('transaction_id', $transaction->transaction_id)
                        ->where('type', 'credit')
                        ->exists();
                    $feeEntry = $transaction->fee > 0 ?
                        LedgerEntry::where('transaction_id', $transaction->transaction_id)
                            ->where('type', 'fee')
                            ->exists() : true;

                    $hasLedger = $debitEntry && $creditEntry && $feeEntry;
                    break;
            }

            if ($transaction->status === 'completed' && (!$hasRecord || !$hasLedger)) {
                $incomplete++;
                $this->errors[] = [
                    'type' => 'incomplete_transaction',
                    'transaction_id' => $transaction->transaction_id,
                    'transaction_type' => $transaction->type,
                    'status' => $transaction->status,
                    'has_record' => $hasRecord,
                    'has_ledger' => $hasLedger,
                    'amount' => $transaction->amount
                ];
            }
        }

        $this->line("  ✓ Checked {$transactions->count()} transactions, found {$incomplete} incomplete");
    }

    /**
     * Audit 4: Verify transfer balances
     */
    private function auditTransferBalances()
    {
        $this->info("\n4. Auditing Transfer Balance Integrity...");

        $transfers = Transfer::with(['transaction', 'senderWallet', 'receiverWallet'])->get();
        $issues = 0;

        foreach ($transfers as $transfer) {
            if (!$transfer->transaction || $transfer->transaction->status !== 'completed') {
                continue;
            }

            // Get ledger entries for this transfer
            $ledgerEntries = LedgerEntry::where('transaction_id', $transfer->transaction_id)->get();

            $senderDebit = $ledgerEntries->where('wallet_id', $transfer->sender_wallet_id)
                ->where('type', 'debit')
                ->first();

            $senderFee = $ledgerEntries->where('wallet_id', $transfer->sender_wallet_id)
                ->where('type', 'fee')
                ->first();

            $receiverCredit = $ledgerEntries->where('wallet_id', $transfer->receiver_wallet_id)
                ->where('type', 'credit')
                ->first();

            // Verify amounts
            if ($senderDebit && $senderDebit->amount != $transfer->amount) {
                $issues++;
                $this->errors[] = [
                    'type' => 'transfer_amount_mismatch',
                    'transfer_id' => $transfer->id,
                    'transaction_id' => $transfer->transaction_id,
                    'expected_debit' => $transfer->amount,
                    'actual_debit' => $senderDebit->amount
                ];
            }

            if ($transfer->fee > 0 && (!$senderFee || $senderFee->amount != $transfer->fee)) {
                $issues++;
                $this->errors[] = [
                    'type' => 'transfer_fee_mismatch',
                    'transfer_id' => $transfer->id,
                    'expected_fee' => $transfer->fee,
                    'actual_fee' => $senderFee ? $senderFee->amount : 0
                ];
            }

            if ($receiverCredit && $receiverCredit->amount != $transfer->amount) {
                $issues++;
                $this->errors[] = [
                    'type' => 'transfer_credit_mismatch',
                    'transfer_id' => $transfer->id,
                    'expected_credit' => $transfer->amount,
                    'actual_credit' => $receiverCredit->amount
                ];
            }
        }

        $this->line("  ✓ Checked {$transfers->count()} transfers, found {$issues} issues");
    }

    /**
     * Audit 5: Verify fee calculations
     */
    private function auditFeeCalculations()
    {
        $this->info("\n5. Auditing Fee Calculations...");

        $transfers = Transfer::where('fee', '>', 0)->get();
        $feeCalculator = new \App\Services\FeeCalculatorService();
        $issues = 0;

        foreach ($transfers as $transfer) {
            $expectedFee = $feeCalculator->calculateTransferFee($transfer->amount);

            if (abs($transfer->fee - $expectedFee) > 0.1) {
                $issues++;
                $this->errors[] = [
                    'type' => 'incorrect_fee',
                    'transfer_id' => $transfer->id,
                    'amount' => $transfer->amount,
                    'expected_fee' => $expectedFee,
                    'actual_fee' => $transfer->fee,
                    'difference' => $transfer->fee - $expectedFee
                ];
            }
        }

        $this->line("  ✓ Checked {$transfers->count()} transfers with fees, found {$issues} incorrect calculations");
    }

    /**
     * Audit 6: Check for duplicate idempotency keys
     */
    private function auditIdempotencyKeys()
    {
        $this->info("\n6. Auditing Idempotency Keys...");

        $duplicates = DB::table('transactions')
            ->select('idempotency_key', DB::raw('COUNT(*) as count'))
            ->whereNotNull('idempotency_key')
            ->groupBy('idempotency_key')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            $this->errors[] = [
                'type' => 'duplicate_idempotency_key',
                'key' => $duplicate->idempotency_key,
                'count' => $duplicate->count
            ];
        }

        $this->line("  ✓ Found {$duplicates->count()} duplicate idempotency keys");
    }

    /**
     * Audit 7: Check for orphaned records
     */
    private function auditOrphanedRecords()
    {
        $this->info("\n7. Auditing Orphaned Records...");

        // Orphaned deposits
        $orphanedDeposits = DB::table('deposits')
            ->leftJoin('transactions', 'deposits.transaction_id', '=', 'transactions.transaction_id')
            ->whereNull('transactions.id')
            ->count();

        // Orphaned withdrawals
        $orphanedWithdrawals = DB::table('withdrawals')
            ->leftJoin('transactions', 'withdrawals.transaction_id', '=', 'transactions.transaction_id')
            ->whereNull('transactions.id')
            ->count();

        // Orphaned transfers
        $orphanedTransfers = DB::table('transfers')
            ->leftJoin('transactions', 'transfers.transaction_id', '=', 'transactions.transaction_id')
            ->whereNull('transactions.id')
            ->count();

        // Orphaned ledger entries
        $orphanedLedgers = DB::table('ledger_entries')
            ->leftJoin('transactions', 'ledger_entries.transaction_id', '=', 'transactions.transaction_id')
            ->whereNull('transactions.id')
            ->count();

        $total = $orphanedDeposits + $orphanedWithdrawals + $orphanedTransfers + $orphanedLedgers;

        if ($total > 0) {
            $this->warnings[] = [
                'type' => 'orphaned_records',
                'deposits' => $orphanedDeposits,
                'withdrawals' => $orphanedWithdrawals,
                'transfers' => $orphanedTransfers,
                'ledger_entries' => $orphanedLedgers
            ];
        }

        $this->line("  ✓ Found {$total} orphaned records");
    }

    /**
     * Audit 8: Verify total system balance
     */
    private function auditSystemBalance()
    {
        $this->info("\n8. Auditing Total System Balance...");

        // Calculate total money in system from different sources
        $walletTotal = Wallet::sum('balance');

        // Calculate from ledger entries (sum of all final balances per wallet)
        $ledgerTotal = DB::table('ledger_entries as l1')
            ->join(DB::raw('(SELECT wallet_id, MAX(id) as max_id FROM ledger_entries GROUP BY wallet_id) as l2'), function($join) {
                $join->on('l1.wallet_id', '=', 'l2.wallet_id')
                    ->on('l1.id', '=', 'l2.max_id');
            })
            ->sum('l1.balance_after');

        // Calculate expected total from transactions
        $totalDeposits = DB::table('deposits')
            ->join('transactions', 'deposits.transaction_id', '=', 'transactions.transaction_id')
            ->where('transactions.status', 'completed')
            ->sum('deposits.amount');

        $totalWithdrawals = DB::table('withdrawals')
            ->join('transactions', 'withdrawals.transaction_id', '=', 'transactions.transaction_id')
            ->where('transactions.status', 'completed')
            ->sum('withdrawals.amount');

        $totalFees = Transaction::where('status', 'completed')
            ->where('type', 'transfer')
            ->sum('fee');

        $expectedTotal = $totalDeposits - $totalWithdrawals - $totalFees;

        $this->line("  Wallet Total:    $" . number_format($walletTotal, 2));
        $this->line("  Ledger Total:    $" . number_format($ledgerTotal, 2));
        $this->line("  Expected Total:  $" . number_format($expectedTotal, 2));
        $this->line("  Total Deposits:  $" . number_format($totalDeposits, 2));
        $this->line("  Total Withdrawals: $" . number_format($totalWithdrawals, 2));
        $this->line("  Total Fees:      $" . number_format($totalFees, 2));

        if (abs($walletTotal - $expectedTotal) > 0.1) {
            $this->errors[] = [
                'type' => 'system_balance_mismatch',
                'wallet_total' => $walletTotal,
                'expected_total' => $expectedTotal,
                'difference' => $walletTotal - $expectedTotal
            ];
        }
    }

    /**
     * Calculate expected balance after ledger entry
     */
    private function calculateExpectedBalance(LedgerEntry $entry): float
    {
        switch ($entry->type) {
            case 'credit':
                return $entry->balance_before + $entry->amount;
            case 'debit':
            case 'fee':
                return $entry->balance_before - $entry->amount;
            default:
                return $entry->balance_before;
        }
    }

    /**
     * Fix wallet balance to match ledger
     */
    private function fixWalletBalance(Wallet $wallet, float $correctBalance)
    {
        $this->warn("  Fixing wallet {$wallet->id}: {$wallet->balance} -> {$correctBalance}");
        $wallet->update(['balance' => $correctBalance]);
    }

    /**
     * Display audit results
     */
    private function displayAuditResults()
    {
        $this->info("\n════════════════════════════════════════");
        $this->info("           AUDIT RESULTS");
        $this->info("════════════════════════════════════════");

        if (count($this->errors) === 0 && count($this->warnings) === 0) {
            $this->info("\n✅ All checks passed! No discrepancies found.");
            return;
        }

        if (count($this->errors) > 0) {
            $this->error("\n❌ ERRORS FOUND: " . count($this->errors));

            if ($this->option('detailed')) {
                foreach ($this->errors as $error) {
                    $this->error(json_encode($error, JSON_PRETTY_PRINT));
                }
            } else {
                // Group errors by type
                $errorsByType = collect($this->errors)->groupBy('type');
                foreach ($errorsByType as $type => $errors) {
                    $this->error("  - {$type}: {$errors->count()} occurrences");
                }
            }
        }

        if (count($this->warnings) > 0) {
            $this->warn("\n⚠️  WARNINGS: " . count($this->warnings));

            if ($this->option('detailed')) {
                foreach ($this->warnings as $warning) {
                    $this->warn(json_encode($warning, JSON_PRETTY_PRINT));
                }
            }
        }

        if ($this->totalDiscrepancy > 0) {
            $this->error("\n💰 Total Financial Discrepancy: $" . number_format($this->totalDiscrepancy, 2));
        }

        // Save detailed report
        $this->saveDetailedReport();
    }

    /**
     * Save detailed report to file
     */
    private function saveDetailedReport()
    {
        $filename = 'audit_report_' . date('Y-m-d_H-i-s') . '.json';
        $path = storage_path('logs/' . $filename);

        $report = [
            'audit_date' => now()->toIso8601String(),
            'summary' => [
                'total_errors' => count($this->errors),
                'total_warnings' => count($this->warnings),
                'total_discrepancy' => $this->totalDiscrepancy
            ],
            'errors' => $this->errors,
            'warnings' => $this->warnings
        ];

        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT));

        $this->info("\n📄 Detailed report saved to: {$filename}");
    }
}
