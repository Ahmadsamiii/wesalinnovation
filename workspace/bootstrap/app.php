<?php

use App\Http\Middleware\EnforceSessionTimeouts;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [EnsureAccountIsActive::class, EnforceSessionTimeouts::class]);
        $middleware->append(SecurityHeaders::class);

        // جذر النطاق الفرعي في Hostinger مجلد داخل جذر الموقع العام، فيصل التطبيق
        // أيضاً عبر wesalinnovation.sa/workspace. خارج التطوير لا يُقبل إلا مضيف
        // APP_URL، فلا تعمل نسخة ثانية على نطاق الموقع العام وكوكيزه.
        $middleware->trustHosts();

        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
