<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class StartSessionClock
{
    /**
     * الدخول يبدأ عدّ مهلة الخمول والحد الأقصى من جديد، أياً كان طريقه (كلمة
     * المرور أو قبول الدعوة). يفرضهما EnforceSessionTimeouts.
     */
    public function handle(Login $event): void
    {
        $now = now()->getTimestamp();

        session()->put(['auth.started_at' => $now, 'auth.seen_at' => $now]);
    }
}
