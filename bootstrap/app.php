<?php

use App\Exceptions\InsufficientCreditsException;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The app runs behind a TLS-terminating proxy (a tunnel locally, the host's load
        // balancer when deployed). Trust its X-Forwarded-* headers so generated URLs use
        // https and match the public host; otherwise browsers block http assets.
        $middleware->trustProxies(at: '*');

        // Pixelforge can't send a CSRF token; the webhook is authenticated by its signature instead.
        $middleware->preventRequestForgery(except: ['webhooks/pixelforge']);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(fn (InsufficientCreditsException $e) => back()
            ->withInput()
            ->withErrors(['credits' => $e->getMessage()]));

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
