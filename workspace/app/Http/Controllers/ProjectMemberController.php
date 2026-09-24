<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\ProjectMemberRole;
use App\Enums\TaskStatus;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * فريق المشروع. الأعضاء من الحسابات الداخلية النشطة فقط: العميل يتابع
 * مشروعه من بوابته، لا من داخل الفريق.
 */
class ProjectMemberController extends Controller
{
    public function index(Project $project): View
    {
        Gate::authorize('viewInternals', $project);

        $members = $project->members()
            ->with('user.roles')
            ->get()
            ->sortBy(fn (ProjectMember $member): string => ($member->role === ProjectMemberRole::Lead ? '0' : '1').$member->user->name);

        $openTasks = $project->tasks()->toBase()
            ->where('status', '!=', TaskStatus::Done->value)
            ->whereNotNull('assignee_id')
            ->groupBy('assignee_id')
            ->selectRaw('assignee_id, count(*) as total')
            ->pluck('total', 'assignee_id');

        return view('projects.members', [
            'project' => $project->load('pm'),
            'members' => $members,
            'openTasks' => $openTasks,
            'candidates' => $this->candidates($project),
            'roles' => collect(ProjectMemberRole::cases())->mapWithKeys(fn (ProjectMemberRole $role): array => [$role->value => $role->label()])->all(),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage', $project);

        $validated = $request->validate([
            'user_id' => ['required', Rule::in(array_keys($this->candidates($project)))],
            'role' => ['required', Rule::enum(ProjectMemberRole::class)],
        ], [
            'user_id.in' => 'اختر حساباً داخلياً نشطاً ليس في الفريق بعد.',
        ]);

        $member = $project->members()->create([...$validated, 'joined_at' => now()]);

        AuditLog::record(AuditAction::ProjectMemberAdded, $project, ['member' => $member->user->name]);

        return back()->with('status', 'أُضيف '.$member->user->name.' إلى الفريق.');
    }

    public function update(Request $request, Project $project, ProjectMember $member): RedirectResponse
    {
        Gate::authorize('manage', $project);

        $validated = $request->validate(['role' => ['required', Rule::enum(ProjectMemberRole::class)]]);
        $member->update($validated);

        return back()->with('status', 'حُدّث دور '.$member->user->name.'.');
    }

    /**
     * مهامه المفتوحة تعود بلا إسناد بدل أن تبقى مسندة لمن خرج من الفريق.
     */
    public function destroy(Project $project, ProjectMember $member): RedirectResponse
    {
        Gate::authorize('manage', $project);

        $name = $member->user->name;

        DB::transaction(function () use ($project, $member): void {
            $project->tasks()->open()->where('assignee_id', $member->user_id)->update(['assignee_id' => null]);
            $member->delete();
        });

        AuditLog::record(AuditAction::ProjectMemberRemoved, $project, ['member' => $name]);

        return back()->with('status', "أُزيل {$name} من الفريق، ومهامه المفتوحة صارت بلا إسناد.");
    }

    /**
     * @return array<int, string>
     */
    private function candidates(Project $project): array
    {
        return User::query()
            ->with('roles')
            ->active()
            ->whereHas('roles', fn (Builder $query) => $query->where('name', '!=', 'client'))
            ->whereKeyNot($project->pm_id)
            ->whereNotIn('id', $project->members()->select('user_id'))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->name.' — '.$user->roleLabel()])
            ->all();
    }
}
