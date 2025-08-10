<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletBalanceSnapshot extends Model
{
    protected $fillable = [
        'snapshot_id',
        'wallet_id',
        'wallet_number',
        'wallet_balance',
        'ledger_balance',
        'discrepancy',
        'transaction_count',
        'last_transaction_at',
        'metadata'
    ];

    protected $casts = [
        'wallet_balance' => 'decimal:2',
        'ledger_balance' => 'decimal:2',
        'discrepancy' => 'decimal:2',
        'last_transaction_at' => 'datetime',
        'metadata' => 'array'
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(BalanceSnapshot::class, 'snapshot_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

}
