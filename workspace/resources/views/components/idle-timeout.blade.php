{{-- تنبيه الخروج التلقائي (idleTimeout في app.js). الوسيط EnforceSessionTimeouts يفرض المهلة نفسها على الخادم. --}}
@php
    $minutes = (int) config('session.idle_timeout');
@endphp

<div x-data="idleTimeout(@js([
        'minutes' => $minutes,
        'heartbeatUrl' => route('session.heartbeat'),
        'timeoutUrl' => route('session.timeout'),
        'logoutUrl' => route('logout'),
        'loginUrl' => route('login'),
    ]))"
    x-show="open" x-cloak
    role="alertdialog" aria-modal="true" aria-labelledby="idle-title" aria-describedby="idle-description"
    class="no-print fixed inset-0 z-[60] flex items-center justify-center bg-brand-ink/50 p-4">
    <div class="w-full max-w-md rounded-2xl border border-brand-border bg-white p-6 shadow-lg">
        <h2 id="idle-title" class="text-lg font-bold text-brand-ink">هل ما زلت هنا؟</h2>
        <p id="idle-description" class="mt-2 text-sm leading-7 text-brand-text">
            لم نلحظ أي نشاط منذ مدة. حفاظاً على بيانات العمل نسجّل خروجك بعد {{ trans_choice('auth.minutes', $minutes) }} دون نشاط.
        </p>
        <p class="mt-3 text-brand-ink" aria-hidden="true">يُسجَّل خروجك خلال <b class="tabular-nums" x-text="remaining"></b></p>
        <p class="mt-2 text-sm text-brand-muted">أي حركة أو ضغطة زر تُبقيك متصلاً.</p>
        <div class="mt-5">
            <x-primary-button type="button" x-ref="stay" @click="stay()">أبقني متصلاً</x-primary-button>
        </div>
        <p class="sr-only" role="status" aria-live="polite" x-text="announcement"></p>
    </div>
</div>
