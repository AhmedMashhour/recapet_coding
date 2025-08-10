<?php

namespace App\Filament\Resources\WalletBalanceSnapshotResource\Pages;

use App\Filament\Resources\WalletBalanceSnapshotResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewWalletBalanceSnapshot extends ViewRecord
{
    protected static string $resource = WalletBalanceSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No edit or delete actions as requested
        ];
    }
}
