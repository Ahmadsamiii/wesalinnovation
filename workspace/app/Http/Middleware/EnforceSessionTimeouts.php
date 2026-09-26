<?php

namespace App\Http\Middleware;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * الخروج التلقائي يفرضه الخادم: الواجهة ترصد حركة المستخدم وتنبّهه قبل الخروج
 * وترسل نبضة عند النشاط، لكن الجلسة تنتهي هنا مهما فعل المتصفح أو لم يفعل
 * (تبويب مغلق، جهاز نائم، كوكي مسروق).
 *
 * كل طلب في مساحة العمل من فعل المستخدم (لا استطلاع آلي فيها)، فكل طلب يجدّد
 * آخر نشاط. الهامش فوق المهلة لأن النبضة تصل مرة في الدقيقة على الأكثر، فلا
 * يسبق الخادمُ تنبيهَ الواجهة.
 */
class EnforceSessionTimeouts
{
    private const GRACE_SECONDS = 120;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $session = $request->session();
        $now = now()->getTimestamp();
        // جلسة فُتحت قبل هذه الميزة، أو بـ actingAs في الاختبارات: يبدأ عدّها الآن.
        $seenAt = (int) $session->get('auth.seen_at', $now);
        $startedAt = (int) $session->get('auth.started_at', $now);

        $reason = match (true) {
            $now - $seenAt > config('session.idle_timeout') * 60 + self::GRACE_SECONDS => 'idle',
            $now - $startedAt > config('session.absolute_timeout') * 60 => 'expired',
            default => null,
        };

        if ($reason !== null) {
            $message = self::endSession($request, $user, $reason);

            return $request->expectsJson()
                ? response()->json(['message' => $message, 'reason' => $reason], 401)
                : redirect()->route('login');
        }

        $session->put(['auth.started_at' => $startedAt, 'auth.seen_at' => $now]);

        return $next($request);
    }

    /**
     * ينهي الجلسة لسبب آلي ويسجّله في سجل التدقيق، ويترك سبب الخروج رسالةً في
     * صفحة الدخول. $reason: idle (خمول) أو expired (بلغت الحد الأقصى).
     */
    public static function endSession(Request $request, User $user, string $reason): string
    {
        AuditLog::record(AuditAction::AuthTimedOut, $user, ['reason' => $reason], actor: $user);

        // logout يُبطل رمز «تذكرني» أيضاً إن بقي منه شيء قبل إزالة الخيار.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = $reason === 'idle'
            ? __('auth.timeout_idle', ['minutes' => trans_choice('auth.minutes', config('session.idle_timeout'))])
            : __('auth.timeout_expired');
        $request->session()->flash('status', $message);

        return $message;
    }
}
