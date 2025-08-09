#!/bin/bash

# Wallet Stress Test Runner Script
# This script runs parallel stress tests on the wallet system

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Default configuration
WORKERS=${1:-50}              # Number of parallel workers
OPERATIONS=${2:-300}          # Operations per worker
WALLET_IDS=${3:-""}          # Comma-separated wallet IDs (empty = use random)
TYPE=${4:-"mixed"}           # Operation type: mixed, deposit, withdraw, transfer
DELAY=${5:-0}                # Delay between operations (milliseconds)

# Display configuration
echo -e "${PURPLE}╔════════════════════════════════════════════════════╗${NC}"
echo -e "${PURPLE}║       Wallet System Stress Test Runner             ║${NC}"
echo -e "${PURPLE}╚════════════════════════════════════════════════════╝${NC}"
echo
echo -e "${BLUE}Configuration:${NC}"
echo -e "  Workers:        ${GREEN}$WORKERS${NC}"
echo -e "  Operations:     ${GREEN}$OPERATIONS${NC} per worker"
echo -e "  Total Ops:      ${GREEN}$((WORKERS * OPERATIONS))${NC}"
echo -e "  Operation Type: ${GREEN}$TYPE${NC}"
echo -e "  Delay:          ${GREEN}$DELAY${NC} ms"
echo -e "  Wallet IDs:     ${GREEN}${WALLET_IDS:-"Random wallets"}${NC}"
echo

# Check if GNU Parallel is installed
if ! command -v parallel &> /dev/null; then
    echo -e "${RED}Error: GNU Parallel is not installed${NC}"
    echo "Install it with: sudo apt-get install parallel"
    echo
    echo "Falling back to xargs..."
    USE_XARGS=true
else
    USE_XARGS=false
fi

# Create log directory
LOG_DIR="storage/logs/stress-test-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$LOG_DIR"
echo -e "${BLUE}Logs will be saved to: ${NC}$LOG_DIR"

# Record start state
echo -e "\n${YELLOW}Recording initial state...${NC}"
php artisan tinker <<EOF > "$LOG_DIR/initial-state.txt" 2>&1
use App\Models\Wallet;
use App\Models\Transaction;

\$activeWallets = Wallet::where('status', 'active')->count();
\$totalBalance = Wallet::where('status', 'active')->sum('balance');
\$transactionCount = Transaction::count();

echo "Active Wallets: \$activeWallets\n";
echo "Total Balance: \$" . number_format(\$totalBalance, 2) . "\n";
echo "Transaction Count: \$transactionCount\n";

if (empty('$WALLET_IDS')) {
    echo "\nRandom wallets will be used for testing\n";
    \$sampleWallets = Wallet::where('status', 'active')
        ->where('balance', '>=', 100)
        ->limit(10)
        ->get(['id', 'wallet_number', 'balance']);
    echo "Sample wallets:\n";
    foreach (\$sampleWallets as \$w) {
        echo "  ID: \$w->id, Number: \$w->wallet_number, Balance: \$\$w->balance\n";
    }
} else {
    \$ids = explode(',', '$WALLET_IDS');
    \$specifiedWallets = Wallet::whereIn('id', \$ids)->get(['id', 'wallet_number', 'balance']);
    echo "\nSpecified wallets:\n";
    foreach (\$specifiedWallets as \$w) {
        echo "  ID: \$w->id, Number: \$w->wallet_number, Balance: \$\$w->balance\n";
    }
}
exit
EOF

# Function to run worker
run_worker() {
    local PROCESS_ID=$1
    local LOG_FILE="$LOG_DIR/worker-$PROCESS_ID.log"

    if [ -n "$WALLET_IDS" ]; then
        php artisan wallet:stress-worker \
            --operations=$OPERATIONS \
            --type=$TYPE \
            --delay=$DELAY \
            --process=$PROCESS_ID \
            --wallets="$WALLET_IDS" \
            > "$LOG_FILE" 2>&1
    else
        php artisan wallet:stress-worker \
            --operations=$OPERATIONS \
            --type=$TYPE \
            --delay=$DELAY \
            --process=$PROCESS_ID \
            > "$LOG_FILE" 2>&1
    fi

    # Extract and display result
    RESULT=$(tail -1 "$LOG_FILE" | grep -oE "Successful: [0-9]+, Failed: [0-9]+" || echo "Unknown result")
    echo -e "${GREEN}Worker $PROCESS_ID${NC} - $RESULT"
}

export -f run_worker
export OPERATIONS TYPE DELAY WALLET_IDS LOG_DIR GREEN NC

# Start timer
START_TIME=$(date +%s.%N)

echo -e "\n${YELLOW}Starting $WORKERS parallel workers...${NC}"
echo -e "${YELLOW}═══════════════════════════════════════${NC}"

# Run workers in parallel
if [ "$USE_XARGS" = true ]; then
    # Using xargs
    seq 1 $WORKERS | xargs -P $WORKERS -I {} bash -c 'run_worker {}'
else
    # Using GNU Parallel
    seq 1 $WORKERS | parallel -j $WORKERS 'run_worker {}'
fi

# Calculate duration
END_TIME=$(date +%s.%N)
DURATION=$(echo "$END_TIME - $START_TIME" | bc)

echo -e "${YELLOW}═══════════════════════════════════════${NC}"

# Generate final report
echo -e "\n${YELLOW}Generating final report...${NC}"

php artisan tinker <<'EOF' > "$LOG_DIR/final-report.txt" 2>&1
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;

$startTime = now()->subMinutes(10);

// Transaction statistics
$transactions = Transaction::where('created_at', '>=', $startTime)->get();
$byType = $transactions->groupBy('type')->map->count();
$byStatus = $transactions->groupBy('status')->map->count();

echo "=== TRANSACTION SUMMARY ===\n";
echo "Total: " . $transactions->count() . "\n";
echo "\nBy Type:\n";
foreach ($byType as $type => $count) {
    echo "  " . ucfirst($type) . ": $count\n";
}
echo "\nBy Status:\n";
foreach ($byStatus as $status => $count) {
    echo "  " . ucfirst($status) . ": $count\n";
}

// Calculate TPS
$firstTx = $transactions->min('created_at');
$lastTx = $transactions->max('created_at');
if ($firstTx && $lastTx) {
    $duration = $lastTx->diffInSeconds($firstTx);
    $tps = $duration > 0 ? $transactions->count() / $duration : 0;
    echo "\nPerformance:\n";
    echo "  Duration: " . number_format($duration, 2) . " seconds\n";
    echo "  TPS: " . number_format($tps, 2) . "\n";
}

// Financial summary
$totalFees = Transaction::where('created_at', '>=', $startTime)
    ->where('type', 'transfer')
    ->sum('fee');

echo "\n=== FINANCIAL SUMMARY ===\n";
echo "Total Fees Collected: $" . number_format($totalFees, 2) . "\n";

// Data integrity checks
$negativeBalances = Wallet::where('balance', '<', 0)->count();
$pendingTx = Transaction::where('created_at', '>=', $startTime)
    ->whereIn('status', ['pending', 'processing'])
    ->count();

echo "\n=== DATA INTEGRITY ===\n";
echo "Negative Balances: " . ($negativeBalances > 0 ? "❌ $negativeBalances found" : "✅ None") . "\n";
echo "Stuck Transactions: " . ($pendingTx > 0 ? "⚠️  $pendingTx pending/processing" : "✅ None") . "\n";

// Ledger consistency check
$ledgerIssues = DB::select("
    SELECT w.id, w.balance as wallet_balance, l.balance_after as ledger_balance
    FROM wallets w
    JOIN (
        SELECT wallet_id, balance_after,
               ROW_NUMBER() OVER (PARTITION BY wallet_id ORDER BY id DESC) as rn
        FROM ledger_entries
    ) l ON w.id = l.wallet_id AND l.rn = 1
    WHERE ABS(w.balance - l.balance_after) > 0.01
");

echo "Ledger Consistency: " . (empty($ledgerIssues) ? "✅ OK" : "❌ " . count($ledgerIssues) . " mismatches") . "\n";

// Error analysis
$failedTx = Transaction::where('created_at', '>=', $startTime)
    ->where('status', 'failed')
    ->limit(10)
    ->get();

if ($failedTx->count() > 0) {
    echo "\n=== SAMPLE FAILED TRANSACTIONS ===\n";
    foreach ($failedTx as $tx) {
        echo "  ID: {$tx->transaction_id}, Type: {$tx->type}, Amount: \${$tx->amount}\n";
    }
}

exit
EOF

# Display report
echo
cat "$LOG_DIR/final-report.txt"

# Summary
echo -e "\n${PURPLE}╔════════════════════════════════════════════════════╗${NC}"
echo -e "${PURPLE}║                  TEST COMPLETED                    ║${NC}"
echo -e "${PURPLE}╚════════════════════════════════════════════════════╝${NC}"
echo
echo -e "${BLUE}Duration:${NC} $(printf "%.2f" $DURATION) seconds"
echo -e "${BLUE}Workers:${NC} $WORKERS"
echo -e "${BLUE}Total Operations Attempted:${NC} $((WORKERS * OPERATIONS))"
echo -e "${BLUE}Operations Per Second:${NC} $(echo "scale=2; ($WORKERS * $OPERATIONS) / $DURATION" | bc)"
echo
echo -e "${GREEN}✅ Full report saved to:${NC} $LOG_DIR/"
echo -e "${GREEN}✅ Worker logs available in:${NC} $LOG_DIR/worker-*.log"

