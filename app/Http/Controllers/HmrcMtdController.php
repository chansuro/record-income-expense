<?php

namespace App\Http\Controllers;

use App\Models\HmrcClientAuthorisation;
use App\Services\HmrcService;
use App\Services\HmrcQuarterDashboard;
use App\Services\HmrcTaxAndPaymentsDashboard;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HmrcMtdController extends Controller
{
    public function createSandboxBusiness(Request $request, HmrcService $hmrc): JsonResponse
    {
        if (config('services.hmrc.environment') !== 'sandbox') {
            return response()->json(['success' => false,
                'message' => 'Test businesses can only be created in HMRC sandbox.'], 403);
        }

        $validated = $request->validate([
            'tax_year' => ['bail', 'required', 'regex:/^20\d{2}-\d{2}$/', function ($attribute, $value, $fail) {
                $year = (int) substr($value, 0, 4);
                if (substr((string) ($year + 1), -2) !== substr($value, -2)) {
                    $fail('Use consecutive tax years, for example 2026-27.');
                }
            }],
            'trading_type' => ['required', 'string', 'max:35'],
            'trading_name' => ['required', 'string', 'max:105'],
            'accounting_type' => ['required', 'in:CASH,ACCRUALS'],
            'quarterly_period_type' => ['required', 'in:standard,calendar'],
            'business_address_line_one' => ['required', 'string', 'max:35'],
            'business_address_line_two' => ['nullable', 'string', 'max:35'],
            'business_address_line_three' => ['nullable', 'string', 'max:35'],
            'business_address_line_four' => ['nullable', 'string', 'max:35'],
            'business_address_postcode' => ['required', 'string', 'max:10'],
            'business_address_country_code' => ['required', 'string', 'size:2'],
            'nino' => ['prohibited'],
            'user_id' => ['prohibited'],
        ]);
        $authorisation = $this->authorisation();
        if (!$authorisation) {
            return $this->notAuthorised();
        }

        $year = (int) substr($validated['tax_year'], 0, 4);
        $start = $validated['quarterly_period_type'] === 'calendar'
            ? sprintf('%d-04-01', $year) : sprintf('%d-04-06', $year);
        $end = $validated['quarterly_period_type'] === 'calendar'
            ? sprintf('%d-03-31', $year + 1) : sprintf('%d-04-05', $year + 1);
        $payload = array_filter([
            'typeOfBusiness' => 'self-employment',
            'tradingType' => $validated['trading_type'],
            'tradingName' => $validated['trading_name'],
            'firstAccountingPeriodStartDate' => $start,
            'firstAccountingPeriodEndDate' => $end,
            'quarterlyTypeChoice' => ['quarterlyPeriodType' => $validated['quarterly_period_type'],
                'taxYearOfChoice' => $validated['tax_year']],
            'accountingType' => $validated['accounting_type'],
            'commencementDate' => $start,
            'businessAddressLineOne' => $validated['business_address_line_one'],
            'businessAddressLineTwo' => $validated['business_address_line_two'] ?? null,
            'businessAddressLineThree' => $validated['business_address_line_three'] ?? null,
            'businessAddressLineFour' => $validated['business_address_line_four'] ?? null,
            'businessAddressPostcode' => strtoupper($validated['business_address_postcode']),
            'businessAddressCountryCode' => strtoupper($validated['business_address_country_code']),
        ], fn ($value) => $value !== null && $value !== '');

        try {
            $result = $hmrc->createSandboxBusiness($authorisation->client_id, $payload);
            return response()->json(['success' => true,
                'message' => 'Self-employment test business created in HMRC sandbox. Test data expires after 7 days.',
                'data' => ['business_id' => $result['businessId'], 'tax_year' => $validated['tax_year'],
                    'reporting_period' => $validated['quarterly_period_type'],
                    'correlation_id' => $result['correlationId']]], 201);
        } catch (\Throwable $e) {
            return $this->exception($e);
        }
    }

    public function createSandboxItsaStatus(Request $request, HmrcService $hmrc): JsonResponse
    {
        if (config('services.hmrc.environment') !== 'sandbox') {
            return response()->json(['success' => false,
                'message' => 'Test ITSA statuses can only be created in sandbox.'], 403);
        }

        $validated = $request->validate([
            'tax_year' => ['required', 'string', 'regex:/^20\d{2}-\d{2}$/',
                function ($attribute, $value, $fail) {
                    if (preg_match('/^20\d{2}-\d{2}$/', $value)
                        && substr((string) ((int) substr($value, 0, 4) + 1), -2) !== substr($value, -2)) {
                        $fail('The tax year must contain consecutive years, for example 2026-27.');
                    }
                }],
            'status' => ['required', 'string', 'in:No Status,MTD Mandated,MTD Voluntary,Annual,Non Digital,Dormant,MTD Exempt'],
            'status_reason' => ['required', 'string', \Illuminate\Validation\Rule::in([
                'Sign up - return available', 'Sign up - no return available',
                'ITSA final declaration', 'ITSA Q4 declaration', 'CESA SA return',
                'Complex', 'Ceased income source', 'Reinstated income source', 'Rollover',
                'Income Source Latency Changes', 'MTD ITSA Opt-Out', 'MTD ITSA Opt-In', 'Digitally Exempt',
            ])],
            'business_income_2_years_prior' => ['sometimes', 'numeric', 'min:0', 'max:99999999999.99', 'decimal:0,2'],
            'nino' => ['prohibited'],
            'user_id' => ['prohibited'],
        ]);

        $authorisation = $this->authorisation();
        if (!$authorisation) {
            return $this->notAuthorised();
        }

        $details = [
            'submittedOn' => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'status' => $validated['status'],
            'statusReason' => $validated['status_reason'],
        ];
        if (array_key_exists('business_income_2_years_prior', $validated)) {
            $details['businessIncome2YearsPrior'] = (float) $validated['business_income_2_years_prior'];
        }

        try {
            $hmrc->createSandboxItsaStatus($authorisation->client_id, $validated['tax_year'], [
                'itsaStatusDetails' => [$details],
            ]);
            return response()->json(['success' => true,
                'message' => 'Test ITSA status saved in HMRC sandbox. Test data expires after 7 days.',
                'data' => ['tax_year' => $validated['tax_year'], 'status' => $validated['status']]]);
        } catch (\Throwable $e) {
            return $this->exception($e);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Find accepted HMRC authorisation for logged-in AppTax customer
    |--------------------------------------------------------------------------
    */
    private function authorisation(): ?HmrcClientAuthorisation
    {
        return HmrcClientAuthorisation::where(
            'user_id',
            auth()->id()
        )
        ->where(
            'environment',
            config('services.hmrc.environment')
        )
        ->where(
            'service',
            'MTD-IT'
        )
        ->where(
            'status',
            'Accepted'
        )
        ->latest()
        ->first();
    }


    /*
    |--------------------------------------------------------------------------
    | Fraud prevention headers
    |--------------------------------------------------------------------------
    */
    private function fraudHeaders(Request $request): array {
        return app(\App\Services\HmrcFraudHeaders::class)->build($request);
    }


    /*
    |--------------------------------------------------------------------------
    | 1. Check agent relationship
    |--------------------------------------------------------------------------
    */
    public function checkRelationship(Request $request,HmrcService $hmrc): JsonResponse {
        $validated = $request->validate([
            'postcode' => [
                'required',
                'string',
                'max:10',
            ],
        ]);
        $authorisation = $this->authorisation();
        if (!$authorisation) {
            return response()->json([
                'success' => false,
                'message' =>
                    'HMRC authorisation has not been accepted.',
            ], 403);
        }
        try {
            $active = $hmrc->checkRelationship(
                $authorisation->client_id,
                $validated['postcode'],
                $authorisation->agent_type
                    ?? 'main'
            );
            return response()->json([
                'success' => true,
                'data' => [
                    'relationship_active' => $active,
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | 2. MTD / ITSA status
    |--------------------------------------------------------------------------
    */
    public function mtdStatus(Request $request,HmrcService $hmrc): JsonResponse {

        $validator = Validator::make($request->all(), [
            // 'nino' => [
            //     'required',
            //     'string',
            // ],
            'tax_year' => [
                'required',
                'string',
                'regex:/^\d{4}-\d{2}$/',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $authorisation = $this->authorisation();

        if (!$authorisation) {
            return $this->notAuthorised();
        }

        try {
            $data = $hmrc->getMtdCustomerStatus(
                //$validated['nino'],
                $authorisation->client_id,
                $validated['tax_year'],
                $this->fraudHeaders($request)
            );

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Throwable $e) {
            return $this->exception($e);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | 3. HMRC businesses
    |--------------------------------------------------------------------------
    */
    public function businesses(Request $request,HmrcService $hmrc): JsonResponse {

        $authorisation = $this->authorisation();

        if (!$authorisation) {
            return $this->notAuthorised();
        }

        try {
            $data = $hmrc->getBusinessDetails(
                $authorisation->client_id,
                $this->fraudHeaders($request)
            );
            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Throwable $e) {
            return $this->exception($e);
        }
    }
    
    /*
    |--------------------------------------------------------------------------
    | 3.1 HMRC businesses
    |--------------------------------------------------------------------------
    */
    public function savebusinesses(Request $request,HmrcService $hmrc): JsonResponse {

        $authorisation = $this->authorisation();

        if (!$authorisation) {
            return $this->notAuthorised();
        }

        try {
            $validated = $request->validate([
                'id' => [
                    'required'
                ],
                'typeOfBusiness' => [
                    'required',
                    'string',
                ],

                'businessId' => [
                    'required',
                    'string',
                ],
                'tradingType' => [
                    'required',
                    'string',
                ],
                'tradingName' => [
                    'required',
                    'string',
                ],
            ]);
            $clientAuthData = HmrcClientAuthorisation::where('id', $validated['id'])->update([
                'typeOfBusiness' => $validated['typeOfBusiness'],
                'businessId' => $validated['businessId'],
                'tradingType' => $validated['tradingType'],
                'tradingName' => $validated['tradingName'],
            ]);
            $authorisation = HmrcClientAuthorisation::where(
                'user_id',
                auth()->id()
            )
            ->where(
                'environment',
                config('services.hmrc.environment')
            )
            ->first();
            return response()->json([
                'success' => true,
                'data' => $clientAuthData,
            ]);
        } catch (\Throwable $e) {
            return $this->exception($e);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | 4. Obligations
    |--------------------------------------------------------------------------
    */
    public function obligations(Request $request,HmrcService $hmrc): JsonResponse {

        $validated = $request->validate([
            'business_id' => [
                'nullable',
                'string',
            ],

            'type_of_business' => [
                'nullable',
                'in:self-employment,uk-property,foreign-property',
            ],

            'from_date' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'to_date' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'status' => [
                'nullable',
                'in:open,fulfilled',
            ],
        ]);

        $authorisation =
            $this->authorisation();

        if (!$authorisation) {
            return $this->notAuthorised();
        }
        
        try {
            $data = $hmrc->getObligations(
                $authorisation->client_id,
                $validated['business_id']
                    ?? null,

                $validated['type_of_business']
                    ?? null,

                $validated['from_date']
                    ?? null,

                $validated['to_date']
                    ?? null,

                $validated['status']
                    ?? null,

                $this->fraudHeaders($request)
            );

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Throwable $e) {
            return $this->exception($e);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | 5. Submit quarterly cumulative update
    |--------------------------------------------------------------------------
    */
    public function submitQuarterlyUpdate(
        \App\Http\Requests\SubmitHmrcQuarterlyUpdateRequest $request,
        HmrcService $hmrc
    ): JsonResponse {
        $validated = $request->validated();
        $authorisation = $this->authorisation();
        if (!$authorisation) {
            return $this->notAuthorised();
        }
        $submission = null;
        $receipt = null;
        $dispatchStarted = false;
        try {
            $fraudHeaders = app(\App\Services\HmrcFraudHeaders::class)->build($request);
            $expense_categories = [
                '0'=>'carVanTravelExpenses',
                '1'=>'premisesRunningCosts',
                '2'=>'adminCosts',
                '3'=>'interestOnBankOtherFinancialCharges',
                '4'=>'financeCharges',
                '5'=>'professionalFees',
                '6' => 'otherExpenses'
            ];
            $fromDate = $validated['periodStartDate'];
            $toDate = $validated['periodEndDate'];
            $userId = auth()->id();
            $expenses = DB::table('transactions as t')
            ->join('category_lists as cl', 'cl.id', '=', 't.category_list_id')
            ->select([
                'cl.parent',
                DB::raw('ROUND(SUM(t.amount), 2) AS total_expenses'),
            ])
            ->where('t.user_id', $userId)
            ->where('t.type', 'expenses')
            ->where('t.status', '1')
            ->where('cl.status', '1')
            ->whereIn('cl.parent', [0, 1, 2, 3, 4, 5, 6])
            ->whereIn('cl.type', ['dailyexp', 'recurringexp'])
            ->where(function ($query) use ($userId) {
                $query->whereNull('cl.user_id')
                    ->orWhere('cl.user_id', $userId);
            })
            ->whereBetween('t.transaction_date', [
                $fromDate . ' 00:00:00',
                $toDate . ' 23:59:59',
            ])
            ->groupBy('cl.parent')
            ->orderBy('cl.parent')
            ->get();
            $periodExpenses = array_fill_keys(
                array_values($expense_categories),
                0.00
            );
            foreach ($expenses as $parent => $data) {
                if (isset($expense_categories[$data->parent])) {
                    $hmrcField = $expense_categories[$data->parent];

                    $periodExpenses[$hmrcField] = round((float) $data->total_expenses, 2);
                }
            }
            
            $periodExpenses = array_fill_keys(
                array_values($expense_categories),
                0.00
            );
            foreach ($expenses as $parent => $data) {
                if (isset($expense_categories[$data->parent])) {
                    $hmrcField = $expense_categories[$data->parent];

                    $periodExpenses[$hmrcField] = round((float) $data->total_expenses, 2);
                }
            }
            $totalIncome = DB::table('transactions')
                ->where('user_id', $userId)
                ->where('type', 'income')
                ->where('status', '1')
                ->whereBetween('transaction_date', [
                    $fromDate . ' 00:00:00',
                    $toDate . ' 23:59:59',
                ])
                ->sum('amount');

            $totalIncome = round((float) $totalIncome, 2);
            $payload = [
                'periodDates' => [
                    'periodStartDate' => $fromDate,
                    'periodEndDate' => $toDate,
                ],

                'periodIncome' => [
                    'turnover' => $totalIncome,
                    'other' => 0.00,
                ],

                'periodExpenses' => $periodExpenses,
            ];

            
           // Persist before contacting HMRC. A database failure here prevents an untracked submission.
            $submission = \App\Models\HmrcQuarterlySubmission::create([
                'user_id' => auth()->id(), 'authorisation_id' => $authorisation->id,
                'environment' => config('services.hmrc.environment'),
                'business_id' => $validated['business_id'], 'tax_year' => $validated['tax_year'],
                'test_scenario' => $hmrc->quarterlyTestScenario(),
                'period_start_date' => $fromDate,
                'period_end_date' => $toDate,
                'payload' => $payload, 'status' => 'pending',
            ]);
            $dispatchStarted = true;
            $receipt = $hmrc->submitQuarterlyUpdate($authorisation->client_id,
                $validated['business_id'], $validated['tax_year'], $payload, $fraudHeaders);
            $submission->update([
                'status' => $receipt['status'], 'hmrc_http_status' => $receipt['hmrc_http_status'],
                'correlation_id' => $receipt['correlation_id'], 'submitted_at' => now(),
            ]);
            return response()->json(['success' => true,
                'message' => $receipt['status'] === 'simulated'
                    ? 'HMRC sandbox simulated success; this does not confirm a stored update.'
                    : 'Quarterly update accepted by HMRC ' . $receipt['environment'] . '.',
                'data' => array_merge($receipt, ['submission_id' => $submission->id])]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($receipt !== null) {
                // HMRC accepted the request, but saving the receipt failed. Never tell the user to blindly retry.
                Log::error('HMRC receipt persistence failed', ['submission_id' => $submission->id,
                    'correlation_id' => $receipt['correlation_id'], 'hmrc_status' => $receipt['status']]);
                return response()->json(['success' => true,
                    'message' => 'HMRC returned success, but the local receipt could not be saved. Do not resubmit; reconcile this submission.',
                    'data' => array_merge($receipt, ['submission_id' => $submission->id, 'history_saved' => false])]);
            }
            $hmrcError = $e instanceof \App\Exceptions\HmrcSubmissionException;
            $outcome = $hmrcError ? $e->outcome : ($dispatchStarted ? 'unknown' : 'not_sent');
            if ($submission) {
                try {
                    $submission->update(['status' => $outcome,
                        'hmrc_http_status' => $hmrcError ? $e->httpStatus : null,
                        'hmrc_code' => $hmrcError ? $e->hmrcCode : null,
                        'correlation_id' => $hmrcError ? $e->correlationId : null]);
                } catch (\Throwable $storageError) {
                    Log::error('HMRC failure receipt persistence failed', ['submission_id' => $submission->id]);
                }
            }
            // Do not report database exceptions with encrypted payload bindings or taxpayer data.
            Log::warning('HMRC quarterly submission failed', ['submission_id' => $submission?->id,
                'outcome' => $outcome, 'exception_class' => get_class($e)]);
            return response()->json(['success' => false,
                'message' => $e instanceof \Illuminate\Database\QueryException
                    ? 'Could not save the submission record. Verify the HMRC submission migration has been applied.' : $e->getMessage(),
                'data' => ['submission_id' => $submission?->id, 'status' => $outcome,
                    'hmrc_code' => $hmrcError ? $e->hmrcCode : null,
                    'correlation_id' => $hmrcError ? $e->correlationId : null]], $outcome === 'unknown' ? 502 : 422);
        }
    }

    public function quarterlySubmissionHistory(Request $request): JsonResponse
    {
        $validated = $request->validate(['business_id' => ['nullable', 'string'],
            'tax_year' => ['nullable', 'regex:/^20\d{2}-\d{2}$/']]);
        $query = \App\Models\HmrcQuarterlySubmission::where('user_id', auth()->id())
            ->where('environment', config('services.hmrc.environment'));
        foreach (['business_id', 'tax_year'] as $key) {
            if (!empty($validated[$key])) {
                $query->where($key, $validated[$key]);
            }
        }
        return response()->json(['success' => true, 'data' => $query->latest()->paginate(20)]);
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Annual Self Employment submission
    |--------------------------------------------------------------------------
    */
    public function submitAnnualSubmission(
        \App\Http\Requests\SubmitHmrcAnnualSubmissionRequest $request,
        HmrcService $hmrc
    ): JsonResponse {
        $validated = $request->validated();
        $authorisation = $this->authorisation();
        if (!$authorisation) {
            return $this->notAuthorised();
        }
        $submission = null;
        $receipt = null;
        $dispatchStarted = false;
        try {
            $fraudHeaders = app(\App\Services\HmrcFraudHeaders::class)->build($request);
            $submission = \App\Models\HmrcAnnualSubmission::create([
                'user_id' => auth()->id(), 'authorisation_id' => $authorisation->id,
                'environment' => config('services.hmrc.environment'),
                'business_id' => $validated['business_id'], 'tax_year' => $validated['tax_year'],
                'test_scenario' => $hmrc->annualTestScenario(), 'payload' => $validated['payload'],
                'status' => 'pending',
            ]);
            $dispatchStarted = true;
            $receipt = $hmrc->submitAnnualSubmission($authorisation->client_id,
                $validated['business_id'], $validated['tax_year'], $validated['payload'], $fraudHeaders);
            $submission->update(['status' => $receipt['status'],
                'hmrc_http_status' => $receipt['hmrc_http_status'],
                'correlation_id' => $receipt['correlation_id'], 'submitted_at' => now()]);
            return response()->json(['success' => true,
                'message' => $receipt['status'] === 'simulated'
                    ? 'HMRC sandbox simulated annual success; this does not confirm stored data.'
                    : 'Annual self-employment submission accepted by HMRC ' . $receipt['environment'] . '.',
                'data' => array_merge($receipt, ['submission_id' => $submission->id])]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($receipt !== null) {
                Log::error('HMRC annual receipt persistence failed', ['submission_id' => $submission->id,
                    'correlation_id' => $receipt['correlation_id'], 'hmrc_status' => $receipt['status']]);
                return response()->json(['success' => true,
                    'message' => 'HMRC returned annual success, but the local receipt could not be saved. Do not resubmit; reconcile it.',
                    'data' => array_merge($receipt, ['submission_id' => $submission->id, 'history_saved' => false])]);
            }
            $hmrcError = $e instanceof \App\Exceptions\HmrcSubmissionException;
            $outcome = $hmrcError ? $e->outcome : ($dispatchStarted ? 'unknown' : 'not_sent');
            if ($submission) {
                try {
                    $submission->update(['status' => $outcome,
                        'hmrc_http_status' => $hmrcError ? $e->httpStatus : null,
                        'hmrc_code' => $hmrcError ? $e->hmrcCode : null,
                        'correlation_id' => $hmrcError ? $e->correlationId : null]);
                } catch (\Throwable $storageError) {
                    Log::error('HMRC annual failure receipt persistence failed', ['submission_id' => $submission->id]);
                }
            }
            Log::warning('HMRC annual submission failed', ['submission_id' => $submission?->id,
                'outcome' => $outcome, 'exception_class' => get_class($e)]);
            return response()->json(['success' => false,
                'message' => $e instanceof \Illuminate\Database\QueryException
                    ? 'Could not save the annual submission record. Verify its migration has been applied.' : $e->getMessage(),
                'data' => ['submission_id' => $submission?->id, 'status' => $outcome,
                    'hmrc_code' => $hmrcError ? $e->hmrcCode : null,
                    'correlation_id' => $hmrcError ? $e->correlationId : null]], $outcome === 'unknown' ? 502 : 422);
        }
    }

    public function annualSubmissionHistory(Request $request): JsonResponse
    {
        $validated = $request->validate(['business_id' => ['nullable', 'string'],
            'tax_year' => ['nullable', 'regex:/^20\d{2}-\d{2}$/']]);
        $query = \App\Models\HmrcAnnualSubmission::where('user_id', auth()->id())
            ->where('environment', config('services.hmrc.environment'));
        foreach (['business_id', 'tax_year'] as $key) {
            if (!empty($validated[$key])) {
                $query->where($key, $validated[$key]);
            }
        }
        return response()->json(['success' => true, 'data' => $query->latest()->paginate(20)]);
    }


    /*
    |--------------------------------------------------------------------------
    | 7. Trigger an in-year calculation
    |--------------------------------------------------------------------------
    */
    public function triggerCalculation(Request $request,HmrcService $hmrc): JsonResponse {

        $validated = $request->validate([
            'tax_year' => [
                'required',
                'regex:/^20\d{2}-\d{2}$/',
            ],
        ]);

        $authorisation =
            $this->authorisation();

        if (!$authorisation) {
            return $this->notAuthorised();
        }

        try {

            $data = $hmrc->triggerTaxCalculation(
                $authorisation->client_id,
                $validated['tax_year'],
                'in-year',
                $this->fraudHeaders($request)
            );

            return response()->json([
                'success' => true,

                'message' => 'HMRC tax calculation triggered successfully.',

                'retry_after_seconds' => 5,

                'data' => $data,
            ]);

        } catch (\Throwable $e) {
            return $this->exception($e);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | 8. Retrieve calculation
    |--------------------------------------------------------------------------
    */
    public function retrieveCalculation(Request $request,string $calculationId,HmrcService $hmrc): JsonResponse {

        $validated = $request->validate([
            'tax_year' => [
                'required',
                'regex:/^20\d{2}-\d{2}$/',
            ],
        ]);

        $authorisation =
            $this->authorisation();

        if (!$authorisation) {
            return $this->notAuthorised();
        }

        try {

            $data = $hmrc->retrieveTaxCalculation(
                $authorisation->client_id,
                $validated['tax_year'],
                $calculationId,
                $this->fraudHeaders($request)
            );

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Throwable $e) {
            return $this->exception($e);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | 9. Trigger final calculation
    |--------------------------------------------------------------------------
    */
    public function triggerFinalCalculation(Request $request,HmrcService $hmrc): JsonResponse {

        $validated = $request->validate([
            'tax_year' => [
                'required',
                'regex:/^20\d{2}-\d{2}$/',
            ],
        ]);

        $authorisation =
            $this->authorisation();

        if (!$authorisation) {
            return $this->notAuthorised();
        }

        try {

            $data = $hmrc->triggerTaxCalculation(
                $authorisation->client_id,
                $validated['tax_year'],
                'intent-to-finalise',
                $this->fraudHeaders($request)
            );

            return response()->json([
                'success' => true,

                'message' =>
                    'Final HMRC calculation triggered.',

                'retry_after_seconds' => 5,

                'data' => $data,
            ]);

        } catch (\Throwable $e) {
            return $this->exception($e);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | 10. Submit Final Declaration
    |--------------------------------------------------------------------------
    */
    public function submitFinalDeclaration(Request $request,HmrcService $hmrc): JsonResponse {

        $validated = $request->validate([
            'tax_year' => [
                'required',
                'regex:/^20\d{2}-\d{2}$/',
            ],

            'calculation_id' => [
                'required',
                'string',
            ],

            /*
             * Client MUST explicitly approve the final
             * tax calculation before submission.
             */
            'client_approved' => [
                'required',
                'accepted',
            ],
        ]);

        $authorisation =
            $this->authorisation();

        if (!$authorisation) {
            return $this->notAuthorised();
        }

        /*
         * IMPORTANT:
         *
         * In production, persist this approval in an
         * audit table before submitting.
         */
        Log::info(
            'HMRC final declaration approved by client',
            [
                'user_id' => auth()->id(),

                'tax_year' =>
                    $validated['tax_year'],

                'calculation_id' =>
                    $validated['calculation_id'],

                'approved_at' =>
                    now()->toIso8601String(),
            ]
        );

        try {

            $hmrc->submitFinalDeclaration(
                $authorisation->client_id,
                $validated['tax_year'],
                $validated['calculation_id'],
                $this->fraudHeaders($request)
            );

            return response()->json([
                'success' => true,

                'message' =>
                    'Your Self Assessment tax return has been submitted successfully to HMRC.',
            ]);

        } catch (\Throwable $e) {
            return $this->exception($e);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 11. Self Assessment account summary, POA and amounts due
    |--------------------------------------------------------------------------
    */
    public function accountSummary(
        Request $request,
        HmrcService $hmrc
    ): JsonResponse {
        $validated = $request->validate([
            'from_date' => [
                'nullable',
                'date_format:Y-m-d',
                'required_with:to_date',
            ],
            'to_date' => [
                'nullable',
                'date_format:Y-m-d',
                'required_with:from_date',
                'after_or_equal:from_date',
            ],
            'only_open_items' => [
                'nullable',
                'boolean',
            ],
            'include_estimated_charges' => [
                'nullable',
                'boolean',
            ],
        ]);

        $authorisation = $this->authorisation();

        if (!$authorisation) {
            return $this->notAuthorised();
        }

        try {
            $account = $hmrc->getSelfAssessmentBalanceAndTransactions(
                $authorisation->client_id,
                $validated['from_date'] ?? null,
                $validated['to_date'] ?? null,
                (bool) ($validated['only_open_items'] ?? false),
                (bool) ($validated['include_estimated_charges'] ?? true),
                $this->fraudHeaders($request)
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'balances' => $this->normaliseAccountBalances($account),
                    'payments_on_account' =>
                        $this->extractPaymentsOnAccount($account),
                    'hmrc' => $account,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->exception($e);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 12. Self Assessment payments and allocations
    |--------------------------------------------------------------------------
    */
    public function paymentsAndAllocations(
        Request $request,
        HmrcService $hmrc
    ): JsonResponse {
        $validated = $request->validate([
            'from_date' => [
                'nullable',
                'date_format:Y-m-d',
                'required_with:to_date',
            ],
            'to_date' => [
                'nullable',
                'date_format:Y-m-d',
                'required_with:from_date',
                'after_or_equal:from_date',
            ],
        ]);

        $authorisation = $this->authorisation();

        if (!$authorisation) {
            return $this->notAuthorised();
        }

        try {
            $data = $hmrc->getSelfAssessmentPaymentsAndAllocations(
                $authorisation->client_id,
                $validated['from_date'] ?? null,
                $validated['to_date'] ?? null,
                $this->fraudHeaders($request)
            );

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return $this->exception($e);
        }
    }

    private function normaliseAccountBalances(array $account): array
    {
        $balance = $account['balanceDetails'] ?? [];

        return [
            'overdue_amount' => (float) ($balance['overdueAmount'] ?? 0),
            'earliest_overdue_date' =>
                $balance['earliestPaymentDateOverdue'] ?? null,
            'payable_amount' => (float) ($balance['payableAmount'] ?? 0),
            'payable_due_date' => $balance['payableDueDate'] ?? null,
            'pending_amount' =>
                (float) ($balance['pendingChargeDueAmount'] ?? 0),
            'pending_due_date' =>
                $balance['pendingChargeDueDate'] ?? null,
            'total_balance' => (float) ($balance['totalBalance'] ?? 0),
            'available_credit' =>
                (float) ($balance['availableCredit'] ?? 0),
            'unallocated_credit' =>
                (float) ($balance['unallocatedCredit'] ?? 0),
        ];
    }

    private function extractPaymentsOnAccount(array $account): array
    {
        $charges = [];

        foreach (($account['documentDetails'] ?? []) as $document) {
            $description = trim(
                (string) (
                    $document['documentDescription']
                    ?? $document['documentText']
                    ?? ''
                )
            );

            if (!$this->isPaymentOnAccount($description)) {
                continue;
            }

            $charges[] = $this->normalisePoaCharge([
                'tax_year' => $document['taxYear'] ?? null,
                'transaction_id' => $document['documentId'] ?? null,
                'charge_reference' => $document['chargeReference'] ?? null,
                'description' => $description,
                'due_date' => $document['documentDueDate'] ?? null,
                'original_amount' => $document['originalAmount'] ?? 0,
                'outstanding_amount' => $document['outstandingAmount'] ?? 0,
                'is_estimate' => $document['isChargeEstimate'] ?? false,
            ]);
        }

        foreach (($account['financialDetails'] ?? []) as $financial) {
            $chargeDetail = $financial['chargeDetail'] ?? [];
            $description = trim(
                (string) (
                    $chargeDetail['chargeTypeDescription']
                    ?? $chargeDetail['documentTypeDescription']
                    ?? ''
                )
            );

            if (!$this->isPaymentOnAccount($description)) {
                continue;
            }

            $dueDate = $financial['items'][0]['dueDate'] ?? null;

            $charges[] = $this->normalisePoaCharge([
                'tax_year' => $financial['taxYear'] ?? null,
                'transaction_id' => $financial['documentNumber'] ?? null,
                'charge_reference' => $financial['chargeReference'] ?? null,
                'description' => $description,
                'due_date' => $dueDate,
                'original_amount' => $financial['originalAmount'] ?? 0,
                'outstanding_amount' =>
                    $financial['outstandingAmount'] ?? 0,
                'is_estimate' =>
                    $financial['items'][0]['isChargeEstimate'] ?? false,
            ]);
        }

        return collect($charges)
            ->unique(fn (array $charge) => implode('|', [
                $charge['transaction_id'] ?? '',
                $charge['charge_reference'] ?? '',
                $charge['due_date'] ?? '',
                $charge['original_amount'],
            ]))
            ->sortBy('due_date')
            ->values()
            ->all();
    }

    private function isPaymentOnAccount(string $description): bool
    {
        $description = strtolower($description);

        return str_contains($description, 'poa')
            || str_contains($description, 'payment on account');
    }

    private function normalisePoaCharge(array $charge): array
    {
        $original = (float) $charge['original_amount'];
        $outstanding = (float) $charge['outstanding_amount'];
        $dueDate = $charge['due_date'];

        if ($outstanding <= 0) {
            $status = 'paid';
        } elseif ($dueDate && Carbon::parse($dueDate)->isPast()) {
            $status = 'overdue';
        } else {
            $status = 'upcoming';
        }

        return [
            'tax_year' => $charge['tax_year'],
            'transaction_id' => $charge['transaction_id'],
            'charge_reference' => $charge['charge_reference'],
            'description' => $charge['description'],
            'instalment' => str_contains(
                strtolower($charge['description']),
                'poa 1'
            ) ? 1 : (
                str_contains(strtolower($charge['description']), 'poa 2')
                    ? 2
                    : null
            ),
            'due_date' => $dueDate,
            'days_until_due' => $dueDate
                ? now()->startOfDay()->diffInDays(
                    Carbon::parse($dueDate)->startOfDay(),
                    false
                )
                : null,
            'original_amount' => $original,
            'paid_amount' => max(0, $original - $outstanding),
            'outstanding_amount' => $outstanding,
            'status' => $status,
            'is_estimate' => (bool) $charge['is_estimate'],
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Common responses
    |--------------------------------------------------------------------------
    */
    private function notAuthorised():
        JsonResponse
    {
        return response()->json([
            'success' => false,

            'message' =>
                'AppTax is not authorised with HMRC for this customer.',
        ], 403);
    }


    private function exception(
        \Throwable $e
    ): JsonResponse {

        report($e);

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 422);
    }
    
    public function dashboard(
        Request $request,
        HmrcService $hmrc,
        HmrcQuarterDashboard $quarterDashboard,
        HmrcTaxAndPaymentsDashboard $taxAndPaymentsDashboard
    ): JsonResponse
    {
        $validated = $request->validate([
            'business_id' => ['nullable', 'string'],
            'type_of_business' => ['nullable', 'in:self-employment,uk-property,foreign-property'],
            'tax_year' => ['nullable', 'regex:/^20\d{2}-\d{2}$/', function ($attribute, $value, $fail) {
                $startYear = (int) substr((string) $value, 0, 4);
                if (substr((string) ($startYear + 1), -2) !== substr((string) $value, -2)) {
                    $fail('Use consecutive tax years, for example 2026-27.');
                }
            }],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'in:open,fulfilled'],
            'reporting_period' => ['nullable', 'in:standard,calendar'],
        ]);
        $authorisation = $this->authorisation();
        if (!$authorisation) {
            return $this->notAuthorised();
        }

        $date = \Carbon\CarbonImmutable::parse($validated['date'] ?? 'now')->startOfDay();
        if (!empty($validated['tax_year'])) {
            $year = (int) substr($validated['tax_year'], 0, 4);
            $taxYearStart = \Carbon\CarbonImmutable::create($year, 4, 6)->startOfDay();
        } else {
            // Backward compatibility: infer the assessment year from the existing date input.
            $taxYearStart = $date->setDate($date->year, 4, 6);
            if ($date->lt($taxYearStart)) {
                $taxYearStart = $taxYearStart->subYear();
            }
            $year = $taxYearStart->year;
        }
        $taxYearEnd = $taxYearStart->addYear()->subDay();

        $taxYear = $year . '-' . substr((string) ($year + 1), -2);
        $fraudHeaders = $this->fraudHeaders($request);
        // HMRC permits at most 732 days. Two complete assessment years include the
        // selected year's POAs and the following year's first POA without exceeding it.
        $financialFromDate = $taxYearStart->toDateString();
        $financialToDate = $taxYearStart->addYears(2)->subDay()->toDateString();
        // Payment history cannot contain future allocations. A past assessment year still
        // includes allocations made up to its second following-year POA deadline.
        $today = \Carbon\CarbonImmutable::now()->startOfDay();
        $paymentsToDate = $today->lt($taxYearStart)
            ? null
            : min($financialToDate, $today->toDateString());
        $availability = [
            'obligations' => true,
            'mtd_status' => true,
            'account' => true,
            'payments' => true,
        ];
        $errors = [];

        try {
            try {
                $data = $hmrc->getObligations(
                    $authorisation->client_id,
                    $validated['business_id'] ?? null,
                    $validated['type_of_business'] ?? null,
                    $taxYearStart->toDateString(),
                    $taxYearEnd->toDateString(),
                    $validated['status'] ?? null,
                    $fraudHeaders
                );
            } catch (\Throwable $e) {
                report($e);
                $availability['obligations'] = false;
                $errors['obligations'] = $e->getMessage();
                $data = ['obligations' => []];
            }

            try {
                $mtdStatus = $hmrc->getMtdCustomerStatus(
                    $authorisation->client_id,
                    $taxYear,
                    $fraudHeaders
                );
            } catch (\Throwable $e) {
                report($e);
                $availability['mtd_status'] = false;
                $errors['mtd_status'] = $e->getMessage();
                $mtdStatus = null;
            }

            try {
                $account = $hmrc->getSelfAssessmentBalanceAndTransactions(
                    $authorisation->client_id,
                    $financialFromDate,
                    $financialToDate,
                    false,
                    true,
                    $fraudHeaders
                );
            } catch (\Throwable $e) {
                report($e);
                $availability['account'] = false;
                $errors['account'] = $e->getMessage();
                $account = [];
            }

            try {
                $payments = $paymentsToDate === null
                    ? []
                    : $hmrc->getSelfAssessmentPaymentsAndAllocations(
                        $authorisation->client_id,
                        $financialFromDate,
                        $paymentsToDate,
                        $fraudHeaders
                    );
            } catch (\Throwable $e) {
                report($e);
                $availability['payments'] = false;
                $errors['payments'] = $e->getMessage();
                $payments = [];
            }

            $groups = $data['obligations'] ?? [];
            if (!$groups && !empty($validated['business_id'])) {
                $groups = [[
                    'businessId' => $validated['business_id'],
                    'typeOfBusiness' => $validated['type_of_business'] ?? null,
                    'obligationDetails' => [],
                ]];
            }
            $progress = $quarterDashboard->format(
                $groups,
                $year,
                $date,
                $validated['reporting_period'] ?? 'standard'
            );
            $taxResult = app(TaxCalculationController::class)->taxyeartodate(
                'cash',
                $year . '-' . ($year + 1),
                auth()->id()
            );
            $figures = $taxResult['data'] ?? [];
            $estimatedLiability = (float) ($figures['totaltax'] ?? 0);
            $taxAndPayments = $taxAndPaymentsDashboard->format(
                $account,
                $payments,
                $estimatedLiability,
                $taxYear,
                $date
            );
            if (!$availability['account']) {
                $taxAndPayments['scenario'] = 'data_unavailable';
                $taxAndPayments['scenario_code'] = null;
                $taxAndPayments['previous_poa'] = null;
                $taxAndPayments['balancing_position'] = null;
                $taxAndPayments['estimated_credit'] = null;
                $taxAndPayments['total_remaining'] = null;
            }

            $quarters = $progress['quarters'] ?? [];
            $fulfilled = collect($quarters)->where('status', 'fulfilled')->count();
            $total = count($quarters);
            $next = $progress['next_obligation'] ?? null;

            return response()->json([
                'success' => true,
                'data' => array_merge($progress, [
                    // Existing dashboard contract. Do not rename or remove these fields.
                    'tax_year' => $taxYear,
                    'tax_year_range' => $taxYearStart->toDateString() . ' - ' . $taxYearEnd->toDateString(),
                    'assessment_year' => $taxYearStart->format('j M Y') . ' - ' . $taxYearEnd->format('j M Y'),
                    'as_of_date' => $date->toDateString(),
                    'status_filter' => $validated['status'] ?? null,
                    'tax_details' => $figures,

                    // Additive data for the enhanced mobile dashboard.
                    'dashboard_extensions' => [
                        'connection' => [
                            'connected' => true,
                            'status' => $authorisation->status,
                            'environment' => config('services.hmrc.environment'),
                        ],
                        'business' => [
                            'business_id' => $validated['business_id'] ?? $authorisation->businessId,
                            'type_of_business' => $validated['type_of_business'] ?? $authorisation->typeOfBusiness,
                            'trading_type' => $authorisation->tradingType,
                            'trading_name' => $authorisation->tradingName,
                        ],
                        'mtd_status' => $mtdStatus,
                        'financial_period' => [
                            'from_date' => $financialFromDate,
                            'to_date' => $financialToDate,
                            'payments_to_date' => $paymentsToDate,
                        ],
                        'next_due' => $next ? [
                            'quarter' => $next['quarter'],
                            'period_start' => $next['start'],
                            'period_end' => $next['end'],
                            'due_date' => $next['dueDate'],
                            'due_date_formatted' => $next['due_date_formated'],
                            'due_in_days' => $next['due_in_days'],
                            'overdue' => $next['overdue'],
                        ] : null,
                        'year_progress' => [
                            'submitted' => $fulfilled,
                            'total' => $total,
                            'percentage' => $total > 0 ? (int) round(($fulfilled / $total) * 100) : 0,
                        ],
                        'figures' => [
                            'income' => (float) ($figures['income'] ?? 0),
                            'expenses' => (float) ($figures['expenses'] ?? 0),
                            'profit' => (float) ($figures['profit'] ?? 0),
                            'estimated_income_tax' => (float) ($figures['taxfortheperiod'] ?? 0),
                            'estimated_class_4_ni' => (float) ($figures['national_insurance'] ?? 0),
                            'estimated_tax_and_ni' => $estimatedLiability,
                            'personal_allowance' => (float) ($figures['personal_allowance'] ?? 0),
                            'taxable_profit' => (float) ($figures['taxableprofit'] ?? 0),
                            'source' => 'local_estimate',
                        ],
                        'account_balances' => $availability['account']
                            ? $taxAndPaymentsDashboard->balances($account) : null,
                        'tax_and_payments' => $taxAndPayments,
                        'data_availability' => $availability,
                        'partial' => in_array(false, $availability, true),
                        'errors' => $errors,
                    ],
                ]),
            ]);
        } catch (\Throwable $e) {
            return $this->exception($e);
        }
    }
}
