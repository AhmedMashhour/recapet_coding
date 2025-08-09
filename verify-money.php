<?php
// File: verify-money.php
// Quick script to verify money movements in the database
// Run with: php verify-money.php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n🔍 WALLET SYSTEM FINANCIAL VERIFICATION\n";
echo "=====================================\n\n";

// 1. Check wallet vs ledger balances
echo "1. WALLET vs LEDGER BALANCE CHECK\n";
echo "---------------------------------\n";

$walletLedgerCheck = DB::select("
    SELECT
        w.id,
        w.wallet_number,
        w.balance as wallet_balance,
        COALESCE(l.balance_after, 0) as ledger_balance,
        ABS(w.balance - COALESCE(l.balance_after, 0)) as difference
    FROM wallets w
    LEFT JOIN (
        SELECT
            wallet_id,
            balance_after,
            ROW_NUMBER() OVER (PARTITION BY wallet_id ORDER BY id DESC) as rn
        FROM ledger_entries
    ) l ON w.id = l.wallet_id AND l.rn = 1
    WHERE ABS(w.balance - COALESCE(l.balance_after, 0)) > 0.01
    ORDER BY difference DESC
");

if (empty($walletLedgerCheck)) {
    echo "✅ All wallet balances match their ledger entries\n";
} else {
    echo "❌ Found " . count($walletLedgerCheck) . " wallets with balance mismatches:\n";
    foreach ($walletLedgerCheck as $wallet) {
        echo "   Wallet {$wallet->id}: Wallet={$wallet->wallet_balance}, Ledger={$wallet->ledger_balance}, Diff={$wallet->difference}\n";
    }
}

// 2. Check ledger continuity
echo "\n2. LEDGER CONTINUITY CHECK\n";
echo "--------------------------\n";

$ledgerGaps = DB::select("
    SELECT
        l1.wallet_id,
        l1.id as entry_id,
        l1.balance_before,
        l2.balance_after as prev_balance_after,
        ABS(l1.balance_before - l2.balance_after) as gap
    FROM ledger_entries l1
    JOIN ledger_entries l2 ON l1.wallet_id = l2.wallet_id AND l2.id = (
        SELECT MAX(id) FROM ledger_entries WHERE wallet_id = l1.wallet_id AND id < l1.id
    )
    WHERE ABS(l1.balance_before - l2.balance_after) > 0.01
    LIMIT 10
");

if (empty($ledgerGaps)) {
    echo "✅ All ledger entries are continuous\n";
} else {
    echo "❌ Found " . count($ledgerGaps) . " ledger continuity gaps:\n";
    foreach ($ledgerGaps as $gap) {
        echo "   Wallet {$gap->wallet_id}, Entry {$gap->entry_id}: Gap of {$gap->gap}\n";
    }
}

// 3. System balance verification
echo "\n3. SYSTEM BALANCE VERIFICATION\n";
echo "------------------------------\n";

$stats = DB::selectOne("
    SELECT
        (SELECT SUM(balance) FROM wallets) as total_wallet_balance,
        (SELECT SUM(d.amount) FROM deposits d
         JOIN transactions t ON d.transaction_id = t.transaction_id
         WHERE t.status = 'completed') as total_deposits,
        (SELECT SUM(w.amount) FROM withdrawals w
         JOIN transactions t ON w.transaction_id = t.transaction_id
         WHERE t.status = 'completed') as total_withdrawals,
        (SELECT SUM(fee) FROM transactions
         WHERE type = 'transfer' AND status = 'completed') as total_fees
");

$expectedBalance = $stats->total_deposits - $stats->total_withdrawals - $stats->total_fees;
$difference = $stats->total_wallet_balance - $expectedBalance;

echo "Total in Wallets:     $" . number_format($stats->total_wallet_balance, 2) . "\n";
echo "Total Deposits:      +$" . number_format($stats->total_deposits, 2) . "\n";
echo "Total Withdrawals:   -$" . number_format($stats->total_withdrawals, 2) . "\n";
echo "Total Fees:          -$" . number_format($stats->total_fees, 2) . "\n";
echo "Expected Balance:     $" . number_format($expectedBalance, 2) . "\n";
echo "Difference:           $" . number_format($difference, 2) . "\n";

if (abs($difference) < 0.01) {
    echo "✅ System balance is correct!\n";
} else {
    echo "❌ System balance mismatch of $" . number_format($difference, 2) . "\n";
}

// 4. Check for incomplete transactions
echo "\n4. INCOMPLETE TRANSACTIONS CHECK\n";
echo "--------------------------------\n";

$incompleteTransactions = DB::select("
    SELECT
        t.transaction_id,
        t.type,
        t.status,
        t.amount,
        t.created_at,
        CASE
            WHEN t.type = 'deposit' THEN EXISTS(SELECT 1 FROM deposits WHERE transaction_id = t.transaction_id)
            WHEN t.type = 'withdrawal' THEN EXISTS(SELECT 1 FROM withdrawals WHERE transaction_id = t.transaction_id)
            WHEN t.type = 'transfer' THEN EXISTS(SELECT 1 FROM transfers WHERE transaction_id = t.transaction_id)
        END as has_detail_record,
        EXISTS(SELECT 1 FROM ledger_entries WHERE transaction_id = t.transaction_id) as has_ledger
    FROM transactions t
    WHERE t.status = 'completed'
    HAVING has_detail_record = 0 OR has_ledger = 0
    LIMIT 10
");

if (empty($incompleteTransactions)) {
    echo "✅ All completed transactions have proper records\n";
} else {
    echo "❌ Found " . count($incompleteTransactions) . " incomplete transactions:\n";
    foreach ($incompleteTransactions as $tx) {
        echo "   {$tx->transaction_id}: {$tx->type} for {$tx->amount} - ";
        echo "Detail: " . ($tx->has_detail_record ? "✓" : "✗") . ", ";
        echo "Ledger: " . ($tx->has_ledger ? "✓" : "✗") . "\n";
    }
}

// 5. Check for negative balances
echo "\n5. NEGATIVE BALANCE CHECK\n";
echo "-------------------------\n";

$negativeBalances = DB::select("
    SELECT id, wallet_number, balance, status
    FROM wallets
    WHERE balance < 0
");

if (empty($negativeBalances)) {
    echo "✅ No negative balances found\n";
} else {
    echo "❌ Found " . count($negativeBalances) . " wallets with negative balance:\n";
    foreach ($negativeBalances as $wallet) {
        echo "   Wallet {$wallet->id} ({$wallet->wallet_number}): {$wallet->balance}\n";
    }
}

// 6. Transfer balance verification
echo "\n6. TRANSFER VERIFICATION\n";
echo "------------------------\n";

$transferIssues = DB::select("
    SELECT
        tr.id,
        tr.transaction_id,
        tr.amount,
        tr.fee,
        t.status,
        (SELECT COUNT(*) FROM ledger_entries WHERE transaction_id = tr.transaction_id AND type = 'debit') as debit_entries,
        (SELECT COUNT(*) FROM ledger_entries WHERE transaction_id = tr.transaction_id AND type = 'credit') as credit_entries,
        (SELECT COUNT(*) FROM ledger_entries WHERE transaction_id = tr.transaction_id AND type = 'fee') as fee_entries
    FROM transfers tr
    JOIN transactions t ON tr.transaction_id = t.transaction_id
    WHERE t.status = 'completed'
    AND (
        (SELECT COUNT(*) FROM ledger_entries WHERE transaction_id = tr.transaction_id AND type = 'debit') != 1
        OR (SELECT COUNT(*) FROM ledger_entries WHERE transaction_id = tr.transaction_id AND type = 'credit') != 1
        OR (tr.fee > 0 AND (SELECT COUNT(*) FROM ledger_entries WHERE transaction_id = tr.transaction_id AND type = 'fee') != 1)
    )
    LIMIT 10
");

if (empty($transferIssues)) {
    echo "✅ All transfers have correct ledger entries\n";
} else {
    echo "❌ Found " . count($transferIssues) . " transfers with incorrect ledger entries:\n";
    foreach ($transferIssues as $transfer) {
        echo "   Transfer {$transfer->id}: Debit={$transfer->debit_entries}, Credit={$transfer->credit_entries}, Fee={$transfer->fee_entries}\n";
    }
}

// 7. Duplicate idempotency keys
echo "\n7. IDEMPOTENCY KEY CHECK\n";
echo "------------------------\n";

$duplicateKeys = DB::select("
    SELECT
        idempotency_key,
        COUNT(*) as count,
        GROUP_CONCAT(transaction_id) as transaction_ids
    FROM transactions
    WHERE idempotency_key IS NOT NULL
    GROUP BY idempotency_key
    HAVING COUNT(*) > 1
    LIMIT 10
");

if (empty($duplicateKeys)) {
    echo "✅ No duplicate idempotency keys found\n";
} else {
    echo "❌ Found " . count($duplicateKeys) . " duplicate idempotency keys:\n";
    foreach ($duplicateKeys as $key) {
        echo "   Key: {$key->idempotency_key} used {$key->count} times\n";
    }
}

// Summary
echo "\n=====================================\n";
echo "SUMMARY\n";
echo "=====================================\n";

$issues = 0;
$issues += !empty($walletLedgerCheck) ? count($walletLedgerCheck) : 0;
$issues += !empty($ledgerGaps) ? count($ledgerGaps) : 0;
$issues += abs($difference) > 0.01 ? 1 : 0;
$issues += !empty($incompleteTransactions) ? count($incompleteTransactions) : 0;
$issues += !empty($negativeBalances) ? count($negativeBalances) : 0;
$issues += !empty($transferIssues) ? count($transferIssues) : 0;
$issues += !empty($duplicateKeys) ? count($duplicateKeys) : 0;

if ($issues == 0) {
    echo "✅ All checks passed! Your money movements are accurate.\n";
} else {
    echo "❌ Found {$issues} total issues that need attention.\n";
    echo "\nRun 'php artisan wallet:audit --detailed' for a comprehensive report.\n";
}

echo "\n";
