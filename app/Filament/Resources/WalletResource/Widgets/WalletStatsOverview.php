<?php

namespace App\Filament\Resources\WalletResource\Widgets;

use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\Deposit;
use App\Models\Withdrawal;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class WalletStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalBalance = Wallet::sum('balance');
        $activeWallets = Wallet::where('status', 'active')->count();
        $totalWallets = Wallet::count();

        // Calculate today's transactions
        $todayDeposits = Deposit::whereHas('transaction', function ($query) {
            $query->where('status', 'completed')
                ->whereDate('completed_at', today());
        })->sum('amount');

        $todayWithdrawals = Withdrawal::whereHas('transaction', function ($query) {
            $query->where('status', 'completed')
                ->whereDate('completed_at', today());
        })->sum('amount');

        $todayFees = Transaction::where('status', 'completed')
            ->where('type', 'transfer')
            ->whereDate('completed_at', today())
            ->sum('fee');

        // Calculate 7-day trend
        $lastWeekBalance = DB::table('wallets')
            ->join(DB::raw('(SELECT wallet_id, MAX(created_at) as max_date FROM ledger_entries WHERE created_at <= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY wallet_id) as last_week'), function($join) {
                $join->on('wallets.id', '=', 'last_week.wallet_id');
            })
            ->join('ledger_entries', function($join) {
                $join->on('ledger_entries.wallet_id', '=', 'wallets.id')
                    ->on('ledger_entries.created_at', '=', 'last_week.max_date');
            })
            ->sum('ledger_entries.balance_after');

        $balanceChange = $totalBalance - $lastWeekBalance;
        $balanceChangePercentage = $lastWeekBalance > 0 ? ($balanceChange / $lastWeekBalance) * 100 : 0;

        return [
            Stat::make('Total System Balance', '$' . number_format($totalBalance, 2))
                ->description(($balanceChange >= 0 ? '+' : '') . '$' . number_format($balanceChange, 2) . ' from last week')
                ->descriptionIcon($balanceChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($balanceChange >= 0 ? 'success' : 'danger')
                ->chart($this->getBalanceChart()),

            Stat::make('Active Wallets', number_format($activeWallets))
                ->description($totalWallets . ' total wallets')
                ->descriptionIcon('heroicon-m-wallet')
                ->color('info'),

            Stat::make('Today\'s Volume', '$' . number_format($todayDeposits + $todayWithdrawals, 2))
                ->description('Deposits: $' . number_format($todayDeposits, 2) . ' | Withdrawals: $' . number_format($todayWithdrawals, 2))
                ->descriptionIcon('heroicon-m-arrows-right-left')
                ->color('warning'),

            Stat::make('Today\'s Fees', '$' . number_format($todayFees, 2))
                ->description('From transfer transactions')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
        ];
    }

    protected function getBalanceChart(): array
    {
        // Get daily balance for the last 7 days
        $balances = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->endOfDay();

            $balance = DB::table('wallets')
                ->join(DB::raw('(SELECT wallet_id, MAX(created_at) as max_date FROM ledger_entries WHERE created_at <= ? GROUP BY wallet_id) as daily'), function($join) {
                    $join->on('wallets.id', '=', 'daily.wallet_id');
                })
                ->join('ledger_entries', function($join) {
                    $join->on('ledger_entries.wallet_id', '=', 'wallets.id')
                        ->on('ledger_entries.created_at', '=', 'daily.max_date');
                })
                ->setBindings([$date])
                ->sum('ledger_entries.balance_after');

            $balances[] = $balance ?: 0;
        }

        return $balances;
    }
}
