<?php

namespace App\Filament\Resources\WalletResource\Pages;

use App\Filament\Resources\WalletResource;
use App\Filament\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\Wallet;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms;
use Illuminate\Contracts\Support\Htmlable;

class ViewWalletTransactions extends Page implements HasTable
{
    use InteractsWithTable;
    use InteractsWithRecord;

    protected static string $resource = WalletResource::class;

    protected static string $view = 'filament.pages.view-wallet-transactions';

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string | Htmlable
    {
        return "Transactions for Wallet: {$this->record->wallet_number}";
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
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
                                if ($transfer && $transfer->sender_wallet_id === $this->record->id) {
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
                                if ($transfer && $transfer->sender_wallet_id === $this->record->id) {
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
                    ->sortable(),
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
                                    if ($transfer->sender_wallet_id === $this->record->id) {
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
                Tables\Columns\TextColumn::make('balance_impact')
                    ->label('Balance After')
                    ->getStateUsing(function (Model $record): ?string {
                        $ledgerEntry = \App\Models\LedgerEntry::where('transaction_id', $record->transaction_id)
                            ->where('wallet_id', $this->record->id)
                            ->orderBy('id', 'desc')
                            ->first();

                        return $ledgerEntry ? '$' . number_format($ledgerEntry->balance_after, 2) : null;
                    })
                    ->weight('bold'),
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
                Tables\Actions\ViewAction::make()
                    ->url(fn (Transaction $record): string =>
                    TransactionResource::getUrl('view', ['record' => $record])
                    ),
            ])
            ->bulkActions([
                // No bulk actions
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected function getTableQuery(): Builder
    {
        return Transaction::query()
            ->with(['deposit', 'withdrawal', 'transfer.senderWallet', 'transfer.receiverWallet'])
            ->where(function ($q) {
                $q->whereHas('deposit', fn ($deposit) => $deposit->where('wallet_id', $this->record->id))
                    ->orWhereHas('withdrawal', fn ($withdrawal) => $withdrawal->where('wallet_id', $this->record->id))
                    ->orWhereHas('transfer', fn ($transfer) =>
                    $transfer->where('sender_wallet_id', $this->record->id)
                        ->orWhere('receiver_wallet_id', $this->record->id)
                    );
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back')
                ->label('Back to Wallet')
                ->url(WalletResource::getUrl('view', ['record' => $this->record]))
                ->icon('heroicon-o-arrow-left'),
        ];
    }
}
