<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }


    public function configure(): static
    {
        return $this->afterCreating(function ($user) {
            $user->wallet()->create([
                'wallet_number' => $this->generateUniqueWalletNumber(),
                'balance' => 0,
                'status' => 'active',
            ]);
        });
    }
    protected function generateUniqueWalletNumber(): string
    {
        do {
            $number = 'W-' . rand(10000000, 99999999);
        } while (Wallet::where('wallet_number', $number)->exists());

        return $number;
    }
}
