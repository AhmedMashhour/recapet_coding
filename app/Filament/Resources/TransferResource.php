<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransferResource\Pages;
use App\Models\Transfer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransferResource extends Resource
{
    protected static ?string $model = Transfer::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Financial Management';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('transaction_id')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('sender_wallet_id')
                            ->relationship('senderWallet', 'wallet_number')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->label('From Wallet'),
                        Forms\Components\Select::make('receiver_wallet_id')
                            ->relationship('receiverWallet', 'wallet_number')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->label('To Wallet'),
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\TextInput::make('fee')
                            ->numeric()
                            ->prefix('$')
                            ->default(0),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('transaction_id')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Transaction ID copied'),
                Tables\Columns\TextColumn::make('senderWallet.wallet_number')
                    ->label('From Wallet')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('senderWallet.user.name')
                    ->label('Sender')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('receiverWallet.wallet_number')
                    ->label('To Wallet')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('receiverWallet.user.name')
                    ->label('Receiver')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
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
                Tables\Filters\SelectFilter::make('sender_wallet')
                    ->relationship('senderWallet', 'wallet_number')
                    ->searchable()
                    ->preload()
                    ->label('From Wallet'),
                Tables\Filters\SelectFilter::make('receiver_wallet')
                    ->relationship('receiverWallet', 'wallet_number')
                    ->searchable()
                    ->preload()
                    ->label('To Wallet'),
                Tables\Filters\Filter::make('amount')
                    ->form([
                        Forms\Components\TextInput::make('amount_from')
                            ->numeric()
                            ->prefix('$'),
                        Forms\Components\TextInput::make('amount_to')
                            ->numeric()
                            ->prefix('$'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['amount_from'],
                                fn (Builder $query, $amount): Builder => $query->where('amount', '>=', $amount),
                            )
                            ->when(
                                $data['amount_to'],
                                fn (Builder $query, $amount): Builder => $query->where('amount', '<=', $amount),
                            );
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
            'index' => Pages\ListTransfers::route('/'),
            'view' => Pages\ViewTransfer::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Transfer|\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(Transfer|\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
