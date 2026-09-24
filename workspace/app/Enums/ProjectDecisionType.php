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
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'اعتماد',
            self::Rejected => 'رفض',
            self::OnHold => 'إيقاف مؤقت',
            self::Resumed => 'استئناف',
            self::Cancelled => 'إلغاء',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Approved, self::Resumed => 'green',
            self::Rejected, self::Cancelled => 'red',
            self::OnHold => 'orange',
        };
    }

    /**
     * ما يوقف العمل أو يرفضه يُعلَّل دائماً؛ الاعتماد والاستئناف قد يمرّان بلا تعليل.
     */
    public function requiresNote(): bool
    {
        return in_array($this, [self::Rejected, self::OnHold, self::Cancelled], true);
    }

    /**
     * هل يجوز هذا القرار على مشروع في هذه الحالة؟
     */
    public function isAllowedFrom(ProjectStatus $status): bool
    {
        return match ($this) {
            self::Approved, self::Rejected => $status === ProjectStatus::PendingApproval,
            self::OnHold => in_array($status, [ProjectStatus::Approved, ProjectStatus::InProgress], true),
            self::Resumed => $status === ProjectStatus::OnHold,
            self::Cancelled => ! $status->isTerminal(),
        };
    }
}
