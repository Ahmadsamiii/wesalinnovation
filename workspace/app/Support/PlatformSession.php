<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;

/**
 * الجلسة الموحدة مع المنصة العامة (api/session-lib.php في جذر المستودع).
 *
 * المنصة تُنشئ لكل دخول صفاً في auth_sessions وتضع الكوكي wesal_auth برمزه
 * العشوائي على النطاق الأعلى. هذا الصف هو المرجع الوحيد لوقت الدخول وآخر نشاط
 * والحدّين، ومساحة العمل تقرؤه وتجدّد آخر نشاط فيه: النشاط هنا يُبقي الجلسة في
 * المنصة والعكس، والخروج أو الإيقاف في أحدهما يُخرج الآخر عند طلبه التالي.
 *
 * هذا الصنف هو الوجه الآخر لعقد المنصة: اسم الكوكي، وأعمدة الجدول، ومعنى
 * ended_at وidle_sec وmax_sec، وهامش الخمول (120 ثانية). تغيير أي منها يستلزم
 * تغيير الطرفين معاً، ويفحص tests/Feature/Auth/UnifiedSessionTest.php الطرف
 * هنا وtools/check-auth-session.php في الجذر الطرف الآخر.
 *
 * يعمل فقط حين UNIFIED_AUTH=true وقاعدة المنصة مضبوطة (PLATFORM_DB_*). غير ذلك
 * يبقى الدخول المحلي كما كان تماماً.
 */
final class PlatformSession
{
    public static function enabled(): bool
    {
        return (bool) config('workspace.unified_auth')
            && filled(config('database.connections.platform.database'));
    }

    public static function cookieName(): string
    {
        return (string) config('workspace.auth_cookie', 'wesal_auth');
    }

    /**
     * رمز الجلسة من الكوكي: 64 خانة سداسية أو لا شيء. الكوكي غير مشفَّر عند
     * لارافل عمداً (تضعه المنصة لا هذا التطبيق).
     */
    public static function token(Request $request): ?string
    {
        $token = $request->cookies->get(self::cookieName());

        return is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) === 1 ? $token : null;
    }

    /**
     * صف الجلسة مع بيانات صاحبها من المنصة، أو null.
     */
    public static function find(string $token): ?object
    {
        return DB::connection('platform')->table('auth_sessions as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('s.token_hash', hash('sha256', $token))
            ->first([
                's.id as session_id', 's.user_id', 's.auth_at', 's.seen_at', 's.idle_sec', 's.max_sec',
                's.ended_at', 'u.status', 'u.role', 'u.org_role', 'u.name', 'u.email', 'u.phone',
            ]);
    }

    /**
     * حالة الصف الآن: live أو idle أو max أو ended. القاعدة نفسها في المنصة
     * (authRowState): الخمول مع هامش، والحد الأقصى من لحظة الدخول.
     */
    public static function state(object $row, ?int $now = null): string
    {
        $now ??= now()->getTimestamp();
        $grace = (int) config('workspace.session_grace_seconds', 120);

        return match (true) {
            $row->ended_at !== null => 'ended',
            $now - (int) $row->seen_at > (int) $row->idle_sec + $grace => 'idle',
            $now - (int) $row->auth_at > (int) $row->max_sec => 'max',
            default => 'live',
        };
    }

    /**
     * دور صاحب الجلسة في مساحة العمل: مدير النظام في المنصة (admin) هو sysadmin
     * هنا (يُشتق ولا يُخزَّن مرتين)، وغيره من org_role. دور غير معرَّف في
     * config/roles.php يُعامَل كأنه بلا دور.
     */
    public static function orgRole(object $row): ?string
    {
        $role = $row->role === 'admin' ? 'sysadmin' : (($row->org_role ?? null) ?: null);

        return $role !== null && array_key_exists($role, config('roles')) ? $role : null;
    }

    /**
     * يجدّد آخر نشاط: كل طلب إلى مساحة العمل من فعل المستخدم.
     */
    public static function touch(int $sessionId): void
    {
        $now = now()->getTimestamp();

        DB::connection('platform')->table('auth_sessions')
            ->where('id', $sessionId)->whereNull('ended_at')->where('seen_at', '<', $now)
            ->update(['seen_at' => $now]);
    }

    /**
     * ينهي الصف فيخرج صاحبه من المنصة أيضاً. لا يُنهي صفاً منتهياً أصلاً.
     */
    public static function end(int $sessionId, string $why): void
    {
        DB::connection('platform')->table('auth_sessions')
            ->where('id', $sessionId)->whereNull('ended_at')
            ->update(['ended_at' => now()->getTimestamp(), 'ended_why' => mb_substr($why, 0, 16)]);
    }

    /**
     * ينهي صف جلسة هذا المتصفح (إن وُجد) ويمسح كوكيه. الخروج لا يتعطل لعطل في القاعدة.
     */
    public static function endFromRequest(Request $request, string $why): void
    {
        try {
            $token = self::token($request);
            if ($token !== null && ($row = self::find($token)) !== null) {
                self::end((int) $row->session_id, $why);
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        self::forgetCookie();
    }

    /**
     * يمسح الكوكي الميت من المتصفح، بنطاقه نفسه الذي وضعته به المنصة.
     */
    public static function forgetCookie(): void
    {
        Cookie::queue(Cookie::forget(self::cookieName(), '/', self::cookieDomain()));
    }

    /**
     * نطاق الكوكي: النطاق الأعلى بنقطة، كما تشتقه المنصة من SITE_URL (يحذف
     * www وchat وworkspace من أوله). يُضبط صراحةً بـ UNIFIED_AUTH_COOKIE_DOMAIN.
     */
    public static function cookieDomain(): ?string
    {
        $configured = config('workspace.auth_cookie_domain');
        if ($configured !== null && $configured !== '') {
            return (string) $configured;
        }

        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $host = (string) preg_replace('/^(www|chat|workspace)\./', '', $host);

        if ($host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) || ! str_contains($host, '.')) {
            return null;
        }

        return '.'.$host;
    }

    /**
     * شاشة الدخول الموحدة، مع الوجهة التي يعود إليها المستخدم بعد الدخول.
     */
    public static function loginUrl(Request $request): string
    {
        $next = $request->session()->get('url.intended') ?: url('/dashboard');

        return config('workspace.login_url').'?next='.rawurlencode((string) $next);
    }
}
