<?php

namespace Database\Factories;

use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deposit>
 */
class DepositFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory()->deposit()->create()->transaction_id,
            'wallet_id' => Wallet::factory(),
            'amount' => fake()->randomFloat(2, 10, 1000),
            'payment_method' => fake()->randomElement(['bank_transfer', 'card', 'cash', 'paypal']),
            'payment_reference' => fake()->boolean(70) ? fake()->uuid() : null,
        ];
    }


}
