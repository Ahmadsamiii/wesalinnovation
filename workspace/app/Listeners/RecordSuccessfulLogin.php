<?php

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Login;

class RecordSuccessfulLogin
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();

        AuditLog::record(AuditAction::AuthLogin, $event->user, actor: $event->user);
    }
}
