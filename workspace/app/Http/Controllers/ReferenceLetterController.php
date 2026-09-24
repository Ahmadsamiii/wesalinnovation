<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\ReferenceLetterStatus;
use App\Models\AuditLog;
use App\Models\ReferenceLetter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * «طلب إفادة»: الموظف يطلب، والمدير التنفيذي يعتمد أو يرفض بتعليل، والإفادة
 * المعتمدة تُطبع برمز تحقق عام.
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
            'isApprover' => $user->hasRole('executive'),
            'missingProfile' => blank($user->job_title) || $user->joined_at === null,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', ReferenceLetter::class);

        return view('reference-letters.create', ['user' => $request->user()]);
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

        return redirect()->route('reference-letters.index')->with('status', 'أُرسل طلبك للمدير التنفيذي. تظهر الإفادة هنا حين تُعتمد.');
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
