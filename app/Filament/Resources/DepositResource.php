<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DepositResource\Pages;
use App\Models\Deposit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DepositResource extends Resource
{
    protected static ?string $model = Deposit::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-circle';

    protected static ?string $navigationGroup = 'Financial Management';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('transaction_id')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('wallet_id')
                            ->relationship('wallet', 'wallet_number')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\TextInput::make('payment_method')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('payment_reference')
                            ->maxLength(255),
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
                Tables\Columns\TextColumn::make('wallet.wallet_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('wallet.user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('USD'),
                    ]),
                Tables\Columns\TextColumn::make('payment_method')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('payment_reference')
                    ->searchable()
                    ->toggleable(),
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
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options(fn () => Deposit::distinct('payment_method')->pluck('payment_method', 'payment_method')),
                Tables\Filters\SelectFilter::make('wallet')
                    ->relationship('wallet', 'wallet_number')
                    ->searchable()
                    ->preload(),
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
            'index' => Pages\ListDeposits::route('/'),
            'view' => Pages\ViewDeposit::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Deposit|\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(Deposit|\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
