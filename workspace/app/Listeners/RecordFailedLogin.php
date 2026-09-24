<?php

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Illuminate\Auth\Events\Failed;

class RecordFailedLogin
{
    /**
     * يُسجَّل البريد المُدخَل وحده، لا كلمة المرور أبداً.
     */
    public function handle(Failed $event): void
    {
        AuditLog::record(
            AuditAction::AuthFailed,
            $event->user,
            ['email' => $event->credentials['email'] ?? null],
        );
    }
}
