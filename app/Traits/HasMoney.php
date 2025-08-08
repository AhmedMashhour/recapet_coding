<?php
namespace App\Traits;

trait HasMoney
{
    /**
     * Format money for display
     */
    public function formatMoney($amount): string
    {
        return number_format($amount, 2, '.', ',');
    }

    /**
     * Parse money value with precision
     */
    public function parseMoney($amount): float
    {
        return round((float) $amount, 2);
    }

    /**
     * Calculate with precision using BCMath
     */
    public function calculateWithPrecision($operation, ...$amounts): float
    {
        $scale = 2;
        bcscale($scale);

        switch ($operation) {
            case 'add':
                $result = '0';
                foreach ($amounts as $amount) {
                    $result = bcadd($result, (string) $amount);
                }
                return (float) $result;

            case 'subtract':
                $result = (string) $amounts[0];
                for ($i = 1; $i < count($amounts); $i++) {
                    $result = bcsub($result, (string) $amounts[$i]);
                }
                return (float) $result;

            case 'multiply':
                $result = '1';
                foreach ($amounts as $amount) {
                    $result = bcmul($result, (string) $amount);
                }
                return (float) $result;

            case 'divide':
                $result = (string) $amounts[0];
                for ($i = 1; $i < count($amounts); $i++) {
                    if ($amounts[$i] == 0) {
                        throw new \InvalidArgumentException('Division by zero');
                    }
                    $result = bcdiv($result, (string) $amounts[$i]);
                }
                return (float) $result;

            default:
                throw new \InvalidArgumentException('Invalid operation: ' . $operation);
        }
    }

    /**
     * Compare two amounts with precision
     */
    public function compareAmounts($amount1, $amount2, $precision = 2): int
    {
        bcscale($precision);
        return bccomp((string) $amount1, (string) $amount2);
    }

    /**
     * Check if amount is valid (positive and within precision)
     */
    public function isValidAmount($amount, $maxDecimals = 2): bool
    {
        if (!is_numeric($amount) || $amount < 0) {
            return false;
        }

        // Check decimal places
        $parts = explode('.', (string) $amount);
        if (isset($parts[1]) && strlen($parts[1]) > $maxDecimals) {
            return false;
        }

        return true;
    }
}
