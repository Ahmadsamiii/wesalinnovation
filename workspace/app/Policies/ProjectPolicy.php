<?php

namespace App\Policies;

use App\Enums\ProjectDecisionType;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;

/**
 * من يرى المشروع ومن يغيّره. مدير المشروع (pm_id) يدير مشروعه، والمدير
 * التنفيذي يعتمد ويوقف ويلغي ويستطيع التدخل في أي مشروع، والعميل يرى
 * مشاريعه بلا تفاصيل العمل الداخلي، والعضو يرى مشروعه ويعمل على مهامه.
 */
class ProjectPolicy
{
    /**
     * القائمة نفسها مفلترة بـ Project::visibleTo، فكل مستخدم بدور يرى ما يخصه.
     */
    public function viewAny(User $user): bool
    {
        return $user->roleName() !== null;
    }

    public function view(User $user, Project $project): bool
    {
        return $user->hasAnyRole(['executive', 'finance'])
            || $project->pm_id === $user->id
            || $project->client_id === $user->id
            || $project->hasMember($user);
    }

    /**
     * تفاصيل العمل الداخلي (المهام والتعليقات والفريق والقرارات) مخفية عن العميل.
     */
    public function viewInternals(User $user, Project $project): bool
    {
        return $this->view($user, $project) && ! $user->hasRole('client');
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['pm', 'executive']);
    }

    public function update(User $user, Project $project): bool
    {
        return $project->isManagedBy($user) && ! $project->status->isTerminal();
    }

    /**
     * حذف مسودة لم تُقدَّم بعد فقط؛ ما بعدها يُلغى بقرار ويبقى سجله.
     */
    public function delete(User $user, Project $project): bool
    {
        return $project->pm_id === $user->id
            && $project->status === ProjectStatus::Draft
            && $project->submitted_at === null
            && $project->contracts()->doesntExist()
            && $project->purchaseOrders()->doesntExist()
            && $project->invoices()->doesntExist();
    }

    public function submit(User $user, Project $project): bool
    {
        return $project->pm_id === $user->id && $project->status->canBeSubmitted();
    }

    public function decide(User $user, Project $project, ?ProjectDecisionType $type = null): bool
    {
        if (! $user->hasRole('executive')) {
            return false;
        }

        return $type
            ? $type->isAllowedFrom($project->status)
            : collect(ProjectDecisionType::cases())->contains(fn (ProjectDecisionType $type): bool => $type->isAllowedFrom($project->status));
    }

    public function start(User $user, Project $project): bool
    {
        return $project->pm_id === $user->id && $project->status === ProjectStatus::Approved;
    }

    public function complete(User $user, Project $project): bool
    {
        return $project->pm_id === $user->id && $project->status === ProjectStatus::InProgress;
    }

    /**
     * الفريق والمعالم: لمدير المشروع والتنفيذي ما دام المشروع مفتوحاً.
     */
    public function manage(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    /**
     * إنشاء المهام وتعديلها وإسنادها: الإدارة، وقائد الفريق من الأعضاء.
     */
    public function manageTasks(User $user, Project $project): bool
    {
        return ! $project->status->isTerminal()
            && ($project->isManagedBy($user) || $project->isLedBy($user));
    }

    /**
     * رفع المرفقات: كل من يعمل على المشروع، لا العميل ولا المطّلع فقط.
     */
    public function upload(User $user, Project $project): bool
    {
        return ! $project->status->isTerminal()
            && ($project->isManagedBy($user) || $project->hasMember($user));
    }
}
