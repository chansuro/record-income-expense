<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class HmrcTaxAndPaymentsDashboard
{
    public function format(
        array $account,
        array $payments,
        float $estimatedLiability,
        string $taxYear,
        CarbonImmutable $asOf
    ): array {
        $charges = $this->paymentsOnAccount($account, $asOf);
        $currentCharges = array_values(array_filter(
            $charges,
            fn (array $charge) => $this->sameTaxYear($charge['tax_year'], $taxYear)
        ));

        $charged = $this->sum($currentCharges, 'original_amount');
        $outstanding = $this->sum($currentCharges, 'outstanding_amount');
        $paidFromCharges = max(0, $charged - $outstanding);
        $allocated = $this->allocatedPoaPayments($payments, $currentCharges);
        $paid = $allocated['matched'] ? $allocated['amount'] : $paidFromCharges;
        $balance = round($estimatedLiability - $paid, 2);

        if ($paid <= 0) {
            $scenario = 'no_previous_poa';
            $scenarioCode = 'A';
        } elseif ($balance > 0) {
            $scenario = 'poa_insufficient';
            $scenarioCode = 'B';
        } elseif ($balance < 0) {
            $scenario = 'poa_overpaid';
            $scenarioCode = 'C';
        } else {
            $scenario = 'poa_covers_liability';
            $scenarioCode = 'D';
        }

        $startYear = (int) substr($taxYear, 0, 4);
        $nextTaxYear = ($startYear + 1) . '-' . substr((string) ($startYear + 2), -2);
        $poaRequired = $estimatedLiability >= 1000;
        $estimatedNextPoa = $poaRequired ? round($estimatedLiability / 2, 2) : 0.0;
        $nextCharges = array_values(array_filter(
            $charges,
            fn (array $charge) => $this->sameTaxYear($charge['tax_year'], $nextTaxYear)
        ));
        $first = $this->instalment($nextCharges, 1, $estimatedNextPoa,
            CarbonImmutable::create($startYear + 2, 1, 31));
        $second = $this->instalment($nextCharges, 2, $estimatedNextPoa,
            CarbonImmutable::create($startYear + 2, 7, 31));
        $credit = max(0, -$balance);

        return [
            'scenario' => $scenario,
            'scenario_code' => $scenarioCode,
            'calculation_status' => 'estimated',
            'tax_year' => $taxYear,
            'estimated_liability' => $this->money($estimatedLiability),
            'previous_poa' => [
                'charged' => $this->money($charged),
                'paid' => $this->money($paid),
                'outstanding' => $this->money($outstanding),
                'source' => $allocated['matched'] ? 'hmrc_allocations' : 'hmrc_charges',
            ],
            'balancing_position' => [
                'type' => $balance > 0 ? 'amount_due' : ($balance < 0 ? 'estimated_credit' : 'settled'),
                'amount' => $this->money(abs($balance)),
                'official_available_credit' => $this->money(
                    (float) data_get($account, 'balanceDetails.availableCredit', 0)
                ),
            ],
            'next_year_poa' => [
                'tax_year' => $nextTaxYear,
                'required' => $poaRequired,
                'estimated' => count($nextCharges) === 0,
                'eligibility_assumed' => true,
                'first' => $first,
                'second' => $second,
            ],
            'total_remaining' => $this->money(
                max(0, $balance) + $first['outstanding_amount'] + $second['outstanding_amount']
            ),
            'estimated_credit' => $this->money($credit),
            'charges' => $charges,
            'as_of_date' => $asOf->toDateString(),
            'data_quality' => [
                'liability_source' => 'local_estimate',
                'payments_source' => $allocated['matched'] ? 'hmrc_allocations' : 'hmrc_charges',
                'poa_eligibility' => 'estimated_without_tax_deducted_at_source',
            ],
        ];
    }

    public function balances(array $account): array
    {
        $balance = $account['balanceDetails'] ?? [];

        return [
            'overdue_amount' => $this->money((float) ($balance['overdueAmount'] ?? 0)),
            'earliest_overdue_date' => $balance['earliestPaymentDateOverdue'] ?? null,
            'payable_amount' => $this->money((float) ($balance['payableAmount'] ?? 0)),
            'payable_due_date' => $balance['payableDueDate'] ?? null,
            'pending_amount' => $this->money((float) ($balance['pendingChargeDueAmount'] ?? 0)),
            'pending_due_date' => $balance['pendingChargeDueDate'] ?? null,
            'total_balance' => $this->money((float) ($balance['totalBalance'] ?? 0)),
            'available_credit' => $this->money((float) ($balance['availableCredit'] ?? 0)),
            'unallocated_credit' => $this->money((float) ($balance['unallocatedCredit'] ?? 0)),
        ];
    }

    public function paymentsOnAccount(array $account, ?CarbonImmutable $asOf = null): array
    {
        $asOf ??= CarbonImmutable::now()->startOfDay();
        $charges = [];
        foreach (($account['documentDetails'] ?? []) as $document) {
            $description = trim((string) ($document['documentDescription'] ?? $document['documentText'] ?? ''));
            if (!$this->isPoa($description, $document['documentType'] ?? null)) {
                continue;
            }
            $charges[] = $this->charge([
                'tax_year' => $document['taxYear'] ?? null,
                'transaction_id' => $document['documentId'] ?? null,
                'charge_reference' => $document['chargeReference'] ?? null,
                'description' => $description,
                'due_date' => $document['documentDueDate'] ?? null,
                'original_amount' => $document['originalAmount'] ?? 0,
                'outstanding_amount' => $document['outstandingAmount'] ?? 0,
                'is_estimate' => $document['isChargeEstimate'] ?? false,
            ], $asOf);
        }

        foreach (($account['financialDetails'] ?? []) as $financial) {
            $detail = $financial['chargeDetail'] ?? [];
            $description = trim((string) ($detail['chargeTypeDescription'] ?? $detail['documentTypeDescription'] ?? ''));
            $type = $detail['chargeType'] ?? $detail['documentType'] ?? null;
            if (!$this->isPoa($description, $type)) {
                continue;
            }
            $charges[] = $this->charge([
                'tax_year' => $financial['taxYear'] ?? null,
                'transaction_id' => $financial['documentNumber'] ?? null,
                'charge_reference' => $financial['chargeReference'] ?? null,
                'description' => $description,
                'due_date' => data_get($financial, 'items.0.dueDate'),
                'original_amount' => $financial['originalAmount'] ?? 0,
                'outstanding_amount' => $financial['outstandingAmount'] ?? 0,
                'is_estimate' => data_get($financial, 'items.0.isChargeEstimate', false),
            ], $asOf);
        }

        return collect($charges)->unique(fn (array $charge) => implode('|', [
            $charge['transaction_id'] ?? '', $charge['charge_reference'] ?? '',
            $charge['due_date'] ?? '', $charge['original_amount'],
        ]))->sortBy('due_date')->values()->all();
    }

    private function charge(array $charge, CarbonImmutable $asOf): array
    {
        $due = $charge['due_date'] ? CarbonImmutable::parse($charge['due_date'])->startOfDay() : null;
        $outstanding = (float) $charge['outstanding_amount'];
        $description = strtolower((string) $charge['description']);
        $instalment = preg_match('/(?:poa|payment on account)\D*(1|first)\b/i', $description) ? 1
            : (preg_match('/(?:poa|payment on account)\D*(2|second)\b/i', $description) ? 2 : null);

        return [
            'tax_year' => $charge['tax_year'],
            'transaction_id' => $charge['transaction_id'],
            'charge_reference' => $charge['charge_reference'],
            'description' => $charge['description'],
            'instalment' => $instalment,
            'due_date' => $due?->toDateString(),
            'days_until_due' => $due ? (int) $asOf->startOfDay()->diffInDays($due, false) : null,
            'original_amount' => $this->money((float) $charge['original_amount']),
            'outstanding_amount' => $this->money($outstanding),
            'status' => $outstanding <= 0 ? 'paid' : ($due && $due->lt($asOf->startOfDay()) ? 'overdue' : 'upcoming'),
            'is_estimate' => (bool) $charge['is_estimate'],
        ];
    }

    private function allocatedPoaPayments(array $payments, array $charges): array
    {
        $ids = collect($charges)->flatMap(fn (array $charge) => [
            $charge['transaction_id'], $charge['charge_reference'],
        ])->filter()->map(fn ($id) => (string) $id)->all();
        if (!$ids) {
            return ['matched' => false, 'amount' => 0.0];
        }

        $amount = 0.0;
        $matched = false;
        $walk = function ($value) use (&$walk, &$amount, &$matched, $ids): void {
            if (!is_array($value)) {
                return;
            }
            $references = collect(['transactionId', 'documentId', 'chargeReference', 'mainTransaction'])
                ->map(fn ($key) => $value[$key] ?? null)->filter()->map(fn ($id) => (string) $id)->all();
            if (array_intersect($ids, $references)) {
                $candidate = $value['amount'] ?? $value['allocatedAmount'] ?? $value['allocationAmount'] ?? null;
                if (is_numeric($candidate)) {
                    $amount += (float) $candidate;
                    $matched = true;
                }
            }
            foreach ($value as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };
        $walk($payments);

        return ['matched' => $matched, 'amount' => round($amount, 2)];
    }

    private function instalment(array $charges, int $number, float $fallback, CarbonImmutable $due): array
    {
        $charge = collect($charges)->firstWhere('instalment', $number);
        return [
            'amount' => $this->money((float) ($charge['original_amount'] ?? $fallback)),
            'outstanding_amount' => $this->money((float) ($charge['outstanding_amount'] ?? $fallback)),
            'due_date' => $charge['due_date'] ?? $due->toDateString(),
            'status' => $charge['status'] ?? 'estimated',
            'source' => $charge ? 'hmrc' : 'local_estimate',
        ];
    }

    private function isPoa(string $description, mixed $type): bool
    {
        $value = strtolower(trim($description . ' ' . (string) $type));
        return str_contains($value, 'payment on account') || preg_match('/\bpoa\b/', $value) === 1;
    }

    private function sameTaxYear(mixed $value, string $taxYear): bool
    {
        if (!$value) {
            return false;
        }
        return str_replace('/', '-', (string) $value) === $taxYear;
    }

    private function sum(array $rows, string $key): float
    {
        return round(array_sum(array_map(fn (array $row) => (float) ($row[$key] ?? 0), $rows)), 2);
    }

    private function money(float $amount): float
    {
        return round($amount, 2);
    }
}
