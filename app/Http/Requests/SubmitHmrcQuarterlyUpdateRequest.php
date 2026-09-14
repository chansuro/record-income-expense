<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubmitHmrcQuarterlyUpdateRequest extends FormRequest
{
    public const EXPENSES = ['costOfGoods', 'paymentsToSubcontractors', 'wagesAndStaffCosts',
        'carVanTravelExpenses', 'premisesRunningCosts', 'maintenanceCosts', 'adminCosts',
        'businessEntertainmentCosts', 'advertisingCosts', 'interestOnBankOtherLoans',
        'financeCharges', 'irrecoverableDebts', 'professionalFees', 'depreciation', 'otherExpenses'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $disallowable = array_map(fn ($key) => $key . 'Disallowable', self::EXPENSES);
        $money = function ($attribute, $value, $fail) {
            if (!is_int($value) && !is_float($value)) {
                $fail('Amounts must be JSON numbers, not strings.');
            }
        };
        $rules = [
            'business_id' => ['required', 'string', 'regex:/^X[A-Z0-9]{1}IS[0-9]{11}$/'],
            'type_of_business' => ['sometimes', 'in:self-employment'],
            'tax_year' => ['bail', 'required', 'string', 'regex:/^20\d{2}-\d{2}$/', function ($attribute, $value, $fail) {
                $year = (int) substr($value, 0, 4);
                if ($year < 2025 || substr((string) ($year + 1), -2) !== substr($value, -2)) {
                    $fail('Use consecutive tax years from 2025-26 onwards, for example 2026-27.');
                }
            }],
            'payload' => ['required', 'array:periodDates,periodIncome,periodExpenses,periodDisallowableExpenses'],
            'payload.periodDates' => ['required', 'array:periodStartDate,periodEndDate'],
            'payload.periodDates.periodStartDate' => ['required', 'date_format:Y-m-d'],
            'payload.periodDates.periodEndDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:payload.periodDates.periodStartDate'],
            'payload.periodIncome' => ['required', 'array:turnover,other,taxTakenOffTradingIncome', 'min:1'],
            'payload.periodExpenses' => ['required', 'array:' . implode(',', array_merge(self::EXPENSES, ['consolidatedExpenses'])), 'min:1'],
            'payload.periodDisallowableExpenses' => ['sometimes', 'array:' . implode(',', $disallowable), 'min:1'],
            'nino' => ['prohibited'],
            'user_id' => ['prohibited'],
        ];
        foreach (['turnover', 'other', 'taxTakenOffTradingIncome'] as $key) {
            $rules['payload.periodIncome.' . $key] = ['sometimes', 'bail', 'numeric', $money, 'between:0,99999999999.99', 'decimal:0,2'];
        }
        foreach (array_merge(self::EXPENSES, ['consolidatedExpenses']) as $key) {
            $rules['payload.periodExpenses.' . $key] = ['sometimes', 'bail', 'numeric', $money, 'between:-99999999999.99,99999999999.99', 'decimal:0,2'];
        }
        foreach ($disallowable as $key) {
            $rules['payload.periodDisallowableExpenses.' . $key] = ['sometimes', 'bail', 'numeric', $money, 'between:-99999999999.99,99999999999.99', 'decimal:0,2'];
        }
        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $expenses = $this->input('payload.periodExpenses');
            if (array_key_exists('consolidatedExpenses', $expenses)
                && (count($expenses) > 1 || $this->has('payload.periodDisallowableExpenses'))) {
                $validator->errors()->add('payload.periodExpenses', 'Consolidated expenses cannot be combined with itemised or disallowable expenses.');
            }
            $year = (int) substr($this->input('tax_year'), 0, 4);
            $start = $this->input('payload.periodDates.periodStartDate');
            $end = $this->input('payload.periodDates.periodEndDate');
            // Allow calendar reporting and businesses commencing during the year.
            // HMRC validates the customer's actual reporting basis and commencement date.
            if ($start < "$year-04-01" || $end > ($year + 1) . '-04-05') {
                $validator->errors()->add('payload.periodDates', 'Reporting dates must fall within the selected tax year (including calendar reporting).');
            }
        }];
    }
}
