<?php

namespace App\Http\Controllers\Executive;

use App\Enums\ApprovalDecision;
use App\Enums\ProjectDecisionType;
use App\Http\Controllers\Controller;
use App\Models\ProjectDecision;
use App\Models\PurchaseOrderApproval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «سجل القرارات»: التاريخ الكامل لقرارات المشاريع (اعتماد ورفض وإيقاف وإلغاء)
 * والقرارات المالية على أوامر الشراء، بتعليلاتها ومن اتخذها ومتى.
 */
class DecisionLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'kind' => ['nullable', Rule::in(['projects', 'finance'])],
            'type' => ['nullable', 'string', 'max:32'],
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $kind = $filters['kind'] ?? 'projects';
        $search = isset($filters['q']) ? '%'.addcslashes($filters['q'], '%_\\').'%' : null;

        $dated = fn (Builder $query, string $column) => $query
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where($column, '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where($column, '<', Carbon::parse($to)->addDay()));

        if ($kind === 'finance') {
            $records = PurchaseOrderApproval::query()
                ->with(['purchaseOrder.project', 'decider'])
                ->when(ApprovalDecision::tryFrom($filters['type'] ?? ''), fn (Builder $query, ApprovalDecision $decision) => $query->where('decision', $decision))
                ->when($search, fn (Builder $query) => $query->whereHas('purchaseOrder', fn (Builder $query) => $query->where('number', 'like', $search)->orWhere('vendor_name', 'like', $search)))
                ->tap(fn (Builder $query) => $dated($query, 'decided_at'))
                ->latest('decided_at')
                ->latest('id')
                ->paginate(25)
                ->withQueryString();
            $types = collect(ApprovalDecision::cases())->mapWithKeys(fn (ApprovalDecision $decision): array => [$decision->value => $decision->label()])->all();
        } else {
            $records = ProjectDecision::query()
                ->with(['project', 'decider'])
                ->when(ProjectDecisionType::tryFrom($filters['type'] ?? ''), fn (Builder $query, ProjectDecisionType $type) => $query->where('type', $type))
                ->when($search, fn (Builder $query) => $query->whereHas('project', fn (Builder $query) => $query->where('name', 'like', $search)))
                ->tap(fn (Builder $query) => $dated($query, 'decided_at'))
                ->latest('decided_at')
                ->latest('id')
                ->paginate(25)
                ->withQueryString();
            $types = collect(ProjectDecisionType::cases())->mapWithKeys(fn (ProjectDecisionType $type): array => [$type->value => $type->label()])->all();
        }

        return view('executive.decisions', [
            'kind' => $kind,
            'records' => $records,
            'filters' => $filters,
            'types' => $types,
        ]);
    }
}
