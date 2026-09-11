<?php

namespace App\Http\Controllers;

use App\Models\HmrcClientAuthorisation;
use App\Services\HmrcService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class HmrcMtdController extends Controller
{
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
    |
    | TODO:
    | Replace this before production with your HMRC fraud-header builder.
    |
    */
    private function fraudHeaders(Request $request): array {
        return [];
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
    public function submitQuarterlyUpdate(Request $request,HmrcService $hmrc): JsonResponse {

        $validated = $request->validate([
            'business_id' => [
                'required',
                'string',
            ],

            'tax_year' => [
                'required',
                'regex:/^20\d{2}-\d{2}$/',
            ],

            'payload' => [
                'required',
                'array',
            ],
        ]);

        $authorisation = $this->authorisation();

        if (!$authorisation) {
            return $this->notAuthorised();
        }

        try {

            $hmrc->submitQuarterlyUpdate(
                $authorisation->client_id,
                $validated['business_id'],
                $validated['tax_year'],
                $validated['payload'],
                $this->fraudHeaders($request)
            );

            return response()->json([
                'success' => true,
                'message' => 'Quarterly update submitted successfully to HMRC.',
            ]);

        } catch (\Throwable $e) {
            return $this->exception($e);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | 6. Annual Self Employment submission
    |--------------------------------------------------------------------------
    */
    public function submitAnnualSubmission(Request $request,HmrcService $hmrc): JsonResponse {

        $validated = $request->validate([
            'business_id' => [
                'required',
                'string',
            ],

            'tax_year' => [
                'required',
                'regex:/^20\d{2}-\d{2}$/',
            ],

            'payload' => [
                'required',
                'array',
            ],
        ]);

        $authorisation =
            $this->authorisation();

        if (!$authorisation) {
            return $this->notAuthorised();
        }

        try {

            $hmrc->submitAnnualSubmission(
                $authorisation->client_id,
                $validated['business_id'],
                $validated['tax_year'],
                $validated['payload'],
                $this->fraudHeaders($request)
            );

            return response()->json([
                'success' => true,

                'message' =>
                    'Annual Self Employment information submitted successfully.',
            ]);

        } catch (\Throwable $e) {
            return $this->exception($e);
        }
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
    
    public function dashboard(Request $request,HmrcService $hmrc): JsonResponse
    {
        $validated = $request->validate([
            'business_id' => [
                'nullable',
                'string',
            ],

            'type_of_business' => [
                'nullable',
                'in:self-employment,uk-property,foreign-property',
            ],
            'date' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            
            'status' => [
                'nullable',
                'in:open,fulfilled',
            ],
        ]);

        $authorisation = $this->authorisation();

        if (!$authorisation) {
            return $this->notAuthorised();
        }

        $date = $validated['date'] ? Carbon::parse($validated['date']) : now();

        $year = $date->year;

        // Determine tax year start
        $taxYearStart = Carbon::create($year, 4, 6)->startOfDay();

        if ($date->lt($taxYearStart)) {
            $taxYearStart = Carbon::create($year - 1, 4, 6)->startOfDay();
        }

        $taxYearEnd = Carbon::create($year, 4, 5)->endOfDay();

        if ($date->gt($taxYearEnd)) {
            $taxYearEnd = Carbon::create($year + 1, 4, 5)->endOfDay();
        }

        //echo "Tax Year Start: " . $taxYearStart->toDateString() . "\n";
        //echo "Tax Year End: " . $taxYearEnd->toDateString() . "\n";

        $taxYear = $taxYearStart->year;
        $data = $hmrc->getObligations(
            $authorisation->client_id,
            $validated['business_id']
                ?? null,

            $validated['type_of_business']
                ?? null,

            $taxYearStart->toDateString()
                ?? null,

            $taxYearEnd->toDateString()
                ?? null,

            $validated['status']
                ?? null,

            $this->fraudHeaders($request)
        );
        
        // $quarters = [
        //     1 => [
        //         'start' => Carbon::create($taxYear, 4, 6),
        //         'end'   => Carbon::create($taxYear, 7, 5),
        //     ],
        //     2 => [
        //         'start' => Carbon::create($taxYear, 7, 6),
        //         'end'   => Carbon::create($taxYear, 10, 5),
        //     ],
        //     3 => [
        //         'start' => Carbon::create($taxYear, 10, 6),
        //         'end'   => Carbon::create($taxYear + 1, 1, 5),
        //     ],
        //     4 => [
        //         'start' => Carbon::create($taxYear + 1, 1, 6),
        //         'end'   => Carbon::create($taxYear + 1, 4, 5),
        //     ],
        // ];
        $quarters = $data['obligations'][0]['obligationDetails'];
        $quarters[0]['periodStartDate']  = str_replace('2018', '2026', $quarters[0]['periodStartDate']);
        $quarters[0]['periodStartDate']  = str_replace('2019', '2027', $quarters[0]['periodStartDate']);
        $quarters[3]['periodEndDate']  = str_replace('2019', '2027', $quarters[3]['periodEndDate']);
        $quarters[3]['periodEndDate']  = str_replace('2018', '2026', $quarters[3]['periodEndDate']);
        
        $assessmentYear = Carbon::parse($quarters[0]['periodStartDate'])->format('j M Y') . '-' . Carbon::parse($quarters[3]['periodEndDate'])->format('j M Y');
        $duedate = null;
        $currentquarter = null;
        $dueInDays = null;
        foreach ($quarters as $key => $quarter) {
            $quarter['periodStartDate']  = str_replace('2018', '2026', $quarter['periodStartDate']);
            $quarter['periodStartDate']  = str_replace('2019', '2027', $quarter['periodStartDate']);
            $quarter['periodEndDate']  = str_replace('2019', '2027', $quarter['periodEndDate']);
            $quarter['periodEndDate']  = str_replace('2018', '2026', $quarter['periodEndDate']);
            
            $quarter['dueDate']  = str_replace('2019', '2027', $quarter['dueDate']);
            $quarter['dueDate']  = str_replace('2018', '2026', $quarter['dueDate']);
            if(isset($quarter['receivedDate'])){
            $quarter['receivedDate']  = str_replace('2019', '2027', $quarter['receivedDate']);
            $quarter['receivedDate']  = str_replace('2018', '2026', $quarter['receivedDate']);
            }
            
            if ($date->betweenIncluded($quarter['periodStartDate'], $quarter['periodEndDate'])) {
                $quarters[$key]['current'] = true;
                $duedate = Carbon::parse($quarter['periodEndDate'])->copy()->addMonth()->format('j M Y');
                $dueInDays = (int) now()->startOfDay()->diffInDays(
                    Carbon::parse($duedate)->copy()->startOfDay(),
                    false
                );
                $currentquarter = 'Quarter ' . $key+1;
            } else {
                $quarters[$key]['current'] = false;
            }

            $quarters[$key]['start'] = Carbon::parse($quarter['periodStartDate'])->format('Y-m-d');
            $quarters[$key]['end'] = Carbon::parse($quarter['periodEndDate'])->format('Y-m-d');
            $quarters[$key]['quarter'] = 'Q' . $key+1;
            $quarters[$key]['quarter_range'] = Carbon::parse($quarter['periodStartDate'])->format('j M') . ' - ' . Carbon::parse($quarter['periodEndDate'])->format('j M Y');
            if(isset($quarter['receivedDate'])){
                $quarters[$key]['received_date_formated'] = Carbon::parse($quarter['receivedDate'])->format('j M Y');
            }else{
                $quarters[$key]['received_date_formated'] = null;
            }
            $quarters[$key]['due_date_formated'] = Carbon::parse($quarter['dueDate'])->format('j M Y');

        }
        $taxController = app(\App\Http\Controllers\TaxCalculationController::class);
        $TaxDetails = $taxController->taxyeartodate('cash', $year, auth()->id());

        return response()->json([
            'success' => true,
            'data' => [
                'tax_year' => $taxYear . '-' . substr($taxYear + 1, 2),
                'quarters' => $quarters,
                'due_date' => $duedate,
                'due_in_days' => $dueInDays,
                'tax_year' => $taxYearStart->format('Y-m-d'). ' - '.Carbon::create($taxYear + 1, 4, 5)->format('Y-m-d'),
                'current_quarter' => $currentquarter,
                'assessment_year' => $assessmentYear,
                'tax_details' => $TaxDetails['data'],
            ],
        ]);

    }
}