<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class HmrcQuarterDashboard
{
    public function format(array $groups, int $year, CarbonImmutable $date, string $fallback = 'standard'): array
    {
        $businesses = [];
        $quarters = [];
        foreach ($groups as $group) {
            $details = $group['obligationDetails'] ?? [];
            $period = $fallback;
            $detected = false;
            foreach ($details as $detail) {
                $end = $detail['periodEndDate'] ?? '';
                if (in_array($end, ["$year-06-30", "$year-09-30", "$year-12-31", ($year + 1) . '-03-31'], true)) {
                    $period = 'calendar';
                    $detected = true;
                    break;
                }
                if (in_array($end, ["$year-07-05", "$year-10-05", ($year + 1) . '-01-05', ($year + 1) . '-04-05'], true)) {
                    $period = 'standard';
                    $detected = true;
                }
            }
            $start = CarbonImmutable::create($year, 4, $period === 'calendar' ? 1 : 6)->startOfDay();
            $businessQuarters = [];
            $matched = [];
            for ($number = 1; $number <= 4; $number++) {
                $quarterStart = $start->addMonths(($number - 1) * 3);
                $quarterEnd = $start->addMonths($number * 3)->subDay();
                $obligation = null;
                foreach ($details as $index => $detail) {
                    if (($detail['periodEndDate'] ?? null) === $quarterEnd->toDateString()) {
                        $obligation = $detail;
                        $matched[] = $index;
                        break;
                    }
                }
                $status = $obligation['status'] ?? 'not_returned';
                $due = isset($obligation['dueDate']) ? CarbonImmutable::parse($obligation['dueDate'])->startOfDay() : null;
                $received = $obligation['receivedDate'] ?? null;
                $row = array_merge($obligation ?? [], [
                    'businessId' => $group['businessId'] ?? null,
                    'typeOfBusiness' => $group['typeOfBusiness'] ?? null,
                    'quarter' => 'Q' . $number,
                    'quarter_number' => $number,
                    'reporting_period' => $period,
                    'start' => $quarterStart->toDateString(),
                    'end' => $quarterEnd->toDateString(),
                    'quarter_range' => $quarterStart->format('j M Y') . ' - ' . $quarterEnd->format('j M Y'),
                    'periodStartDate' => $obligation['periodStartDate'] ?? null,
                    'periodEndDate' => $obligation['periodEndDate'] ?? null,
                    'reporting_range' => $obligation
                        ? $obligation['periodStartDate'] . ' - ' . $obligation['periodEndDate'] : null,
                    'status' => $status,
                    'fulfilled' => $obligation ? ($status === 'fulfilled' ? true : ($status === 'open' ? false : null)) : null,
                    'obligation_returned' => $obligation !== null,
                    'current' => $date->betweenIncluded($quarterStart, $quarterEnd->endOfDay()),
                    'dueDate' => $obligation['dueDate'] ?? null,
                    'due_date_formated' => $due?->format('j M Y'),
                    'due_in_days' => $due ? (int) $date->startOfDay()->diffInDays($due, false) : null,
                    'overdue' => $status === 'open' && $due && $due->lt($date->startOfDay()),
                    'receivedDate' => $received,
                    'received_date_formated' => $received ? CarbonImmutable::parse($received)->format('j M Y') : null,
                ]);
                $businessQuarters[] = $row;
                $quarters[] = $row;
            }
            $businesses[] = [
                'businessId' => $group['businessId'] ?? null,
                'typeOfBusiness' => $group['typeOfBusiness'] ?? null,
                'reporting_period' => $period,
                'reporting_period_assumed' => !$detected,
                'quarters' => $businessQuarters,
                'unmapped_obligations' => array_values(array_diff_key($details, array_flip($matched))),
            ];
        }
        $next = collect($quarters)->where('status', 'open')->filter(fn ($row) => $row['dueDate'])
            ->sortBy('dueDate')->first();
        $current = collect($quarters)->where('current', true)->pluck('quarter_number')->unique()->values();

        return [
            'businesses' => $businesses,
            'quarters' => $quarters,
            'current_quarter' => $current->count() === 1 ? 'Quarter ' . $current->first() : null,
            'due_date' => $next['due_date_formated'] ?? null,
            'due_in_days' => $next['due_in_days'] ?? null,
            'next_obligation' => $next,
        ];
    }
}
