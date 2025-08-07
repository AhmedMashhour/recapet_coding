<?php

namespace App\Models;

use Database\Factories\LedgerEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    /** @use HasFactory<LedgerEntryFactory> */
    use HasFactory;

    public const LEDGER_TYPE_CREDIT = 'credit';
    public const LEDGER_TYPE_DEBIT = 'debit';
    public const LEDGER_TYPE_FEE = 'fee';

    public const LEDGER_REFERANCE_TYPE_DEPOSIT = 'deposit';
    public const LEDGER_REFERANCE_TYPE_WITHDRAWAL = 'withdrawal';
    public const LEDGER_REFERANCE_TYPE_TRANSFER = 'transfer';

    protected $fillable = [
        'transaction_id',
        'wallet_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'reference_type',
        'reference_id',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

}
