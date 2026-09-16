<?php

namespace Tests\Unit;

use App\Services\HmrcTaxAndPaymentsDashboard;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class HmrcTaxAndPaymentsDashboardTest extends TestCase
{
    private HmrcTaxAndPaymentsDashboard $service;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-09-14 12:00:00');
        $this->service = new HmrcTaxAndPaymentsDashboard;
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_no_previous_poa_returns_scenario_a(): void
    {
        $result = $this->service->format([], [], 1622, '2026-27', CarbonImmutable::parse('2026-09-14'));

        $this->assertSame('no_previous_poa', $result['scenario']);
        $this->assertSame('A', $result['scenario_code']);
        $this->assertSame(811.0, $result['next_year_poa']['first']['amount']);
        $this->assertSame('2028-01-31', $result['next_year_poa']['first']['due_date']);
        $this->assertSame(3244.0, $result['total_remaining']);
    }

    public function test_partial_poa_returns_scenario_b(): void
    {
        $result = $this->service->format($this->account(800, 0), [], 1622, '2026-27', CarbonImmutable::parse('2026-09-14'));

        $this->assertSame('poa_insufficient', $result['scenario']);
        $this->assertSame('B', $result['scenario_code']);
        $this->assertSame(800.0, $result['previous_poa']['paid']);
        $this->assertSame(822.0, $result['balancing_position']['amount']);
        $this->assertSame(2444.0, $result['total_remaining']);
    }

    public function test_overpaid_poa_returns_scenario_c(): void
    {
        $result = $this->service->format($this->account(2000, 0), [], 1622, '2026-27', CarbonImmutable::parse('2026-09-14'));

        $this->assertSame('poa_overpaid', $result['scenario']);
        $this->assertSame('C', $result['scenario_code']);
        $this->assertSame('estimated_credit', $result['balancing_position']['type']);
        $this->assertSame(378.0, $result['estimated_credit']);
    }

    public function test_allocations_override_inferred_paid_amount(): void
    {
        $account = $this->account(1000, 500);
        $payments = ['paymentsAndAllocations' => [[
            'paymentLot' => 'lot-1',
            'allocations' => [[
                'chargeReference' => 'POA-1',
                'allocatedAmount' => 700,
            ]],
        ]]];

        $result = $this->service->format($account, $payments, 1622, '2026-27', CarbonImmutable::parse('2026-09-14'));

        $this->assertSame(700.0, $result['previous_poa']['paid']);
        $this->assertSame('hmrc_allocations', $result['previous_poa']['source']);
    }

    public function test_due_today_is_upcoming_not_overdue(): void
    {
        $charges = $this->service->paymentsOnAccount($this->account(1000, 1000, '2026-09-14'));

        $this->assertSame('upcoming', $charges[0]['status']);
        $this->assertSame(0, $charges[0]['days_until_due']);
    }

    private function account(float $original, float $outstanding, string $due = '2027-01-31'): array
    {
        return ['documentDetails' => [[
            'taxYear' => '2026-27',
            'documentId' => 'transaction-1',
            'chargeReference' => 'POA-1',
            'documentDescription' => 'First payment on account',
            'documentDueDate' => $due,
            'originalAmount' => $original,
            'outstandingAmount' => $outstanding,
        ]]];
    }
}
