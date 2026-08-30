<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateFromTokenCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        // Keep explicit bearer authentication authoritative. The cookie is the
        // browser-only fallback, so API clients can continue using tokens.
        if (! $request->bearerToken() && is_string($token = $request->cookie('personal_token')) && $token !== '') {
            $request->headers->set('Authorization', 'Bearer '.$token);
        }

        return $next($request);
    }
}
