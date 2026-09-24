<?php

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;

class CompleteInvitationOnPasswordReset
{
    /**
     * من فاته رابط دعوته واستخدم «نسيت كلمة المرور» قد عيّن كلمة مروره من
     * بريده فعلاً، فالدعوة مقبولة والبريد مؤكَّد.
     */
    public function handle(PasswordReset $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill([
            'invitation_accepted_at' => $event->user->invitation_accepted_at ?? now(),
            'email_verified_at' => $event->user->email_verified_at ?? now(),
        ])->save();

        AuditLog::record(AuditAction::PasswordReset, $event->user, actor: $event->user);
    }
}
