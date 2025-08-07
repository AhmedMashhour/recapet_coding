<?php

namespace Database\Factories;

use App\Models\LedgerEntry;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\LedgerEntry>
 */
class LedgerEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement([LedgerEntry::LEDGER_TYPE_CREDIT, LedgerEntry::LEDGER_TYPE_DEBIT, LedgerEntry::LEDGER_TYPE_FEE]);
        $amount = fake()->randomFloat(2, 10, 500);
        $balanceBefore = fake()->randomFloat(2, 0, 10000);

        $balanceAfter = match($type) {
            LedgerEntry::LEDGER_TYPE_CREDIT => $balanceBefore + $amount,
            LedgerEntry::LEDGER_TYPE_DEBIT, LedgerEntry::LEDGER_TYPE_FEE => $balanceBefore - $amount,
        };

        return [
            'transaction_id' => Str::uuid(),
            'wallet_id' => Wallet::factory(),
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => max(0, $balanceAfter),
            'reference_type' => fake()->randomElement([LedgerEntry::LEDGER_REFERANCE_TYPE_DEPOSIT, LedgerEntry::LEDGER_REFERANCE_TYPE_WITHDRAWAL, LedgerEntry::LEDGER_REFERANCE_TYPE_TRANSFER]),
            'reference_id' => fake()->numberBetween(1, 1000),
            'description' => fake()->sentence(),
        ];
    }
}
