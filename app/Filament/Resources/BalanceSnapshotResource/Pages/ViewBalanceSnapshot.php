<?php

namespace App\Filament\Resources\BalanceSnapshotResource\Pages;

use App\Filament\Resources\BalanceSnapshotResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBalanceSnapshot extends ViewRecord
{
    protected static string $resource = BalanceSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No edit or delete actions as requested
        ];
    }
}
