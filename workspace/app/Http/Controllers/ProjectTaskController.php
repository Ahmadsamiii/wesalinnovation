<?php

namespace App\Http\Controllers;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Http\Requests\TaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * مهام مشروع واحد بعرضين (الوثيقة التنفيذية ٤٫٣): كانبان بأعمدة الحالات،
 * أو قائمة بتصفية وترتيب بالموعد.
 */
class ProjectTaskController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        Gate::authorize('viewInternals', $project);

        $filters = $request->validate([
            'view' => ['nullable', Rule::in(['board', 'list'])],
            'assignee' => ['nullable', 'integer'],
            'milestone' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
        ]);
        $view = $filters['view'] ?? 'board';

        $tasks = $project->tasks()
            ->with(['assignee', 'milestone'])
            ->withCount('comments')
            ->when($filters['assignee'] ?? null, fn (Builder $query, string $assignee) => $query->where('assignee_id', $assignee))
            ->when($filters['milestone'] ?? null, fn (Builder $query, string $milestone) => $query->where('milestone_id', $milestone))
            ->when(($filters['status'] ?? null) && $view === 'list', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($view === 'board',
                fn (Builder $query) => $query->orderBy('position')->orderBy('id'),
                fn (Builder $query) => $query->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('id'))
            ->get();

        // فحص صلاحية كل بطاقة يقرأ المشروع وأعضاءه؛ نفس النسخة المحمّلة لكل
        // المهام بدل استعلامين لكل بطاقة.
        $project->load('members');
        $tasks->each->setRelation('project', $project);

        return view('projects.tasks', [
            'project' => $project->load('pm'),
            'view' => $view,
            'filters' => $filters,
            'tasks' => $tasks,
            'columns' => $view === 'board' ? $tasks->groupBy(fn (Task $task): string => $task->status->value) : collect(),
            'assignees' => User::whereIn('id', TaskRequest::assignableUserIds($project))->orderBy('name')->pluck('name', 'id')->all(),
            'milestones' => $project->milestones()->pluck('title', 'id')->all(),
        ]);
    }

    public function create(Request $request, Project $project): View
    {
        Gate::authorize('manageTasks', $project);

        return view('tasks.create', [
            'project' => $project,
            ...$this->formOptions($project),
            'defaultMilestone' => $request->integer('milestone') ?: null,
        ]);
    }

    public function store(TaskRequest $request, Project $project): RedirectResponse
    {
        $task = new Task($request->validated());
        $task->project()->associate($project);
        $task->created_by = $request->user()->id;
        $task->position = ($project->tasks()->where('status', TaskStatus::Todo)->max('position') ?? -1) + 1;
        $task->save();

        return redirect()->route('projects.tasks.index', $project)->with('status', 'أُضيفت المهمة «'.$task->title.'».');
    }

    /**
     * @return array{assignees: array<int, string>, milestones: array<int, string>, priorities: array<string, string>}
     */
    public static function formOptions(Project $project): array
    {
        return [
            'assignees' => User::whereIn('id', TaskRequest::assignableUserIds($project))->orderBy('name')->pluck('name', 'id')->all(),
            'milestones' => $project->milestones()->pluck('title', 'id')->all(),
            'priorities' => collect(Priority::cases())->mapWithKeys(fn (Priority $priority): array => [$priority->value => $priority->label()])->all(),
        ];
    }
}
