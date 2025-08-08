<?php
namespace App\Repositories;

use App\Models\Withdrawal;

class WithdrawalRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(Withdrawal::class);
    }

    /**
     * Get wallet withdrawals
     */
    public function getWalletWithdrawals(int $walletId, array $filters = [])
    {
        $query = $this->getModel
            ->with(['transaction'])
            ->where('wallet_id', $walletId);

        if (!empty($filters['withdrawal_method'])) {
            $query->where('withdrawal_method', $filters['withdrawal_method']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Get total withdrawals for wallet
     */
    public function getTotalWithdrawals(int $walletId, ?string $fromDate = null): float
    {
        $query = $this->getModel->where('wallet_id', $walletId);

        if ($fromDate) {
            $query->where('created_at', '>=', $fromDate);
        }

        return (float) $query->sum('amount');
    }
}
