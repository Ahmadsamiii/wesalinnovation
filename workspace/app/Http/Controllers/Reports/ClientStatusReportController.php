<?php

namespace App\Http\Controllers\Reports;

use App\Enums\ContractStatus;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «تقرير حالة مشروعي» للعميل: وثيقة واحدة قابلة للطباعة تجمع التقدّم والمراحل
 * والعقود والفواتير والشهادة. لا شيء من العمل الداخلي (المهام والميزانية
 * وأوامر الشراء) يظهر هنا.
 */
class ClientStatusReportController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $projects = Project::query()
            ->visibleTo($user)
            ->orderByRaw('case status when ? then 0 when ? then 1 when ? then 2 else 3 end', [
                ProjectStatus::InProgress->value, ProjectStatus::Approved->value, ProjectStatus::OnHold->value,
            ])
            ->latest('id')
            ->get(['id', 'name', 'status']);

        $project = $request->filled('project')
            ? $projects->firstWhere('id', $request->integer('project'))
            : $projects->first();

        abort_if($request->filled('project') && $project === null, 404);

        if ($project === null) {
            return view('reports.client', ['projects' => $projects, 'project' => null]);
        }

        $project = Project::query()->with(['pm', 'milestones'])->findOrFail($project->id);

        $contracts = Contract::query()->visibleTo($user)->whereBelongsTo($project)->orderBy('number')->get();
        $invoices = Invoice::query()->visibleTo($user)->whereBelongsTo($project)->orderBy('issue_date')->get();

        return view('reports.client', [
            'projects' => $projects,
            'project' => $project,
            'contracts' => $contracts,
            'invoices' => $invoices,
            'certificates' => Certificate::query()->visibleTo($user)->valid()->whereBelongsTo($project)->get(),
            'totals' => [
                'contracted' => $contracts->whereIn('status', [ContractStatus::Active, ContractStatus::Completed])->sum('value'),
                'invoiced' => $invoices->sum('total'),
                'paid' => $invoices->sum('paid_amount'),
                'outstanding' => $invoices->sum(fn (Invoice $invoice) => (float) $invoice->balance()),
            ],
        ]);
    }
}
