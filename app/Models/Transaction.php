<?php

namespace App\Models;

use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    public const TRANSACTION_TYPE_DEPOSIT = 'deposit';
    public const TRANSACTION_TYPE_WITHDRAWAL = 'withdrawal';
    public const TRANSACTION_TYPE_TRANSFER = 'transfer';

    public const TRANSACTION_STATUS_PENDING = 'pending';
    public const TRANSACTION_STATUS_PROCESSING = 'processing';
    public const TRANSACTION_STATUS_COMPLETED = 'completed';
    public const TRANSACTION_STATUS_FAILED = 'failed';

    protected $fillable = [
        'transaction_id',
        'idempotency_key',
        'type',
        'status',
        'amount',
        'fee',
        'metadata',
        'completed_at',
    ];
    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'metadata' => 'array',
        'completed_at' => 'datetime',
    ];

    public function deposit(): HasOne
    {
        return $this->hasOne(Deposit::class, 'transaction_id', 'transaction_id');
    }

    public function withdrawal(): HasOne
    {
        return $this->hasOne(Withdrawal::class, 'transaction_id', 'transaction_id');
    }

    public function transfer(): HasOne
    {
        return $this->hasOne(Transfer::class, 'transaction_id', 'transaction_id');
    }


}
