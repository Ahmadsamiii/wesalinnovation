<?php

namespace App\Enums;

enum HealthContentCategory: string
{
    case Rights = 'rights';
    case HealthCare = 'health';
    case Rehabilitation = 'rehabilitation';
    case MentalHealth = 'mental_health';
    case AssistiveTech = 'assistive_tech';
    case Caregivers = 'caregivers';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Rights => 'حقوق وخدمات ذوي الإعاقة',
            self::HealthCare => 'رعاية صحية',
            self::Rehabilitation => 'تأهيل وعلاج طبيعي',
            self::MentalHealth => 'صحة نفسية',
            self::AssistiveTech => 'تقنيات مساعدة',
            self::Caregivers => 'إرشاد الأسر ومقدّمي الرعاية',
            self::Other => 'أخرى',
        };
    }
}
