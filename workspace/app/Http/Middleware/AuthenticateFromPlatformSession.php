<?php

namespace App\Http\Middleware;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\PlatformSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * الدخول الموحد: الجلسة في المنصة العامة هي المرجع (App\Support\PlatformSession).
 * كل طلب يقارن الجلسة المحلية بصف المنصة الذي يشير إليه الكوكي wesal_auth:
 *
 *  - صف حي لحساب له دور في مساحة العمل: يُدخَل الحساب المحلي المربوط به
 *    (ويُنشأ إن كان بريده جديداً)، وتُزامَن بياناته ودوره من المنصة، ويتجدد آخر نشاط.
 *  - لا صف أو انتهى (خروج أو إيقاف أو خمول في أي مكان): تنتهي الجلسة المحلية.
 *  - حساب في المنصة بلا دور هنا، أو محلي بالبريد نفسه لم يُربط بعد، أو موقوف:
 *    لا دخول، وتُرفع رسالة السبب حين يطلب المستخدم صفحة تتطلب الدخول
 *    (redirectGuestsTo في bootstrap/app.php)، فتبقى الصفحات العامة مفتوحة.
 *
 * لا يربط حساباً محلياً موجوداً بالبريد وحده: الربط بأداة الربط بعد مراجعة
 * تقريرها. وأي عطل في قاعدة المنصة يُسجَّل ويترك الطلب على قواعد الجلسة
 * المحلية بدل أن يُخرج الجميع.
 */
class AuthenticateFromPlatformSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! PlatformSession::enabled()) {
            return $next($request);
        }

        try {
            $this->sync($request);
            $request->attributes->set('platform_session_checked', true);
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
        }

        return $next($request);
    }

    private function sync(Request $request): void
    {
        $user = $request->user();
        $token = PlatformSession::token($request);
        $row = $token === null ? null : PlatformSession::find($token);
        $state = $row === null ? 'none' : PlatformSession::state($row);

        if ($state !== 'live') {
            if ($user instanceof User) {
                in_array($state, ['idle', 'max'], true)
                    ? EnforceSessionTimeouts::endSession($request, $user, $state === 'idle' ? 'idle' : 'expired')
                    : $this->logoutLocal($request);
            }
            if ($row !== null && in_array($state, ['idle', 'max'], true)) {
                PlatformSession::end((int) $row->session_id, $state);
            }
            if ($token !== null) {
                PlatformSession::forgetCookie();
            }

            return;
        }

        if (($row->status ?? 'active') === 'suspended') {
            PlatformSession::end((int) $row->session_id, 'suspended');
            PlatformSession::forgetCookie();
            $this->deny($request, $user, __('auth.deactivated'));

            return;
        }

        $role = PlatformSession::orgRole($row);
        if ($role === null) {
            $this->deny($request, $user, __('auth.no_workspace_role'));

            return;
        }

        $local = $this->localUser($row, $role);
        if ($local === null) {
            $this->deny($request, $user, __('auth.not_linked'));

            return;
        }

        if ($local->isDeactivated()) {
            $this->deny($request, $user, __('auth.deactivated'));

            return;
        }

        if (! $user instanceof User || ! $user->is($local)) {
            Auth::guard('web')->login($local);
            $request->session()->regenerate();
        }

        PlatformSession::touch((int) $row->session_id);
    }

    /**
     * يمنع الدخول ويحفظ السبب ليظهر عند أول صفحة تتطلب الدخول.
     */
    private function deny(Request $request, mixed $user, string $message): void
    {
        if ($user instanceof User) {
            $this->logoutLocal($request);
        }

        $request->attributes->set('platform_denied', $message);
    }

    private function logoutLocal(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /**
     * الحساب المحلي لصاحب الجلسة: المربوط برقمه في المنصة، أو جديد إن لم يوجد
     * محلي بالبريد نفسه (يُترك للربط المراجَع). null إن وُجد ولم يُربط.
     */
    private function localUser(object $row, string $role): ?User
    {
        $local = User::query()->where('platform_user_id', $row->user_id)->first();

        if ($local === null) {
            if (User::query()->where('email', $row->email)->exists()) {
                return null;
            }

            $local = (new User)->forceFill([
                'name' => $row->name,
                'email' => $row->email,
                'phone' => $row->phone,
                'password' => Str::random(64),
                'platform_user_id' => $row->user_id,
                'invitation_accepted_at' => now(),
                'email_verified_at' => now(),
            ]);
            $local->save();
        } else {
            $this->syncProfile($local, $row);
        }

        $this->syncRole($local, $role);

        return $local;
    }

    /**
     * الاسم والبريد والجوال من المنصة. لا يُغيَّر البريد إن كان لحساب محلي آخر.
     */
    private function syncProfile(User $local, object $row): void
    {
        $changes = array_filter([
            'name' => $row->name !== $local->name ? $row->name : null,
            'phone' => $row->phone !== $local->phone ? $row->phone : null,
            'email' => $row->email !== $local->email
                && ! User::query()->where('email', $row->email)->whereKeyNot($local->getKey())->exists()
                ? $row->email : null,
            'invitation_accepted_at' => $local->invitation_accepted_at === null ? now() : null,
        ], fn ($value) => $value !== null);

        if ($changes !== []) {
            $local->forceFill($changes)->saveQuietly();
        }
    }

    private function syncRole(User $local, string $role): void
    {
        $current = $local->roleName();
        if ($current === $role) {
            return;
        }

        $local->assignSingleRole($role);
        $local->unsetRelation('roles');

        if ($current !== null) {
            AuditLog::record(AuditAction::UserRoleChanged, $local, ['from' => $current, 'to' => $role, 'source' => 'platform'], actor: $local);
        }
    }
}
