<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\PurchaseOrderStatus;
use App\Http\Requests\PurchaseOrderRequest;
use App\Models\AuditLog;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «أوامر الشراء» للمالية ومدير المشاريع. ما ينتظر مراجعة المستخدم الحالي
 * يظهر أولاً.
 */
class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PurchaseOrder::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(PurchaseOrderStatus::class)],
            'project' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $awaitingMe = match (true) {
            $user->hasRole('finance') => PurchaseOrderStatus::PendingFinance,
            $user->hasRole('executive') => PurchaseOrderStatus::PendingExecutive,
            default => null,
        };

        $orders = PurchaseOrder::query()
            ->visibleTo($user)
            ->with(['project', 'requester'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['project'] ?? null, fn (Builder $query, string $project) => $query->where('project_id', $project))
            ->when($awaitingMe, fn (Builder $query) => $query->orderByRaw('case when status = ? then 0 else 1 end', [$awaitingMe->value]))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('purchase-orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'awaitingMe' => $awaitingMe,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', PurchaseOrder::class);

        return view('purchase-orders.create', [
            'projects' => PurchaseOrderRequest::projectOptions($request->user()),
            'selectedProject' => $request->integer('project') ?: null,
        ]);
    }

    public function store(PurchaseOrderRequest $request): RedirectResponse
    {
        $order = DB::transaction(function () use ($request): PurchaseOrder {
            $order = new PurchaseOrder(Arr::except($request->validated(), 'items'));
            $order->requested_by = $request->user()->id;
            $order->save();
            $order->syncItems($request->validated('items'));

            AuditLog::record(AuditAction::PurchaseOrderCreated, $order);

            return $order;
        });

        return redirect()->route('purchase-orders.show', $order)
            ->with('status', "أُنشئ أمر الشراء {$order->number} كمسودة. قدّمه للمالية حين يكتمل.");
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        Gate::authorize('view', $purchaseOrder);

        $purchaseOrder->load(['project', 'requester', 'items', 'approvals.decider', 'attachments.uploader']);
        $project = $purchaseOrder->project;

        return view('purchase-orders.show', [
            'order' => $purchaseOrder,
            'committed' => $project->committedSpend(),
            'budget' => $project->budget,
        ]);
    }

    public function edit(Request $request, PurchaseOrder $purchaseOrder): View
    {
        Gate::authorize('update', $purchaseOrder);

        return view('purchase-orders.edit', [
            'order' => $purchaseOrder->load('items'),
            'projects' => PurchaseOrderRequest::projectOptions($request->user()),
        ]);
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        DB::transaction(function () use ($request, $purchaseOrder): void {
            $purchaseOrder->update(Arr::except($request->validated(), 'items'));
            $purchaseOrder->syncItems($request->validated('items'));
        });

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('status', 'حُفظ أمر الشراء.');
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        Gate::authorize('delete', $purchaseOrder);

        $purchaseOrder->attachments->each->deleteWithFile();
        $purchaseOrder->delete();

        return redirect()->route('purchase-orders.index')->with('status', 'حُذفت مسودة أمر الشراء.');
    }

    public function submit(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        Gate::authorize('submit', $purchaseOrder);

        $purchaseOrder->submit($request->user());

        return back()->with('status', 'قُدّم أمر الشراء لمراجعة المالية.');
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        Gate::authorize('receive', $purchaseOrder);

        $purchaseOrder->markReceived($request->user());

        return back()->with('status', 'سُجّل استلام أمر الشراء.');
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        Gate::authorize('cancel', $purchaseOrder);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $purchaseOrder->cancel($request->user(), $validated['reason']);

        return back()->with('status', 'أُلغي أمر الشراء.');
    }
}
