<?php

use App\Http\Middleware\EnsureSchoolIsActive;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\RequireMenuAccess;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'school.active' => EnsureSchoolIsActive::class,
            'menu' => RequireMenuAccess::class,
            'super.admin' => EnsureSuperAdmin::class,
            'tenant.resolve' => ResolveTenant::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
