<?php

use App\Http\Middleware\ApplyRedirects;
use App\Http\Middleware\PreviewHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Redirections 301 (ancien site WordPress) : middleware global, car il doit aussi
        // s'appliquer aux URLs qui ne correspondent à aucune route (donc 404 sans lui).
        $middleware->prepend(ApplyRedirects::class);
        $middleware->web(append: [PreviewHeaders::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
