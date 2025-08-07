<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transfer>
 */
class TransferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 10, 500);
        $fee = $amount > 25 ? (2.50 + ($amount * 0.10)) : 2.50;

        return [
            'transaction_id' => Transaction::factory()->transfer()->create()->transaction_id,
            'sender_wallet_id' => Wallet::factory(),
            'receiver_wallet_id' => Wallet::factory(),
            'amount' => $amount,
            'fee' => round($fee, 2,PHP_ROUND_HALF_UP),
        ];
    }
}
