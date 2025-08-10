<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BalanceSnapshot extends Model
{
    protected $fillable = [
        'snapshot_id',
        'snapshot_time',
        'total_wallets',
        'active_wallets',
        'total_balance',
        'total_deposits',
        'total_withdrawals',
        'total_fees',
        'calculated_balance',
        'balance_discrepancy',
        'wallet_balances',
        'statistics',
        'discrepancies',
        'status',
        'notes'
    ];

    protected $casts = [
        'snapshot_time' => 'datetime',
        'wallet_balances' => 'array',
        'statistics' => 'array',
        'discrepancies' => 'array',
        'total_balance' => 'decimal:2',
        'total_deposits' => 'decimal:2',
        'total_withdrawals' => 'decimal:2',
        'total_fees' => 'decimal:2',
        'calculated_balance' => 'decimal:2',
        'balance_discrepancy' => 'decimal:2',
    ];

    public function walletSnapshots(): HasMany
    {
        return $this->hasMany(WalletBalanceSnapshot::class, 'snapshot_id');
    }
}
