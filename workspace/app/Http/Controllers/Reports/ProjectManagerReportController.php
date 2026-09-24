<?php

namespace App\Http\Controllers\Reports;

use App\Enums\InvoiceStatus;
use App\Enums\ProjectStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * «التقارير» لمدير المشاريع: مشاريعه قيد التسليم، ووتيرة الإنجاز، وحمل فريقه.
 */
class ProjectManagerReportController extends Controller
{
    use BuildsMonthlySeries, WritesCsv;

    public function __invoke(Request $request): View|StreamedResponse
    {
        $user = $request->user();
        $period = $this->period($request);
        $projects = $this->deliveryProjects($user);

        if ($request->query('export') === 'projects') {
            return $this->exportProjects($projects);
        }

        $workload = Task::query()->toBase()
            ->whereIn('project_id', $projects->modelKeys())
            ->where('status', '!=', TaskStatus::Done->value)
            ->whereNotNull('assignee_id')
            ->groupBy('assignee_id')
            ->selectRaw('assignee_id, count(*) as total')
            ->pluck('total', 'assignee_id');

        return view('reports.pm', [
            'period' => $period,
            'projects' => $projects,
            'kpis' => [
                'delivering' => $projects->count(),
                'openTasks' => $projects->sum('open_tasks_count'),
                'overdueTasks' => $projects->sum('overdue_tasks_count'),
                'milestonesDue' => ProjectMilestone::query()
                    ->whereIn('project_id', $projects->modelKeys())
                    ->whereNull('completed_at')
                    ->whereBetween('due_date', [today(), today()->addDays(30)])
                    ->count(),
            ],
            'completedTasks' => $this->monthly(
                Task::query()
                    ->whereHas('project', fn (Builder $query) => $query->where('pm_id', $user->id))
                    ->where('status', TaskStatus::Done)
                    ->where('completed_at', '>=', $period['from'])
                    ->pluck('completed_at'),
                $period['keys'],
                fn ($completedAt) => $completedAt,
            ),
            'workloadRows' => User::query()->whereIn('id', $workload->keys())->get(['id', 'name'])
                ->map(fn (User $person): array => [
                    'label' => $person->name,
                    'value' => (int) $workload[$person->id],
                    'url' => route('tasks.index', ['assignee' => $person->id]),
                ])
                ->sortByDesc('value')
                ->values()
                ->all(),
        ]);
    }

    /**
     * مشاريعه المعتمدة والجارية والمتوقفة مؤقتاً، بعدّاداتها في استعلام واحد.
     *
     * @return Collection<int, Project>
     */
    private function deliveryProjects(User $user): Collection
    {
        return Project::query()
            ->where('pm_id', $user->id)
            ->whereIn('status', [ProjectStatus::Approved, ProjectStatus::InProgress, ProjectStatus::OnHold])
            ->with('client')
            ->withProgressCounts()
            ->withCount([
                'tasks as open_tasks_count' => fn (Builder $query) => $query->open(),
                'tasks as overdue_tasks_count' => fn (Builder $query) => $query->overdue(),
            ])
            ->withSum(['purchaseOrders as committed_sum' => fn (Builder $query) => $query->whereIn('status', PurchaseOrderStatus::committed())], 'total')
            ->withSum(['invoices as invoiced_sum' => fn (Builder $query) => $query->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Paid])], 'total')
            ->orderByRaw('end_date is null')
            ->orderBy('end_date')
            ->get();
    }

    /**
     * @param  Collection<int, Project>  $projects
     */
    private function exportProjects(Collection $projects): StreamedResponse
    {
        return $this->csvDownload(
            'my-projects-'.today()->toDateString().'.csv',
            ['المشروع', 'الحالة', 'العميل', 'نسبة الإنجاز', 'مهام مفتوحة', 'مهام متأخرة', 'المعالم المبلوغة', 'الميزانية', 'الملتزَم به', 'المفوتر', 'النهاية المخططة'],
            $projects->map(fn (Project $project): array => [
                $project->name,
                $project->status->label(),
                $project->client?->name ?? 'داخلي',
                $project->progress().'%',
                $project->open_tasks_count,
                $project->overdue_tasks_count,
                $project->reached_milestones_count.' من '.$project->milestones_count,
                $project->budget,
                number_format((float) $project->committed_sum, 2, '.', ''),
                number_format((float) $project->invoiced_sum, 2, '.', ''),
                $project->end_date?->toDateString(),
            ]),
        );
    }
}
