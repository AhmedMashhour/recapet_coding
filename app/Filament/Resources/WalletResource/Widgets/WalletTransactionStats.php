<?php

namespace App\Filament\Resources\WalletResource\Widgets;

use App\Models\Wallet;
use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

class WalletTransactionStats extends BaseWidget
{
    public ?Model $record = null;

    protected function getStats(): array
    {
        if (!$this->record instanceof Wallet) {
            return [];
        }

        $wallet = $this->record;

        // Get transaction counts
        $totalTransactions = Transaction::query()
            ->where(function ($q) use ($wallet) {
                $q->whereHas('deposit', fn ($deposit) => $deposit->where('wallet_id', $wallet->id))
                    ->orWhereHas('withdrawal', fn ($withdrawal) => $withdrawal->where('wallet_id', $wallet->id))
                    ->orWhereHas('transfer', fn ($transfer) =>
                    $transfer->where('sender_wallet_id', $wallet->id)
                        ->orWhere('receiver_wallet_id', $wallet->id)
                    );
            })
            ->count();

        $completedTransactions = Transaction::query()
            ->where('status', 'completed')
            ->where(function ($q) use ($wallet) {
                $q->whereHas('deposit', fn ($deposit) => $deposit->where('wallet_id', $wallet->id))
                    ->orWhereHas('withdrawal', fn ($withdrawal) => $withdrawal->where('wallet_id', $wallet->id))
                    ->orWhereHas('transfer', fn ($transfer) =>
                    $transfer->where('sender_wallet_id', $wallet->id)
                        ->orWhere('receiver_wallet_id', $wallet->id)
                    );
            })
            ->count();

        // Calculate total inflows and outflows
        $totalDeposits = $wallet->deposits()
            ->whereHas('transaction', fn ($q) => $q->where('status', 'completed'))
            ->sum('amount');

        $totalWithdrawals = $wallet->withdrawals()
            ->whereHas('transaction', fn ($q) => $q->where('status', 'completed'))
            ->sum('amount');

        $totalSent = $wallet->sentTransfers()
            ->whereHas('transaction', fn ($q) => $q->where('status', 'completed'))
            ->sum('amount');

        $totalReceived = $wallet->receivedTransfers()
            ->whereHas('transaction', fn ($q) => $q->where('status', 'completed'))
            ->sum('amount');

        $totalFees = $wallet->sentTransfers()
            ->whereHas('transaction', fn ($q) => $q->where('status', 'completed'))
            ->sum('fee');

        $totalInflow = $totalDeposits + $totalReceived;
        $totalOutflow = $totalWithdrawals + $totalSent + $totalFees;

        return [
            Stat::make('Total Transactions', number_format($totalTransactions))
                ->description($completedTransactions . ' completed')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('info'),

            Stat::make('Total Inflow', '$' . number_format($totalInflow, 2))
                ->description('Deposits + Received transfers')
                ->descriptionIcon('heroicon-m-arrow-down-circle')
                ->color('success'),

            Stat::make('Total Outflow', '$' . number_format($totalOutflow, 2))
                ->description('Withdrawals + Sent transfers + Fees')
                ->descriptionIcon('heroicon-m-arrow-up-circle')
                ->color('danger'),

            Stat::make('Net Flow', '$' . number_format($totalInflow - $totalOutflow, 2))
                ->description('Inflow - Outflow')
                ->descriptionIcon($totalInflow >= $totalOutflow ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($totalInflow >= $totalOutflow ? 'success' : 'danger'),
        ];
    }

    public static function canView(): bool
    {
        return true;
    }
}
