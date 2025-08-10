@php
    $record = $this->record ?? null;
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Wallet Info Card -->
        @if($record)
            <x-filament::card>
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-medium">Wallet Information</h3>
                        <div class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                            <p>Wallet Number: <span class="font-medium">{{ $record->wallet_number }}</span></p>
                            <p>Owner: <span class="font-medium">{{ $record->user->name }}</span></p>
                            <p>Current Balance: <span class="font-medium text-lg">${{ number_format($record->balance, 2) }}</span></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <x-filament::badge :color="$record->status === 'active' ? 'success' : 'danger'">
                            {{ ucfirst($record->status) }}
                        </x-filament::badge>
                    </div>
                </div>
            </x-filament::card>
        @endif

        <!-- Transactions Table -->
        {{ $this->table }}
    </div>
</x-filament-panels::page>
