<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\CookieUser;
use App\Http\Middleware\CookieAuth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ✅ tambahkan ke grup 'api' yang sudah ada, jangan override
        $middleware->appendToGroup('api', CookieAuth::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
