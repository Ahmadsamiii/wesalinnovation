<?php

namespace App\Http\Controllers\Executive;

use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderApproval;
use Illuminate\View\View;

/**
 * «الاعتمادات المالية» للمدير التنفيذي: أوامر الشراء التي تجاوزت حد اعتماد
 * المالية وحدها، مع ميزانية كل مشروع وما التزم به منها.
 */
class FinancialApprovalController extends Controller
{
    public function index(): View
    {
        return view('executive.financial-approvals', [
            'pending' => PurchaseOrder::query()
                ->where('status', PurchaseOrderStatus::PendingExecutive)
                ->with(['project', 'requester', 'items', 'approvals.decider'])
                ->oldest('submitted_at')
                ->get(),
            'recent' => PurchaseOrderApproval::query()
                ->with(['purchaseOrder.project', 'decider'])
                ->latest('decided_at')
                ->limit(10)
                ->get(),
            'threshold' => config('workspace.executive_approval_threshold'),
        ]);
    }
}
