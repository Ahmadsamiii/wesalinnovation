<?php

namespace App\Http\Controllers\Medical;

use App\Enums\HealthContentStatus;
use App\Http\Controllers\Controller;
use App\Models\HealthContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * «قائمة مراجعة المحتوى الصحي»: ما ينتظر قرار المدير الطبي بالأقدم أولاً،
 * وما حان موعد مراجعته الدورية من المنشور.
 */
class ContentReviewController extends Controller
{
    public function index(): View
    {
        return view('medical.review', [
            'queue' => HealthContent::query()
                ->where('status', HealthContentStatus::InReview)
                ->with('author')
                ->orderBy('submitted_at')
                ->get(),
            'due' => HealthContent::query()
                ->published()
                ->where('status', HealthContentStatus::Approved)
                ->whereDate('review_due_on', '<=', today()->addDays(30))
                ->orderBy('review_due_on')
                ->get(),
        ]);
    }

    public function show(HealthContent $content): View
    {
        Gate::authorize('view', $content);

        return view('medical.review-show', ['content' => $content->load(['author', 'reviews.reviewer'])]);
    }

    public function approve(Request $request, HealthContent $content): RedirectResponse
    {
        Gate::authorize('review', $content);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $content->approve($request->user(), $validated['note'] ?? null);

        return redirect()->route('medical.review')->with('status', "اعتُمد «{$content->title}» ونُشر.");
    }

    public function reject(Request $request, HealthContent $content): RedirectResponse
    {
        Gate::authorize('review', $content);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']], ['reason.required' => 'الرفض يحتاج ملاحظات يعدّل بها المحرّر.']);
        $content->reject($request->user(), $validated['reason']);

        return redirect()->route('medical.review')->with('status', "أُعيد «{$content->title}» للتعديل.");
    }

    public function renew(Request $request, HealthContent $content): RedirectResponse
    {
        Gate::authorize('renew', $content);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $content->renewApproval($request->user(), $validated['note'] ?? null);

        return back()->with('status', "جُدّد اعتماد «{$content->title}» حتى {$content->review_due_on->translatedFormat('j F Y')}.");
    }
}
