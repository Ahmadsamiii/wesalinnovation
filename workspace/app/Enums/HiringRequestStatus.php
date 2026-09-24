<?php

namespace App\Enums;

/**
 * بانتظار الاعتماد ──اعتماد──▶ معتمد (قيد التوظيف) ──شغل──▶ شُغلت
 *        │                          │
 *        ├──رفض──▶ مرفوض            └──إلغاء──▶ ملغى
 *        └──سحب──▶ ملغى
 */
enum HiringRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Filled = 'filled';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار الاعتماد',
            self::Approved => 'معتمد — قيد التوظيف',
            self::Rejected => 'مرفوض',
            self::Filled => 'شُغلت',
            self::Cancelled => 'ملغى',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Approved => 'blue',
            self::Rejected => 'red',
            self::Filled => 'green',
            self::Cancelled => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Approved], true);
    }
}
