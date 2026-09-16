<?php

namespace App\Enums;

/**
 * دورة حياة المشروع. rejected طرف نهائي قابل لإعادة التقديم (يعود
 * PendingApproval)، لا يُنشأ مشروع جديد لكل محاولة اعتماد.
 */
enum ProjectStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::PendingApproval => 'بانتظار الاعتماد',
            self::Approved => 'معتمَد',
            self::Rejected => 'مرفوض',
            self::InProgress => 'قيد التنفيذ',
            self::OnHold => 'متوقف مؤقتاً',
            self::Completed => 'منجَز',
            self::Cancelled => 'ملغى',
        };
    }
}
