<?php

namespace App\Filament\Resources\WalletResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LedgerEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'ledgerEntries';

    protected static ?string $title = 'Ledger Entries';

    protected static ?string $icon = 'heroicon-o-book-open';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('transaction_id')
                    ->required()
                    ->maxLength(255),
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
                Forms\Components\Textarea::make('description')
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('transaction_id')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Entry ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('transaction_id')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'success' => 'credit',
                        'danger' => 'debit',
                        'warning' => 'fee',
                    ]),
                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable()
                    ->color(fn ($state, $record) => match($record->type) {
                        'credit' => 'success',
                        'debit', 'fee' => 'danger',
                        default => null,
                    }),
                Tables\Columns\TextColumn::make('balance_before')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('balance_after')
                    ->money('USD')
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('balance_change')
                    ->label('Change')
                    ->getStateUsing(fn ($record) => $record->balance_after - $record->balance_before)
                    ->money('USD')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
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
