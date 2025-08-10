<?php

namespace App\Filament\Resources\WalletResource\Pages;

use App\Filament\Resources\WalletResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWallets extends ListRecords
{
    protected static string $resource = WalletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action as requested
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            WalletResource\Widgets\WalletStatsOverview::class,
        ];
    }
}
