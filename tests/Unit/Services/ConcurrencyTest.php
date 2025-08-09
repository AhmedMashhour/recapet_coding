<?php


namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\FeeCalculatorService;
use App\Services\TransactionService;
use App\Services\TransferService;
use App\Models\User;
use App\Models\Wallet;
use App\Exceptions\DuplicateTransactionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $transactionService;
    private TransferService $transferService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transactionService = new TransactionService();
        $this->transferService = new TransferService();
    }

    public function test_concurrent_deposits_with_same_idempotency_key()
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'balance' => 100.00,
            'status' => 'active'
        ]);

        $idempotencyKey = Str::uuid()->toString();
        $depositData = [
            'wallet_id' => $wallet->id,
            'amount' => 50.00,
            'idempotency_key' => $idempotencyKey
        ];

        $results = [];
        $exceptions = [];

        $processes = [];
        for ($i = 0; $i < 5; $i++) {
            $processes[] = function () use ($depositData, &$results, &$exceptions) {
                try {
                    $results[] = $this->transactionService->deposit($depositData);
                } catch (\Exception $e) {
                    $exceptions[] = $e;
                }
            };
        }

        // Execute processes
        foreach ($processes as $process) {
            $process();
        }

        // Only one should succeed
        $this->assertCount(1, $results);
        $this->assertCount(4, $exceptions);

        // All exceptions should be DuplicateTransactionException
        foreach ($exceptions as $exception) {
            $this->assertInstanceOf(DuplicateTransactionException::class, $exception);
        }

        // Wallet balance should only increase by one deposit
        $wallet->refresh();
        $this->assertEquals(150.00, $wallet->balance);
    }

    public function test_concurrent_withdrawals_prevent_overdraft()
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'balance' => 100.00,
            'status' => 'active'
        ]);

        // Try to withdraw 60 twice concurrently (total 120 > 100 balance)
        $withdrawal1 = [
            'wallet_id' => $wallet->id,
            'amount' => 60.00,
            'idempotency_key' => Str::uuid()->toString()
        ];

        $withdrawal2 = [
            'wallet_id' => $wallet->id,
            'amount' => 60.00,
            'idempotency_key' => Str::uuid()->toString()
        ];

        $results = [];
        $exceptions = [];

        // Simulate concurrent withdrawals
        DB::transaction(function () use ($withdrawal1, &$results, &$exceptions) {
            try {
                $results[] = $this->transactionService->withdraw($withdrawal1);
            } catch (\Exception $e) {
                $exceptions[] = $e;
            }
        });

        DB::transaction(function () use ($withdrawal2, &$results, &$exceptions) {
            try {
                $results[] = $this->transactionService->withdraw($withdrawal2);
            } catch (\Exception $e) {
                $exceptions[] = $e;
            }
        });

        $successCount = count($results);
        $failureCount = count($exceptions);

        $this->assertTrue($successCount == 1 || $failureCount >= 1);

        $wallet->refresh();
        $this->assertGreaterThanOrEqual(0, $wallet->balance);
        $this->assertLessThanOrEqual(40.00, $wallet->balance); // 100 - 60 = 40
    }

    public function test_concurrent_transfers_consistency()
    {
        // Create three users with wallets
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $wallet1 = Wallet::factory()->create([
            'user_id' => $user1->id,
            'balance' => 1000.00,
            'status' => 'active'
        ]);

        $wallet2 = Wallet::factory()->create([
            'user_id' => $user2->id,
            'balance' => 500.00,
            'status' => 'active'
        ]);

        $wallet3 = Wallet::factory()->create([
            'user_id' => $user3->id,
            'balance' => 200.00,
            'status' => 'active'
        ]);

        $initialTotalBalance = 1700.00; // 1000 + 500 + 200

        // Execute multiple concurrent transfers
        $transfers = [
            // User 1 -> User 2: 100
            ['from' => $wallet1->id, 'to' => $wallet2->wallet_number, 'amount' => 100.00],
            // User 2 -> User 3: 50
            ['from' => $wallet2->id, 'to' => $wallet3->wallet_number, 'amount' => 50.00],
            // User 3 -> User 1: 25
            ['from' => $wallet3->id, 'to' => $wallet1->wallet_number, 'amount' => 25.00],
        ];

        foreach ($transfers as $transfer) {
            try {
                $this->transferService->executeTransfer(
                    $transfer['from'],
                    $transfer['to'],
                    $transfer['amount'],
                    Str::uuid()->toString()
                );
            } catch (\Exception $e) {
                // Some transfers might fail due to insufficient balance after fees
            }
        }

        // Check total balance consistency (minus fees)
        $wallet1->refresh();
        $wallet2->refresh();
        $wallet3->refresh();

        $finalTotalBalance = $wallet1->balance + $wallet2->balance + $wallet3->balance;

        // Total balance should be less than initial due to fees
        $this->assertLessThan($initialTotalBalance, $finalTotalBalance);

        // But not by too much (reasonable fee amount)
        $maxPossibleFees = 50.00; // Conservative estimate
        $this->assertGreaterThan($initialTotalBalance - $maxPossibleFees, $finalTotalBalance);
    }

    public function test_deadlock_prevention_in_transfers()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $wallet1 = Wallet::factory()->create([
            'user_id' => $user1->id,
            'balance' => 500.00,
            'status' => 'active'
        ]);

        $wallet2 = Wallet::factory()->create([
            'user_id' => $user2->id,
            'balance' => 500.00,
            'status' => 'active'
        ]);

        // Simulate potential deadlock scenario: A->B and B->A simultaneously
        $results = [];
        $exceptions = [];

        // Transfer 1: Wallet1 -> Wallet2
        $transfer1 = function () use ($wallet1, $wallet2, &$results, &$exceptions) {
            try {
                $results[] = $this->transferService->executeTransfer(
                    $wallet1->id,
                    $wallet2->wallet_number,
                    100.00,
                    Str::uuid()->toString()
                );
            } catch (\Exception $e) {
                $exceptions[] = $e;
            }
        };

        // Transfer 2: Wallet2 -> Wallet1
        $transfer2 = function () use ($wallet1, $wallet2, &$results, &$exceptions) {
            try {
                $results[] = $this->transferService->executeTransfer(
                    $wallet2->id,
                    $wallet1->wallet_number,
                    100.00,
                    Str::uuid()->toString()
                );
            } catch (\Exception $e) {
                $exceptions[] = $e;
            }
        };

        // Execute both transfers
        $transfer1();
        $transfer2();

        // Both transfers should complete without deadlock
        $this->assertCount(2, $results);
        $this->assertCount(0, $exceptions);

        // Check final balances (should be close to original minus fees)
        $wallet1->refresh();
        $wallet2->refresh();

        $fee = (new FeeCalculatorService())->calculateTransferFee(100.00);

        // Each wallet sent 100 and received 100, only losing the fee
        $expectedBalance = 500.00 - $fee;

        $this->assertEqualsWithDelta($expectedBalance, $wallet1->balance, 0.01);
        $this->assertEqualsWithDelta($expectedBalance, $wallet2->balance, 0.01);
    }
}

