<?php

namespace App\Enums;

/**
 * دورة حياة المشروع. rejected طرف نهائي قابل لإعادة التقديم (يعود
 * PendingApproval)، لا يُنشأ مشروع جديد لكل محاولة اعتماد.
 *
 *   مسودة ──تقديم──▶ بانتظار الاعتماد ──اعتماد──▶ معتمَد ──بدء──▶ قيد التنفيذ ──إنجاز──▶ منجَز
 *     ▲                   │                        │                  │
 *     └──(إعادة تقديم)── مرفوض                    └──إيقاف مؤقت──▶ متوقف ──استئناف──┘
 *
 * الإلغاء قرار تنفيذي من أي حالة غير نهائية.
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

    public function color(): string
    {
        return match ($this) {
            self::Draft, self::Cancelled => 'gray',
            self::PendingApproval => 'yellow',
            self::Approved => 'blue',
            self::Rejected => 'red',
            self::InProgress => 'brand',
            self::OnHold => 'orange',
            self::Completed => 'green',
        };
    }

    /**
     * لا تعديل ولا تخطيط بعد الإنجاز أو الإلغاء: المشروع صار سجلاً.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * يقدّمه مدير المشروع للاعتماد: أول مرة، أو بعد رفض وتعديل.
     */
    public function canBeSubmitted(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }

    /**
     * التنفيذ الفعلي للمهام متوقف قبل الاعتماد، وأثناء الإيقاف، وبعد الإغلاق.
     */
    public function allowsTaskProgress(): bool
    {
        return in_array($this, [self::Approved, self::InProgress], true);
    }
}
