<?php

namespace App\Enums;

/**
 * الفاتورة المصدرة لا تُعدَّل ولا تُحذف: تُلغى بسبب مكتوب ما لم تُسجَّل عليها
 * دفعة. «متأخرة» ليست حالة مخزّنة بل مصدرة تجاوزت موعد استحقاقها.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Issued => 'مصدرة',
            self::Paid => 'مدفوعة',
            self::Cancelled => 'ملغاة',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft, self::Cancelled => 'gray',
            self::Issued => 'blue',
            self::Paid => 'green',
        };
    }
}
