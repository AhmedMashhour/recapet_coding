<?php
namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\FeeCalculatorService;

class FeeCalculatorServiceTest extends TestCase
{
    private FeeCalculatorService $feeCalculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->feeCalculator = new FeeCalculatorService();
    }

    public function test_no_fee_for_amounts_under_threshold()
    {
        $fee = $this->feeCalculator->calculateTransferFee(25.00);
        $this->assertEquals(2.50, $fee);

        $fee = $this->feeCalculator->calculateTransferFee(10.00);
        $this->assertEquals(2.5, $fee);
    }

    public function test_fee_calculation_for_amounts_over_threshold()
    {
        // $100 transfer: base fee $2.50 + 10% of $100 = $2.50 + $10 = $12.50
        $fee = $this->feeCalculator->calculateTransferFee(100.00);
        $this->assertEquals(12.50, $fee);

        // $50 transfer: base fee $2.50 + 10% of $50 = $2.50 + $5 = $7.50
        $fee = $this->feeCalculator->calculateTransferFee(50.00);
        $this->assertEquals(7.50, $fee);
    }

    public function test_fee_breakdown_structure()
    {
        $breakdown = $this->feeCalculator->getFeeBreakdown(100.00);

        $this->assertArrayHasKey('base_fee', $breakdown);
        $this->assertArrayHasKey('percentage_fee', $breakdown);
        $this->assertArrayHasKey('total_fee', $breakdown);
        $this->assertArrayHasKey('fee_rate', $breakdown);

        $this->assertEquals(2.50, $breakdown['base_fee']);
        $this->assertEquals(10.00, $breakdown['percentage_fee']);
        $this->assertEquals(12.50, $breakdown['total_fee']);
        $this->assertEquals(12.50, $breakdown['fee_rate']); // 12.5% of 100
    }
}
