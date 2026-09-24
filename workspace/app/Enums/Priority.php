<?php

namespace App\Enums;

/**
 * نفس مفردات الأولوية في نظام تذاكر الدعم (api/tickets.php بالمستودع
 * الرئيسي) — اتساق بين النظامين لا اتفاق تقني بينهما.
 */
enum Priority: string
{
    case Critical = 'critical';
    case High = 'high';
    case Normal = 'normal';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'حرجة',
            self::High => 'عالية',
            self::Normal => 'عادية',
            self::Low => 'منخفضة',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Critical => 'red',
            self::High => 'orange',
            self::Normal => 'blue',
            self::Low => 'gray',
        };
    }
}
