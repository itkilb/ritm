<?php

namespace App\Ship\Middleware;

use App\Ship\Parents\Middleware\Middleware as ParentMiddleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Product API routes always speak JSON, including auth errors. This prevents
 * Laravel's "auth" middleware from redirecting a browser request to the SPA
 * login page instead of returning a 401 response.
 */
final class ForceJsonResponse extends ParentMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
