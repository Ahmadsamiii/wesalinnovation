<?php

namespace App\Enums;

/**
 * سجل قرارات الاعتماد التنفيذي — تاريخ كامل، لا آخر حالة فقط. مشروع
 * يُرفض ثم يُعاد تقديمه واعتماده يترك أثرين هنا، لا أثراً واحداً يُستبدَل.
 */
enum ProjectDecisionType: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case OnHold = 'on_hold';
    case Resumed = 'resumed';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'اعتماد',
            self::Rejected => 'رفض',
            self::OnHold => 'إيقاف مؤقت',
            self::Resumed => 'استئناف',
        };
    }
}
