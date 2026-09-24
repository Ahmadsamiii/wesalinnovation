<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * مراحل المشروع ومعالمه (الوثيقة التنفيذية ٤٫٣). حذف المعلم يُبقي مهامه في
 * المشروع بلا معلم (nullOnDelete في المخطط) بدل أن تختفي معه.
 */
class ProjectMilestoneController extends Controller
{
    public function index(Project $project): View
    {
        Gate::authorize('viewInternals', $project);

        return view('projects.milestones', [
            'project' => $project,
            'milestones' => $project->milestones()->withCount([
                'tasks',
                'tasks as done_tasks_count' => fn (Builder $query) => $query->where('status', TaskStatus::Done),
            ])->get(),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage', $project);

        $project->milestones()->create([
            ...$this->validated($request),
            'position' => ($project->milestones()->max('position') ?? -1) + 1,
        ]);

        return back()->with('status', 'أُضيف المعلم.');
    }

    public function update(Request $request, Project $project, ProjectMilestone $milestone): RedirectResponse
    {
        Gate::authorize('manage', $project);

        $milestone->update($this->validated($request));

        return back()->with('status', 'حُدّث المعلم.');
    }

    public function destroy(Project $project, ProjectMilestone $milestone): RedirectResponse
    {
        Gate::authorize('manage', $project);

        $milestone->delete();

        return back()->with('status', 'حُذف المعلم، وبقيت مهامه في المشروع بلا معلم.');
    }

    /**
     * بلوغ المعلم واقعة بتاريخ، وإلغاؤه يمحو التاريخ.
     */
    public function toggle(Project $project, ProjectMilestone $milestone): RedirectResponse
    {
        Gate::authorize('manage', $project);

        $milestone->forceFill(['completed_at' => $milestone->isReached() ? null : now()])->save();

        return back()->with('status', $milestone->isReached() ? 'سُجّل بلوغ المعلم.' : 'أُلغي بلوغ المعلم.');
    }

    /**
     * @return array{title: string, description: ?string, due_date: ?string}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date'],
        ]);
    }
}
