<?php

namespace App\Enums;

/**
 * مسار أمر الشراء: مسودة ← مراجعة المالية ← (اعتماد تنفيذي إن تجاوز الحد) ←
 * معتمد ← مستلَم. الرفض في أي مرحلة يعيده لمقدّمه ليعدّله ويعيد تقديمه.
 */
enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case PendingFinance = 'pending_finance';
    case PendingExecutive = 'pending_executive';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::PendingFinance => 'بانتظار مراجعة المالية',
            self::PendingExecutive => 'بانتظار الاعتماد التنفيذي',
            self::Approved => 'معتمد',
            self::Rejected => 'مرفوض',
            self::Received => 'مستلَم',
            self::Cancelled => 'ملغى',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft, self::Cancelled => 'gray',
            self::PendingFinance, self::PendingExecutive => 'yellow',
            self::Approved => 'blue',
            self::Rejected => 'red',
            self::Received => 'green',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }

    /**
     * التزام مالي قائم أو محتمل على ميزانية المشروع.
     *
     * @return list<self>
     */
    public static function committed(): array
    {
        return [self::PendingFinance, self::PendingExecutive, self::Approved, self::Received];
    }
}
