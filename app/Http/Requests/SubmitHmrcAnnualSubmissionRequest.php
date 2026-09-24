<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubmitHmrcAnnualSubmissionRequest extends FormRequest
{
    private const ADJUSTMENTS = ['includedNonTaxableProfits', 'basisAdjustment', 'overlapReliefUsed',
        'accountingAdjustment', 'averagingAdjustment', 'outstandingBusinessIncome', 'balancingChargeBpra',
        'balancingChargeOther', 'goodsAndServicesOwnUse', 'transitionProfitAmount',
        'transitionProfitAccelerationAmount', 'adjustmentToProfitsForClass4'];

    private const ALLOWANCES = ['annualInvestmentAllowance', 'capitalAllowanceMainPool',
        'capitalAllowanceSpecialRatePool', 'businessPremisesRenovationAllowance', 'enhancedCapitalAllowance',
        'allowanceOnSales', 'capitalAllowanceSingleAssetPool', 'zeroEmissionsCarAllowance',
        'tradingIncomeAllowance', 'structuredBuildingAllowance', 'enhancedStructuredBuildingAllowance'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $money = function ($attribute, $value, $fail) {
            if (!is_int($value) && !is_float($value)) {
                $fail('Amounts must be JSON numbers, not strings.');
            }
        };
        $rules = [
            'business_id' => ['required', 'string', 'regex:/^X[A-Z0-9]IS[0-9]{11}$/'],
            'type_of_business' => ['sometimes', 'in:self-employment'],
            'tax_year' => ['bail', 'required', 'string', 'regex:/^20\d{2}-\d{2}$/', function ($attribute, $value, $fail) {
                $year = (int) substr($value, 0, 4);
                if ($year < 2025 || substr((string) ($year + 1), -2) !== substr($value, -2)) {
                    $fail('Use consecutive tax years from 2025-26 onwards, for example 2026-27.');
                }
            }],
            // 'payload' => ['required', 'array:adjustments,allowances,nonFinancials', 'min:1'],
            // 'payload.adjustments' => ['sometimes', 'array:' . implode(',', self::ADJUSTMENTS), 'min:1'],
            // 'payload.allowances' => ['sometimes', 'array:' . implode(',', self::ALLOWANCES), 'min:1'],
            // 'payload.nonFinancials' => ['sometimes', 'array:class4NicsExemptionReason', 'min:1'],
            // 'payload.nonFinancials.class4NicsExemptionReason' => ['sometimes', 'in:non-resident,trustee,diver,ITTOIA-2005,over-state-pension-age,under-16'],
            'nino' => ['prohibited'],
            'user_id' => ['prohibited'],
        ];
        foreach (self::ADJUSTMENTS as $key) {
            $minimum = in_array($key, ['basisAdjustment', 'averagingAdjustment'], true)
                ? -99999999999.99 : 0;
            $rules['payload.adjustments.' . $key] = ['sometimes', 'bail', 'numeric', $money,
                'between:' . $minimum . ',99999999999.99', 'decimal:0,2'];
        }
        foreach (array_diff(self::ALLOWANCES, ['structuredBuildingAllowance', 'enhancedStructuredBuildingAllowance']) as $key) {
            $maximum = $key === 'tradingIncomeAllowance' ? 1000 : 99999999999.99;
            $rules['payload.allowances.' . $key] = ['sometimes', 'bail', 'numeric', $money,
                'between:0,' . $maximum, 'decimal:0,2'];
        }
        // foreach (['structuredBuildingAllowance', 'enhancedStructuredBuildingAllowance'] as $key) {
        //     $base = 'payload.allowances.' . $key;
        //     $rules[$base] = ['sometimes', 'array', 'min:1'];
        //     $rules[$base . '.*'] = ['array:amount,firstYear,building'];
        //     $rules[$base . '.*.amount'] = ['required', 'bail', 'numeric', $money, 'between:0,99999999999.99', 'decimal:0,2'];
        //     $rules[$base . '.*.firstYear'] = ['sometimes', 'array:qualifyingDate,qualifyingAmountExpenditure'];
        //     $rules[$base . '.*.firstYear.qualifyingDate'] = ['required_with:' . $base . '.*.firstYear', 'date_format:Y-m-d'];
        //     $rules[$base . '.*.firstYear.qualifyingAmountExpenditure'] = ['required_with:' . $base . '.*.firstYear',
        //         'bail', 'numeric', $money, 'between:0,99999999999.99', 'decimal:0,2'];
        //     $rules[$base . '.*.building'] = ['required', 'array:name,number,postcode'];
        //     $rules[$base . '.*.building.name'] = ['nullable', 'string', 'max:90'];
        //     $rules[$base . '.*.building.number'] = ['nullable', 'string', 'max:90'];
        //     $rules[$base . '.*.building.postcode'] = ['required', 'string', 'max:90'];
        // }
        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $payload = $this->input('payload', []);
            if (count(array_filter($payload, fn ($value) => is_array($value) && $value !== [])) === 0) {
                $validator->errors()->add('payload', 'At least one annual adjustment, allowance or non-financial value is required.');
            }
            $allowances = $payload['allowances'] ?? [];
            if (array_key_exists('tradingIncomeAllowance', $allowances) && count($allowances) > 1) {
                $validator->errors()->add('payload.allowances', 'Trading income allowance cannot be supplied with another allowance.');
            }
            $adjustments = $payload['adjustments'] ?? [];
            if (array_key_exists('transitionProfitAccelerationAmount', $adjustments)
                && !array_key_exists('transitionProfitAmount', $adjustments)) {
                $validator->errors()->add('payload.adjustments.transitionProfitAmount',
                    'Transition profit amount is required with an acceleration amount.');
            }
            $year = (int) substr($this->input('tax_year'), 0, 4);
            if ($year >= 2026 && array_key_exists('overlapReliefUsed', $adjustments)) {
                $validator->errors()->add('payload.adjustments.overlapReliefUsed', 'Overlap relief is not supported from 2026-27.');
            }
            if ($year === 2025 && array_key_exists('adjustmentToProfitsForClass4', $adjustments)) {
                $validator->errors()->add('payload.adjustments.adjustmentToProfitsForClass4',
                    'Adjustment to profits for Class 4 is supported from 2026-27.');
            }
            foreach (['structuredBuildingAllowance', 'enhancedStructuredBuildingAllowance'] as $key) {
                foreach ($allowances[$key] ?? [] as $index => $item) {
                    $building = $item['building'] ?? [];
                    if (blank($building['name'] ?? null) && blank($building['number'] ?? null)) {
                        $validator->errors()->add("payload.allowances.$key.$index.building",
                            'Building name or number is required.');
                    }
                }
            }
        }];
    }
}
