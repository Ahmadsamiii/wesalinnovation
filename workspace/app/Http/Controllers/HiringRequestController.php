<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\HiringRequestStatus;
use App\Http\Requests\HiringRequestRequest;
use App\Models\AuditLog;
use App\Models\HiringRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «طلبات التوظيف»: مسؤول الفريق يطلب بمبرراته، والمدير التنفيذي يقرّر، ثم
 * يُغلق الطلب بشغل الوظيفة أو إلغائه — وكل خطوة في سجل التدقيق.
 */
class HiringRequestController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', HiringRequest::class);

        $user = $request->user();
        $filters = $request->validate(['status' => ['nullable', Rule::enum(HiringRequestStatus::class)]]);

        return view('hiring-requests.index', [
            'hiringRequests' => HiringRequest::query()
                ->visibleTo($user)
                ->with(['requester', 'project'])
                ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
                ->orderByRaw('case status when ? then 0 when ? then 1 else 2 end', [HiringRequestStatus::Pending->value, HiringRequestStatus::Approved->value])
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'counts' => HiringRequest::query()->visibleTo($user)->toBase()->groupBy('status')->selectRaw('status, count(*) as total')->pluck('total', 'status'),
            'filters' => $filters,
            'isApprover' => $user->hasRole('executive'),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', HiringRequest::class);

        return view('hiring-requests.create', [
            'hiringRequest' => null,
            'projects' => HiringRequestRequest::projectOptions($request->user()),
        ]);
    }

    public function store(HiringRequestRequest $request): RedirectResponse
    {
        $hiringRequest = new HiringRequest($request->validated());
        $hiringRequest->requested_by = $request->user()->id;
        $hiringRequest->department ??= $request->user()->department;
        $hiringRequest->save();

        AuditLog::record(AuditAction::HiringRequested, $hiringRequest);

        return redirect()->route('hiring-requests.show', $hiringRequest)
            ->with('status', "أُرسل طلب التوظيف {$hiringRequest->number} للمدير التنفيذي.");
    }

    public function show(HiringRequest $hiringRequest): View
    {
        Gate::authorize('view', $hiringRequest);

        return view('hiring-requests.show', [
            'hiringRequest' => $hiringRequest->load(['requester', 'decider', 'closer', 'project']),
        ]);
    }

    public function edit(Request $request, HiringRequest $hiringRequest): View
    {
        Gate::authorize('update', $hiringRequest);

        return view('hiring-requests.edit', [
            'hiringRequest' => $hiringRequest,
            'projects' => HiringRequestRequest::projectOptions($request->user()),
        ]);
    }

    public function update(HiringRequestRequest $request, HiringRequest $hiringRequest): RedirectResponse
    {
        $hiringRequest->update($request->validated());

        return redirect()->route('hiring-requests.show', $hiringRequest)->with('status', 'حُفظ الطلب.');
    }

    public function approve(Request $request, HiringRequest $hiringRequest): RedirectResponse
    {
        Gate::authorize('decide', $hiringRequest);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $hiringRequest->approve($request->user(), $validated['note'] ?? null);

        return back()->with('status', "اعتُمد الطلب {$hiringRequest->number}؛ يبدأ التوظيف.");
    }

    public function reject(Request $request, HiringRequest $hiringRequest): RedirectResponse
    {
        Gate::authorize('decide', $hiringRequest);

        $validated = $request->validate(
            ['reason' => ['required', 'string', 'max:2000']],
            ['reason.required' => 'الرفض يحتاج تعليلاً يراه صاحب الطلب.'],
        );
        $hiringRequest->reject($request->user(), $validated['reason']);

        return back()->with('status', "رُفض الطلب {$hiringRequest->number}.");
    }

    public function fill(Request $request, HiringRequest $hiringRequest): RedirectResponse
    {
        Gate::authorize('fill', $hiringRequest);

        $validated = $request->validate(
            ['note' => ['required', 'string', 'max:2000']],
            ['note.required' => 'اذكر من شُغلت به الوظيفة وتاريخ مباشرته.'],
        );
        $hiringRequest->markFilled($request->user(), $validated['note']);

        return back()->with('status', "أُغلق الطلب {$hiringRequest->number} بشغل الوظيفة.");
    }

    public function cancel(Request $request, HiringRequest $hiringRequest): RedirectResponse
    {
        Gate::authorize('cancel', $hiringRequest);

        $validated = $request->validate(
            ['reason' => ['required', 'string', 'max:2000']],
            ['reason.required' => 'اذكر سبب الإلغاء.'],
        );
        $hiringRequest->cancel($request->user(), $validated['reason']);

        return back()->with('status', "أُلغي الطلب {$hiringRequest->number}.");
    }
}
