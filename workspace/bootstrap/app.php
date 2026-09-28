<?php

use App\Http\Middleware\AuthenticateFromPlatformSession;
use App\Http\Middleware\EnforceSessionTimeouts;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\RedirectToCanonicalHost;
use App\Http\Middleware\RedirectToPlatformLogin;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
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
        // الدخول الموحد (مطفأ افتراضياً): الكوكي wesal_auth تضعه المنصة لا هذا التطبيق، فلا
        // يُفكّ تشفيره. والحارس يسبق فحص الإيقاف والخمول لأنه هو من يُدخل الحساب أو يُخرجه.
        $middleware->encryptCookies(except: ['wesal_auth']);
        $middleware->web(append: [
            AuthenticateFromPlatformSession::class,
            EnsureAccountIsActive::class,
            EnforceSessionTimeouts::class,
        ]);
        // مجموعة web تُرتَّب بقائمة الأولويات: وسيط auth يسبق ما يُضاف إليها، فيرى زائراً ويحوّل للدخول
        // قبل أن يُدخل الحارس صاحبَ الكوكي. لذلك يُقدَّم الحارس عليه (بعد بدء الجلسة).
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: AuthenticateFromPlatformSession::class,
        );
        // من مُنع من الدخول في الجلسة الموحدة (بلا دور هنا، أو حساب لم يُربط) يرى سبب المنع لا
        // شاشة دخول تعيده إلى المنصة فتدور.
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($reason = $request->attributes->get('platform_denied')) {
                abort(403, $reason);
            }

            return route('login');
        });
        $middleware->append(SecurityHeaders::class);

        // جذر النطاق الفرعي في Hostinger مجلد داخل جذر الموقع العام، فيصل التطبيق
        // أيضاً عبر wesalinnovation.sa/workspace. خارج التطوير لا يُقبل إلا مضيف
        // APP_URL، فلا تعمل نسخة ثانية على نطاق الموقع العام وكوكيزه.
        // WORKSPACE_HOSTS تسمّي المضيفات المقبولة صراحةً (الموقع الرئيسي مع النطاق الفرعي القديم في
        // مرحلة الانتقال). بدونها السلوك السابق: مضيف APP_URL ونطاقاته الفرعية.
        $middleware->trustHosts(at: function (): array {
            $hosts = config('workspace.hosts');

            if ($hosts === []) {
                return ['^(.+\.)?'.preg_quote((string) parse_url((string) config('app.url'), PHP_URL_HOST), '/').'$'];
            }

            return array_map(fn (string $host): string => '^'.preg_quote($host, '/').'$', $hosts);
        }, subdomains: false);
        $middleware->append(RedirectToCanonicalHost::class);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'platform.credentials' => RedirectToPlatformLogin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
