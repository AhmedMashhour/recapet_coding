<?php


namespace Tests\Unit\Services;

use App\Exceptions\DuplicateTransactionException;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\WalletLockedException;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $transactionService;
    private User $user;
    private Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transactionService = new TransactionService();

        // Create test user with wallet
        $this->user = User::factory()->create();
        $this->wallet = Wallet::factory()->create([
            'user_id' => $this->user->id,
            'balance' => 1000.00,
            'status' => 'active'
        ]);
    }

    public function test_successful_deposit()
    {
        $depositData = [
            'wallet_id' => $this->wallet->id,
            'amount' => 100.00,
            'payment_method' => 'bank_transfer',
            'idempotency_key' => Str::uuid()->toString()
        ];

        $transaction = $this->transactionService->deposit($depositData);

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals('deposit', $transaction->type);
        $this->assertEquals('completed', $transaction->status);
        $this->assertEquals(100.00, $transaction->amount);

        // Check wallet balance updated
        $this->wallet->refresh();
        $this->assertEquals(1100.00, $this->wallet->balance);

        // Check deposit record created
        $this->assertNotNull($transaction->deposit);
        $this->assertEquals(100.00, $transaction->deposit->amount);
    }

    public function test_deposit_idempotency()
    {
        $idempotencyKey = Str::uuid()->toString();
        $depositData = [
            'wallet_id' => $this->wallet->id,
            'amount' => 100.00,
            'idempotency_key' => $idempotencyKey
        ];

        // First deposit should succeed
        $transaction1 = $this->transactionService->deposit($depositData);
        $this->assertEquals('completed', $transaction1->status);

        // Second deposit with same idempotency key should fail
        $this->expectException(DuplicateTransactionException::class);
        $this->transactionService->deposit($depositData);
    }

    public function test_successful_withdrawal()
    {
        $withdrawalData = [
            'wallet_id' => $this->wallet->id,
            'amount' => 200.00,
            'withdrawal_method' => 'bank_transfer',
            'idempotency_key' => Str::uuid()->toString()
        ];

        $transaction = $this->transactionService->withdraw($withdrawalData);

        $this->assertEquals('withdrawal', $transaction->type);
        $this->assertEquals('completed', $transaction->status);
        $this->assertEquals(200.00, $transaction->amount);

        // Check wallet balance updated
        $this->wallet->refresh();
        $this->assertEquals(800.00, $this->wallet->balance);
    }

    public function test_withdrawal_insufficient_balance()
    {
        $withdrawalData = [
            'wallet_id' => $this->wallet->id,
            'amount' => 1500.00, // More than available balance
            'idempotency_key' => Str::uuid()->toString()
        ];

        $this->expectException(InsufficientBalanceException::class);
        $this->transactionService->withdraw($withdrawalData);

        // Ensure balance hasn't changed
        $this->wallet->refresh();
        $this->assertEquals(1000.00, $this->wallet->balance);
    }

    public function test_failed_transaction_rollback()
    {
        // Create a wallet with specific balance
        $wallet = Wallet::factory()->create([
            'user_id' => $this->user->id,
            'balance' => 500.00,
            'status' => 'active'
        ]);

        // Mock a failure during deposit by using invalid wallet status
        $wallet->update(['status' => 'suspended']);

        $depositData = [
            'wallet_id' => $wallet->id,
            'amount' => 100.00,
            'idempotency_key' => Str::uuid()->toString()
        ];

        try {
            $this->transactionService->deposit($depositData);
        } catch (\Exception $e) {
            // Expected exception
        }

        // Check transaction marked as failed
        $transaction = Transaction::query()->where('idempotency_key', $depositData['idempotency_key'])->first();
        $this->assertEquals('failed', $transaction->status);

        // Balance should remain unchanged
        $wallet->refresh();
        $this->assertEquals(500.00, $wallet->balance);
    }
}
