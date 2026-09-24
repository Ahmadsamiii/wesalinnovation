<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return $user->can('viewInternals', $task->project);
    }

    public function update(User $user, Task $task): bool
    {
        return $user->can('manageTasks', $task->project);
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->can('manageTasks', $task->project);
    }

    /**
     * المُسنَد إليه يحرّك مهمته، والإدارة تحرّك أي مهمة — ما دام المشروع في
     * طور التنفيذ (لا قبل الاعتماد ولا أثناء الإيقاف ولا بعد الإغلاق).
     */
    public function move(User $user, Task $task): bool
    {
        return $task->project->status->allowsTaskProgress()
            && ($task->assignee_id === $user->id || $user->can('manageTasks', $task->project));
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->view($user, $task) && ! $task->project->status->isTerminal();
    }
}
