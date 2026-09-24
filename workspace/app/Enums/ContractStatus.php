<?php

namespace App\Enums;

/**
 * مسودة ← ساري (بعد التوقيع) ← منتهٍ أو مفسوخ. العقد الساري لا تُعدَّل بنوده.
 */
enum ContractStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Active => 'ساري',
            self::Completed => 'منتهٍ',
            self::Terminated => 'مفسوخ',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Active => 'green',
            self::Completed => 'blue',
            self::Terminated => 'red',
        };
    }
}
