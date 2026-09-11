<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\HmrcAgentConnection;


class HmrcAuthController extends Controller
{
    public function index() 
    { 
        $connection = HmrcAgentConnection::where( 'environment', config('services.hmrc.environment') )->first(); 
        return view('admin.hmrcindex', compact('connection')); 
    }

    public function authorize()
    {
        $state = Str::random(64);

        session([
            'hmrc_oauth_state' => $state,
            'hmrc_connected_by' => auth()->id(),
        ]);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.hmrc.client_id'),
            'scope' => config('services.hmrc.scopes'),
            'redirect_uri' => config('services.hmrc.redirect_uri'),
            'state' => $state,
        ]);

        $url = config('services.hmrc.auth_url')
            . '/oauth/authorize?'
            . $query;

        return redirect()->away($url);
    }

    public function callback(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Check if HMRC returned an OAuth error
        |--------------------------------------------------------------------------
        */
        if ($request->filled('error')) {

            Log::warning('HMRC OAuth authorization error', [
                'error' => $request->error,
                'error_description' => $request->error_description,
            ]);

            session()->forget([
                'hmrc_oauth_state',
                'hmrc_connected_by',
            ]);

            return redirect()
                ->route('hmrc.index')
                ->with(
                    'error',
                    $request->error_description
                        ?? 'HMRC authorization was cancelled or failed.'
                );
        }


        /*
        |--------------------------------------------------------------------------
        | 2. Check authorization code
        |--------------------------------------------------------------------------
        */
        if (!$request->filled('code')) {

            return redirect()
                ->route('hmrc.index')
                ->with(
                    'error',
                    'HMRC authorization code was not received.'
                );
        }


        /*
        |--------------------------------------------------------------------------
        | 3. Check OAuth state
        |--------------------------------------------------------------------------
        */
        if (!$request->filled('state')) {

            return redirect()
                ->route('ahmrc.index')
                ->with(
                    'error',
                    'HMRC authorization state was not received.'
                );
        }


        /*
        |--------------------------------------------------------------------------
        | 4. Validate OAuth state against Laravel session
        |--------------------------------------------------------------------------
        */
        $sessionState = session('hmrc_oauth_state');

        if (
            !$sessionState ||
            !hash_equals(
                (string) $sessionState,
                (string) $request->state
            )
        ) {

            Log::warning('Invalid HMRC OAuth state', [
                'received_state' => $request->state,
            ]);

            return redirect()
                ->route('hmrc.index')
                ->with(
                    'error',
                    'Invalid or expired HMRC authorization request.'
                );
        }


        /*
        |--------------------------------------------------------------------------
        | 5. Get admin who initiated HMRC connection
        |--------------------------------------------------------------------------
        */
        $connectedBy = session('hmrc_connected_by');


        /*
        |--------------------------------------------------------------------------
        | 6. Clear temporary OAuth session data
        |--------------------------------------------------------------------------
        */
        session()->forget([
            'hmrc_oauth_state',
            'hmrc_connected_by',
        ]);


        /*
        |--------------------------------------------------------------------------
        | 7. Exchange HMRC authorization code for tokens
        |--------------------------------------------------------------------------
        */
        try {

            $response = Http::asForm()
                ->timeout(30)
                ->post(
                    config('services.hmrc.base_url') . '/oauth/token',
                    [
                        'client_id' => config('services.hmrc.client_id'),

                        'client_secret' =>
                            config('services.hmrc.client_secret'),

                        'grant_type' =>
                            'authorization_code',

                        'redirect_uri' =>
                            config('services.hmrc.redirect_uri'),

                        'code' =>
                            $request->code,
                    ]
                );

        } catch (\Throwable $e) {

            Log::error('HMRC OAuth token request exception', [
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('hmrc.index')
                ->with(
                    'error',
                    'Unable to communicate with HMRC. Please try again.'
                );
        }


        /*
        |--------------------------------------------------------------------------
        | 8. Check HMRC token response
        |--------------------------------------------------------------------------
        */
        if ($response->failed()) {

            Log::error('HMRC OAuth token exchange failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return redirect()
                ->route('hmrc.index')
                ->with(
                    'error',
                    'Unable to connect AppTax with HMRC.'
                );
        }


        /*
        |--------------------------------------------------------------------------
        | 9. Get token data
        |--------------------------------------------------------------------------
        */
        $token = $response->json();

        if (
            empty($token['access_token']) ||
            empty($token['refresh_token'])
        ) {

            Log::error('HMRC OAuth token response incomplete', [
                'response' => $token,
            ]);

            return redirect()
                ->route('hmrc.index')
                ->with(
                    'error',
                    'HMRC returned an invalid token response.'
                );
        }


        /*
        |--------------------------------------------------------------------------
        | 10. Store HMRC agent connection
        |--------------------------------------------------------------------------
        */
        try {

            HmrcAgentConnection::updateOrCreate(
                [
                    'environment' =>
                        config('services.hmrc.environment'),
                ],
                [
                    'arn' =>
                        config('services.hmrc.arn'),

                    'access_token' =>
                        $token['access_token'],

                    'refresh_token' =>
                        $token['refresh_token'],

                    'expires_at' =>
                        now()->addSeconds(
                            (int) ($token['expires_in'] ?? 14400)
                        ),

                    'scope' =>
                        $token['scope'] ?? null,

                    'connected_by' =>
                        $connectedBy,

                    'connected_at' =>
                        now(),

                    'is_active' =>
                        true,
                ]
            );

        } catch (\Throwable $e) {
            Log::error('Failed storing HMRC agent connection', [
                'message' => $e->getMessage(),
            ]);
            return redirect()
                ->route('hmrc.index')
                ->with(
                    'error',
                    'HMRC authorization succeeded, but AppTax could not save the connection.'
                );
        }
        /*
        |--------------------------------------------------------------------------
        | 11. Redirect back to AppTax admin
        |--------------------------------------------------------------------------
        */
        return redirect()
            ->route('hmrc.index')
            ->with(
                'success',
                'AppTax has been successfully connected to HMRC.'
            );
    }
}