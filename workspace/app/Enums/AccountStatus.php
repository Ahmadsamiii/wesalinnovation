<?php

namespace App\Enums;

/**
 * حالة الحساب مشتقة من تواريخه لا مخزّنة: الإيقاف يغلب على كل شيء، ثم
 * الدعوة التي لم تُقبل بعد.
 */
enum AccountStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Deactivated = 'deactivated';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'نشط',
            self::Pending => 'بانتظار قبول الدعوة',
            self::Deactivated => 'موقوف',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Pending => 'yellow',
            self::Deactivated => 'red',
        };
    }
}
