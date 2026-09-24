<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * قبول الدعوة: صاحب الحساب يعيّن كلمة مروره من الرابط الموقَّع في بريده.
 */
class InvitationController extends Controller
{
    public function show(Request $request, User $user): View|Response
    {
        if (! $this->isUsableInvitation($request, $user)) {
            return response()->view('auth.invitation-invalid', status: 410);
        }

        return view('auth.accept-invitation', ['user' => $user]);
    }

    public function store(Request $request, User $user): RedirectResponse|Response
    {
        if (! $this->isUsableInvitation($request, $user)) {
            return response()->view('auth.invitation-invalid', status: 410);
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->forceFill([
            'password' => $validated['password'],
            'invitation_accepted_at' => now(),
            // وصل الرابط إلى بريده فاستخدمه: البريد مؤكَّد بذلك.
            'email_verified_at' => $user->email_verified_at ?? now(),
            'remember_token' => Str::random(60),
        ])->save();

        AuditLog::record(AuditAction::InvitationAccepted, $user, actor: $user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'مرحباً بك في مساحة عمل وصال. تم تفعيل حسابك.');
    }

    /**
     * الرابط صالح إن كان توقيعه سليماً ولم تنتهِ مدته، ويخص آخر دعوة أُرسلت،
     * ولم يُستخدم بعد، والحساب غير موقوف.
     */
    private function isUsableInvitation(Request $request, User $user): bool
    {
        return $request->hasValidRelativeSignature()
            && $user->invited_at !== null
            && (int) $request->query('invited') === $user->invited_at->getTimestamp()
            && $user->invitation_accepted_at === null
            && ! $user->isDeactivated();
    }
}
