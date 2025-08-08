<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\Wallet;
use App\Repositories\LedgerEntryRepository;
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
    protected IdempotencyService $idempotencyService;
    protected LedgerService $ledgerService;


    public function __construct()
    {
        $this->transactionRepository = new TransactionRepository();
        $this->transferRepository = new TransferRepository();
        $this->walletRepository = new WalletRepository();
        $this->feeCalculator = new FeeCalculatorService();
        $this->idempotencyService = new IdempotencyService();
        $this->ledgerService = new LedgerService();

    }

    /**
     * @throws \Throwable
     */
    public function executeTransfer(int $senderWalletId, string $receiverWalletNumber, float $amount , string $idempotency_key)
    {
        $this->idempotencyService->checkIdempotent($idempotency_key);

        return DB::transaction(function () use ($senderWalletId, $receiverWalletNumber, $amount) {
            $transaction = $this->transactionRepository->create([
                'transaction_id' => Str::uuid()->toString(),
                'type' => 'transfer',
                'amount' => $amount,
                'status' => 'processing'
            ]);

            try {
                $receiverWallet = $this->walletRepository->getByKey('wallet_number', $receiverWalletNumber)->first();
//                dd($receiverWallet);
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

                $transfer = $this->transferRepository->create([
                    'transaction_id' => $transaction->transaction_id,
                    'sender_wallet_id' => $senderWallet->id,
                    'receiver_wallet_id' => $receiverWallet->id,
                    'amount' => $amount,
                    'fee' => $fee
                ]);
                // sender ledger
                $this->ledgerService->legerEntryLog(
                    wallet: $senderWallet, amount: $amount, type: LedgerEntry::LEDGER_TYPE_DEBIT,
                    transactionId: $transfer->transaction_id, referenceType: LedgerEntry::LEDGER_REFERANCE_TYPE_TRANSFER,
                    referenceId: $transfer->id, description: "Transfer to wallet {$receiverWallet->wallet_number}",

                );
                //fee ledger

                $this->ledgerService->legerEntryLog(
                    wallet: $senderWallet, amount: $fee, type: LedgerEntry::LEDGER_TYPE_FEE,
                    transactionId: $transfer->transaction_id, referenceType: LedgerEntry::LEDGER_REFERANCE_TYPE_TRANSFER,
                    referenceId: $transfer->id, description: "Transfer fee",
                );
                //receiver ledger

                $this->ledgerService->legerEntryLog(
                    wallet: $receiverWallet, amount: $amount, type: LedgerEntry::LEDGER_TYPE_CREDIT,
                    transactionId: $transfer->transaction_id, referenceType: LedgerEntry::LEDGER_REFERANCE_TYPE_TRANSFER,
                    referenceId: $transfer->id, description: "Transfer from wallet {$senderWallet->wallet_number}",
                );

                $this->transactionRepository->update($transaction, [
                    'status' => 'completed',
                    'completed_at' => now()
                ]);

                return $transaction->fresh()->load('transfer.senderWallet', 'transfer.receiverWallet');

            } catch (\Exception $e) {
                $this->transactionRepository->update($transaction, ['status' => 'failed']);
                throw $e;
            }
        }, 5); // Retry up to 5 times on deadlock


    }




}
