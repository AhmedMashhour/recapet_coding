<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\LedgerEntry;
use App\Models\Wallet;
use App\Repositories\LedgerEntryRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\TransferRepository;
use App\Repositories\WalletRepository;
use App\Exceptions\WalletLockedException;
use App\Exceptions\WalletNotFoundException;
use App\Traits\HasMoney;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    const WALLET_LOCK_TIMEOUT = 30;
    const MAX_RETRY_ATTEMPTS = 3;
    const RETRY_DELAY_MS = 500;
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
    public function executeTransfer(int $senderWalletId, string $receiverWalletNumber, float $amount, string $idempotency_key)
    {
        $this->idempotencyService->checkIdempotent($idempotency_key);
        $transaction = $this->transactionRepository->create([
            'transaction_id' => Str::uuid()->toString(),
            'type' => 'transfer',
            'amount' => $amount,
            'status' => 'pending'
        ]);
        try {

            $receiverWallet = $this->walletRepository->getByKey('wallet_number', $receiverWalletNumber)->first();
            if (!$receiverWallet || $receiverWallet->status !== 'active') {
                throw new WalletNotFoundException("Receiver wallet not found or inactive");
            }

            // Lock both wallets (ordered by ID to prevent deadlock)
            $walletIds = [$senderWalletId, $receiverWallet->id];
            sort($walletIds);
            $result = $this->executeWithMultipleWalletLocks($walletIds, function ($wallets) use ($senderWalletId, $receiverWallet, $amount, &$transaction) {
                // Get fresh wallet data
                $senderWallet = $wallets[$senderWalletId];
                $receiverWallet = $wallets[$receiverWallet->id];

                // Update transaction status
                $this->transactionRepository->update($transaction, [
                    'status' => 'processing'
                ]);

                // Calculate fee and total debit
                $fee = $this->feeCalculator->calculateTransferFee($amount);
                $totalDebit = $this->calculateWithPrecision('add', $amount, $fee);

                // Update transaction with fee
                $this->transactionRepository->update($transaction, ['fee' => $fee]);

                // Check sufficient balance
                if ($senderWallet->balance < $totalDebit) {
                    throw new InsufficientBalanceException("Insufficient balance");
                }

                // Update sender balance
                $senderWallet->balance = $this->calculateWithPrecision('subtract', $senderWallet->balance, $totalDebit);
                $senderWallet->save();

                // Update receiver balance
                $receiverWallet->balance = $this->calculateWithPrecision('add', $receiverWallet->balance, $amount);
                $receiverWallet->save();

                // Create transfer record
                $transfer = $this->transferRepository->create([
                    'transaction_id' => $transaction->transaction_id,
                    'sender_wallet_id' => $senderWallet->id,
                    'receiver_wallet_id' => $receiverWallet->id,
                    'amount' => $amount,
                    'fee' => $fee
                ]);

                // Create ledger entries
                $this->createTransferLedgerEntries($senderWallet, $receiverWallet, $transfer);

                // Mark transaction as completed
                $this->transactionRepository->update($transaction, [
                    'status' => 'completed',
                    'completed_at' => now()
                ]);

                return $transaction->fresh()->load('transfer.senderWallet', 'transfer.receiverWallet');
            });

            return $result;


        } catch (\Exception $e) {
            $this->transactionRepository->update($transaction, ['status' => 'failed']);
            throw $e;
        }

    }

    protected function executeWithMultipleWalletLocks(array $walletIds, callable $callback, $attempt = 1)
    {
        $locks = [];
        $lockedWallets = [];

        try {
            // Try to acquire all locks
            foreach ($walletIds as $walletId) {
                $lockKey = "wallet_lock:{$walletId}";
                $lock = Cache::lock($lockKey, self::WALLET_LOCK_TIMEOUT);

                if ($lock->get()) {
                    $locks[$walletId] = $lock;
                } else {
                    // Failed to get lock, retry or throw exception
                    if ($attempt < self::MAX_RETRY_ATTEMPTS) {
                        // Release all acquired locks
                        foreach ($locks as $acquiredLock) {
                            $acquiredLock->release();
                        }

                        // Wait and retry
                        usleep(self::RETRY_DELAY_MS * 1000);
                        return $this->executeWithMultipleWalletLocks($walletIds, $callback, $attempt + 1);
                    } else {
                        throw new WalletLockedException("One or more wallets are currently processing other transactions. Please try again.");
                    }
                }
            }

            // All locks acquired, execute transaction
            return DB::transaction(function () use ($walletIds, $callback) {
                $wallets = [];

                // Get fresh wallet data with row locks
                foreach ($walletIds as $walletId) {
                    $wallet = $this->walletRepository->getByIdAndLock($walletId);
                    if (!$wallet) {
                        throw new \Exception("Wallet {$walletId} not found");
                    }
                    $wallets[$walletId] = $wallet;
                }

                // Execute the callback
                return $callback($wallets);
            });

        } finally {
            // Always release all locks
            foreach ($locks as $lock) {
                $lock->release();
            }
        }
    }

    protected function createTransferLedgerEntries($senderWallet, $receiverWallet, $transfer)
    {
        // Sender debit
        $this->ledgerService->legerEntryLog(
            wallet: $senderWallet,
            amount: $transfer->amount,
            type: LedgerEntry::LEDGER_TYPE_DEBIT,
            transactionId: $transfer->transaction_id,
            referenceType: LedgerEntry::LEDGER_REFERANCE_TYPE_TRANSFER,
            referenceId: $transfer->id,
            description: "Transfer to wallet {$receiverWallet->wallet_number}"
        );

        // Fee entry
            $this->ledgerService->legerEntryLog(
                wallet: $senderWallet,
                amount: $transfer->fee,
                type: LedgerEntry::LEDGER_TYPE_FEE,
                transactionId: $transfer->transaction_id,
                referenceType: LedgerEntry::LEDGER_REFERANCE_TYPE_TRANSFER,
                referenceId: $transfer->id,
                description: "Transfer fee"
            );

        // Receiver credit
        $this->ledgerService->legerEntryLog(
            wallet: $receiverWallet,
            amount: $transfer->amount,
            type: LedgerEntry::LEDGER_TYPE_CREDIT,
            transactionId: $transfer->transaction_id,
            referenceType: LedgerEntry::LEDGER_REFERANCE_TYPE_TRANSFER,
            referenceId: $transfer->id,
            description: "Transfer from wallet {$senderWallet->wallet_number}"
        );
    }


}
