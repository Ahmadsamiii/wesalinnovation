<?php

namespace App\Enums;

enum ApprovalDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'اعتماد',
            self::Rejected => 'رفض',
        };
    }

    public function color(): string
    {
        return $this === self::Approved ? 'green' : 'red';
    }
}
