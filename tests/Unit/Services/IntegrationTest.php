<?php
namespace Tests\Unit\Services;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use App\Services\FeeCalculatorService;
use App\Services\TransactionService;
use App\Services\TransferService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;


class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @throws \Throwable
     */
    public function test_complete_user_flow()
    {
        // 1. Register users
        $userService = new UserService();

        $user1Data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123'
        ];

        $user2Data = [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'password' => 'password456'
        ];

        $user1 = $userService->register($user1Data);
        $user2 = $userService->register($user2Data);

        $this->assertNotNull($user1->wallet);
        $this->assertNotNull($user2->wallet);
        $this->assertEquals(0.00, $user1->wallet->balance);
        $this->assertEquals(0.00, $user2->wallet->balance);

        // 2. User 1 deposits money
        $transactionService = new TransactionService();

        $deposit = $transactionService->deposit([
            'wallet_id' => $user1->wallet->id,
            'amount' => 1000.00,
            'payment_method' => 'bank_transfer',
            'idempotency_key' => Str::uuid()->toString()
        ]);

        $this->assertEquals('completed', $deposit->status);
        $user1->wallet->refresh();
        $this->assertEquals(1000.00, $user1->wallet->balance);

        // 3. User 1 transfers to User 2
        $transferService = new TransferService();

        $transfer = $transferService->executeTransfer(
            $user1->wallet->id,
            $user2->wallet->wallet_number,
            200.00,
            Str::uuid()->toString()
        );

        $this->assertEquals('completed', $transfer->status);

        // 4. Check final balances
        $user1->wallet->refresh();
        $user2->wallet->refresh();

        $fee = (new FeeCalculatorService())->calculateTransferFee(200.00);
        $expectedUser1Balance = 1000.00 - 200.00 - $fee;

        $this->assertEquals($expectedUser1Balance, $user1->wallet->balance);
        $this->assertEquals(200.00, $user2->wallet->balance);

        // 5. User 2 withdraws
        $withdrawal = $transactionService->withdraw([
            'wallet_id' => $user2->wallet->id,
            'amount' => 50.00,
            'withdrawal_method' => 'bank_transfer',
            'idempotency_key' => Str::uuid()->toString()
        ]);

        $this->assertEquals('completed', $withdrawal->status);
        $user2->wallet->refresh();
        $this->assertEquals(150.00, $user2->wallet->balance);

        // 6. Verify transaction history
        $user1Transactions = $transactionService->getUserTransactions($user1->id)->get();
        $user2Transactions = $transactionService->getUserTransactions($user2->id)->get();

        $this->assertCount(2, $user1Transactions); // deposit + transfer
        $this->assertCount(2, $user2Transactions); // transfer + withdrawal
    }
}
