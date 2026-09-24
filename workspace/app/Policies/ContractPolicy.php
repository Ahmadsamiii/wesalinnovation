<?php

namespace App\Policies;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\User;

/**
 * العقود: مدير المشروع يصوغ مسودات عقود مشاريعه، والمالية تصوغ وتفعّل عند
 * التوقيع وتغلق. العميل يطّلع على عقوده الموقّعة ويحمّل نسختها.
 */
class ContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['executive', 'finance', 'pm', 'client']);
    }

    public function view(User $user, Contract $contract): bool
    {
        return $user->hasAnyRole(['executive', 'finance'])
            || $contract->project->pm_id === $user->id
            || ($contract->client_id === $user->id && $contract->status !== ContractStatus::Draft);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['finance', 'pm']);
    }

    public function update(User $user, Contract $contract): bool
    {
        return $contract->status === ContractStatus::Draft
            && ($user->hasRole('finance') || $contract->project->pm_id === $user->id);
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $this->update($user, $contract) && $contract->invoices()->doesntExist();
    }

    public function activate(User $user, Contract $contract): bool
    {
        return $contract->status === ContractStatus::Draft && $user->hasAnyRole(['finance', 'executive']);
    }

    public function close(User $user, Contract $contract): bool
    {
        return $contract->status === ContractStatus::Active && $user->hasAnyRole(['finance', 'executive']);
    }

    /**
     * نسخة العقد ومرفقاته: من يصوغه ما دام لم يُغلق.
     */
    public function upload(User $user, Contract $contract): bool
    {
        return in_array($contract->status, [ContractStatus::Draft, ContractStatus::Active], true)
            && ($user->hasAnyRole(['finance', 'executive']) || $contract->project->pm_id === $user->id);
    }
}
