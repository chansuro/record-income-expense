<?php

namespace App\Services;

use App\Models\HmrcAgentConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Illuminate\Http\Client\Response;

class HmrcService
{
    protected string $baseUrl;
    protected string $arn;

    public function __construct()
    {
        $this->baseUrl = rtrim(
            config('services.hmrc.base_url'),
            '/'
        );

        $this->arn = config('services.hmrc.arn');
    }


    /*
    |--------------------------------------------------------------------------
    | Get current HMRC connection
    |--------------------------------------------------------------------------
    */
    public function connection(): HmrcAgentConnection
    {
        $connection = HmrcAgentConnection::where(
            'environment',
            config('services.hmrc.environment')
        )
            ->where('is_active', true)
            ->first();

        if (!$connection) {
            throw new RuntimeException(
                'AppTax is not connected to HMRC.'
            );
        }

        return $connection;
    }


    /*
    |--------------------------------------------------------------------------
    | Get valid access token
    |--------------------------------------------------------------------------
    */
    public function getAccessToken(): string
    {
        $connection = $this->connection();

        /*
         * Refresh slightly before actual expiry.
         */
        if (
            $connection->expires_at &&
            $connection->expires_at->gt(now()->addMinutes(2))
        ) {
            return $connection->access_token;
        }
        Log::info("Access token is not valid, refreshing...");
        return $this->refreshAccessToken();
    }


    /*
    |--------------------------------------------------------------------------
    | Refresh HMRC access token
    |--------------------------------------------------------------------------
    */
    public function refreshAccessToken(): string
    {
        $lock = Cache::lock(
            'hmrc-agent-token-refresh-' .
            config('services.hmrc.environment'),
            20
        );

        return $lock->block(10, function () {

            /*
             * Re-read connection because another request
             * may have refreshed the token already.
             */
            $connection = $this->connection()->fresh();

            if (
                $connection->expires_at &&
                $connection->expires_at->gt(now()->addMinutes(2))
            ) {
                return $connection->access_token;
            }

            $response = Http::asForm()
                ->timeout(30)
                ->post(
                    $this->baseUrl . '/oauth/token',
                    [
                        'client_id' =>
                            config('services.hmrc.client_id'),

                        'client_secret' =>
                            config('services.hmrc.client_secret'),

                        'grant_type' =>
                            'refresh_token',

                        'refresh_token' =>
                            $connection->refresh_token,
                    ]
                );

            if ($response->failed()) {
                throw new RuntimeException(
                    'Unable to refresh HMRC access token: ' .
                    $response->body()
                );
            }

            $token = $response->json();

            $connection->update([
                'access_token' =>
                    $token['access_token'],

                'refresh_token' =>
                    $token['refresh_token'],

                'expires_at' =>
                    now()->addSeconds(
                        (int) $token['expires_in']
                    ),

                'last_refreshed_at' =>
                    now(),
            ]);

            return $connection->fresh()->access_token;
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Create client authorisation invitation
    |--------------------------------------------------------------------------
    */
    public function createClientInvitation(string $nino,string $postcode,string $agentType = 'main'): array {

            $token = $this->getAccessToken();

            $url = $this->baseUrl
                . '/agents/'
                . urlencode($this->arn)
                . '/invitations';

            $response = Http::withToken($token)
                ->withHeaders([
                    'Accept' => 'application/vnd.hmrc.2.0+json',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(30)
                ->post($url, [
                    'service' => 'MTD-IT',
                    'clientType' => 'personal',
                    'clientIdType' => 'ni',

                    'clientId' => strtoupper(
                        str_replace(' ', '', $nino)
                    ),

                    'knownFact' => strtoupper(
                        trim($postcode)
                    ),

                    'agentType' => $agentType,
                ]);


            /*
            |--------------------------------------------------------------------------
            | New invitation successfully created
            |--------------------------------------------------------------------------
            */
            if ($response->status() === 204) {

                $location = $response->header('Location');

                if (!$location) {
                    throw new RuntimeException(
                        'HMRC created the invitation but did not return its location.'
                    );
                }

                $invitationId = basename(
                    parse_url($location, PHP_URL_PATH)
                );

                $invitation = $this->getInvitation(
                    $invitationId
                );

                return [
                    'invitation_id' => $invitationId,

                    'status' =>
                        $invitation['status'] ?? 'Pending',

                    'client_action_url' =>
                        $invitation['clientActionUrl'] ?? null,

                    'expires_on' =>
                        $invitation['expiresOn'] ?? null,

                    'agent_type' =>
                        $invitation['agentType'] ?? $agentType,

                    'is_duplicate' => false,
                ];
            }


            /*
            |--------------------------------------------------------------------------
            | Existing pending invitation
            |--------------------------------------------------------------------------
            |
            | HMRC API v2 returns 403 DUPLICATE_AUTHORISATION_REQUEST
            | and provides the existing invitation URL in Location.
            |
            */
            if (
                $response->status() === 403 &&
                $response->json('code') === 'DUPLICATE_AUTHORISATION_REQUEST'
            ) {

                $location = $response->header('Location');

                if (!$location) {
                    throw new RuntimeException(
                        'HMRC found an existing authorisation request but did not return its location.'
                    );
                }

                $invitationId = basename(
                    parse_url($location, PHP_URL_PATH)
                );

                $invitation = $this->getInvitation(
                    $invitationId
                );

                return [
                    'invitation_id' => $invitationId,

                    'status' =>
                        $invitation['status'] ?? 'Pending',

                    'client_action_url' =>
                        $invitation['clientActionUrl'] ?? null,

                    'expires_on' =>
                        $invitation['expiresOn'] ?? null,

                    'agent_type' =>
                        $invitation['agentType'] ?? $agentType,

                    'is_duplicate' => true,
                ];
            }


        /*
        |--------------------------------------------------------------------------
        | Other HMRC errors
        |--------------------------------------------------------------------------
        */
        \Log::error('HMRC Create Invitation Failed', [
            'status' => $response->status(),
            'code' => $response->json('code'),
            'message' => $response->json('message'),
        ]);

        throw new RuntimeException(
            $response->json('message')
                ?? 'Unable to create HMRC authorisation request.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Get invitation
    |--------------------------------------------------------------------------
    */
    public function getInvitation(string $invitationId): array {

        $token = $this->getAccessToken();

        $url = $this->baseUrl
            . '/agents/'
            . urlencode($this->arn)
            . '/invitations/'
            . urlencode($invitationId);

        $response = Http::withToken($token)
            ->withHeaders([
                'Accept' => 'application/vnd.hmrc.2.0+json',
            ])
            ->timeout(30)
            ->get($url);

        if ($response->failed()) {

            \Log::error('HMRC Get Invitation Failed', [
                'status' => $response->status(),
                'code' => $response->json('code'),
                'message' => $response->json('message'),
            ]);

            throw new RuntimeException(
                $response->json('message')
                    ?? 'Unable to retrieve HMRC invitation.'
            );
        }

        return $response->json();
    }

    public function acceptSandboxInvitation(string $invitationId): array
    {
        /*
        |--------------------------------------------------------------------------
        | Sandbox only
        |--------------------------------------------------------------------------
        */
        if (config('services.hmrc.environment') !== 'sandbox') {
            throw new \RuntimeException(
                'Sandbox invitation acceptance is not available in production.'
            );
        }

        $url = rtrim(config('services.hmrc.base_url'), '/')
            . '/agent-authorisation-test-support/invitations/'
            . urlencode($invitationId);

        /*
        |--------------------------------------------------------------------------
        | Accept invitation
        |--------------------------------------------------------------------------
        |
        | This HMRC Test Support endpoint is OPEN.
        | It does not require the agent bearer token.
        |
        */

        $response = Http::withHeaders([
            'Accept' => 'application/vnd.hmrc.1.0+json',
            'Content-Length' => '0',
        ])
            ->timeout(30)
            ->send('PUT', $url, [
                'body' => '',
            ]);

        if ($response->status() !== 204) {

            \Log::error('HMRC sandbox invitation acceptance failed', [
                'invitation_id' => $invitationId,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            if ($response->status() === 404) {
                throw new \RuntimeException(
                    'HMRC authorisation request was not found.'
                );
            }

            if ($response->status() === 409) {
                throw new \RuntimeException(
                    'HMRC authorisation request has already been rejected or has expired.'
                );
            }

            throw new \RuntimeException(
                $response->json('message')
                    ?? 'Unable to accept HMRC sandbox authorisation.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Ask normal Agent Authorisation API for actual status
        |--------------------------------------------------------------------------
        */

        return $this->getInvitation($invitationId);
    }

    /*
    |--------------------------------------------------------------------------
    | Common HMRC headers
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | $fraudHeaders is empty during basic sandbox development.
    |
    | Before production you must supply HMRC Fraud Prevention Headers.
    |
    */
    private function hmrcHeaders(
        string $version,
        array $fraudHeaders = []
    ): array {
        return array_merge(
            $fraudHeaders,
            [
                'Accept' => "application/vnd.hmrc.{$version}+json"
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Normalise NINO
    |--------------------------------------------------------------------------
    */
    private function normaliseNino(string $nino): string
    {
        return strtoupper(
            str_replace(' ', '', trim($nino))
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Handle HMRC API error
    |--------------------------------------------------------------------------
    */
    private function throwHmrcError(
        Response $response,
        string $action
    ): never {

        Log::error($action, [
            'status' => $response->status(),
            'code' => $response->json('code'),
            'message' => $response->json('message'),
            'body' => $response->body(),
            'correlation_id' =>
                $response->header('X-CorrelationId'),
        ]);

        throw new RuntimeException(
            $response->json('message')
            ?? $response->json('code')
            ?? ($action . ' failed. HTTP ' . $response->status())
        );
    }

     /*
    |--------------------------------------------------------------------------
    | 1. Check Agent / Client Relationship
    |--------------------------------------------------------------------------
    */
    public function checkRelationship(
        string $nino,
        string $postcode,
        string $agentType = 'main'
    ): bool {

        $token = $this->getAccessToken();

        $url = $this->baseUrl
            . '/agents/'
            . urlencode($this->arn)
            . '/relationships';

        $response = Http::withToken($token)
            ->withHeaders([
                'Accept' =>
                    'application/vnd.hmrc.2.0+json',

                'Content-Type' =>
                    'application/json',
            ])
            ->timeout(30)
            ->post($url, [
                'service' => 'MTD-IT',

                'clientIdType' => 'ni',

                'clientId' =>
                    $this->normaliseNino($nino),

                'knownFact' =>
                    strtoupper(trim($postcode)),

                'agentType' => $agentType,
            ]);

        /*
         * HMRC returns 204 when relationship exists.
         */
        if ($response->status() === 204) {
            return true;
        }

        /*
         * No active relationship
         */
        if (
            $response->status() === 404 &&
            $response->json('code')
                === 'RELATIONSHIP_NOT_FOUND'
        ) {
            return false;
        }

        $this->throwHmrcError(
            $response,
            'HMRC relationship check failed'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Get customer's MTD / ITSA status
    |--------------------------------------------------------------------------
    */
    public function getMtdCustomerStatus(
        string $nino,
        string $taxYear,
        array $fraudHeaders = []
    ): array {

        $token = $this->getAccessToken();

        $url = $this->baseUrl
            . '/individuals/person/itsa-status/'
            . urlencode($this->normaliseNino($nino))
            . '/'
            . urlencode($taxYear);
        $headers = $this->hmrcHeaders('2.0', $fraudHeaders);

        if (config('services.hmrc.environment') === 'sandbox') {
            $scenario = strtoupper(trim((string) config(
                'services.hmrc.itsa_status_test_scenario',
                'STATEFUL'
            )));

            if (!in_array($scenario, ['', 'DEFAULT', 'STATEFUL', 'NOT_FOUND', 'NOT_ENROLLED'], true)) {
                throw new RuntimeException('Invalid HMRC ITSA status test scenario configuration.');
            }

            if ($scenario !== '' && $scenario !== 'DEFAULT') {
                $headers['Gov-Test-Scenario'] = $scenario;
            }
        }

        $response = Http::withToken($token)
            ->withHeaders($headers)
            ->timeout(30)
            ->get($url);
        if ($response->failed()) {
            $this->throwHmrcError(
                $response,
                'Unable to retrieve MTD status'
            );
        }

        $data = $response->json();
        $returnedYears = array_column($data['itsaStatuses'] ?? [], 'taxYear');

        if (!in_array($taxYear, $returnedYears, true)) {
            // Log routing information only: never log NINOs, tokens or income data.
            Log::warning('HMRC ITSA status tax year mismatch', [
                'requested_tax_year' => $taxYear,
                'returned_tax_years' => $returnedYears,
                'environment' => config('services.hmrc.environment'),
                'hmrc_host' => parse_url($this->baseUrl, PHP_URL_HOST),
                'test_scenario' => $headers['Gov-Test-Scenario'] ?? 'DEFAULT',
                'correlation_id' => $response->header('X-CorrelationId'),
            ]);

            throw new RuntimeException(
                'HMRC returned ITSA status for ' . (implode(', ', $returnedYears) ?: 'no tax year')
                . ' instead of ' . $taxYear . '. '
                . (config('services.hmrc.environment') === 'sandbox'
                    ? 'Sandbox scenario sent: ' . ($headers['Gov-Test-Scenario'] ?? 'DEFAULT')
                        . '. Create test status for the logged-in customer using POST /api/hmrc/sandbox/itsa-status, '
                        . 'and verify the deployed configuration uses STATEFUL.'
                    : 'Check the HMRC response using the correlation ID in the application log.')
            );
        }

        return $data;
    }

    public function createSandboxItsaStatus(string $nino, string $taxYear, array $payload): void
    {
        // Guard here too, so callers outside the controller cannot use production.
        if (config('services.hmrc.environment') !== 'sandbox'
            || $this->baseUrl !== 'https://test-api.service.hmrc.gov.uk') {
            throw new RuntimeException('Test ITSA statuses require the HMRC sandbox environment and base URL.');
        }

        $response = Http::withToken($this->getAccessToken())
            ->withHeaders($this->hmrcHeaders('1.0'))
            ->timeout(30)
            ->post($this->baseUrl . '/individuals/self-assessment-test-support/itsa-status/'
                . urlencode($this->normaliseNino($nino)) . '/' . urlencode($taxYear), $payload);

        if ($response->status() !== 204) {
            $this->throwHmrcError($response, 'Unable to create HMRC sandbox ITSA status');
        }
    }

    public function createSandboxBusiness(string $nino, array $payload): array
    {
        if (config('services.hmrc.environment') !== 'sandbox'
            || $this->baseUrl !== 'https://test-api.service.hmrc.gov.uk') {
            throw new RuntimeException('Test businesses require the HMRC sandbox environment and base URL.');
        }
        $response = Http::withToken($this->getAccessToken())
            ->withHeaders($this->hmrcHeaders('1.0'))
            ->asJson()->withoutRedirecting()->timeout(30)
            ->post($this->baseUrl . '/individuals/self-assessment-test-support/business/'
                . urlencode($this->normaliseNino($nino)), $payload);
        if ($response->status() !== 201) {
            $this->throwHmrcError($response, 'Unable to create HMRC sandbox test business');
        }
        $businessId = $response->json('businessId');
        if (!is_string($businessId) || !preg_match('/^X[A-Z0-9]IS[0-9]{11}$/', $businessId)) {
            throw new RuntimeException('HMRC created the test business but returned an invalid business ID.');
        }
        return ['businessId' => $businessId,
            'correlationId' => $response->header('X-CorrelationId') ?: null];
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Get customer's HMRC businesses
    |--------------------------------------------------------------------------
    */
    public function getBusinessDetails(
        string $nino,
        array $fraudHeaders = []
    ): array {

        $token = $this->getAccessToken();

        $url = $this->baseUrl
            . '/individuals/business/details/'
            . urlencode($this->normaliseNino($nino))
            . '/list';

        $response = Http::withToken($token)
            ->withHeaders(
                $this->hmrcHeaders(
                    '2.0',
                    $fraudHeaders
                )
            )
            ->timeout(30)
            ->get($url);

        if ($response->failed()) {
            $this->throwHmrcError(
                $response,
                'Unable to retrieve HMRC businesses'
            );
        }

        return $response->json();
    }

     /*
    |--------------------------------------------------------------------------
    | 4. Get customer's obligations
    |--------------------------------------------------------------------------
    */
    public function getObligations(
        string $nino,
        ?string $businessId = null,
        ?string $typeOfBusiness = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $status = null,
        array $fraudHeaders = []
    ): array {

        $token = $this->getAccessToken();

        $url = $this->baseUrl
            . '/obligations/details/'
            . urlencode($this->normaliseNino($nino))
            . '/income-and-expenditure';

        $query = array_filter(
            [
                'businessId' => $businessId,

                'typeOfBusiness' =>
                    $typeOfBusiness,

                'fromDate' => $fromDate,

                'toDate' => $toDate,

                'status' => $status,
            ],
            fn ($value) =>
                $value !== null &&
                $value !== ''
        );

        $headers = $this->hmrcHeaders('3.0', $fraudHeaders);
        if (config('services.hmrc.environment') === 'sandbox') {
            $scenario = strtoupper(trim((string) config('services.hmrc.obligations_test_scenario', 'DYNAMIC')));
            if (!in_array($scenario, ['', 'DEFAULT', 'DYNAMIC', 'CUMULATIVE', 'OPEN', 'FULFILLED',
                'INSOLVENT_TRADER', 'NOT_FOUND', 'NO_OBLIGATIONS_FOUND'], true)) {
                throw new RuntimeException('Invalid HMRC obligations test scenario. Use DYNAMIC, not STATEFUL, for date-based sandbox testing.');
            }
            if ($scenario !== '' && $scenario !== 'DEFAULT') {
                $headers['Gov-Test-Scenario'] = $scenario;
            }
        }

        $response = Http::withToken($token)
            ->withHeaders($headers)
            ->timeout(30)
            ->get($url, $query);

        if ($response->failed()) {
            $this->throwHmrcError(
                $response,
                'Unable to retrieve HMRC obligations'
            );
        }

        return $response->json();
    }

     /*
    |--------------------------------------------------------------------------
    | 5. Submit / amend cumulative quarterly update
    |--------------------------------------------------------------------------
    |
    | For tax year 2025-26 onwards.
    |
    */
    public function quarterlyTestScenario(): ?string
    {
        if (config('services.hmrc.environment') !== 'sandbox') {
            return null;
        }
        $scenario = strtoupper(trim((string) config('services.hmrc.quarterly_test_scenario', 'STATEFUL')));
        if (!in_array($scenario, ['STATEFUL', 'DEFAULT', 'NOT_FOUND', 'TAX_YEAR_NOT_SUPPORTED', 'BOTH_EXPENSES_SUPPLIED'], true)) {
            throw new RuntimeException('Invalid HMRC quarterly test scenario configuration.');
        }
        return $scenario;
    }

    public function submitQuarterlyUpdate(
        string $nino,
        string $businessId,
        string $taxYear,
        array $payload,
        array $fraudHeaders = []
    ): array {
        $environment = config('services.hmrc.environment');
        $expectedUrl = ['sandbox' => 'https://test-api.service.hmrc.gov.uk',
            'production' => 'https://api.service.hmrc.gov.uk'][$environment] ?? null;
        if ($expectedUrl === null || $this->baseUrl !== $expectedUrl) {
            throw new RuntimeException('HMRC environment and base URL do not match.');
        }
        if ($environment === 'production') {
            app(HmrcFraudHeaders::class)->assertComplete($fraudHeaders);
        }
        $headers = $this->hmrcHeaders('5.0', $fraudHeaders);
        unset($headers['Gov-Test-Scenario']);
        $scenario = $this->quarterlyTestScenario();
        if ($scenario !== null && $scenario !== 'DEFAULT') {
            $headers['Gov-Test-Scenario'] = $scenario;
        }
        $url = $this->baseUrl . '/individuals/business/self-employment/'
            . urlencode($this->normaliseNino($nino)) . '/' . urlencode($businessId)
            . '/cumulative/' . urlencode($taxYear);
        $token = $this->getAccessToken();
        try {
            // Never retry or follow redirects for a write whose outcome might be unknown.
            $response = Http::withToken($token)->withHeaders($headers)->asJson()
                ->withoutRedirecting()->timeout(30)->put($url, $payload);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new \App\Exceptions\HmrcSubmissionException(
                'HMRC submission outcome is unknown after a connection failure. Reconcile with HMRC before resubmitting.'
            );
        }
        $correlationId = $response->header('X-CorrelationId') ?: null;
        if ($response->status() !== 204) {
            $rejected = $response->clientError();
            throw new \App\Exceptions\HmrcSubmissionException(
                $rejected ? ($response->json('message') ?? 'HMRC rejected the quarterly update.')
                    : 'HMRC did not return the expected 204 response. Reconcile the submission before resubmitting.',
                $response->status(), $response->json('code'), $correlationId,
                $rejected ? 'rejected' : 'unknown'
            );
        }
        return ['hmrc_http_status' => 204, 'correlation_id' => $correlationId,
            'test_scenario' => $scenario, 'environment' => $environment,
            'status' => $scenario !== null && $scenario !== 'STATEFUL' ? 'simulated' : 'accepted'];
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Create / amend Self Employment annual submission
    |--------------------------------------------------------------------------
    */
    public function annualTestScenario(): ?string
    {
        if (config('services.hmrc.environment') !== 'sandbox') {
            return null;
        }
        $scenario = strtoupper(trim((string) config('services.hmrc.annual_test_scenario', 'STATEFUL')));
        if (!in_array($scenario, ['STATEFUL', 'DEFAULT', 'ALLOWANCE_NOT_SUPPORTED', 'NOT_FOUND',
            'WRONG_TPA_AMOUNT_SUBMITTED', 'OUTSIDE_AMENDMENT_WINDOW'], true)) {
            throw new RuntimeException('Invalid HMRC annual test scenario configuration.');
        }
        return $scenario;
    }

    public function submitAnnualSubmission(
        string $nino,
        string $businessId,
        string $taxYear,
        array $payload,
        array $fraudHeaders = []
    ): array {
        $environment = config('services.hmrc.environment');
        $expectedUrl = ['sandbox' => 'https://test-api.service.hmrc.gov.uk',
            'production' => 'https://api.service.hmrc.gov.uk'][$environment] ?? null;
        if ($expectedUrl === null || $this->baseUrl !== $expectedUrl) {
            throw new RuntimeException('HMRC environment and base URL do not match.');
        }
        if ($environment === 'production') {
            app(HmrcFraudHeaders::class)->assertComplete($fraudHeaders);
        }
        $headers = $this->hmrcHeaders('5.0', $fraudHeaders);
        unset($headers['Gov-Test-Scenario']);
        $scenario = $this->annualTestScenario();
        if ($scenario !== null && $scenario !== 'DEFAULT') {
            $headers['Gov-Test-Scenario'] = $scenario;
        }
        $url = $this->baseUrl . '/individuals/business/self-employment/'
            . urlencode($this->normaliseNino($nino)) . '/' . urlencode($businessId)
            . '/annual/' . urlencode($taxYear);
        try {
            $response = Http::withToken($this->getAccessToken())->withHeaders($headers)->asJson()
                ->withoutRedirecting()->timeout(30)->put($url, $payload);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new \App\Exceptions\HmrcSubmissionException(
                'HMRC annual submission outcome is unknown after a connection failure. Reconcile with HMRC before resubmitting.'
            );
        }
        $correlationId = $response->header('X-CorrelationId') ?: null;
        if ($response->status() !== 204) {
            $rejected = $response->clientError();
            throw new \App\Exceptions\HmrcSubmissionException(
                $rejected ? ($response->json('message') ?? 'HMRC rejected the annual submission.')
                    : 'HMRC did not return the expected 204 response. Reconcile the submission before resubmitting.',
                $response->status(), $response->json('code'), $correlationId,
                $rejected ? 'rejected' : 'unknown'
            );
        }
        return ['hmrc_http_status' => 204, 'correlation_id' => $correlationId,
            'test_scenario' => $scenario, 'environment' => $environment,
            'status' => $scenario !== null && $scenario !== 'STATEFUL' ? 'simulated' : 'accepted'];
    }

    /*
    |--------------------------------------------------------------------------
    | 7. Trigger Self Assessment calculation
    |--------------------------------------------------------------------------
    |
    | Examples:
    |
    | in-year
    | intent-to-finalise
    | intent-to-amend
    |
    */
    public function triggerTaxCalculation(
        string $nino,
        string $taxYear,
        string $calculationType = 'in-year',
        array $fraudHeaders = []
    ): array {

        $allowed = [
            'in-year',
            'intent-to-finalise',
            'intent-to-amend',
        ];

        if (!in_array(
            $calculationType,
            $allowed,
            true
        )) {
            throw new RuntimeException(
                'Invalid HMRC calculation type.'
            );
        }

        $token = $this->getAccessToken();

        $url = $this->baseUrl
            . '/individuals/calculations/'
            . urlencode($this->normaliseNino($nino))
            . '/self-assessment/'
            . urlencode($taxYear)
            . '/trigger/'
            . urlencode($calculationType);

        $response = Http::withToken($token)
            ->withHeaders(
                $this->hmrcHeaders(
                    '8.0',
                    $fraudHeaders
                )
            )
            ->timeout(30)
            ->send(
                'POST',
                $url,
                [
                    'body' => '',
                ]
            );

        if ($response->failed()) {
            $this->throwHmrcError(
                $response,
                'Unable to trigger HMRC tax calculation'
            );
        }

        return $response->json();
    }

     /*
    |--------------------------------------------------------------------------
    | 8. Retrieve Tax Calculation
    |--------------------------------------------------------------------------
    */
    public function retrieveTaxCalculation(
        string $nino,
        string $taxYear,
        string $calculationId,
        array $fraudHeaders = []
    ): array {

        $token = $this->getAccessToken();

        $url = $this->baseUrl
            . '/individuals/calculations/'
            . urlencode($this->normaliseNino($nino))
            . '/self-assessment/'
            . urlencode($taxYear)
            . '/'
            . urlencode($calculationId);

        $response = Http::withToken($token)
            ->withHeaders(
                $this->hmrcHeaders(
                    '8.0',
                    $fraudHeaders
                )
            )
            ->timeout(30)
            ->get($url);

        if ($response->failed()) {
            $this->throwHmrcError(
                $response,
                'Unable to retrieve HMRC tax calculation'
            );
        }

        return $response->json();
    }


    /*
    |--------------------------------------------------------------------------
    | 9. Submit Final Declaration
    |--------------------------------------------------------------------------
    */
    public function submitFinalDeclaration(
        string $nino,
        string $taxYear,
        string $calculationId,
        array $fraudHeaders = []
    ): bool {

        $token = $this->getAccessToken();

        $url = $this->baseUrl
            . '/individuals/calculations/'
            . urlencode($this->normaliseNino($nino))
            . '/self-assessment/'
            . urlencode($taxYear)
            . '/'
            . urlencode($calculationId)
            . '/confirm-calculation';

        $response = Http::withToken($token)
            ->withHeaders(
                $this->hmrcHeaders(
                    '8.0',
                    $fraudHeaders
                )
            )
            ->timeout(30)
            ->send(
                'POST',
                $url,
                [
                    'body' => '',
                ]
            );

        if ($response->failed()) {
            $this->throwHmrcError(
                $response,
                'Unable to submit HMRC final declaration'
            );
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | 10. Retrieve Self Assessment balance and transactions
    |--------------------------------------------------------------------------
    |
    | Returns HMRC's official overdue, payable and pending balances together
    | with charge details, including Payments on Account when present.
    |
    */
    public function getSelfAssessmentBalanceAndTransactions(
        string $nino,
        ?string $fromDate = null,
        ?string $toDate = null,
        bool $onlyOpenItems = false,
        bool $includeEstimatedCharges = true,
        array $fraudHeaders = []
    ): array {
        if (($fromDate === null) !== ($toDate === null)) {
            throw new RuntimeException(
                'Both from date and to date must be supplied together.'
            );
        }

        $token = $this->getAccessToken();

        $url = $this->baseUrl
            . '/accounts/self-assessment/'
            . urlencode($this->normaliseNino($nino))
            . '/balance-and-transactions';

        $query = [
            'onlyOpenItems' => $onlyOpenItems ? 'true' : 'false',
            // Explicitly keep POA charges in the response.
            'removePOA' => 'false',
            'includeEstimatedCharges' =>
                $includeEstimatedCharges ? 'true' : 'false',
        ];

        if ($fromDate !== null && $toDate !== null) {
            $query['fromDate'] = $fromDate;
            $query['toDate'] = $toDate;
        }

        $response = Http::withToken($token)
            ->withHeaders(
                $this->hmrcHeaders('4.0', $fraudHeaders)
            )
            ->timeout(30)
            ->get($url, $query);

        if ($response->failed()) {
            $this->throwHmrcError(
                $response,
                'Unable to retrieve HMRC Self Assessment account'
            );
        }

        return $response->json();
    }

    /*
    |--------------------------------------------------------------------------
    | 11. List Self Assessment payments and allocation details
    |--------------------------------------------------------------------------
    */
    public function getSelfAssessmentPaymentsAndAllocations(
        string $nino,
        ?string $fromDate = null,
        ?string $toDate = null,
        array $fraudHeaders = []
    ): array {
        if (($fromDate === null) !== ($toDate === null)) {
            throw new RuntimeException(
                'Both from date and to date must be supplied together.'
            );
        }

        $token = $this->getAccessToken();

        $url = $this->baseUrl
            . '/accounts/self-assessment/'
            . urlencode($this->normaliseNino($nino))
            . '/payments-and-allocations';

        $query = [];

        if ($fromDate !== null && $toDate !== null) {
            $query['fromDate'] = $fromDate;
            $query['toDate'] = $toDate;
        }

        $response = Http::withToken($token)
            ->withHeaders(
                $this->hmrcHeaders('4.0', $fraudHeaders)
            )
            ->timeout(30)
            ->get($url, $query);

        if ($response->failed()) {
            $this->throwHmrcError(
                $response,
                'Unable to retrieve HMRC payments and allocations'
            );
        }

        return $response->json();
    }

}
