<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class CheckUserStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        if ($user && $user->status == 3) {
            // Optional: revoke all tokens
            $user->tokens()->delete();
            return response()->json([
                'message' => 'Your account has beed suspended or blocked! If you think, your account has been suspended incorrectly, please contact us at service@taxitax.uk.',
                'code' => 'ACCOUNT_SUSPENDED'
            ], 403);
        }
        return $next($request);
    }
}
