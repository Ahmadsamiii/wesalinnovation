<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\ContractStatus;
use App\Enums\InvoiceStatus;
use App\Enums\Priority;
use App\Enums\ProjectDecisionType;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Http\Requests\ProjectRequest;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «مشاريعي» لمدير المشاريع و«حالة مشروعي» للعميل: نفس القائمة مفلترة بما
 * يخص كل مستخدم (Project::visibleTo).
 */
class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Project::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
        ]);

        $projects = Project::query()
            ->visibleTo($request->user())
            ->with(['pm', 'client'])
            ->withProgressCounts()
            ->when($filters['q'] ?? null, fn (Builder $query, string $search) => $query->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderByRaw('case when status in (?, ?) then 1 else 0 end', [ProjectStatus::Completed->value, ProjectStatus::Cancelled->value])
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('projects.index', [
            'projects' => $projects,
            'filters' => $filters,
            'isClient' => $request->user()->hasRole('client'),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Project::class);

        return view('projects.create', $this->formOptions($request->user()));
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        $user = $request->user();

        $project = new Project($request->validated());
        $project->pm_id = $user->hasRole('executive') ? $request->integer('pm_id') : $user->id;
        $project->created_by = $user->id;
        $project->save();

        AuditLog::record(AuditAction::ProjectCreated, $project);

        return redirect()->route('projects.show', $project)
            ->with('status', 'أُنشئ المشروع كمسودة. أضف المعالم والفريق ثم قدّمه للاعتماد.');
    }

    public function show(Request $request, Project $project): View
    {
        Gate::authorize('view', $project);

        $project->load(['pm', 'client', 'creator', 'milestones' => fn ($query) => $query->withCount([
            'tasks',
            'tasks as done_tasks_count' => fn (Builder $query) => $query->where('status', TaskStatus::Done),
        ])]);

        if ($request->user()->cannot('viewInternals', $project)) {
            return view('projects.client-show', ['project' => $project]);
        }

        $project->load(['decisions.decider', 'members.user']);

        $taskCounts = $project->tasks()->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $user = $request->user();
        $finance = $user->hasAnyRole(['executive', 'finance']) || $project->pm_id === $user->id ? [
            'committed' => $project->committedSpend(),
            'contracted' => (string) $project->contracts()->whereIn('status', [ContractStatus::Active, ContractStatus::Completed])->sum('value'),
            'invoiced' => (string) $project->invoices()->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Paid])->sum('total'),
            'collected' => (string) $project->invoices()->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Paid])->sum('paid_amount'),
        ] : null;

        return view('projects.show', [
            'project' => $project,
            'finance' => $finance,
            'taskCounts' => $taskCounts,
            'overdueTasks' => $project->tasks()->overdue()->with('assignee')->orderBy('due_date')->limit(5)->get(),
            'decisionTypes' => collect(ProjectDecisionType::cases())
                ->filter(fn (ProjectDecisionType $type): bool => $type->isAllowedFrom($project->status))
                ->values(),
        ]);
    }

    public function edit(Request $request, Project $project): View
    {
        Gate::authorize('update', $project);

        return view('projects.edit', ['project' => $project, ...$this->formOptions($request->user())]);
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        $project->fill($request->validated());

        if ($request->user()->hasRole('executive')) {
            $project->pm_id = $request->integer('pm_id');
        }

        $changed = array_keys($project->getDirty());
        $project->save();

        if ($changed !== []) {
            AuditLog::record(AuditAction::ProjectUpdated, $project, ['fields' => $changed]);
        }

        return redirect()->route('projects.show', $project)->with('status', 'حُفظت بيانات المشروع.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        AuditLog::record(AuditAction::ProjectDeleted, null, ['subject_label' => $project->name]);

        // المرفقات تُحذف ملفاتها أولاً؛ الجداول التابعة تسقط بقيود cascade.
        $project->tasks()->with('attachments')->get()->each(
            fn ($task) => $task->attachments->each->deleteWithFile()
        );
        $project->attachments->each->deleteWithFile();
        $project->delete();

        return redirect()->route('projects.index')->with('status', 'حُذفت المسودة.');
    }

    /**
     * @return array{clients: array<int, string>, pms: array<int, string>, priorities: array<string, string>, canChoosePm: bool}
     */
    private function formOptions(User $user): array
    {
        return [
            'clients' => User::withRole('client')->active()->orderBy('name')->pluck('name', 'id')->all(),
            'pms' => User::withRole('pm')->active()->orderBy('name')->pluck('name', 'id')->all(),
            'priorities' => collect(Priority::cases())->mapWithKeys(fn (Priority $priority): array => [$priority->value => $priority->label()])->all(),
            'canChoosePm' => $user->hasRole('executive'),
        ];
    }
}
