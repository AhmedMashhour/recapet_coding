<?php

namespace App\Filament\Resources\BalanceSnapshotResource\Pages;

use App\Filament\Resources\BalanceSnapshotResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBalanceSnapshots extends ListRecords
{
    protected static string $resource = BalanceSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action as requested
        ];
    }
}
