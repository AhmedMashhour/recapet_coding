<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $testUsers = [
            [
                'name' => 'Ahmed',
                'email' => 'ahmed@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        ];

        foreach ($testUsers as $index => $userData) {
            $user = User::query()->create($userData);

            $user->wallet()->create([
                'wallet_number' => 'W-' . rand(10000000, 99999999),
                'balance' => match ($index) {
                    0 => 10000.00,
                    1 => 5000.00,
                    2 => 2500.00,
                    3 => 100.00,
                },
                'status' => 'active',
            ]);
        }

        $this->command->info('Created ' . count($testUsers) . ' test users with wallets');

        $randomUserCount = 50000;
        User::factory()
            ->count($randomUserCount)
            ->create()
            ->each(function ($user) {
                $user->wallet->update([
                    'balance' => fake()->randomFloat(2, 100, 5000),
                ]);
            });

        $this->command->info('Created ' . $randomUserCount . ' random users with wallets');

        Wallet::query()->inRandomOrder()
            ->limit(5)
            ->update(['status' => 'suspended']);

        $this->command->info('Suspended 5 random wallets');
    }
}
