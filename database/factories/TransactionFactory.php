<?php

namespace Database\Factories;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement([Transaction::TRANSACTION_TYPE_DEPOSIT, Transaction::TRANSACTION_TYPE_TRANSFER, Transaction::TRANSACTION_TYPE_WITHDRAWAL]);
        $status = fake()->randomElement([Transaction::TRANSACTION_STATUS_COMPLETED, Transaction::TRANSACTION_STATUS_PENDING, Transaction::TRANSACTION_STATUS_FAILED, Transaction::TRANSACTION_STATUS_PROCESSING]);
        $amount = fake()->randomFloat(2, 10, 1000);
        $fee = 0.00;
        when($type === Transaction::TRANSACTION_TYPE_TRANSFER, function () use ($amount, &$fee) {
            if ($amount > 25) {
                $fee = 2.50 + ($amount * 0.1);
            } else
                $fee = 2.50;
        });

        return [
            'transaction_id' => Str::uuid(),
            'type' => $type,
            'status' => $status,
            'amount' => $amount,
            'fee' => round($fee, 2,PHP_ROUND_HALF_UP),
            'metadata' => fake()->boolean(30) ? ['note' => fake()->sentence()] : null,
            'completed_at' => $status === 'completed' ? fake()->dateTimeBetween('-30 days', 'now') : null,
        ];
    }
}
