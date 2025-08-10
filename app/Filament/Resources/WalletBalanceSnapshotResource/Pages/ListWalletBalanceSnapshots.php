<?php

namespace App\Filament\Resources\WalletBalanceSnapshotResource\Pages;

use App\Filament\Resources\WalletBalanceSnapshotResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWalletBalanceSnapshots extends ListRecords
{
    protected static string $resource = WalletBalanceSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action as requested
        ];
    }
}
