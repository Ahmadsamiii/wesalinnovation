<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * الإيقاف بديل الحذف: يمنع الدخول فوراً (EnsureAccountIsActive يُخرج الجلسات
 * المفتوحة) ويُبقي الحساب منسوباً إليه كل ما أنشأه أو قرّره.
 */
class UserActivationController extends Controller
{
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'لا يمكنك إيقاف حسابك بنفسك.');
        }

        if ($user->isLastActiveSysadmin()) {
            return back()->with('error', 'هذا آخر مدير نظام نشط؛ عيّن مديراً آخر قبل إيقافه.');
        }

        if (! $user->isDeactivated()) {
            // تدوير رمز «تذكرني» يُبطل ملفات الدخول التلقائي على كل الأجهزة.
            $user->forceFill(['deactivated_at' => now(), 'remember_token' => Str::random(60)])->save();
            AuditLog::record(AuditAction::UserDeactivated, $user);
        }

        return back()->with('status', "أُوقف حساب {$user->name}.");
    }

    public function store(User $user): RedirectResponse
    {
        if ($user->isDeactivated()) {
            $user->forceFill(['deactivated_at' => null])->save();
            AuditLog::record(AuditAction::UserReactivated, $user);
        }

        return back()->with('status', "أُعيد تفعيل حساب {$user->name}.");
    }
}
