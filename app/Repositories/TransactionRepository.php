<?php

namespace App\Repositories;

use App\Models\Transaction;
use Illuminate\Support\Str;

class TransactionRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(Transaction::class);
    }

    public function getUserTransactions(int $userId, array $filters = [])
    {
        $query = $this->getModel
            ->with(['deposit'])
            ->where(function ($q) use ($userId) {
                $q->whereHas('deposit', function ($sq) use ($userId) {
                    $sq->whereHas('wallet', function ($wq) use ($userId) {
                        $wq->where('user_id', $userId);
                    });
                })->orWhereHas('withdrawal', function ($sq) use ($userId) {
                    $sq->whereHas('wallet', function ($wq) use ($userId) {
                        $wq->where('user_id', $userId);
                    });
                });
            });

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        return $query->orderBy('created_at', 'desc');
    }

}
