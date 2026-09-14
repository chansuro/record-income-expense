<?php

namespace Tests\Feature;

use App\Models\HmrcClientAuthorisation;
use App\Models\HmrcQuarterlySubmission;
use App\Models\User;
use App\Services\HmrcService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HmrcQuarterlySubmissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'services.hmrc.environment' => 'sandbox', 'services.hmrc.arn' => 'test-agent',
            'services.hmrc.base_url' => 'https://test-api.service.hmrc.gov.uk',
            'services.hmrc.quarterly_test_scenario' => 'STATEFUL']);
        DB::purge('sqlite');
        (require base_path('database/migrations/2026_08_12_075021_create_hmrc_client_authorisations_table.php'))->up();
        (require base_path('database/migrations/2026_09_11_120000_create_hmrc_quarterly_submissions_table.php'))->up();
        (require base_path('database/migrations/2026_09_14_120000_create_hmrc_annual_submissions_table.php'))->up();
        $user = new User;
        $user->id = 101;
        $user->email = 'test@example.invalid';
        $user->status = 1;
        $this->actingAs($user);
        HmrcClientAuthorisation::create(['user_id' => 101, 'environment' => 'sandbox', 'service' => 'MTD-IT',
            'status' => 'Accepted', 'client_id' => 'AA123456A']);
        $this->app->instance(HmrcService::class, new class extends HmrcService {
            public function getAccessToken(): string { return 'test-hmrc-token'; }
        });
        Http::preventStrayRequests();
    }

    private function body(): array
    {
        return ['business_id' => 'XAIS12345678901', 'tax_year' => '2026-27', 'payload' => [
            'periodDates' => ['periodStartDate' => '2026-04-06', 'periodEndDate' => '2026-10-05'],
            'periodIncome' => ['turnover' => 15000, 'other' => 0],
            'periodExpenses' => ['costOfGoods' => 2300.50, 'adminCosts' => 100],
        ]];
    }

    private function businessBody(): array
    {
        return ['tax_year' => '2026-27', 'trading_type' => 'Taxi driver',
            'trading_name' => 'Test Taxi', 'accounting_type' => 'CASH',
            'quarterly_period_type' => 'standard', 'business_address_line_one' => '1 Test Street',
            'business_address_postcode' => 'SW1A 1AA', 'business_address_country_code' => 'GB'];
    }

    public function test_sandbox_business_endpoint_uses_authorised_nino_and_returns_business_id(): void
    {
        Http::fake(['*' => Http::response(['businessId' => 'XAIS12345678910'], 201,
            ['X-CorrelationId' => 'business-1'])]);
        $this->postJson('/api/hmrc/sandbox/business', $this->businessBody())->assertCreated()
            ->assertJsonPath('data.business_id', 'XAIS12345678910')
            ->assertJsonPath('data.correlation_id', 'business-1');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/business/AA123456A')
            && $request->data()['firstAccountingPeriodStartDate'] === '2026-04-06'
            && $request->data()['firstAccountingPeriodEndDate'] === '2027-04-05'
            && $request->data()['quarterlyTypeChoice']['quarterlyPeriodType'] === 'standard');
    }

    public function test_sandbox_business_endpoint_rejects_client_identity_and_invalid_year(): void
    {
        Http::fake();
        $body = array_merge($this->businessBody(), ['tax_year' => '2026-29', 'nino' => 'AA123456A']);
        $this->postJson('/api/hmrc/sandbox/business', $body)->assertUnprocessable()
            ->assertJsonValidationErrors(['tax_year', 'nino']);
        Http::assertNothingSent();
    }

    public function test_acceptance_sends_validated_payload_and_saves_encrypted_history(): void
    {
        Http::fake(['*' => Http::response('', 204, ['X-CorrelationId' => 'correlation-1'])]);
        $response = $this->putJson('/api/hmrc/quarterly-update', $this->body());
        $response->assertOk()->assertJsonPath('data.status', 'accepted')->assertJsonPath('data.environment', 'sandbox');
        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/AA123456A/XAIS12345678901/cumulative/2026-27')
            && $request->hasHeader('Gov-Test-Scenario', 'STATEFUL')
            && $request->hasHeader('Accept', 'application/vnd.hmrc.5.0+json')
            && $request->hasHeader('Authorization', 'Bearer test-hmrc-token')
            && $request->data() === $this->body()['payload']);
        $saved = HmrcQuarterlySubmission::firstOrFail();
        $this->assertSame('accepted', $saved->status);
        $this->assertSame('correlation-1', $saved->correlation_id);
        $this->assertEquals($this->body()['payload'], $saved->payload);
        $this->assertStringNotContainsString('turnover', DB::table('hmrc_quarterly_submissions')->value('payload'));
    }

    public function test_invalid_payloads_are_rejected_before_hmrc_or_history_write(): void
    {
        Http::fake();
        $cases = [
            ['tax_year', '2026-99'], ['tax_year', '2024-25'], ['business_id', 'wrong'],
            ['payload.periodDates.periodStartDate', '2025-04-06'],
            ['payload.periodDates.periodEndDate', '2026-04-05'],
            ['payload.periodIncome.turnover', -1], ['payload.periodIncome.turnover', '100'],
            ['payload.periodIncome.turnover', 1.234], ['payload.periodExpenses', []],
            ['payload.periodExpenses.unknownCategory', 5],
            ['payload.periodExpenses.consolidatedExpenses', 10], ['type_of_business', 'uk-property'],
        ];
        foreach ($cases as [$path, $value]) {
            $body = $this->body();
            data_set($body, $path, $value);
            $this->putJson('/api/hmrc/quarterly-update', $body)->assertUnprocessable();
        }
        Http::assertNothingSent();
        $this->assertSame(0, HmrcQuarterlySubmission::count());
    }

    public function test_zero_income_and_negative_expense_adjustments_are_allowed(): void
    {
        Http::fake(['*' => Http::response('', 204)]);
        $body = $this->body();
        $body['payload']['periodIncome'] = ['turnover' => 0, 'other' => 0];
        $body['payload']['periodExpenses'] = ['adminCosts' => -10.25];
        $this->putJson('/api/hmrc/quarterly-update', $body)->assertOk();
    }

    public function test_hmrc_rejection_is_recorded_with_code_and_correlation_id(): void
    {
        Http::fake(['*' => Http::response(['code' => 'RULE_INCORRECT_OR_EMPTY_BODY_SUBMITTED',
            'message' => 'Invalid request'], 400, ['X-CorrelationId' => 'rejected-1'])]);
        $this->putJson('/api/hmrc/quarterly-update', $this->body())->assertUnprocessable()
            ->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.correlation_id', 'rejected-1');
        $this->assertSame('rejected', HmrcQuarterlySubmission::first()->status);
    }

    public function test_unexpected_200_is_not_accepted(): void
    {
        Http::fake(['*' => Http::response(['message' => 'not the expected response'], 200)]);
        $this->putJson('/api/hmrc/quarterly-update', $this->body())->assertStatus(502)->assertJsonPath('data.status', 'unknown');
        $this->assertSame('unknown', HmrcQuarterlySubmission::first()->status);
    }

    public function test_timeout_is_unknown_and_not_automatically_retried(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            throw new \Illuminate\Http\Client\ConnectionException('timeout');
        });
        $this->putJson('/api/hmrc/quarterly-update', $this->body())->assertStatus(502)->assertJsonPath('data.status', 'unknown');
        $this->assertSame(1, $calls);
        $this->assertSame('unknown', HmrcQuarterlySubmission::first()->status);
    }

    public function test_default_sandbox_response_is_explicitly_simulated(): void
    {
        config(['services.hmrc.quarterly_test_scenario' => 'DEFAULT']);
        Http::fake(['*' => Http::response('', 204)]);
        $this->putJson('/api/hmrc/quarterly-update', $this->body())->assertOk()->assertJsonPath('data.status', 'simulated');
        $this->assertSame('simulated', HmrcQuarterlySubmission::first()->status);
    }

    public function test_production_without_fraud_configuration_is_blocked(): void
    {
        config(['services.hmrc.environment' => 'production', 'services.hmrc.fraud.connection_method' => null]);
        HmrcClientAuthorisation::query()->update(['environment' => 'production']);
        Http::fake();
        $this->putJson('/api/hmrc/quarterly-update', $this->body())->assertUnprocessable()->assertJsonPath('data.status', 'not_sent');
        Http::assertNothingSent();
        $this->assertSame(0, HmrcQuarterlySubmission::count());
    }

    public function test_missing_authorisation_blocks_submission(): void
    {
        HmrcClientAuthorisation::query()->update(['status' => 'Pending']);
        Http::fake();
        $this->putJson('/api/hmrc/quarterly-update', $this->body())->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_history_is_scoped_to_current_customer_and_environment(): void
    {
        Http::fake(['*' => Http::response('', 204)]);
        $this->putJson('/api/hmrc/quarterly-update', $this->body())->assertOk();
        $this->getJson('/api/hmrc/quarterly-submissions')->assertOk()->assertJsonPath('data.total', 1)
            ->assertJsonMissingPath('data.data.0.payload');
        HmrcQuarterlySubmission::query()->update(['user_id' => 999]);
        $this->getJson('/api/hmrc/quarterly-submissions')->assertJsonPath('data.total', 0);
        HmrcQuarterlySubmission::query()->update(['user_id' => 101, 'environment' => 'production']);
        $this->getJson('/api/hmrc/quarterly-submissions')->assertJsonPath('data.total', 0);
    }

    public function test_missing_history_table_prevents_hmrc_submission(): void
    {
        \Illuminate\Support\Facades\Schema::drop('hmrc_quarterly_submissions');
        Http::fake();
        $this->putJson('/api/hmrc/quarterly-update', $this->body())->assertUnprocessable()
            ->assertJsonPath('data.status', 'not_sent');
        Http::assertNothingSent();
    }

    public function test_receipt_storage_failure_does_not_hide_hmrc_acceptance(): void
    {
        Http::fake(['*' => Http::response('', 204, ['X-CorrelationId' => 'accepted-1'])]);
        HmrcQuarterlySubmission::updating(function () { throw new \RuntimeException('storage unavailable'); });
        try {
            $this->putJson('/api/hmrc/quarterly-update', $this->body())->assertOk()
                ->assertJsonPath('data.status', 'accepted')->assertJsonPath('data.history_saved', false)
                ->assertJsonPath('data.correlation_id', 'accepted-1');
            Http::assertSentCount(1);
        } finally {
            HmrcQuarterlySubmission::flushEventListeners();
        }
    }

    public function test_redirect_is_unknown_instead_of_accepted(): void
    {
        Http::fake(['*' => Http::response('', 302, ['Location' => 'https://example.invalid'])]);
        $this->putJson('/api/hmrc/quarterly-update', $this->body())->assertStatus(502)
            ->assertJsonPath('data.status', 'unknown');
        Http::assertSentCount(1);
    }
}
