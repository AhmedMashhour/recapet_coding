<?php

namespace Tests\Unit\Services;

use App\Exceptions\InsufficientBalanceException;
use Tests\TestCase;
use App\Services\FeeCalculatorService;
use App\Services\TransferService;
use App\Models\User;
use App\Models\Wallet;
use App\Exceptions\WalletLockedException;
use App\Exceptions\WalletNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
class TransferServiceTest extends TestCase
{
    use RefreshDatabase;

    private TransferService $transferService;
    private FeeCalculatorService $feeCalculator;
    private User $sender;
    private User $receiver;
    private Wallet $senderWallet;
    private Wallet $receiverWallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transferService = new TransferService();
        $this->feeCalculator = new FeeCalculatorService();

        // Create sender and receiver
        $this->sender = User::factory()->create();
        $this->receiver = User::factory()->create();

        $this->senderWallet = Wallet::factory()->create([
            'user_id' => $this->sender->id,
            'balance' => 1000.00,
            'status' => 'active'
        ]);

        $this->receiverWallet = Wallet::factory()->create([
            'user_id' => $this->receiver->id,
            'balance' => 500.00,
            'status' => 'active'
        ]);
    }

    public function test_successful_transfer_with_fee()
    {
        $amount = 100.00;
        $idempotencyKey = Str::uuid()->toString();

        $transaction = $this->transferService->executeTransfer(
            $this->senderWallet->id,
            $this->receiverWallet->wallet_number,
            $amount,
            $idempotencyKey
        );

        $fee = $this->feeCalculator->calculateTransferFee($amount);

        $this->assertEquals('transfer', $transaction->type);
        $this->assertEquals('completed', $transaction->status);
        $this->assertEquals($amount, $transaction->amount);
        $this->assertEquals($fee, $transaction->fee);

        // Check balances
        $this->senderWallet->refresh();
        $this->receiverWallet->refresh();

        $expectedSenderBalance = 1000.00 - $amount - $fee;
        $expectedReceiverBalance = 500.00 + $amount;

        $this->assertEquals($expectedSenderBalance, $this->senderWallet->balance);
        $this->assertEquals($expectedReceiverBalance, $this->receiverWallet->balance);
    }

    public function test_transfer_no_fee_under_threshold()
    {
        $amount = 20.00; // Under $25 threshold
        $idempotencyKey = Str::uuid()->toString();

        $transaction = $this->transferService->executeTransfer(
            $this->senderWallet->id,
            $this->receiverWallet->wallet_number,
            $amount,
            $idempotencyKey
        );

        $this->assertEquals(2.50, $transaction->fee);

        $this->senderWallet->refresh();
        $this->receiverWallet->refresh();

        $this->assertEquals(977.50, $this->senderWallet->balance);
        $this->assertEquals(520.00, $this->receiverWallet->balance);
    }

    public function test_transfer_insufficient_balance_with_fee()
    {
        $amount = 995.00; // With fee, this exceeds balance
        $idempotencyKey = Str::uuid()->toString();

        $this->expectException(InsufficientBalanceException::class);

        $this->transferService->executeTransfer(
            $this->senderWallet->id,
            $this->receiverWallet->wallet_number,
            $amount,
            $idempotencyKey
        );

        // Ensure no balance changes
        $this->senderWallet->refresh();
        $this->receiverWallet->refresh();

        $this->assertEquals(1000.00, $this->senderWallet->balance);
        $this->assertEquals(500.00, $this->receiverWallet->balance);
    }

    public function test_transfer_to_non_existent_wallet()
    {
        $idempotencyKey = Str::uuid()->toString();

        $this->expectException(WalletNotFoundException::class);

        $this->transferService->executeTransfer(
            $this->senderWallet->id,
            'INVALID_WALLET_NUMBER',
            100.00,
            $idempotencyKey
        );
    }
}
