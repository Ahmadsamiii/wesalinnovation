<?php

namespace App\Http\Controllers\Executive;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectDecision;
use Illuminate\View\View;

/**
 * «اعتماد المشاريع» للمدير التنفيذي: ما ينتظر قراره أولاً، ثم ما يحتاج
 * انتباهه من المشاريع الجارية (متوقفة أو متأخرة عن موعدها).
 */
class ProjectApprovalController extends Controller
{
    public function index(): View
    {
        return view('executive.approvals', [
            'pending' => Project::query()
                ->where('status', ProjectStatus::PendingApproval)
                ->with(['pm', 'client', 'decisions.decider'])
                ->withCount(['milestones', 'members', 'tasks'])
                ->oldest('submitted_at')
                ->get(),
            'attention' => Project::query()
                ->where(fn ($query) => $query
                    ->where('status', ProjectStatus::OnHold)
                    ->orWhere(fn ($query) => $query
                        ->whereIn('status', [ProjectStatus::Approved, ProjectStatus::InProgress])
                        ->whereDate('end_date', '<', today())))
                ->with('pm')
                ->withProgressCounts()
                ->orderBy('end_date')
                ->get(),
            'recent' => ProjectDecision::query()->with(['project', 'decider'])->latest('decided_at')->limit(8)->get(),
        ]);
    }
}
