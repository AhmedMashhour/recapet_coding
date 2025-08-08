<?php

namespace App\Services;

use App\Repositories\TransactionRepository;
use App\Repositories\TransferRepository;
use App\Repositories\WalletRepository;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\WalletNotFoundException;
use App\Traits\HasMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransferService
{
    use HasMoney;

    protected TransactionRepository $transactionRepository;
    protected TransferRepository $transferRepository;
    protected WalletRepository $walletRepository;
    protected FeeCalculatorService $feeCalculator;

    public function __construct()
    {
        $this->transactionRepository = new TransactionRepository();
        $this->transferRepository = new TransferRepository();
        $this->walletRepository = new WalletRepository();
        $this->feeCalculator = new FeeCalculatorService();
    }

    /**
     * @throws \Throwable
     */
    public function executeTransfer(int $senderWalletId, string $receiverWalletNumber, float $amount)
    {
        return DB::transaction(function () use ($senderWalletId, $receiverWalletNumber, $amount) {
            $transaction = $this->transactionRepository->create([
                'transaction_id' => Str::uuid()->toString(),
                'type' => 'transfer',
                'amount' => $amount,
                'status' => 'processing'
            ]);

            try {
                $receiverWallet = $this->walletRepository->getByKey('wallet_number', $receiverWalletNumber)->first();
                if (!$receiverWallet) {
                    throw new WalletNotFoundException("Receiver wallet not found or inactive");
                }

                // Lock both wallets (ordered by ID to prevent deadlock)
                $walletIds = [$senderWalletId, $receiverWallet->id];
                sort($walletIds);

                $wallet1 = $this->walletRepository->getByIdAndLock($walletIds[0]);
                $wallet2 = $this->walletRepository->getByIdAndLock($walletIds[1]);

                $senderWallet = $walletIds[0] === $senderWalletId ? $wallet1 : $wallet2;
                $receiverWallet = $walletIds[0] === $senderWalletId ? $wallet2 : $wallet1;

                if (!$senderWallet || $senderWallet->status !== 'active') {
                    throw new WalletNotFoundException("Sender wallet not available");
                }

                $fee = $this->feeCalculator->calculateTransferFee($amount);
                $totalDebit = $this->calculateWithPrecision('add', $amount, $fee);

                $this->transactionRepository->update($transaction, ['fee' => $fee]);

                if ($senderWallet->balance < $totalDebit) {
                    throw new InsufficientBalanceException("Insufficient balance");
                }

                $senderNewBalance = $this->calculateWithPrecision('subtract', $senderWallet->balance, $totalDebit);
                $senderWallet = $this->walletRepository->update($senderWallet, [
                    'balance' => $senderNewBalance,
                ]);

                if (!$senderWallet) {
                    throw new \Exception("Failed to update sender balance");
                }

                $receiverNewBalance = $this->calculateWithPrecision('add', $receiverWallet->balance, $amount);
                $success = $this->walletRepository->update($receiverWallet, [
                    'balance' => $receiverNewBalance,
                ]);

                if (!$success) {
                    throw new \Exception("Failed to update receiver balance");
                }

                $this->transferRepository->create([
                    'transaction_id' => $transaction->transaction_id,
                    'sender_wallet_id' => $senderWallet->id,
                    'receiver_wallet_id' => $receiverWallet->id,
                    'amount' => $amount,
                    'fee' => $fee
                ]);

                $this->transactionRepository->update($transaction, [
                    'status' => 'completed',
                    'completed_at' => now()
                ]);
//                Log::info("sender wallet  " . json_encode(json_decode($senderWallet,true)));
//                Log::info("receiverWallet  " . json_encode(json_decode($receiverWallet,true)));

                return $transaction->fresh()->load('transfer.senderWallet', 'transfer.receiverWallet');

            } catch (\Exception $e) {
                $this->transactionRepository->update($transaction, ['status' => 'failed']);
                throw $e;
            }
        }, 5); // Retry up to 5 times on deadlock


    }


}
