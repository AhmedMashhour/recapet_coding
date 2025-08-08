<?php
namespace App\Services;

use App\Traits\HasMoney;

class FeeCalculatorService
{
    use HasMoney;

    const TRANSFER_THRESHOLD = 25.00;
    const TRANSFER_BASE_FEE = 2.50;
    const TRANSFER_PERCENTAGE_FEE = 0.10;

    /**
     * Calculate transfer fee
     */
    public function calculateTransferFee(float $amount): float
    {
        if ($amount <= self::TRANSFER_THRESHOLD) {
            return 0.00;
        }

        $percentageFee = $this->calculateWithPrecision(
            'multiply',
            $amount,
            self::TRANSFER_PERCENTAGE_FEE
        );

        return $this->calculateWithPrecision(
            'add',
            self::TRANSFER_BASE_FEE,
            $percentageFee
        );
    }

    /**
     * Get fee breakdown
     */
    public function getFeeBreakdown(float $amount): array
    {
        if ($amount <= self::TRANSFER_THRESHOLD) {
            return [
                'base_fee' => 0,
                'percentage_fee' => 0,
                'total_fee' => 0,
                'fee_rate' => 0
            ];
        }

        $percentageFee = $this->calculateWithPrecision(
            'multiply',
            $amount,
            self::TRANSFER_PERCENTAGE_FEE
        );

        $totalFee = $this->calculateWithPrecision(
            'add',
            self::TRANSFER_BASE_FEE,
            $percentageFee
        );

        return [
            'base_fee' => self::TRANSFER_BASE_FEE,
            'percentage_fee' => $percentageFee,
            'total_fee' => $totalFee,
            'fee_rate' => ($totalFee / $amount) * 100
        ];
    }
}
