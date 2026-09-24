<?php

namespace App\Http\Controllers\Executive;

use App\Enums\ProjectDecisionType;
use App\Http\Controllers\Controller;
use App\Models\ProjectDecision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «سجل القرارات»: التاريخ الكامل لقرارات الاعتماد والرفض والإيقاف والإلغاء،
 * بتعليلاتها ومن اتخذها ومتى.
 */
class DecisionLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::enum(ProjectDecisionType::class)],
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $decisions = ProjectDecision::query()
            ->with(['project', 'decider'])
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['q'] ?? null, fn (Builder $query, string $search) => $query->whereHas('project', fn (Builder $query) => $query->where('name', 'like', '%'.addcslashes($search, '%_\\').'%')))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('decided_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('decided_at', '<', Carbon::parse($to)->addDay()))
            ->latest('decided_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('executive.decisions', [
            'decisions' => $decisions,
            'filters' => $filters,
            'types' => collect(ProjectDecisionType::cases())->mapWithKeys(fn (ProjectDecisionType $type): array => [$type->value => $type->label()])->all(),
        ]);
    }
}
