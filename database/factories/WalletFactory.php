<?php

namespace Database\Seeders;

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

    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'suspended',
        ]);
    }

    public function withBalance(float $balance): static
    {
        return $this->state(fn(array $attributes) => [
            'balance' => $balance,
        ]);
    }

    protected function generateUniqueWalletNumber(): string
    {
        do {
            $number = 'W-' . rand(10000000, 99999999);
        } while (Wallet::query()->where('wallet_number', $number)->exists());

        return $number;
    }
}
