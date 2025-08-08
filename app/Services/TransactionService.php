<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Repositories\WalletRepository;
use App\Repositories\DepositRepository;
use App\Repositories\WithdrawalRepository;
use App\Traits\HasMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionService extends CrudService
{
    use HasMoney;

    protected WalletRepository $walletRepository;
    protected DepositRepository $depositRepository;
    protected WithdrawalRepository $withdrawalRepository;

    protected IdempotencyService $idempotencyService;

    public function __construct()
    {
        parent::__construct('Transaction');
        $this->walletRepository = new WalletRepository();
        $this->depositRepository = new DepositRepository();
        $this->withdrawalRepository = new WithdrawalRepository();
        $this->idempotencyService = new IdempotencyService();

    }


    /**
     * @throws \Throwable
     */
    public function deposit(array $data)
    {
        $this->idempotencyService->checkIdempotent($data['idempotency_key']);

        return DB::transaction(function () use ($data, &$transaction) {

            $transaction = $this->repository->create([
                'transaction_id' => Str::uuid()->toString(),
                'type' => 'deposit',
                'idempotency_key' => $data['idempotency_key'],
                'amount' => $data['amount'],
                'status' => 'processing',
                'fee' => 0
            ]);

            try {
                $wallet = $this->walletRepository->getByIdAndLock($data['wallet_id']);
                if (!$wallet || $wallet->status !== 'active') {
                    throw new \Exception("Wallet not available");
                }
                $newBalance = $this->calculateWithPrecision('add', $wallet->balance, $data['amount']);

                $affected = $this->walletRepository->update($wallet, [
                    'balance' => $newBalance,
                ]);

                if (!$affected) {
                    throw new \Exception("Failed to update balance");
                }

                $this->depositRepository->create([
                    'transaction_id' => $transaction->transaction_id,
                    'wallet_id' => $wallet->id,
                    'amount' => $data['amount'],
                    'payment_method' => $data['payment_method'] ?? 'bank_transfer',
                    'payment_reference' => $data['payment_reference'] ?? null
                ]);

                $this->repository->update($transaction, [
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
                return $transaction->fresh()->load('deposit');

            } catch (\Exception $e) {
                $this->repository->update($transaction, [
                    'status' => 'failed',
                ]);
                throw $e;
            }
        });

    }

    /**
     * @throws \Throwable
     */
    public function withdraw(array $data)
    {
        $this->idempotencyService->checkIdempotent($data['idempotency_key']);

        return DB::transaction(function () use ($data) {
            $transaction = $this->repository->create([
                'transaction_id' => Str::uuid()->toString(),
                'type' => 'withdrawal',
                'idempotency_key' => $data['idempotency_key'],
                'amount' => $data['amount'],
                'status' => 'pending',
                'fee' => 0
            ]);

            try {
                $wallet = $this->walletRepository->getByIdAndLock($data['wallet_id']);

                if (!$wallet || $wallet->status !== 'active') {
                    throw new \Exception("Wallet not available");
                }

                if ($wallet->balance < $data['amount']) {
                    throw new InsufficientBalanceException("Insufficient balance");
                }

                $newBalance = $this->calculateWithPrecision('subtract', $wallet->balance, $data['amount']);

                $success = $this->walletRepository->update($wallet, [
                    'balance' => $newBalance,
                ]);

                if (!$success) {
                    throw new \Exception("Failed to update balance");
                }

                $this->withdrawalRepository->create([
                    'transaction_id' => $transaction->transaction_id,
                    'wallet_id' => $wallet->id,
                    'amount' => $data['amount'],
                    'withdrawal_method' => $data['withdrawal_method'] ?? 'bank_transfer',
                    'withdrawal_reference' => $data['withdrawal_reference'] ?? null
                ]);

                $this->repository->update($transaction, [
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                return $transaction->fresh()->load('withdrawal');

            } catch (\Exception $e) {
                $this->repository->update($transaction, [
                    'status' => 'failed',
                    'completed_at' => null,
                ]);
                throw $e;
            }
        });

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
