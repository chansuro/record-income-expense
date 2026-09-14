<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class HmrcFraudHeaders
{
    // Only client-observable data is accepted from the JSON body.
    public function build(Request $request): array
    {
        if (config('services.hmrc.environment') === 'sandbox' && !$request->has('fraud_prevention')) {
            return [];
        }
        $method = config('services.hmrc.fraud.connection_method');
        if (!in_array($method, ['WEB_APP_VIA_SERVER', 'MOBILE_APP_VIA_SERVER'], true)) {
            throw new RuntimeException('Configure HMRC_FRAUD_CONNECTION_METHOD for the actual client before production submission.');
        }
        $clientKeys = ['Gov-Client-Device-ID', 'Gov-Client-Screens', 'Gov-Client-Timezone', 'Gov-Client-Window-Size'];
        $clientKeys = array_merge($clientKeys, $method === 'WEB_APP_VIA_SERVER'
            ? ['Gov-Client-Browser-JS-User-Agent']
            : ['Gov-Client-User-Agent', 'Gov-Client-Local-IPs', 'Gov-Client-Local-IPs-Timestamp']);
        $rules = ['fraud_prevention' => ['required', 'array:' . implode(',', $clientKeys)]];
        foreach ($clientKeys as $key) {
            $rules['fraud_prevention.' . $key] = ['present', 'nullable', 'string', 'max:4096', 'not_regex:/[\r\n]/'];
        }
        $rules['fraud_prevention.Gov-Client-Device-ID'] = ['required', 'uuid'];
        $rules['fraud_prevention.Gov-Client-Timezone'] = ['required', 'regex:/^UTC[+-](0\d|1[0-4]):[0-5]\d$/'];
        $validated = Validator::make($request->all(), $rules)->validate();
        $headers = array_map(fn ($value) => $value ?? '', $validated['fraud_prevention']);

        // This attribute may ONLY be set by trusted server middleware, never copied from client headers/body.
        $server = $request->attributes->get('hmrc_fraud_server_context');
        if ($server === null && config('services.hmrc.fraud.direct_connection') === true) {
            $ip = $request->server('REMOTE_ADDR');
            $vendorIp = config('services.hmrc.fraud.vendor_public_ip');
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                || !filter_var($vendorIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                || $request->headers->has('Forwarded') || $request->headers->has('X-Forwarded-For')) {
                throw new RuntimeException('Direct fraud-header collection requires public client/server IPs and no proxy. Configure trusted proxy collection for this deployment.');
            }
            $server = [
                'Gov-Client-Public-IP' => $ip,
                'Gov-Client-Public-IP-Timestamp' => \Carbon\CarbonImmutable::createFromTimestamp(
                    $request->server('REQUEST_TIME_FLOAT', microtime(true)), 'UTC')->format('Y-m-d\TH:i:s.v\Z'),
                'Gov-Client-Public-Port' => (string) $request->server('REMOTE_PORT'),
                'Gov-Vendor-Public-IP' => $vendorIp,
                'Gov-Vendor-Forwarded' => 'by=' . rawurlencode($vendorIp) . '&for=' . rawurlencode($ip),
                // This application authenticates with password only and has no device software licence.
                // HMRC requires the headers to be present; an empty value reports unavailable data.
                'Gov-Client-Multi-Factor' => '',
                'Gov-Vendor-License-IDs' => '',
            ];
        }
        if (!is_array($server)) {
            throw new RuntimeException('Production fraud-header collection is not configured for this server/proxy topology.');
        }
        $requiredServer = ['Gov-Client-Public-IP', 'Gov-Client-Public-IP-Timestamp', 'Gov-Client-Public-Port',
            'Gov-Vendor-Public-IP', 'Gov-Vendor-Forwarded', 'Gov-Client-Multi-Factor', 'Gov-Vendor-License-IDs'];
        // MFA and license facts must come from the server's authentication/licensing integration.
        foreach ($requiredServer as $key) {
            if (!array_key_exists($key, $server) || !is_string($server[$key]) || preg_match('/[\r\n]/', $server[$key])) {
                throw new RuntimeException('Missing or invalid trusted fraud-prevention context: ' . $key);
            }
            $headers[$key] = $server[$key];
        }
        $product = config('services.hmrc.fraud.product_name');
        $version = config('services.hmrc.fraud.vendor_version');
        if (!$product || !$version || preg_match('/[\r\n]/', $product . $version)) {
            throw new RuntimeException('Configure HMRC_FRAUD_PRODUCT_NAME and HMRC_FRAUD_VENDOR_VERSION.');
        }
        $headers['Gov-Client-Connection-Method'] = $method;
        $headers['Gov-Client-User-IDs'] = 'app-user-id=' . rawurlencode((string) $request->user()->getAuthIdentifier())
            . '&app-login=' . rawurlencode((string) $request->user()->email);
        $headers['Gov-Vendor-Product-Name'] = rawurlencode($product);
        $headers['Gov-Vendor-Version'] = $version; // Pre-encoded software=version pairs, including client software.
        $this->assertComplete($headers);
        return $headers;
    }

    public function assertComplete(array $headers): void
    {
        $method = $headers['Gov-Client-Connection-Method'] ?? null;
        if (!in_array($method, ['WEB_APP_VIA_SERVER', 'MOBILE_APP_VIA_SERVER'], true)) {
            throw new RuntimeException('Production quarterly submissions require a supported fraud-prevention connection method.');
        }
        $required = ['Gov-Client-Device-ID', 'Gov-Client-Screens', 'Gov-Client-Timezone',
            'Gov-Client-Window-Size', 'Gov-Client-User-IDs', 'Gov-Client-Multi-Factor',
            'Gov-Client-Public-IP', 'Gov-Client-Public-IP-Timestamp', 'Gov-Client-Public-Port',
            'Gov-Vendor-Forwarded', 'Gov-Vendor-Public-IP', 'Gov-Vendor-Version',
            'Gov-Vendor-Product-Name', 'Gov-Vendor-License-IDs'];
        $required = array_merge($required, $method === 'WEB_APP_VIA_SERVER'
            ? ['Gov-Client-Browser-JS-User-Agent']
            : ['Gov-Client-User-Agent', 'Gov-Client-Local-IPs', 'Gov-Client-Local-IPs-Timestamp']);
        foreach ($required as $key) {
            if (!array_key_exists($key, $headers) || !is_string($headers[$key]) || preg_match('/[\r\n]/', $headers[$key])) {
                throw new RuntimeException('Missing or invalid fraud-prevention header: ' . $key);
            }
        }
        foreach (['Gov-Client-Public-IP', 'Gov-Vendor-Public-IP'] as $key) {
            if (!filter_var($headers[$key], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Invalid public IP for ' . $key);
            }
        }
        if (!ctype_digit($headers['Gov-Client-Public-Port']) || (int) $headers['Gov-Client-Public-Port'] < 1
            || (int) $headers['Gov-Client-Public-Port'] > 65535) {
            throw new RuntimeException('Invalid originating client public port.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', $headers['Gov-Client-Public-IP-Timestamp'])) {
            throw new RuntimeException('Invalid client public IP collection timestamp.');
        }
    }
}
