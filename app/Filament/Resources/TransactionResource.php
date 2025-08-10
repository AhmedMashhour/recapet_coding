<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Financial Management';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('transaction_id')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('idempotency_key')
                            ->maxLength(255),
                        Forms\Components\Select::make('type')
                            ->options([
                                'deposit' => 'Deposit',
                                'withdrawal' => 'Withdrawal',
                                'transfer' => 'Transfer',
                            ])
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'processing' => 'Processing',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\TextInput::make('fee')
                            ->numeric()
                            ->prefix('$'),
                        Forms\Components\DateTimePicker::make('completed_at'),
                        Forms\Components\KeyValue::make('metadata')
                            ->reorderable(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_id')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Transaction ID copied'),
                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'success' => 'deposit',
                        'danger' => 'withdrawal',
                        'info' => 'transfer',
                    ]),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'processing',
                        'success' => 'completed',
                        'danger' => 'failed',
                    ]),
                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('USD'),
                    ]),
                Tables\Columns\TextColumn::make('fee')
                    ->money('USD')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('USD'),
                    ]),
                Tables\Columns\TextColumn::make('wallet_info')
                    ->label('Wallet(s)')
                    ->getStateUsing(function ($record) {
                        switch ($record->type) {
                            case 'deposit':
                                return $record->deposit?->wallet?->wallet_number ?? '-';
                            case 'withdrawal':
                                return $record->withdrawal?->wallet?->wallet_number ?? '-';
                            case 'transfer':
                                $from = $record->transfer?->senderWallet?->wallet_number ?? '-';
                                $to = $record->transfer?->receiverWallet?->wallet_number ?? '-';
                                return "From: {$from} → To: {$to}";
                            default:
                                return '-';
                        }
                    })
                    ->searchable(query: function ($query, $search) {
                        return $query->orWhereHas('deposit.wallet', function ($q) use ($search) {
                            $q->where('wallet_number', 'like', "%{$search}%");
                        })->orWhereHas('withdrawal.wallet', function ($q) use ($search) {
                            $q->where('wallet_number', 'like', "%{$search}%");
                        })->orWhereHas('transfer.senderWallet', function ($q) use ($search) {
                            $q->where('wallet_number', 'like', "%{$search}%");
                        })->orWhereHas('transfer.receiverWallet', function ($q) use ($search) {
                            $q->where('wallet_number', 'like', "%{$search}%");
                        });
                    }),
                Tables\Columns\TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'deposit' => 'Deposit',
                        'withdrawal' => 'Withdrawal',
                        'transfer' => 'Transfer',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // No bulk actions as requested
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'view' => Pages\ViewTransaction::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Transaction|\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(Transaction|\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
