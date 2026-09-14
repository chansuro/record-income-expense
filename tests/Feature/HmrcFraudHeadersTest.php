<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\HmrcFraudHeaders;
use Illuminate\Http\Request;
use Tests\TestCase;

class HmrcFraudHeadersTest extends TestCase
{
    public function test_mobile_direct_server_collects_network_facts(): void
    {
        config(['services.hmrc.environment' => 'production',
            'services.hmrc.fraud.connection_method' => 'MOBILE_APP_VIA_SERVER',
            'services.hmrc.fraud.product_name' => 'Taxitax',
            'services.hmrc.fraud.vendor_version' => 'taxitax-server=1.0&taxitax-mobile=1.0',
            'services.hmrc.fraud.direct_connection' => true,
            'services.hmrc.fraud.vendor_public_ip' => '1.1.1.1']);
        $request = Request::create('/api/hmrc/quarterly-update', 'PUT', ['fraud_prevention' => [
            'Gov-Client-Device-ID' => 'beec798b-b366-47fa-b1f8-92cede14a1ce',
            'Gov-Client-Screens' => 'width=375&height=812&scaling-factor=2&colour-depth=32',
            'Gov-Client-Timezone' => 'UTC+01:00',
            'Gov-Client-Window-Size' => 'width=375&height=812',
            'Gov-Client-User-Agent' => 'os-family=iOS&os-version=18.0&device-manufacturer=Apple&device-model=iPhone',
            'Gov-Client-Local-IPs' => '192.168.1.2',
            'Gov-Client-Local-IPs-Timestamp' => '2026-09-14T10:00:00.000Z',
        ]], [], [], ['REMOTE_ADDR' => '8.8.8.8', 'REMOTE_PORT' => '45678',
            'REQUEST_TIME_FLOAT' => 1789372800.123]);
        $user = new User;
        $user->id = 42;
        $user->email = 'test@example.invalid';
        $request->setUserResolver(fn () => $user);

        $headers = (new HmrcFraudHeaders)->build($request);
        $this->assertSame('MOBILE_APP_VIA_SERVER', $headers['Gov-Client-Connection-Method']);
        $this->assertSame('8.8.8.8', $headers['Gov-Client-Public-IP']);
        $this->assertSame('45678', $headers['Gov-Client-Public-Port']);
        $this->assertSame('', $headers['Gov-Client-Multi-Factor']);
        $this->assertSame('', $headers['Gov-Vendor-License-IDs']);
    }

    private function request(): Request
    {
        config(['services.hmrc.environment' => 'production',
            'services.hmrc.fraud.connection_method' => 'WEB_APP_VIA_SERVER',
            'services.hmrc.fraud.product_name' => 'Test Product',
            'services.hmrc.fraud.vendor_version' => 'test-server=1.0',
            'services.hmrc.fraud.direct_connection' => false]);
        $request = Request::create('/api/hmrc/quarterly-update', 'PUT', ['fraud_prevention' => [
            'Gov-Client-Device-ID' => 'beec798b-b366-47fa-b1f8-92cede14a1ce',
            'Gov-Client-Screens' => 'width=1920&height=1080&scaling-factor=1&colour-depth=24',
            'Gov-Client-Timezone' => 'UTC+01:00',
            'Gov-Client-Window-Size' => 'width=1200&height=800',
            'Gov-Client-Browser-JS-User-Agent' => 'Test Browser',
        ]]);
        $user = new User;
        $user->id = 42;
        $user->email = 'test@example.invalid';
        $request->setUserResolver(fn () => $user);
        // Test-only values representing context already collected by trusted middleware.
        $request->attributes->set('hmrc_fraud_server_context', [
            'Gov-Client-Public-IP' => '8.8.8.8', 'Gov-Vendor-Public-IP' => '1.1.1.1',
            'Gov-Client-Public-IP-Timestamp' => '2026-09-11T10:00:00.000Z',
            'Gov-Client-Public-Port' => '12345',
            'Gov-Vendor-Forwarded' => 'by=1.1.1.1&for=8.8.8.8',
            'Gov-Client-Multi-Factor' => '', 'Gov-Vendor-License-IDs' => '',
        ]);
        return $request;
    }

    public function test_web_headers_merge_client_and_trusted_server_facts(): void
    {
        $headers = (new HmrcFraudHeaders)->build($this->request());
        $this->assertSame('WEB_APP_VIA_SERVER', $headers['Gov-Client-Connection-Method']);
        $this->assertSame('Test%20Product', $headers['Gov-Vendor-Product-Name']);
        $this->assertSame('app-user-id=42&app-login=test%40example.invalid', $headers['Gov-Client-User-IDs']);
        $this->assertSame('12345', $headers['Gov-Client-Public-Port']);
    }

    public function test_client_cannot_supply_server_identity_or_authorisation_headers(): void
    {
        $request = $this->request();
        $request->merge(['fraud_prevention' => array_merge($request->input('fraud_prevention'), [
            'Gov-Client-Public-IP' => '9.9.9.9', 'Authorization' => 'Bearer forged',
        ])]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        (new HmrcFraudHeaders)->build($request);
    }

    public function test_proxy_context_cannot_be_replaced_by_incoming_http_headers(): void
    {
        $request = $this->request();
        $request->attributes->remove('hmrc_fraud_server_context');
        $request->headers->set('Gov-Client-Public-IP', '8.8.8.8');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('server/proxy topology');
        (new HmrcFraudHeaders)->build($request);
    }

    public function test_header_injection_is_rejected(): void
    {
        $request = $this->request();
        $client = $request->input('fraud_prevention');
        $client['Gov-Client-Browser-JS-User-Agent'] = "Browser\r\nAuthorization: forged";
        $request->merge(['fraud_prevention' => $client]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        (new HmrcFraudHeaders)->build($request);
    }
}
