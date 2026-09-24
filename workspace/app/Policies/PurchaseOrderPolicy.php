<?php

namespace App\Policies;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;

/**
 * أوامر الشراء: مدير المشروع يطلب، والمالية تراجع، والتنفيذي يعتمد ما يتجاوز
 * الحد. لا يعتمد أحد طلبه بنفسه.
 */
class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['executive', 'finance', 'pm']);
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $user->hasAnyRole(['executive', 'finance'])
            || $order->requested_by === $user->id
            || $order->project->pm_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['pm', 'finance']);
    }

    public function update(User $user, PurchaseOrder $order): bool
    {
        return $order->status->isEditable()
            && ($order->requested_by === $user->id || $order->project->pm_id === $user->id);
    }

    public function delete(User $user, PurchaseOrder $order): bool
    {
        return $order->status === PurchaseOrderStatus::Draft && $order->requested_by === $user->id;
    }

    public function submit(User $user, PurchaseOrder $order): bool
    {
        return $this->update($user, $order);
    }

    public function review(User $user, PurchaseOrder $order): bool
    {
        return $order->status === PurchaseOrderStatus::PendingFinance
            && $user->hasRole('finance')
            && $order->requested_by !== $user->id;
    }

    public function decide(User $user, PurchaseOrder $order): bool
    {
        return $order->status === PurchaseOrderStatus::PendingExecutive
            && $user->hasRole('executive')
            && $order->requested_by !== $user->id;
    }

    public function receive(User $user, PurchaseOrder $order): bool
    {
        return $order->status === PurchaseOrderStatus::Approved
            && ($user->hasRole('finance') || $order->project->pm_id === $user->id);
    }

    public function cancel(User $user, PurchaseOrder $order): bool
    {
        return ! in_array($order->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Received, PurchaseOrderStatus::Cancelled], true)
            && $user->hasAnyRole(['finance', 'executive']);
    }

    /**
     * عروض الأسعار قبل الطلب وفاتورة المورّد بعد الاستلام.
     */
    public function upload(User $user, PurchaseOrder $order): bool
    {
        return $order->status !== PurchaseOrderStatus::Cancelled
            && ($user->hasRole('finance') || $order->requested_by === $user->id || $order->project->pm_id === $user->id);
    }
}
