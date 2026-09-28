<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\ReferenceLetterStatus;
use App\Models\AuditLog;
use App\Models\ReferenceLetter;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * «طلب إفادة»: الموظف يطلب، ومدير الموارد البشرية يعتمد أو يرفض بتعليل (والمدير
 * التنفيذي ما دام لا يوجد مدير موارد نشط)، والإفادة المعتمدة تُطبع برمز تحقق عام.
 */
class ReferenceLetterController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ReferenceLetter::class);

        $user = $request->user();

        return view('reference-letters.index', [
            'letters' => ReferenceLetter::query()
                ->visibleTo($user)
                ->with(['requester', 'decider'])
                ->orderByRaw('case when status = ? then 0 else 1 end', [ReferenceLetterStatus::Pending->value])
                ->latest('id')
                ->paginate(20),
            'seesAll' => $user->hasAnyRole(ReferenceLetter::SEES_ALL_ROLES),
            'canDecide' => $user->hasRole('hr') || ($user->hasRole('executive') && ! User::hasActiveHr()),
            'canRequest' => ! $user->hasRole('executive'),
            'isHr' => $user->hasRole('hr'),
            'approverLabel' => ReferenceLetter::approverLabel(),
            'profileOwnerLabel' => ReferenceLetter::profileOwnerLabel(),
            'missingProfile' => blank($user->job_title) || $user->joined_at === null,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', ReferenceLetter::class);

        return view('reference-letters.create', [
            'user' => $request->user(),
            'approverLabel' => ReferenceLetter::approverLabel($request->user()),
            'profileOwnerLabel' => ReferenceLetter::profileOwnerLabel(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', ReferenceLetter::class);

        $validated = $request->validate([
            'purpose' => ['required', 'string', 'max:255'],
            'addressee' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $letter = new ReferenceLetter($validated);
        $letter->requester_id = $request->user()->id;
        $letter->save();

        AuditLog::record(AuditAction::ReferenceLetterRequested, $letter);

        return redirect()->route('reference-letters.index')->with('status', 'أُرسل طلبك إلى '.ReferenceLetter::approverLabel($request->user()).'. تظهر الإفادة هنا حين تُعتمد.');
    }

    /**
     * نسخة الطباعة للإفادة المعتمدة.
     */
    public function show(ReferenceLetter $referenceLetter): View
    {
        Gate::authorize('print', $referenceLetter);

        return view('reference-letters.show', ['letter' => $referenceLetter->load(['requester', 'decider'])]);
    }

    public function approve(Request $request, ReferenceLetter $referenceLetter): RedirectResponse
    {
        Gate::authorize('decide', $referenceLetter);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $referenceLetter->approve($request->user(), $validated['note'] ?? null);

        return back()->with('status', 'اعتُمدت الإفادة '.$referenceLetter->number.'.');
    }

    public function reject(Request $request, ReferenceLetter $referenceLetter): RedirectResponse
    {
        Gate::authorize('decide', $referenceLetter);

        $validated = $request->validate(['note' => ['required', 'string', 'max:2000']], ['note.required' => 'الرفض يحتاج تعليلاً يراه صاحب الطلب.']);
        $referenceLetter->reject($request->user(), $validated['note']);

        return back()->with('status', 'رُفض الطلب.');
    }
}
