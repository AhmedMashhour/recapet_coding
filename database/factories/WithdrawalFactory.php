<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Withdrawal>
 */
class WithdrawalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory()->withdrawal()->create()->transaction_id,
            'wallet_id' => Wallet::factory(),
            'amount' => fake()->randomFloat(2, 10, 500),
            'withdrawal_method' => fake()->randomElement(['bank_transfer', 'card', 'cash']),
            'withdrawal_reference' => fake()->boolean(70) ? fake()->uuid() : null,
        ];
    }
}
