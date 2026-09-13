<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySsoSecret
{
    /**
     * Verify that the incoming request has a valid SSO secret key.
     * Used to authenticate API calls from the SSO Server.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = $request->header('X-SSO-Secret');
        $expectedSecret = config('services.sso.api_secret');

        if (! $secret || ! $expectedSecret || ! hash_equals($expectedSecret, $secret)) {
            return response()->json([
                'message' => 'Unauthorized. Invalid SSO secret.',
            ], 401);
        }

        return $next($request);
    }
}
