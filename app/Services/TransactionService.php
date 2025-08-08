<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\LedgerEntry;
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
    protected LedgerService $ledgerService;

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

                $deposit = $this->depositRepository->create([
                    'transaction_id' => $transaction->transaction_id,
                    'wallet_id' => $wallet->id,
                    'amount' => $data['amount'],
                    'payment_method' => $data['payment_method'] ?? 'bank_transfer',
                    'payment_reference' => $data['payment_reference'] ?? null
                ]);

//                $lastEntry = $this->ledgerRepository->getByKey('wallet_id' ,$wallet->id)->orderBy('id', 'desc')->first();
//
//                $balanceBefore = $lastEntry ? $lastEntry->balance_after : 0;
//
//                $balanceAfter = $this->calculateWithPrecision('add', $balanceBefore, $data['amount']);
//
//                $this->ledgerRepository->create([
//                    'transaction_id' => $deposit->transaction_id,
//                    'wallet_id' => $wallet->id,
//                    'type' => LedgerEntry::LEDGER_TYPE_CREDIT,
//                    'amount' =>  $data['amount'],
//                    'balance_before' => $balanceBefore,
//                    'balance_after' => max(0, $balanceAfter),
//                    'reference_type' => LedgerEntry::LEDGER_REFERANCE_TYPE_DEPOSIT,
//                    'reference_id' => $deposit->id,
//                    'description' => 'Deposit via ' . ($data['payment_method'] ?? 'bank_transfer'),
//                ]);

                $this->ledgerService->legerEntryLog(
                    wallet: $wallet, amount: $data['amount'], type: LedgerEntry::LEDGER_TYPE_CREDIT,
                    transactionId: $deposit->transaction_id, referenceType: LedgerEntry::LEDGER_REFERANCE_TYPE_DEPOSIT,
                    referenceId: $deposit->id, description: 'Deposit via ' . ($data['payment_method'] ?? 'bank_transfer'),

                );

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

                $withdrawal=  $this->withdrawalRepository->create([
                    'transaction_id' => $transaction->transaction_id,
                    'wallet_id' => $wallet->id,
                    'amount' => $data['amount'],
                    'withdrawal_method' => $data['withdrawal_method'] ?? 'bank_transfer',
                    'withdrawal_reference' => $data['withdrawal_reference'] ?? null
                ]);

//                $lastEntry = $this->ledgerRepository->getByKey('wallet_id' ,$wallet->id)->orderBy('id', 'desc')->first();
//
//                $balanceBefore = $lastEntry ? $lastEntry->balance_after : 0;
//
//                $balanceAfter = $this->calculateWithPrecision('subtract', $balanceBefore, $data['amount']);
//
//                $this->ledgerRepository->create([
//                    'transaction_id' => $withdrawal->transaction_id,
//                    'wallet_id' => $wallet->id,
//                    'type' => LedgerEntry::LEDGER_TYPE_DEBIT,
//                    'amount' =>  $data['amount'],
//                    'balance_before' => $balanceBefore,
//                    'balance_after' => max(0, $balanceAfter),
//                    'reference_type' => LedgerEntry::LEDGER_REFERANCE_TYPE_WITHDRAWAL,
//                    'reference_id' => $withdrawal->id,
//                    'description' => 'Withdrawal via ' . ($data['withdrawal_method'] ?? 'bank_transfer'),
//                ]);

                $this->ledgerService->legerEntryLog(
                    wallet: $wallet, amount: $data['amount'], type: LedgerEntry::LEDGER_TYPE_DEBIT,
                    transactionId: $withdrawal->transaction_id, referenceType: LedgerEntry::LEDGER_REFERANCE_TYPE_WITHDRAWAL,
                    referenceId: $withdrawal->id, description: 'Withdrawal via ' . ($data['withdrawal_method'] ?? 'bank_transfer'),

                );
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
