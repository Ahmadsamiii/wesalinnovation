<?php

namespace App\Http\Controllers;

use App\Enums\ProjectDecisionType;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * انتقالات حالة المشروع. كل انتقال يمر على السياسة (من يحق له ومن أي حالة)
 * ثم على النموذج نفسه الذي يرفض أي انتقال غير مسموح مهما كان مصدره.
 */
class ProjectWorkflowController extends Controller
{
    public function submit(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('submit', $project);

        $project->submitForApproval($request->user());

        return back()->with('status', 'قُدّم المشروع للاعتماد التنفيذي.');
    }

    public function decide(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::enum(ProjectDecisionType::class)],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $type = ProjectDecisionType::from($validated['type']);

        Gate::authorize('decide', [$project, $type]);

        if ($type->requiresNote() && blank($validated['note'] ?? null)) {
            return back()->withErrors(['note' => 'قرار «'.$type->label().'» يحتاج تعليلاً يراه مدير المشروع.'])->withInput();
        }

        $project->decide($type, $request->user(), $validated['note'] ?? null);

        return back()->with('status', 'سُجّل قرار «'.$type->label().'» على مشروع '.$project->name.'.');
    }

    public function start(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('start', $project);

        $project->start($request->user());

        return back()->with('status', 'بدأ تنفيذ المشروع.');
    }

    public function complete(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('complete', $project);

        $openTasks = $project->tasks()->open()->count();

        if ($openTasks > 0 && ! $request->boolean('confirm_open_tasks')) {
            return back()->with('error', "في المشروع {$openTasks} مهمة غير منجزة. أغلقها أو أكّد الإنجاز رغم ذلك.")
                ->with('confirm_complete', true);
        }

        $project->complete($request->user());

        return back()->with('status', 'سُجّل المشروع منجزاً.');
    }
}
