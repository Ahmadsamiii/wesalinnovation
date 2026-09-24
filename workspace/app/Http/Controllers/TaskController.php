<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Http\Requests\TaskRequest;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    /**
     * «المهام والفريق» لمدير المشاريع: مهام كل مشاريعه المفتوحة في مكان واحد،
     * مع حمل كل عضو من المهام المفتوحة والمتأخرة.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['pm', 'executive']), 403);

        $filters = $request->validate([
            'project' => ['nullable', 'integer'],
            'assignee' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'overdue' => ['nullable', 'boolean'],
        ]);

        $projectIds = Project::query()
            ->when(! $user->hasRole('executive'), fn (Builder $query) => $query->where('pm_id', $user->id))
            ->whereNotIn('status', [ProjectStatus::Completed, ProjectStatus::Cancelled])
            ->pluck('id');

        $tasks = Task::query()
            ->whereIn('project_id', $projectIds)
            ->with(['project', 'assignee'])
            ->when($filters['project'] ?? null, fn (Builder $query, string $project) => $query->where('project_id', $project))
            ->when($filters['assignee'] ?? null, fn (Builder $query, string $assignee) => $query->where('assignee_id', $assignee))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status), fn (Builder $query) => $query->open())
            ->when($filters['overdue'] ?? false, fn (Builder $query) => $query->overdue())
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->paginate(25)
            ->withQueryString();

        $workload = Task::query()->toBase()
            ->whereIn('project_id', $projectIds)
            ->whereNotNull('assignee_id')
            ->groupBy('assignee_id')
            ->selectRaw('assignee_id')
            ->selectRaw('sum(case when status != ? then 1 else 0 end) as open_count', [TaskStatus::Done->value])
            ->selectRaw('sum(case when status != ? and due_date < ? then 1 else 0 end) as overdue_count', [TaskStatus::Done->value, today()->toDateString()])
            ->selectRaw('sum(case when status = ? and completed_at >= ? then 1 else 0 end) as done_recently', [TaskStatus::Done->value, now()->subDays(30)])
            ->get()
            ->keyBy('assignee_id');

        return view('tasks.index', [
            'tasks' => $tasks,
            'filters' => $filters,
            'workload' => $workload,
            'people' => User::whereIn('id', $workload->keys())->orderBy('name')->get(),
            'projects' => Project::whereIn('id', $projectIds)->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    /**
     * «مهامي» لعضو الفريق: ما أُسند إليه في كل المشاريع، مرتّباً بما يحتاج
     * انتباهه أولاً.
     */
    public function mine(Request $request): View
    {
        $tasks = Task::query()
            ->where('assignee_id', $request->user()->id)
            ->where(fn (Builder $query) => $query
                ->where('status', '!=', TaskStatus::Done)
                ->orWhere('completed_at', '>=', now()->subDays(14)))
            ->with(['project', 'milestone'])
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->get();

        return view('tasks.mine', [
            'overdue' => $tasks->filter(fn (Task $task): bool => $task->isOverdue()),
            'active' => $tasks->filter(fn (Task $task): bool => ! $task->isOverdue() && in_array($task->status, [TaskStatus::InProgress, TaskStatus::Review, TaskStatus::Blocked], true)),
            'todo' => $tasks->filter(fn (Task $task): bool => ! $task->isOverdue() && $task->status === TaskStatus::Todo),
            'done' => $tasks->filter(fn (Task $task): bool => $task->status === TaskStatus::Done),
        ]);
    }

    public function show(Task $task): View
    {
        Gate::authorize('view', $task);

        $task->load(['project.pm', 'milestone', 'assignee', 'creator', 'comments.author', 'attachments.uploader']);

        return view('tasks.show', ['task' => $task]);
    }

    public function edit(Task $task): View
    {
        Gate::authorize('update', $task);

        return view('tasks.edit', [
            'task' => $task,
            'project' => $task->project,
            ...ProjectTaskController::formOptions($task->project),
        ]);
    }

    public function update(TaskRequest $request, Task $task): RedirectResponse
    {
        $task->update($request->validated());

        return redirect()->route('tasks.show', $task)->with('status', 'حُفظت المهمة.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        Gate::authorize('delete', $task);

        $project = $task->project;

        DB::transaction(function () use ($task): void {
            AuditLog::record(AuditAction::TaskDeleted, $task->project, ['subject_label' => $task->project->name, 'note' => $task->title]);
            $task->attachments->each->deleteWithFile();
            $task->delete();
        });

        return redirect()->route('projects.tasks.index', $project)->with('status', 'حُذفت المهمة.');
    }
}
