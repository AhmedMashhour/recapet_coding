<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

class WalletFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'wallet_number' => $this->generateUniqueWalletNumber(),
            'balance' => fake()->randomFloat(2, 0, 10000),
            'status' => fake()->randomElement(['active', 'suspended', 'closed']),
        ];
    }

    protected function generateUniqueWalletNumber(): string
    {
        do {
            $number = 'W-' . rand(10000000, 99999999);
        } while (Wallet::query()->where('wallet_number', $number)->exists());

        return $number;
    }
}
