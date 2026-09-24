<?php

namespace App\Enums;

enum ApprovalStage: string
{
    case Finance = 'finance';
    case Executive = 'executive';

    public function label(): string
    {
        return match ($this) {
            self::Finance => 'مراجعة المالية',
            self::Executive => 'الاعتماد التنفيذي',
        };
    }
}
