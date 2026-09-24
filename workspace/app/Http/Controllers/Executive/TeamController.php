<?php

namespace App\Http\Controllers\Executive;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «الفريق» للمدير التنفيذي: كل الحسابات الداخلية النشطة مع حملها الحالي —
 * المشاريع المفتوحة التي تديرها أو تعمل فيها، والمهام المفتوحة والمتأخرة.
 */
class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $roles = collect(config('roles'))->except('client')->map(fn (array $role): string => $role['label'])->all();

        $filters = $request->validate([
            'role' => ['nullable', Rule::in(array_keys($roles))],
        ]);

        $openStatuses = [ProjectStatus::Approved->value, ProjectStatus::InProgress->value, ProjectStatus::OnHold->value, ProjectStatus::PendingApproval->value, ProjectStatus::Draft->value];

        $people = User::query()
            ->active()
            ->with('roles')
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', $filters['role'] ?? array_keys($roles)))
            ->withCount([
                'assignedTasks as open_tasks_count' => fn (Builder $query) => $query->where('status', '!=', TaskStatus::Done),
                'assignedTasks as overdue_tasks_count' => fn (Builder $query) => $query->overdue(),
                'assignedTasks as done_last_30_count' => fn (Builder $query) => $query->where('status', TaskStatus::Done)->where('completed_at', '>=', now()->subDays(30)),
            ])
            ->orderBy('name')
            ->get();

        $managedProjects = DB::table('projects')->whereIn('status', $openStatuses)->groupBy('pm_id')->selectRaw('pm_id, count(*) as total')->pluck('total', 'pm_id');
        $memberProjects = DB::table('project_members')
            ->join('projects', 'projects.id', '=', 'project_members.project_id')
            ->whereIn('projects.status', $openStatuses)
            ->groupBy('project_members.user_id')
            ->selectRaw('project_members.user_id, count(*) as total')
            ->pluck('total', 'user_id');

        return view('executive.team', [
            'people' => $people,
            'managedProjects' => $managedProjects,
            'memberProjects' => $memberProjects,
            'roles' => $roles,
            'filters' => $filters,
        ]);
    }
}
