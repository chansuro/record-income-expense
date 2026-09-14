<?php

namespace Tests\Feature;

use App\Models\HmrcAnnualSubmission;
use App\Models\HmrcClientAuthorisation;
use App\Models\User;
use App\Services\HmrcService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HmrcAnnualSubmissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'services.hmrc.environment' => 'sandbox', 'services.hmrc.arn' => 'test-agent',
            'services.hmrc.base_url' => 'https://test-api.service.hmrc.gov.uk',
            'services.hmrc.annual_test_scenario' => 'STATEFUL']);
        DB::purge('sqlite');
        (require base_path('database/migrations/2026_08_12_075021_create_hmrc_client_authorisations_table.php'))->up();
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
        return ['business_id' => 'XAIS12345678910', 'tax_year' => '2026-27',
            'type_of_business' => 'self-employment', 'payload' => [
                'adjustments' => ['includedNonTaxableProfits' => 200.12,
                    'adjustmentToProfitsForClass4' => 500.99],
                'allowances' => ['annualInvestmentAllowance' => 1000],
            ]];
    }

    public function test_accepts_204_and_saves_encrypted_history(): void
    {
        Http::fake(['*' => Http::response('', 204, ['X-CorrelationId' => 'annual-1'])]);
        $this->putJson('/api/hmrc/annual-submission', $this->body())->assertOk()
            ->assertJsonPath('data.status', 'accepted')->assertJsonPath('data.correlation_id', 'annual-1');
        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/AA123456A/XAIS12345678910/annual/2026-27')
            && $request->hasHeader('Gov-Test-Scenario', 'STATEFUL')
            && $request->data() === $this->body()['payload']);
        $saved = HmrcAnnualSubmission::firstOrFail();
        $this->assertSame('accepted', $saved->status);
        $this->assertEquals($this->body()['payload'], $saved->payload);
        $this->assertStringNotContainsString('includedNonTaxableProfits',
            DB::table('hmrc_annual_submissions')->value('payload'));
    }

    public function test_validates_annual_rules_before_sending(): void
    {
        Http::fake();
        $cases = [
            ['tax_year', '2026-99'], ['business_id', 'wrong'], ['type_of_business', 'uk-property'],
            ['payload.adjustments.includedNonTaxableProfits', -1],
            ['payload.adjustments.includedNonTaxableProfits', '100'],
            ['payload.allowances.tradingIncomeAllowance', 1001],
            ['payload.adjustments.overlapReliefUsed', 10],
        ];
        foreach ($cases as [$path, $value]) {
            $body = $this->body();
            data_set($body, $path, $value);
            $this->putJson('/api/hmrc/annual-submission', $body)->assertUnprocessable();
        }
        $body = $this->body();
        $body['payload']['allowances'] = ['tradingIncomeAllowance' => 1000, 'annualInvestmentAllowance' => 1];
        $this->putJson('/api/hmrc/annual-submission', $body)->assertUnprocessable();
        $body = $this->body();
        $body['payload']['adjustments'] = ['transitionProfitAccelerationAmount' => 1];
        $this->putJson('/api/hmrc/annual-submission', $body)->assertUnprocessable();
        Http::assertNothingSent();
        $this->assertSame(0, HmrcAnnualSubmission::count());
    }

    public function test_2025_specific_adjustment_rules_are_enforced(): void
    {
        Http::fake();
        $body = $this->body();
        $body['tax_year'] = '2025-26';
        $body['payload']['adjustments'] = ['overlapReliefUsed' => 10, 'adjustmentToProfitsForClass4' => 5];
        $this->putJson('/api/hmrc/annual-submission', $body)->assertUnprocessable()
            ->assertJsonValidationErrors('payload.adjustments.adjustmentToProfitsForClass4');
        Http::assertNothingSent();
    }

    public function test_hmrc_rejection_is_recorded(): void
    {
        Http::fake(['*' => Http::response(['code' => 'MATCHING_RESOURCE_NOT_FOUND',
            'message' => 'Matching resource not found'], 404, ['X-CorrelationId' => 'annual-reject'])]);
        $this->putJson('/api/hmrc/annual-submission', $this->body())->assertUnprocessable()
            ->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.hmrc_code', 'MATCHING_RESOURCE_NOT_FOUND');
        $this->assertSame('rejected', HmrcAnnualSubmission::first()->status);
    }

    public function test_unexpected_success_response_is_recorded_as_unknown(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $this->putJson('/api/hmrc/annual-submission', $this->body())->assertStatus(502)
            ->assertJsonPath('data.status', 'unknown');
        $this->assertSame('unknown', HmrcAnnualSubmission::first()->status);
    }

    public function test_default_sandbox_is_labelled_simulated(): void
    {
        config(['services.hmrc.annual_test_scenario' => 'DEFAULT']);
        Http::fake(['*' => Http::response('', 204)]);
        $this->putJson('/api/hmrc/annual-submission', $this->body())->assertOk()
            ->assertJsonPath('data.status', 'simulated');
    }

    public function test_history_is_customer_and_environment_scoped(): void
    {
        Http::fake(['*' => Http::response('', 204)]);
        $this->putJson('/api/hmrc/annual-submission', $this->body())->assertOk();
        $this->getJson('/api/hmrc/annual-submissions?tax_year=2026-27')->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonMissingPath('data.data.0.payload');
        HmrcAnnualSubmission::query()->update(['user_id' => 999]);
        $this->getJson('/api/hmrc/annual-submissions')->assertJsonPath('data.total', 0);
    }
}
