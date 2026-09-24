<?php

namespace App\Policies;

use App\Models\Certificate;
use App\Models\User;

/**
 * مدير المشروع يصدر شهادات مشاريعه المنجزة، والتنفيذي يصدر لأي مشروع ويلغي.
 * صاحب الشهادة يطّلع عليها ويطبعها.
 */
class CertificatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->roleName() !== null;
    }

    public function view(User $user, Certificate $certificate): bool
    {
        return $user->hasRole('executive')
            || $certificate->recipient_id === $user->id
            || $certificate->project->pm_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['pm', 'executive']);
    }

    public function revoke(User $user, Certificate $certificate): bool
    {
        return $user->hasRole('executive') && ! $certificate->isRevoked();
    }
}
