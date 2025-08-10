<?php

namespace App\Filament\Resources\WalletResource\Pages;

use App\Filament\Resources\WalletResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewWallet extends ViewRecord
{
    protected static string $resource = WalletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('viewTransactions')
                ->label('View All Transactions')
                ->icon('heroicon-o-banknotes')
                ->url(fn () => WalletResource::getUrl('transactions', ['record' => $this->record]))
                ->color('info'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            WalletResource\Widgets\WalletTransactionStats::class,
        ];
    }
}
