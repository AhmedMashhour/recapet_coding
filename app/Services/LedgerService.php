<?php
namespace App\Services;

use App\Models\Wallet;
use App\Traits\HasMoney;

class LedgerService extends CrudService
{
    use HasMoney;

    public function __construct()
    {
        parent::__construct('LedgerEntry');

    }
    public function legerEntryLog(Wallet $wallet, float $amount ,string $type , string $transactionId,string $referenceType , int $referenceId ,string $description = null)
    {
        $lastEntry = $this->repository->getByKey('wallet_id' ,$wallet->id)->orderBy('id', 'desc')->first();

        $balanceBefore = $lastEntry ? $lastEntry->balance_after : 0.0;

        $balanceAfter = match($type) {
            'credit' => $this->calculateWithPrecision('add', $balanceBefore, $amount),
            'debit', 'fee' => $this->calculateWithPrecision('subtract', $balanceBefore, $amount),
        };

        return $this->create([
            'transaction_id' => $transactionId,
            'wallet_id' => $wallet->id,
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => max(0, $balanceAfter),
            'reference_type' =>$referenceType,
            'reference_id' => $referenceId,
            'description' => $description
        ]);
    }

}
