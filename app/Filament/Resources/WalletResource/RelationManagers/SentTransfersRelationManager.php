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

class SentTransfersRelationManager extends RelationManager
{
    protected static string $relationship = 'sentTransfers';

    protected static ?string $title = 'Sent Transfers';

    protected static ?string $icon = 'heroicon-o-arrows-right-left';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('transaction_id')
                    ->required()
                    ->maxLength(255),
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
            ->modifyQueryUsing(fn (Builder $query) => $query->where(function ($q) {
                $q->where('sender_wallet_id', $this->ownerRecord->id)
                    ->orWhere('receiver_wallet_id', $this->ownerRecord->id);
            }))
            ->columns([
                Tables\Columns\TextColumn::make('transaction_id')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Transaction ID copied'),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->getStateUsing(fn (Model $record): string =>
                    $record->sender_wallet_id === $this->ownerRecord->id ? 'Sent' : 'Received'
                    )
                    ->colors([
                        'danger' => 'Sent',
                        'success' => 'Received',
                    ]),
                Tables\Columns\TextColumn::make('counterparty')
                    ->label('Counterparty')
                    ->getStateUsing(function (Model $record): string {
                        if ($record->sender_wallet_id === $this->ownerRecord->id) {
                            return $record->receiverWallet->wallet_number . ' (' . $record->receiverWallet->user->name . ')';
                        } else {
                            return $record->senderWallet->wallet_number . ' (' . $record->senderWallet->user->name . ')';
                        }
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->whereHas('senderWallet', function ($wallet) use ($search) {
                                $wallet->where('wallet_number', 'like', "%{$search}%")
                                    ->orWhereHas('user', function ($user) use ($search) {
                                        $user->where('name', 'like', "%{$search}%");
                                    });
                            })->orWhereHas('receiverWallet', function ($wallet) use ($search) {
                                $wallet->where('wallet_number', 'like', "%{$search}%")
                                    ->orWhereHas('user', function ($user) use ($search) {
                                        $user->where('name', 'like', "%{$search}%");
                                    });
                            });
                        });
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable()
                    ->getStateUsing(fn (Model $record): float =>
                    $record->sender_wallet_id === $this->ownerRecord->id ? -$record->amount : $record->amount
                    )
                    ->color(fn (Model $record): string =>
                    $record->sender_wallet_id === $this->ownerRecord->id ? 'danger' : 'success'
                    ),
                Tables\Columns\TextColumn::make('fee')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('transaction.status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'processing',
                        'success' => 'completed',
                        'danger' => 'failed',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'sent' => 'Sent',
                        'received' => 'Received',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value'] === 'sent') {
                            return $query->where('sender_wallet_id', $this->ownerRecord->id);
                        } elseif ($data['value'] === 'received') {
                            return $query->where('receiver_wallet_id', $this->ownerRecord->id);
                        }
                        return $query;
                    }),
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
        return \App\Models\Transfer::query();
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
