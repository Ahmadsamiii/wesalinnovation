<?php

namespace App\Enums;

/**
 * نتيجة مراجعة المدير الطبي لسؤال حساس وجوابه.
 */
enum AlertOutcome: string
{
    case Safe = 'safe';
    case ContentGap = 'content_gap';
    case UnsafeAnswer = 'unsafe_answer';
    case Escalated = 'escalated';

    public function label(): string
    {
        return match ($this) {
            self::Safe => 'الجواب سليم',
            self::ContentGap => 'يحتاج محتوى معتمداً',
            self::UnsafeAnswer => 'جواب غير آمن',
            self::Escalated => 'صُعِّد لمتابعة عاجلة',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Safe => 'green',
            self::ContentGap => 'blue',
            self::UnsafeAnswer => 'red',
            self::Escalated => 'orange',
        };
    }
}
