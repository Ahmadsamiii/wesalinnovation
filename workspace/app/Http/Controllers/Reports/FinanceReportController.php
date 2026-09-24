<?php

namespace App\Http\Controllers\Reports;

use App\Enums\ContractStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ProjectStatus;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Project;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * «التقارير المالية»: الفوترة والتحصيل، وأعمار الذمم ومن عليه المتبقي،
 * والتزامات الشراء على الميزانيات، وما فُوتر من كل عقد ساري.
 */
class FinanceReportController extends Controller
{
    use BuildsMonthlySeries, Receivables, WritesCsv;

    public function __invoke(Request $request): View|StreamedResponse
    {
        $period = $this->period($request);

        $issued = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Paid])
            ->where('issue_date', '>=', $period['from'])
            ->with(['client', 'project'])
            ->orderBy('issue_date')
            ->get();

        if ($request->query('export') === 'invoices') {
            return $this->exportInvoices($issued);
        }

        $payments = InvoicePayment::query()->where('paid_on', '>=', $period['from'])->get(['paid_on', 'amount']);
        $open = Invoice::query()->where('status', InvoiceStatus::Issued)->with('client')->get();
        $awaitingApproval = PurchaseOrder::query()->whereIn('status', [PurchaseOrderStatus::PendingFinance, PurchaseOrderStatus::PendingExecutive]);

        return view('reports.finance', [
            'period' => $period,
            'kpis' => [
                'invoiced' => $issued->sum('total'),
                'collected' => $payments->sum('amount'),
                'outstanding' => $open->sum(fn (Invoice $invoice) => (float) $invoice->balance()),
                'overdue' => $open->filter->isOverdue()->sum(fn (Invoice $invoice) => (float) $invoice->balance()),
                'awaitingApprovalCount' => (clone $awaitingApproval)->count(),
                'awaitingApprovalAmount' => (clone $awaitingApproval)->sum('total'),
            ],
            'invoicedSeries' => $this->monthly($issued, $period['keys'], fn (Invoice $invoice) => $invoice->issue_date, fn (Invoice $invoice) => $invoice->total),
            'collectedSeries' => $this->monthly($payments, $period['keys'], fn (InvoicePayment $payment) => $payment->paid_on, fn (InvoicePayment $payment) => $payment->amount),
            'aging' => $this->agingBuckets(),
            'byClient' => $open
                ->groupBy(fn (Invoice $invoice): string => $invoice->client?->name ?? '—')
                ->map(fn (Collection $invoices, string $client): array => [
                    'label' => $client,
                    'value' => $invoices->sum(fn (Invoice $invoice) => (float) $invoice->balance()),
                ])
                ->sortByDesc('value')
                ->values()
                ->all(),
            'budgets' => Project::query()
                ->whereIn('status', [ProjectStatus::Approved, ProjectStatus::InProgress, ProjectStatus::OnHold])
                ->whereNotNull('budget')
                ->withSum(['purchaseOrders as committed_sum' => fn (Builder $query) => $query->whereIn('status', PurchaseOrderStatus::committed())], 'total')
                ->orderBy('name')
                ->get(),
            'contracts' => Contract::query()
                ->where('status', ContractStatus::Active)
                ->with('project')
                ->withSum(['invoices as invoiced_sum' => fn (Builder $query) => $query->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Paid])], 'subtotal')
                ->orderBy('number')
                ->get(),
        ]);
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     */
    private function exportInvoices(Collection $invoices): StreamedResponse
    {
        return $this->csvDownload(
            'invoices-'.today()->toDateString().'.csv',
            ['رقم الفاتورة', 'العميل', 'المشروع', 'تاريخ الإصدار', 'تاريخ الاستحقاق', 'قبل الضريبة', 'الضريبة', 'الإجمالي', 'المدفوع', 'المتبقي', 'الحالة'],
            $invoices->map(fn (Invoice $invoice): array => [
                $invoice->number,
                $invoice->client?->name,
                $invoice->project->name,
                $invoice->issue_date?->toDateString(),
                $invoice->due_date?->toDateString(),
                $invoice->subtotal,
                $invoice->vat_amount,
                $invoice->total,
                $invoice->paid_amount,
                $invoice->balance(),
                $invoice->isOverdue() ? 'متأخرة السداد' : $invoice->status->label(),
            ]),
        );
    }
}
