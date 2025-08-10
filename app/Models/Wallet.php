<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'wallet_number',
        'balance',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'balance' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->balance, 2, '.', ',');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function sentTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'sender_wallet_id');
    }

    public function receivedTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'receiver_wallet_id');
    }
    public function transactions()
    {
        return Transaction::query()
            ->where(function ($query) {
                $query->whereHas('deposit', function ($q) {
                    $q->where('wallet_id', $this->id);
                })
                    ->orWhereHas('withdrawal', function ($q) {
                        $q->where('wallet_id', $this->id);
                    })
                    ->orWhereHas('transfer', function ($q) {
                        $q->where('sender_wallet_id', $this->id)
                            ->orWhere('receiver_wallet_id', $this->id);
                    });
            });
    }
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }



}
