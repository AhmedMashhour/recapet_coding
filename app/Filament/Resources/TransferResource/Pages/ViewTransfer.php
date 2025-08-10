<?php

namespace App\Filament\Resources\TransferResource\Pages;

use App\Filament\Resources\TransferResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTransfer extends ViewRecord
{
    protected static string $resource = TransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No edit or delete actions as requested
        ];
    }
}
