<?php

namespace App\Enums;

/**
 * مسودة ──تقديم──▶ قيد المراجعة الطبية ──اعتماد──▶ معتمد (منشور)
 *   ▲                    │                            │
 *   └──تعديل── مرفوض ◀──رفض                          └──سحب──▶ مسحوب
 *
 * تعديل محتوى معتمد يعيده مسودة، وتبقى نسخته المعتمدة منشورة حتى يُعتمد
 * التعديل أو يُسحب المحتوى.
 */
enum HealthContentStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::InReview => 'قيد المراجعة الطبية',
            self::Approved => 'معتمد',
            self::Rejected => 'مرفوض — يحتاج تعديلاً',
            self::Withdrawn => 'مسحوب',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::InReview => 'yellow',
            self::Approved => 'green',
            self::Rejected => 'red',
            self::Withdrawn => 'orange',
        };
    }

    public function canBeSubmitted(): bool
    {
        return in_array($this, [self::Draft, self::Rejected, self::Withdrawn], true);
    }
}
