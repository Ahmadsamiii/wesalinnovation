<?php

namespace App\Http\Controllers;

use App\Enums\HiringRequestStatus;
use App\Enums\ProjectStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\ReferenceLetterStatus;
use App\Models\HiringRequest;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\PurchaseOrder;
use App\Models\ReferenceLetter;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class DashboardController extends Controller
{
    /**
     * مدخل كل مستخدم بعد الدخول: تبويبه الأول المبني. المدير التنفيذي وحده
     * تبويبه الأول «نظرة عامة» على هذا المسار نفسه.
     *
     * حساب بلا دور حالة غير متوقعة (لا تسجيل ذاتي) تُعرض برسالة واضحة بدل
     * افتراض أي دور، حتى لا يُمنح وصول لم يُعتمد صراحة.
     */
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $tabs = $user->roleTabs();

        if ($tabs === []) {
            return view('dashboard.no-role');
        }

        if (reset($tabs)['route'] === 'dashboard') {
            return view('dashboard.overview', $user->hasRole('executive') ? $this->executiveOverview($user) : []);
        }

        foreach ($tabs as $tab) {
            if (Route::has($tab['route'])) {
                return redirect()->route($tab['route']);
            }
        }

        return redirect()->route('sections.show', array_key_first($tabs));
    }

    /**
     * ما ينتظر قرار المدير التنفيذي، ولمحة المحفظة والمال، وما يحتاج انتباهه.
     *
     * @return array<string, mixed>
     */
    private function executiveOverview(User $user): array
    {
        $delivering = [ProjectStatus::Approved, ProjectStatus::InProgress, ProjectStatus::OnHold];
        $overdueInvoices = Invoice::query()->overdue()->with(['client', 'project'])->orderBy('due_date')->get();

        return [
            'queues' => [
                ['label' => 'مشاريع بانتظار الاعتماد', 'count' => Project::where('status', ProjectStatus::PendingApproval)->count(), 'url' => route('approvals.projects')],
                ['label' => 'أوامر شراء بانتظار اعتمادك', 'count' => PurchaseOrder::where('status', PurchaseOrderStatus::PendingExecutive)->count(), 'url' => route('approvals.financial')],
                ['label' => 'طلبات توظيف', 'count' => HiringRequest::where('status', HiringRequestStatus::Pending)->where('requested_by', '!=', $user->id)->count(), 'url' => route('hiring-requests.index', ['status' => HiringRequestStatus::Pending->value])],
                ['label' => 'طلبات إفادة', 'count' => ReferenceLetter::where('status', ReferenceLetterStatus::Pending)->where('requester_id', '!=', $user->id)->count(), 'url' => route('reference-letters.index')],
            ],
            'kpis' => [
                'active' => Project::whereIn('status', [ProjectStatus::Approved, ProjectStatus::InProgress])->count(),
                'overdueProjects' => Project::whereIn('status', $delivering)->whereDate('end_date', '<', today())->count(),
                'collectedThisMonth' => InvoicePayment::where('paid_on', '>=', now()->startOfMonth())->sum('amount'),
                'overdueReceivables' => $overdueInvoices->sum(fn (Invoice $invoice) => (float) $invoice->balance()),
            ],
            'overdueProjects' => Project::query()
                ->whereIn('status', $delivering)
                ->whereDate('end_date', '<', today())
                ->with('pm')
                ->orderBy('end_date')
                ->limit(5)
                ->get(),
            'overBudget' => Project::query()
                ->whereIn('status', $delivering)
                ->whereNotNull('budget')
                ->withSum(['purchaseOrders as committed_sum' => fn (Builder $query) => $query->whereIn('status', PurchaseOrderStatus::committed())], 'total')
                ->get()
                ->filter(fn (Project $project): bool => (float) $project->committed_sum > (float) $project->budget)
                ->values(),
            'overdueInvoices' => $overdueInvoices->take(5),
            'upcomingMilestones' => ProjectMilestone::query()
                ->whereNull('completed_at')
                ->whereBetween('due_date', [today(), today()->addDays(14)])
                ->whereHas('project', fn (Builder $query) => $query->whereIn('status', [ProjectStatus::Approved, ProjectStatus::InProgress]))
                ->with('project')
                ->orderBy('due_date')
                ->limit(8)
                ->get(),
        ];
    }
}
