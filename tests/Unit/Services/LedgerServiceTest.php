<?php
namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\TransactionService;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $transactionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transactionService = new TransactionService();
    }

    public function test_ledger_entries_maintain_running_balance()
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'balance' => 0.00,
            'status' => 'active'
        ]);

        // Make several transactions
        $transactions = [
            ['amount' => 100.00, 'type' => 'deposit'],
            ['amount' => 50.00, 'type' => 'deposit'],
            ['amount' => 30.00, 'type' => 'withdrawal'],
            ['amount' => 20.00, 'type' => 'deposit'],
        ];

        foreach ($transactions as $trans) {
            $data = [
                'wallet_id' => $wallet->id,
                'amount' => $trans['amount'],
                'idempotency_key' => Str::uuid()->toString()
            ];

            if ($trans['type'] === 'deposit') {
                $this->transactionService->deposit($data);
            } else {
                $this->transactionService->withdraw($data);
            }
        }

        // Check ledger entries
        $ledgerEntries = DB::table('ledger_entries')
            ->where('wallet_id', $wallet->id)
            ->orderBy('id')
            ->get();

        // Verify running balance
        $expectedBalances = [
            ['before' => 0, 'after' => 100],      // +100
            ['before' => 100, 'after' => 150],    // +50
            ['before' => 150, 'after' => 120],    // -30
            ['before' => 120, 'after' => 140],    // +20
        ];

        foreach ($ledgerEntries as $index => $entry) {
            $this->assertEquals($expectedBalances[$index]['before'], $entry->balance_before);
            $this->assertEquals($expectedBalances[$index]['after'], $entry->balance_after);
        }

        // Final wallet balance should match last ledger entry
        $wallet->refresh();
        $this->assertEquals(140.00, $wallet->balance);
    }
}
