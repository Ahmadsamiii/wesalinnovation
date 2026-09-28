<?php

namespace App\Policies;

use App\Enums\ReferenceLetterStatus;
use App\Models\ReferenceLetter;
use App\Models\User;

/**
 * الإفادة لمنسوبي المنشأة وحدهم (لا العملاء). يعتمدها مدير الموارد البشرية،
 * وللمدير التنفيذي القرار نفسه ما دام لا يوجد مدير موارد نشط، كي لا يتوقف
 * الاعتماد قبل أول حساب لهذا الدور. لا يعتمد أحد إفادته بنفسه.
 */
class ReferenceLetterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->roleName() !== null && ! $user->hasRole('client');
    }

    public function view(User $user, ReferenceLetter $letter): bool
    {
        return $letter->requester_id === $user->id || $user->hasAnyRole(ReferenceLetter::SEES_ALL_ROLES);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function decide(User $user, ReferenceLetter $letter): bool
    {
        return $letter->status === ReferenceLetterStatus::Pending
            && $letter->requester_id !== $user->id
            && $this->isDecider($user, $letter);
    }

    public function print(User $user, ReferenceLetter $letter): bool
    {
        return $letter->status === ReferenceLetterStatus::Approved && $this->view($user, $letter);
    }

    /**
     * إفادة مدير الموارد نفسه لا يعتمدها هو، فيعتمدها المدير التنفيذي حتى مع
     * وجود مدير موارد نشط، وإلا بقيت معلّقة إن لم يوجد غيره.
     */
    private function isDecider(User $user, ReferenceLetter $letter): bool
    {
        if ($user->hasRole('hr')) {
            return true;
        }

        if (! $user->hasRole('executive')) {
            return false;
        }

        return ! User::hasActiveHr() || $letter->requester->hasRole('hr');
    }
}
