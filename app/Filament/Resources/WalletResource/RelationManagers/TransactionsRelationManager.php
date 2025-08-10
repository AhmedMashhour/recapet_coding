<?php

namespace App\Filament\Resources\WalletResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'All Transactions';

    protected static ?string $icon = 'heroicon-o-banknotes';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('transaction_id')
                    ->required()
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
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query->where(function ($q) {
                    $q->whereHas('deposit', fn ($deposit) => $deposit->where('wallet_id', $this->ownerRecord->id))
                        ->orWhereHas('withdrawal', fn ($withdrawal) => $withdrawal->where('wallet_id', $this->ownerRecord->id))
                        ->orWhereHas('transfer', fn ($transfer) =>
                        $transfer->where('sender_wallet_id', $this->ownerRecord->id)
                            ->orWhere('receiver_wallet_id', $this->ownerRecord->id)
                        );
                });
            })
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
                    ->getStateUsing(function (Model $record): float {
                        switch ($record->type) {
                            case 'deposit':
                                return $record->amount;
                            case 'withdrawal':
                                return -$record->amount;
                            case 'transfer':
                                $transfer = $record->transfer;
                                if ($transfer && $transfer->sender_wallet_id === $this->ownerRecord->id) {
                                    return -$record->amount;
                                } else {
                                    return $record->amount;
                                }
                            default:
                                return $record->amount;
                        }
                    })
                    ->color(function (Model $record): string {
                        switch ($record->type) {
                            case 'deposit':
                                return 'success';
                            case 'withdrawal':
                                return 'danger';
                            case 'transfer':
                                $transfer = $record->transfer;
                                if ($transfer && $transfer->sender_wallet_id === $this->ownerRecord->id) {
                                    return 'danger';
                                } else {
                                    return 'success';
                                }
                            default:
                                return 'gray';
                        }
                    }),
                Tables\Columns\TextColumn::make('fee')
                    ->money('USD')
                    ->sortable()
                    ->visible(fn ($record) => $record->fee > 0),
                Tables\Columns\TextColumn::make('details')
                    ->label('Details')
                    ->getStateUsing(function (Model $record): string {
                        switch ($record->type) {
                            case 'deposit':
                                return 'Method: ' . ($record->deposit?->payment_method ?? 'N/A');
                            case 'withdrawal':
                                return 'Method: ' . ($record->withdrawal?->withdrawal_method ?? 'N/A');
                            case 'transfer':
                                $transfer = $record->transfer;
                                if ($transfer) {
                                    if ($transfer->sender_wallet_id === $this->ownerRecord->id) {
                                        return 'To: ' . $transfer->receiverWallet->wallet_number;
                                    } else {
                                        return 'From: ' . $transfer->senderWallet->wallet_number;
                                    }
                                }
                                return 'N/A';
                            default:
                                return '';
                        }
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
            ->headerActions([
                // No create action
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // No bulk actions
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected function getTableQuery(): Builder
    {
        return \App\Models\Transaction::query();
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function canEdit($record): bool
    {
        return false;
    }

    public function canDelete($record): bool
    {
        return false;
    }

    public function canDeleteAny(): bool
    {
        return false;
    }
}
