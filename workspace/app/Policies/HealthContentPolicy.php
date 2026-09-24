<?php

namespace App\Policies;

use App\Enums\HealthContentStatus;
use App\Models\HealthContent;
use App\Models\User;

/**
 * مدير النظام يحرّر، والمدير الطبي يعتمد: لا ينشر أحد محتوى صحياً بقراره
 * وحده.
 */
class HealthContentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['sysadmin', 'medical']);
    }

    public function view(User $user, HealthContent $content): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('sysadmin');
    }

    /**
     * لا تعديل أثناء المراجعة: المراجِع يقرّر على نص ثابت.
     */
    public function update(User $user, HealthContent $content): bool
    {
        return $user->hasRole('sysadmin') && $content->status !== HealthContentStatus::InReview;
    }

    /**
     * حذف مسودة لم تُراجَع قط؛ ما رُوجع يبقى بسجله.
     */
    public function delete(User $user, HealthContent $content): bool
    {
        return $user->hasRole('sysadmin')
            && $content->status === HealthContentStatus::Draft
            && ! $content->isPublished()
            && $content->reviews()->doesntExist();
    }

    public function submit(User $user, HealthContent $content): bool
    {
        return $user->hasRole('sysadmin') && $content->status->canBeSubmitted();
    }

    public function review(User $user, HealthContent $content): bool
    {
        return $user->hasRole('medical') && $content->status === HealthContentStatus::InReview;
    }

    /**
     * تجديد اعتماد منشور لم يتغيّر بعد مراجعته الدورية.
     */
    public function renew(User $user, HealthContent $content): bool
    {
        return $user->hasRole('medical') && $content->status === HealthContentStatus::Approved && $content->isPublished();
    }

    public function withdraw(User $user, HealthContent $content): bool
    {
        return $user->hasAnyRole(['sysadmin', 'medical']) && $content->isPublished();
    }
}
