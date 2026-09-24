<?php

namespace App\Http\Controllers\Reports;

use App\Enums\InvoiceStatus;
use App\Enums\ProjectStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * «التقارير الشاملة» للمدير التنفيذي: المحفظة والتسليم والمال في صفحة واحدة،
 * كلها على نفس الفترة المختارة.
 */
class ExecutiveReportController extends Controller
{
    use BuildsMonthlySeries, Receivables, WritesCsv;

    public function __invoke(Request $request): View|StreamedResponse
    {
        $period = $this->period($request);
        $projects = $this->activeProjects();

        if ($request->query('export') === 'projects') {
            return $this->exportProjects($projects);
        }

        $issued = Invoice::query()->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Paid])->where('issue_date', '>=', $period['from'])->get(['issue_date', 'total']);
        $payments = InvoicePayment::query()->where('paid_on', '>=', $period['from'])->get(['paid_on', 'amount']);
        $open = Invoice::query()->where('status', InvoiceStatus::Issued)->get(['status', 'due_date', 'total', 'paid_amount']);

        return view('reports.executive', [
            'period' => $period,
            'projects' => $projects,
            'kpis' => [
                'active' => Project::whereIn('status', [ProjectStatus::Approved, ProjectStatus::InProgress])->count(),
                'pending' => Project::where('status', ProjectStatus::PendingApproval)->count(),
                'overdue' => $projects->filter->isOverdue()->count(),
                'invoiced' => $issued->sum('total'),
                'collected' => $payments->sum('amount'),
                'outstanding' => $open->sum(fn (Invoice $invoice) => (float) $invoice->balance()),
                'overdueReceivables' => $open->filter->isOverdue()->sum(fn (Invoice $invoice) => (float) $invoice->balance()),
                'committed' => PurchaseOrder::whereIn('status', [PurchaseOrderStatus::Approved, PurchaseOrderStatus::PendingFinance, PurchaseOrderStatus::PendingExecutive])->sum('total'),
            ],
            'completedTasks' => $this->monthly(Task::where('status', TaskStatus::Done)->where('completed_at', '>=', $period['from'])->pluck('completed_at'), $period['keys'], fn ($date) => $date),
            'invoicedSeries' => $this->monthly($issued, $period['keys'], fn (Invoice $invoice) => $invoice->issue_date, fn (Invoice $invoice) => $invoice->total),
            'collectedSeries' => $this->monthly($payments, $period['keys'], fn (InvoicePayment $payment) => $payment->paid_on, fn (InvoicePayment $payment) => $payment->amount),
            'statusRows' => collect(ProjectStatus::cases())
                ->map(fn (ProjectStatus $status): array => [
                    'label' => $status->label(),
                    'value' => Project::where('status', $status)->count(),
                    'url' => route('projects.index', ['status' => $status->value]),
                ])
                ->filter(fn (array $row): bool => $row['value'] > 0)
                ->values()
                ->all(),
            'aging' => $this->agingBuckets(),
        ]);
    }

    /**
     * @return Collection<int, Project>
     */
    private function activeProjects()
    {
        return Project::query()
            ->whereIn('status', [ProjectStatus::Approved, ProjectStatus::InProgress, ProjectStatus::OnHold])
            ->with(['pm', 'client'])
            ->withProgressCounts()
            ->withSum(['purchaseOrders as committed_sum' => fn ($query) => $query->whereIn('status', PurchaseOrderStatus::committed())], 'total')
            ->withSum(['invoices as invoiced_sum' => fn ($query) => $query->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Paid])], 'total')
            ->orderBy('end_date')
            ->get();
    }

    /**
     * @param  iterable<Project>  $projects
     */
    private function exportProjects(iterable $projects): StreamedResponse
    {
        return $this->csvDownload(
            'active-projects-'.today()->toDateString().'.csv',
            ['المشروع', 'الحالة', 'مدير المشروع', 'العميل', 'نسبة الإنجاز', 'الميزانية', 'الملتزَم به', 'المفوتر', 'النهاية المخططة', 'متأخر'],
            collect($projects)->map(fn (Project $project): array => [
                $project->name,
                $project->status->label(),
                $project->pm->name,
                $project->client?->name ?? 'داخلي',
                $project->progress().'%',
                $project->budget,
                number_format((float) $project->committed_sum, 2, '.', ''),
                number_format((float) $project->invoiced_sum, 2, '.', ''),
                $project->end_date?->toDateString(),
                $project->isOverdue() ? 'نعم' : 'لا',
            ]),
        );
    }
}
