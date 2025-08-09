<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\WalletLockedException;
use App\Models\LedgerEntry;
use App\Repositories\WalletRepository;
use App\Repositories\DepositRepository;
use App\Repositories\WithdrawalRepository;
use App\Traits\HasMoney;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TransactionService extends CrudService
{
    use HasMoney;

    protected WalletRepository $walletRepository;
    protected DepositRepository $depositRepository;
    protected WithdrawalRepository $withdrawalRepository;

    protected IdempotencyService $idempotencyService;
    protected LedgerService $ledgerService;
    const WALLET_LOCK_TIMEOUT = 30;
    // Max retry attempts
    const MAX_RETRY_ATTEMPTS = 3;
    // Delay between retries in milliseconds
    const RETRY_DELAY_MS = 500;

    public function __construct()
    {
        parent::__construct('Transaction');
        $this->walletRepository = new WalletRepository();
        $this->depositRepository = new DepositRepository();
        $this->withdrawalRepository = new WithdrawalRepository();
        $this->idempotencyService = new IdempotencyService();
        $this->ledgerService = new LedgerService();

    }


    /**
     * @throws \Throwable
     */
    public function deposit(array $data)
    {
        $this->idempotencyService->checkIdempotent($data['idempotency_key']);
        $transaction = $this->repository->create([
            'transaction_id' => Str::uuid()->toString(),
            'type' => 'deposit',
            'idempotency_key' => $data['idempotency_key'],
            'amount' => $data['amount'],
            'status' => 'pending',
            'fee' => 0
        ]);
        try {
            return $this->executeWithWalletLock($data['wallet_id'], function($wallet) use ($data, &$transaction) {
                $this->repository->update($transaction, [
                    'status' => 'processing',
                ]);

                if ($wallet->status !== 'active') {
                    throw new \Exception("Wallet not available");
                }

                $newBalance = $this->calculateWithPrecision('add', $wallet->balance, $data['amount']);

                $wallet->balance = $newBalance;
                $wallet->save();

                $deposit = $this->depositRepository->create([
                    'transaction_id' => $transaction->transaction_id,
                    'wallet_id' => $wallet->id,
                    'amount' => $data['amount'],
                    'payment_method' => $data['payment_method'] ?? 'bank_transfer',
                    'payment_reference' => $data['payment_reference'] ?? null
                ]);

                $this->ledgerService->legerEntryLog(
                    wallet: $wallet, amount: $data['amount'], type: LedgerEntry::LEDGER_TYPE_CREDIT,
                    transactionId: $deposit->transaction_id, referenceType: LedgerEntry::LEDGER_REFERANCE_TYPE_DEPOSIT,
                    referenceId: $deposit->id, description: 'Deposit via ' . ($data['payment_method'] ?? 'bank_transfer'),

                );


                // Mark transaction as completed
                $this->repository->update($transaction, [
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                return $transaction->fresh()->load('deposit');
            });

        } catch (\Exception $e) {
            $this->repository->update($transaction, [
                'status' => 'failed',
            ]);
            throw $e;
        }
    }

    /**
     * @throws \Throwable
     */
    public function withdraw(array $data)
    {
        $this->idempotencyService->checkIdempotent($data['idempotency_key']);
        $transaction = $this->repository->create([
            'transaction_id' => Str::uuid()->toString(),
            'type' => 'withdrawal',
            'idempotency_key' => $data['idempotency_key'],
            'amount' => $data['amount'],
            'status' => 'pending',
            'fee' => 0
        ]);
        try {

            return $this->executeWithWalletLock($data['wallet_id'], function($wallet) use ($data, &$transaction) {
                $this->repository->update($transaction, [
                    'status' => 'processing',
                ]);

                if ($wallet->status !== 'active') {
                    throw new \Exception("Wallet not available");
                }

                if ($wallet->balance < $data['amount']) {
                    throw new InsufficientBalanceException("Insufficient balance");
                }

                $newBalance = $this->calculateWithPrecision('subtract', $wallet->balance, $data['amount']);

                $wallet->balance = $newBalance;
                $wallet->save();

                $withdrawal = $this->withdrawalRepository->create([
                    'transaction_id' => $transaction->transaction_id,
                    'wallet_id' => $wallet->id,
                    'amount' => $data['amount'],
                    'withdrawal_method' => $data['withdrawal_method'] ?? 'bank_transfer',
                    'withdrawal_reference' => $data['withdrawal_reference'] ?? null
                ]);

                $this->ledgerService->legerEntryLog(
                    wallet: $wallet, amount: $data['amount'], type: LedgerEntry::LEDGER_TYPE_DEBIT,
                    transactionId: $withdrawal->transaction_id, referenceType: LedgerEntry::LEDGER_REFERANCE_TYPE_WITHDRAWAL,
                    referenceId: $withdrawal->id, description: 'Withdrawal via ' . ($data['withdrawal_method'] ?? 'bank_transfer'),

                );

                // Mark transaction as completed
                $this->repository->update($transaction, [
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                return $transaction->fresh()->load('withdrawal');
            });

        } catch (\Exception $e) {
            $this->repository->update($transaction, [
                'status' => 'failed',
                'completed_at' => null,
            ]);
            throw $e;
        }

    }

    /**
     * @throws \Throwable
     * @throws WalletLockedException
     */
    protected function executeWithWalletLock($walletId, callable $callback, $attempt = 1)
    {
        $lockKey = "wallet_lock:{$walletId}";
        $lock = Cache::lock($lockKey, self::WALLET_LOCK_TIMEOUT);

        try {
            // Try to acquire lock
            if ($lock->get()) {
                // Lock acquired, execute the transaction
                return DB::transaction(function () use ($walletId, $callback) {
                    // Get fresh wallet data with row lock
                    $wallet = $this->walletRepository->getByIdAndLock($walletId);

                    if (!$wallet) {
                        throw new \Exception("Wallet not found");
                    }

                    // Execute the callback with fresh wallet data
                    return $callback($wallet);
                });
            } else {
                // Lock not acquired
                if ($attempt < self::MAX_RETRY_ATTEMPTS) {
                    // Wait and retry
                    usleep(self::RETRY_DELAY_MS * 1000);
                    return $this->executeWithWalletLock($walletId, $callback, $attempt + 1);
                } else {
                    // Max retries reached
                    throw new WalletLockedException("Wallet is currently processing another transaction. Please try again.");
                }
            }
        } finally {
            // Always release the lock
            optional($lock)->release();
        }
    }


    public function getTransactionById(string $transactionId)
    {
        return $this->repository->getByKey('transaction_id', $transactionId, ['deposit', 'withdrawal', 'transfer'])->first();
    }

    public function getUserTransactions(int $userId, array $filters = [])
    {
        return $this->repository->getUserTransactions($userId, $filters);
    }
}
