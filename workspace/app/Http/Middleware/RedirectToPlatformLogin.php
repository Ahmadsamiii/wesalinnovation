<?php

namespace App\Http\Middleware;

use App\Support\PlatformSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * مسارات الدخول المحلية (الدخول، نسيت كلمة المرور، إعادة التعيين، قبول الدعوة،
 * تغيير كلمة المرور) تنتقل إلى المنصة حين يكون الدخول موحداً: كلمة المرور
 * والحساب هناك، ولا يجوز أن يبقى هنا باب ثانٍ بكلمة مرور مختلفة. مطفأً لا يفعل شيئاً.
 *
 * الوضع profile: صفحة الملف الشخصي (مستخدم داخل) تعود بالتنبيه بدل الانتقال.
 */
class RedirectToPlatformLogin
{
    public function handle(Request $request, Closure $next, string $mode = 'login'): Response
    {
        if (! PlatformSession::enabled()) {
            return $next($request);
        }

        if ($mode === 'profile') {
            return back()->with('status', __('auth.managed_on_platform'));
        }

        return redirect()->away(PlatformSession::loginUrl($request));
    }
}
