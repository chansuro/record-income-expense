<?php

namespace Tests\Unit;

use App\Services\HmrcQuarterDashboard;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class HmrcQuarterDashboardTest extends TestCase
{
    public function test_missing_first_quarter_and_cumulative_ranges_are_not_mislabelled(): void
    {
        $result = (new HmrcQuarterDashboard)->format([['businessId' => 'business-1', 'obligationDetails' => [
            ['periodStartDate' => '2026-04-06', 'periodEndDate' => '2027-04-05', 'dueDate' => '2027-05-07', 'status' => 'open'],
            ['periodStartDate' => '2026-04-06', 'periodEndDate' => '2026-10-05', 'dueDate' => '2026-11-07', 'status' => 'open'],
            ['periodStartDate' => '2026-04-06', 'periodEndDate' => '2027-01-05', 'dueDate' => '2027-02-07', 'status' => 'open'],
        ]]], 2026, CarbonImmutable::parse('2026-09-11'));
        $this->assertSame(['Q1', 'Q2', 'Q3', 'Q4'], array_column($result['quarters'], 'quarter'));
        $this->assertSame('not_returned', $result['quarters'][0]['status']);
        $this->assertNull($result['quarters'][0]['fulfilled']);
        $this->assertSame('2026-07-06', $result['quarters'][1]['start']);
        $this->assertSame('2026-04-06', $result['quarters'][1]['periodStartDate']);
        $this->assertSame([false, true, false, false], array_column($result['quarters'], 'current'));
        $this->assertSame('7 Nov 2026', $result['due_date']);
        $this->assertSame(57, $result['due_in_days']);
    }

    public function test_fulfilled_and_overdue_status_use_hmrc_values(): void
    {
        $result = (new HmrcQuarterDashboard)->format([['obligationDetails' => [
            ['periodStartDate' => '2026-04-06', 'periodEndDate' => '2026-07-05', 'dueDate' => '2026-08-07', 'status' => 'fulfilled', 'receivedDate' => '2026-08-01'],
            ['periodStartDate' => '2026-04-06', 'periodEndDate' => '2026-10-05', 'dueDate' => '2026-11-07', 'status' => 'open'],
        ]]], 2026, CarbonImmutable::parse('2026-11-08'));
        $this->assertTrue($result['quarters'][0]['fulfilled']);
        $this->assertFalse($result['quarters'][0]['overdue']);
        $this->assertSame('1 Aug 2026', $result['quarters'][0]['received_date_formated']);
        $this->assertTrue($result['quarters'][1]['overdue']);
        $this->assertSame(-1, $result['due_in_days']);
        $this->assertSame('Quarter 3', $result['current_quarter']);
    }

    public function test_calendar_reporting_and_multiple_businesses(): void
    {
        $result = (new HmrcQuarterDashboard)->format([
            ['businessId' => 'calendar', 'obligationDetails' => [
                ['periodStartDate' => '2026-04-01', 'periodEndDate' => '2026-09-30', 'dueDate' => '2026-11-07', 'status' => 'open'],
            ]],
            ['businessId' => 'standard', 'obligationDetails' => []],
        ], 2026, CarbonImmutable::parse('2026-10-01'));
        $this->assertCount(8, $result['quarters']);
        $this->assertSame('calendar', $result['businesses'][0]['reporting_period']);
        $this->assertSame('2026-07-01', $result['quarters'][1]['start']);
        $this->assertTrue($result['quarters'][2]['current']);
        $this->assertTrue($result['quarters'][5]['current']);
        $this->assertNull($result['current_quarter']);
    }

    public function test_empty_results_and_old_obligations_are_preserved_without_rewriting(): void
    {
        $formatter = new HmrcQuarterDashboard;
        $this->assertSame([], $formatter->format([], 2026, CarbonImmutable::parse('2026-09-11'))['quarters']);
        $old = ['periodStartDate' => '2018-04-06', 'periodEndDate' => '2018-07-05', 'status' => 'open'];
        $result = $formatter->format([['obligationDetails' => [$old]]], 2026, CarbonImmutable::parse('2026-09-11'));
        $this->assertSame([$old], $result['businesses'][0]['unmapped_obligations']);
        $this->assertSame(['not_returned', 'not_returned', 'not_returned', 'not_returned'], array_column($result['quarters'], 'status'));
    }
}
