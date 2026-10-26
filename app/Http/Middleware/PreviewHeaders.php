<?php

namespace App\Http\Middleware;

use App\Support\Preview;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Les pages en mode aperçu ne sont ni mises en cache ni indexées. */
class PreviewHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (Preview::active()) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
