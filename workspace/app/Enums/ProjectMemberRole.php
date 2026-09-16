<?php

namespace App\Enums;

enum ProjectMemberRole: string
{
    case Lead = 'lead';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Lead => 'قائد الفريق',
            self::Member => 'عضو',
        };
    }
}
