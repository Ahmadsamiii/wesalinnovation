<?php

namespace App\Http\Controllers\Medical;

use App\Enums\AlertOutcome;
use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ChatLog;
use App\Models\QuestionAlertReview;
use App\Models\SensitiveTerm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * «تنبيهات الأسئلة عالية الحساسية»: أسئلة مساعد المنصة التي تحوي كلمة من
 * قائمة التنبيه، مع جوابها، ليحكم المدير الطبي على سلامته. هوية السائل لا
 * تُقرأ أصلاً.
 */
class QuestionAlertController extends Controller
{
    /**
     * نافذة التنبيهات بالأيام.
     */
    private const WINDOW_DAYS = 30;

    public function index(Request $request): View
    {
        $terms = SensitiveTerm::query()->orderBy('term')->get();
        $filters = $request->validate(['show' => ['nullable', Rule::in(['pending', 'all'])]]);
        $showAll = ($filters['show'] ?? 'pending') === 'all';

        if (! ChatLog::isConfigured()) {
            return view('medical.alerts', ['connected' => false, 'terms' => $terms, 'showAll' => $showAll]);
        }

        if ($terms->isEmpty()) {
            return view('medical.alerts', ['connected' => true, 'terms' => $terms, 'showAll' => $showAll, 'alerts' => null, 'pendingCount' => 0, 'reviews' => collect()]);
        }

        try {
            $reviewedIds = QuestionAlertReview::query()->pluck('chat_log_id');
            $matching = fn () => ChatLog::query()
                ->mentioningAny($terms->pluck('term')->all())
                ->where('created_at', '>=', now()->subDays(self::WINDOW_DAYS));

            $alerts = $matching()
                ->when(! $showAll, fn ($query) => $query->whereNotIn('id', $reviewedIds))
                ->latest('created_at')
                ->latest('id')
                ->paginate(15, ['id', 'question', 'answer', 'provider', 'created_at'])
                ->withQueryString();
            $pendingCount = $matching()->whereNotIn('id', $reviewedIds)->count();
        } catch (Throwable $exception) {
            return view('medical.alerts', ['connected' => true, 'terms' => $terms, 'showAll' => $showAll, 'error' => Str::limit($exception->getMessage(), 300)]);
        }

        return view('medical.alerts', [
            'connected' => true,
            'terms' => $terms,
            'showAll' => $showAll,
            'alerts' => $alerts,
            'pendingCount' => $pendingCount,
            'reviews' => QuestionAlertReview::query()->whereIn('chat_log_id', $alerts->pluck('id'))->with('reviewer')->get()->keyBy('chat_log_id'),
        ]);
    }

    public function review(Request $request, int $chatLog): RedirectResponse
    {
        abort_unless(ChatLog::isConfigured() && ChatLog::query()->whereKey($chatLog)->exists(), 404);

        $validated = $request->validate([
            'outcome' => ['required', Rule::enum(AlertOutcome::class)],
            'note' => [Rule::requiredIf(fn (): bool => in_array($request->input('outcome'), [AlertOutcome::UnsafeAnswer->value, AlertOutcome::Escalated->value], true)), 'nullable', 'string', 'max:2000'],
        ], ['note.required' => 'اكتب ما رأيته وما يلزم فعله؛ هذه النتيجة تحتاج متابعة.']);

        QuestionAlertReview::query()->updateOrCreate(['chat_log_id' => $chatLog], [
            'outcome' => $validated['outcome'],
            'note' => $validated['note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        AuditLog::record(AuditAction::AlertReviewed, properties: ['chat_log_id' => $chatLog, 'outcome' => $validated['outcome']]);

        return back()->with('status', 'سُجّلت المراجعة.');
    }

    public function storeTerm(Request $request): RedirectResponse
    {
        $validated = $request->validate(['term' => ['required', 'string', 'min:2', 'max:100', 'unique:sensitive_terms,term']], [
            'term.unique' => 'الكلمة موجودة في القائمة.',
        ]);

        $term = new SensitiveTerm(['term' => trim($validated['term'])]);
        $term->created_by = $request->user()->id;
        $term->save();

        AuditLog::record(AuditAction::SensitiveTermAdded, properties: ['term' => $term->term]);

        return back()->with('status', "أُضيفت «{$term->term}» لكلمات التنبيه.");
    }

    public function destroyTerm(SensitiveTerm $term): RedirectResponse
    {
        $term->delete();

        AuditLog::record(AuditAction::SensitiveTermRemoved, properties: ['term' => $term->term]);

        return back()->with('status', "حُذفت «{$term->term}» من كلمات التنبيه.");
    }
}
