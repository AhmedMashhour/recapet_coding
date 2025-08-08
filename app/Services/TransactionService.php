<?php

namespace App\Services;

use App\Repositories\TransactionRepository;
use App\Repositories\WalletRepository;
use App\Repositories\DepositRepository;
use App\Traits\HasMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionService extends CrudService
{
    use HasMoney;

    protected TransactionRepository $transactionRepository;
    protected WalletRepository $walletRepository;
    protected DepositRepository $depositRepository;

    public function __construct()
    {
        parent::__construct('Transaction');
        $this->transactionRepository = new TransactionRepository();
        $this->walletRepository = new WalletRepository();
        $this->depositRepository = new DepositRepository();
    }


    /**
     * @throws \Throwable
     */
    public function deposit(array $data)
    {
        $transaction = null;

        DB::transaction(function () use ($data, &$transaction) {
            $transaction = $this->transactionRepository->create([
                'transaction_id' => Str::uuid()->toString(),
                'type' => 'deposit',
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

                $affected = $this->walletRepository->updateByIds([$wallet->id], 'id',
                    ['balance' => $newBalance]);

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

                $this->transactionRepository->update($transaction, [
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

            } catch (\Exception $e) {
                $this->transactionRepository->update($transaction, [
                    'status' => 'failed',
                ]);
                throw $e;
            }
        });
        return $transaction->fresh()->load('deposit');

    }

    public function getUserTransactions(int $userId, array $filters = [])
    {
        return $this->transactionRepository->getUserTransactions($userId, $filters);
    }
}
