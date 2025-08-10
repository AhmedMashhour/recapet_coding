<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BalanceSnapshotResource\Pages;
use App\Models\BalanceSnapshot;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BalanceSnapshotResource extends Resource
{
    protected static ?string $model = BalanceSnapshot::class;

    protected static ?string $navigationIcon = 'heroicon-o-camera';

    protected static ?string $navigationGroup = 'Audit & Reports';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Snapshot Information')
                    ->schema([
                        Forms\Components\TextInput::make('snapshot_id')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\DateTimePicker::make('snapshot_time')
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'balanced' => 'Balanced',
                                'discrepancy' => 'Discrepancy Found',
                                'error' => 'Error',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Financial Summary')
                    ->schema([
                        Forms\Components\TextInput::make('total_wallets')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('active_wallets')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('total_balance')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\TextInput::make('total_deposits')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\TextInput::make('total_withdrawals')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\TextInput::make('total_fees')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\TextInput::make('calculated_balance')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\TextInput::make('balance_discrepancy')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Additional Data')
                    ->schema([
                        Forms\Components\KeyValue::make('statistics')
                            ->label('Statistics'),
                        Forms\Components\KeyValue::make('discrepancies')
                            ->label('Discrepancies Found'),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('snapshot_id')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('snapshot_time')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'balanced',
                        'danger' => 'discrepancy',
                        'warning' => 'error',
                    ]),
                Tables\Columns\TextColumn::make('total_wallets')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('active_wallets')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_balance')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('balance_discrepancy')
                    ->money('USD')
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('calculated_balance')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('total_deposits')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('total_withdrawals')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('total_fees')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'balanced' => 'Balanced',
                        'discrepancy' => 'Discrepancy Found',
                        'error' => 'Error',
                    ]),
                Tables\Filters\Filter::make('has_discrepancy')
                    ->query(fn (Builder $query): Builder => $query->where('balance_discrepancy', '!=', 0))
                    ->toggle()
                    ->label('Has Discrepancy'),
                Tables\Filters\Filter::make('snapshot_time')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('snapshot_time', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('snapshot_time', '<=', $date),
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
            ->defaultSort('snapshot_time', 'desc');
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
            'index' => Pages\ListBalanceSnapshots::route('/'),
            'view' => Pages\ViewBalanceSnapshot::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(BalanceSnapshot|\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(BalanceSnapshot|\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
