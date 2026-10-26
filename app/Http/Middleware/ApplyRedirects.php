<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applique les redirections 301 (ancienne URL → nouvelle URL) gérées dans le back-office.
 * La table est mise en cache ; seules les requêtes GET/HEAD hors /admin sont concernées.
 */
class ApplyRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $path = Redirect::normalize($request->getPathInfo());

        if ($path === '/admin' || str_starts_with($path, '/admin/') || str_starts_with($path, '/livewire')) {
            return $next($request);
        }

        // Ancienne recherche WordPress : /?s=terme → /recherche?q=terme
        if ($path === '/' && $request->query->has('s')) {
            return redirect('/recherche?'.http_build_query(['q' => (string) $request->query('s')]), 301);
        }

        $hit = Redirect::map()[$path] ?? null;

        if ($hit === null || Redirect::normalize($hit['to']) === $path) {
            return $next($request);
        }

        DB::table('redirects')->where('id', $hit['id'])->update([
            'hits' => DB::raw('hits + 1'),
            'last_hit_at' => now(),
        ]);

        return redirect($hit['to'], $hit['code']);
    }
}
