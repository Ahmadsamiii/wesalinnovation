<?php

namespace App\Enums;

enum EmploymentType: string
{
    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case Contract = 'contract';
    case Internship = 'internship';

    public function label(): string
    {
        return match ($this) {
            self::FullTime => 'دوام كامل',
            self::PartTime => 'دوام جزئي',
            self::Contract => 'عقد مؤقت',
            self::Internship => 'تدريب تعاوني',
        };
    }
}
