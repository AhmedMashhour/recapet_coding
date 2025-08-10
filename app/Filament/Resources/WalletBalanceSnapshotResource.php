<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WalletBalanceSnapshotResource\Pages;
use App\Models\WalletBalanceSnapshot;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WalletBalanceSnapshotResource extends Resource
{
    protected static ?string $model = WalletBalanceSnapshot::class;

    protected static ?string $navigationIcon = 'heroicon-o-camera';

    protected static ?string $navigationGroup = 'Audit & Reports';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('snapshot_id')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('wallet_id')
                            ->relationship('wallet', 'wallet_number')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('wallet_number')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('wallet_balance')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\TextInput::make('ledger_balance')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\TextInput::make('discrepancy')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\TextInput::make('transaction_count')
                            ->numeric()
                            ->required(),
                        Forms\Components\DateTimePicker::make('last_transaction_at'),
                        Forms\Components\KeyValue::make('metadata')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('snapshot_id')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('wallet_number')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('wallet.user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('wallet_balance')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('ledger_balance')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('discrepancy')
                    ->money('USD')
                    ->sortable()
                    ->color(fn ($state) => $state != 0 ? 'danger' : 'success')
                    ->weight(fn ($state) => $state != 0 ? 'bold' : 'normal'),
                Tables\Columns\IconColumn::make('has_discrepancy')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->getStateUsing(fn ($record) => $record->discrepancy != 0),
                Tables\Columns\TextColumn::make('transaction_count')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_transaction_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_discrepancy')
                    ->query(fn (Builder $query): Builder => $query->where('discrepancy', '!=', 0))
                    ->toggle()
                    ->label('Has Discrepancy'),
                Tables\Filters\SelectFilter::make('snapshot_id')
                    ->relationship('snapshot', 'snapshot_id')
                    ->searchable()
                    ->preload()
                    ->label('Snapshot'),
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
            ->defaultSort('discrepancy', 'desc');
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
            'index' => Pages\ListWalletBalanceSnapshots::route('/'),
            'view' => Pages\ViewWalletBalanceSnapshot::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(WalletBalanceSnapshot|\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(WalletBalanceSnapshot|\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
