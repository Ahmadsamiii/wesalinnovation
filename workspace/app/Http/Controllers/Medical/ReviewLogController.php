<?php

namespace App\Http\Controllers\Medical;

use App\Enums\ApprovalDecision;
use App\Http\Controllers\Controller;
use App\Models\HealthContentReview;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «سجل المحتوى المعتمد والمرفوض»: كل قرار طبي بنسخته وملاحظته، للقراءة فقط.
 */
class ReviewLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['decision' => ['nullable', Rule::enum(ApprovalDecision::class)]]);

        return view('medical.log', [
            'reviews' => HealthContentReview::query()
                ->with(['content', 'reviewer'])
                ->when($filters['decision'] ?? null, fn ($query, string $decision) => $query->where('decision', $decision))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'filters' => $filters,
        ]);
    }
}
