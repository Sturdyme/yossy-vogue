<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // Render terminates TLS in front of the app. Trusting the proxy makes Laravel see the
        // real visitor IP (needed for rate limits), the https scheme and the right host
        // (needed for generated URLs such as the Paystack callback).
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'admin.pin' => \App\Http\Middleware\EnsureAdminPinVerified::class,
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
        ]);

        // HandleCors is already part of Laravel's default global middleware,
        // so it is no longer appended a second time here.

        // Exclude Paystack Webhook from CSRF protection
        $middleware->preventRequestForgery(except: [
            'api/paystack/webhook',
            'paystack/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Always answer API calls with JSON: a clean 401 instead of a redirect
        // to a login route that does not exist.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, \Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
