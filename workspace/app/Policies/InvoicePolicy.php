<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;

/**
 * الفواتير شأن المالية وحدها إنشاءً وإصداراً وتحصيلاً. مدير المشروع والتنفيذي
 * يطّلعان، والعميل يرى فواتيره المصدرة ويطبعها.
 */
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['executive', 'finance', 'pm', 'client']);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasAnyRole(['executive', 'finance'])
            || $invoice->project->pm_id === $user->id
            || ($invoice->client_id === $user->id && in_array($invoice->status, [InvoiceStatus::Issued, InvoiceStatus::Paid], true));
    }

    public function create(User $user): bool
    {
        return $user->hasRole('finance');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $invoice->status === InvoiceStatus::Draft && $user->hasRole('finance');
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }

    public function issue(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $invoice->status === InvoiceStatus::Issued && $user->hasRole('finance');
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $invoice->status === InvoiceStatus::Issued && $user->hasRole('finance') && $invoice->payments()->doesntExist();
    }
}
