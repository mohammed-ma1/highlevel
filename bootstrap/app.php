<?php

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
        // CORS is handled by Laravel's built-in CORS middleware
        // No additional CORS middleware needed for same-origin requests
        
        // CSRF middleware is enabled by default in Laravel 11
        // We'll use the custom VerifyCsrfToken middleware to handle exemptions
        $middleware->web(replace: [
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class => \App\Http\Middleware\VerifyCsrfToken::class,
        ]);
        
        // Add payment policy middleware for Safari iframe compatibility
        $middleware->web(append: [
            \App\Http\Middleware\PaymentPolicyMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
