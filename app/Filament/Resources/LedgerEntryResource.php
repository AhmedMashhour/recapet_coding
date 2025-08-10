<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LedgerEntryResource\Pages;
use App\Models\LedgerEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LedgerEntryResource extends Resource
{
    protected static ?string $model = LedgerEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Financial Management';

    protected static ?int $navigationSort = 6;

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
                        Forms\Components\Select::make('type')
                            ->options([
                                'credit' => 'Credit',
                                'debit' => 'Debit',
                                'fee' => 'Fee',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\TextInput::make('balance_before')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\TextInput::make('balance_after')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\Select::make('reference_type')
                            ->options([
                                'deposit' => 'Deposit',
                                'withdrawal' => 'Withdrawal',
                                'transfer' => 'Transfer',
                            ]),
                        Forms\Components\TextInput::make('reference_id')
                            ->numeric(),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(255)
                            ->columnSpanFull(),
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
                    ->copyable(),
                Tables\Columns\TextColumn::make('wallet.wallet_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'success' => 'credit',
                        'danger' => 'debit',
                        'warning' => 'fee',
                    ]),
                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('balance_before')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('balance_after')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reference_type')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(30)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'credit' => 'Credit',
                        'debit' => 'Debit',
                        'fee' => 'Fee',
                    ]),
                Tables\Filters\SelectFilter::make('reference_type')
                    ->options([
                        'deposit' => 'Deposit',
                        'withdrawal' => 'Withdrawal',
                        'transfer' => 'Transfer',
                    ]),
                Tables\Filters\SelectFilter::make('wallet')
                    ->relationship('wallet', 'wallet_number')
                    ->searchable()
                    ->preload(),
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
            'index' => Pages\ListLedgerEntries::route('/'),
            'view' => Pages\ViewLedgerEntry::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(LedgerEntry|\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(LedgerEntry|\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
