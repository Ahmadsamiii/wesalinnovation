<?php

namespace App\Policies;

use App\Enums\ReferenceLetterStatus;
use App\Models\ReferenceLetter;
use App\Models\User;

/**
 * الإفادة لمنسوبي المنشأة وحدهم (لا العملاء)، ويعتمدها المدير التنفيذي. لا
 * يعتمد أحد إفادته بنفسه.
 */
class ReferenceLetterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->roleName() !== null && ! $user->hasRole('client');
    }

    public function view(User $user, ReferenceLetter $letter): bool
    {
        return $letter->requester_id === $user->id || $user->hasRole('executive');
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function decide(User $user, ReferenceLetter $letter): bool
    {
        return $letter->status === ReferenceLetterStatus::Pending
            && $user->hasRole('executive')
            && $letter->requester_id !== $user->id;
    }

    public function print(User $user, ReferenceLetter $letter): bool
    {
        return $letter->status === ReferenceLetterStatus::Approved && $this->view($user, $letter);
    }
}
