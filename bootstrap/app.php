<?php

use App\Http\Middleware\EnsureSessionNotExpired;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsOwner;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'owner' => EnsureUserIsOwner::class,
            'session.limit' => EnsureSessionNotExpired::class,
        ]);
        // Hosting (e.g. DigitalOcean App Platform) ends HTTPS at its load balancer; trust its X-Forwarded-* headers
        // so links, assets and form actions are generated as https:// instead of http:// (mixed content).
        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
