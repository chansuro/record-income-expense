<?php

namespace Tests\Feature;

use App\Services\HmrcService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HmrcItsaStatusTest extends TestCase
{
    public function test_creates_stateful_sandbox_business_and_returns_id(): void
    {
        config(['services.hmrc.environment' => 'sandbox']);
        Http::fake(['*' => Http::response(['businessId' => 'XAIS12345678910'], 201,
            ['X-CorrelationId' => 'business-correlation'])]);
        $payload = ['typeOfBusiness' => 'self-employment', 'tradingType' => 'Taxi driver'];
        $result = $this->service()->createSandboxBusiness('aa 123456 a', $payload);
        $this->assertSame('XAIS12345678910', $result['businessId']);
        $this->assertSame('business-correlation', $result['correlationId']);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://test-api.service.hmrc.gov.uk/individuals/self-assessment-test-support/business/AA123456A'
            && $request->hasHeader('Accept', 'application/vnd.hmrc.1.0+json')
            && $request->data() === $payload);
    }

    public function test_business_creation_service_blocks_production(): void
    {
        config(['services.hmrc.environment' => 'production']);
        Http::fake();
        $this->expectException(\RuntimeException::class);
        try {
            $this->service()->createSandboxBusiness('AA123456A', []);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_obligations_sends_dynamic_scenario_and_date_filters(): void
    {
        config(['services.hmrc.environment' => 'sandbox',
            'services.hmrc.obligations_test_scenario' => 'DYNAMIC']);
        $body = ['obligations' => [['obligationDetails' => [['periodStartDate' => '2026-04-06',
            'periodEndDate' => '2027-04-05']]]]];
        Http::fake(['*' => Http::response($body)]);
        $this->assertSame($body, $this->service()->getObligations('AA123456A', 'XBIS12345678901',
            'self-employment', '2026-04-06', '2027-04-05', 'open'));
        Http::assertSent(function (Request $request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);
            return $request->hasHeader('Gov-Test-Scenario', 'DYNAMIC')
                && $request->hasHeader('Accept', 'application/vnd.hmrc.3.0+json')
                && $query === ['businessId' => 'XBIS12345678901', 'typeOfBusiness' => 'self-employment',
                    'fromDate' => '2026-04-06', 'toDate' => '2027-04-05', 'status' => 'open'];
        });
    }

    public function test_production_obligations_does_not_add_test_scenario(): void
    {
        config(['services.hmrc.environment' => 'production',
            'services.hmrc.obligations_test_scenario' => 'DYNAMIC']);
        Http::fake(['*' => Http::response(['obligations' => []])]);
        $this->service()->getObligations('AA123456A');
        Http::assertSent(fn (Request $request) => !$request->hasHeader('Gov-Test-Scenario'));
    }

    public function test_obligations_rejects_unsupported_stateful_scenario(): void
    {
        config(['services.hmrc.environment' => 'sandbox',
            'services.hmrc.obligations_test_scenario' => 'STATEFUL']);
        Http::fake();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Use DYNAMIC, not STATEFUL');
        $this->service()->getObligations('AA123456A');
    }

    public function test_creates_status_using_hmrc_token_and_expected_payload(): void
    {
        config(['services.hmrc.environment' => 'sandbox']);
        Http::fake(['*' => Http::response('', 204)]);
        $payload = ['itsaStatusDetails' => [[
            'submittedOn' => '2026-09-11T10:00:00.000Z',
            'status' => 'MTD Mandated',
            'statusReason' => 'Sign up - return available',
        ]]];
        $this->service()->createSandboxItsaStatus('aa 123456 a', '2026-27', $payload);
        Http::assertSent(fn (Request $request) =>
            $request->method() === 'POST'
            && $request->url() === 'https://test-api.service.hmrc.gov.uk/individuals/self-assessment-test-support/itsa-status/AA123456A/2026-27'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request->hasHeader('Accept', 'application/vnd.hmrc.1.0+json')
            && $request->data() === $payload
        );
    }

    public function test_creation_service_blocks_production_before_sending(): void
    {
        config(['services.hmrc.environment' => 'production']);
        Http::fake();
        try {
            $this->service()->createSandboxItsaStatus('AA123456A', '2026-27', []);
            $this->fail('Production creation should be blocked.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('sandbox', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_creation_route_requires_authentication(): void
    {
        $this->postJson('/api/hmrc/sandbox/itsa-status', [])->assertUnauthorized();
    }

    public function test_creation_route_rejects_production(): void
    {
        config(['services.hmrc.environment' => 'production']);
        $this->app->instance(HmrcService::class, $this->service());
        Http::fake();
        $this->withoutMiddleware()->postJson('/api/hmrc/sandbox/itsa-status', [])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_creation_route_validates_year_status_and_identity_overrides(): void
    {
        config(['services.hmrc.environment' => 'sandbox']);
        $this->app->instance(HmrcService::class, $this->service());
        Http::fake();
        $this->withoutMiddleware()->postJson('/api/hmrc/sandbox/itsa-status', [
            'tax_year' => '2026-29', 'status' => 'invalid', 'status_reason' => 'invalid',
            'business_income_2_years_prior' => -1, 'nino' => 'AA123456A',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'tax_year', 'status', 'status_reason', 'business_income_2_years_prior', 'nino',
        ]);
        Http::assertNothingSent();
    }

    private function service(): HmrcService
    {
        config(['services.hmrc.base_url' => 'https://test-api.service.hmrc.gov.uk',
            'services.hmrc.arn' => 'test-agent']);

        return new class extends HmrcService {
            public function getAccessToken(): string
            {
                return 'test-token';
            }
        };
    }

    public function test_sandbox_sends_stateful_header_and_requested_year(): void
    {
        config(['services.hmrc.environment' => 'sandbox',
            'services.hmrc.itsa_status_test_scenario' => 'STATEFUL']);
        $body = ['itsaStatuses' => [['taxYear' => '2026-27']]];
        Http::fake(['*' => Http::response($body)]);

        $this->assertSame($body, $this->service()->getMtdCustomerStatus('AA123456A', '2026-27'));
        Http::assertSent(fn (Request $request) =>
            $request->url() === 'https://test-api.service.hmrc.gov.uk/individuals/person/itsa-status/AA123456A/2026-27'
            && $request->hasHeader('Gov-Test-Scenario', 'STATEFUL')
            && $request->hasHeader('Accept', 'application/vnd.hmrc.2.0+json')
        );
    }

    public function test_production_does_not_add_sandbox_header(): void
    {
        config(['services.hmrc.environment' => 'production',
            'services.hmrc.itsa_status_test_scenario' => 'STATEFUL']);
        Http::fake(['*' => Http::response(['itsaStatuses' => [['taxYear' => '2026-27']]])]);
        $this->service()->getMtdCustomerStatus('AA123456A', '2026-27');
        Http::assertSent(fn (Request $request) => !$request->hasHeader('Gov-Test-Scenario'));
    }

    public function test_default_scenario_rejects_canned_year_without_rewriting_it(): void
    {
        config(['services.hmrc.environment' => 'sandbox',
            'services.hmrc.itsa_status_test_scenario' => 'DEFAULT']);
        $body = ['itsaStatuses' => [['taxYear' => '2019-20']]];
        Http::fake(['*' => Http::response($body)]);
        try {
            $this->service()->getMtdCustomerStatus('AA123456A', '2026-27');
            $this->fail('A mismatched tax year must not be reported as success.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('2019-20 instead of 2026-27', $e->getMessage());
            $this->assertStringContainsString('Sandbox scenario sent: DEFAULT', $e->getMessage());
        }
        Http::assertSent(fn (Request $request) => !$request->hasHeader('Gov-Test-Scenario'));
    }

    public function test_stateful_response_with_wrong_year_is_rejected(): void
    {
        config(['services.hmrc.environment' => 'sandbox',
            'services.hmrc.itsa_status_test_scenario' => 'STATEFUL']);
        Http::fake(['*' => Http::response(['itsaStatuses' => [['taxYear' => '2019-20']]])]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Sandbox scenario sent: STATEFUL');
        $this->service()->getMtdCustomerStatus('AA123456A', '2026-27');
    }
}
