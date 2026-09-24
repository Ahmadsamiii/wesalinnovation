<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\HealthContentCategory;
use App\Enums\HealthContentStatus;
use App\Http\Requests\HealthContentRequest;
use App\Models\AuditLog;
use App\Models\HealthContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «إدارة المحتوى»: مكتبة المحتوى الصحي. مدير النظام يحرّر ويقدّم، والمدير
 * الطبي يعتمد (ContentReviewController)، والمعتمد وحده يُنشر ويُصدَّر
 * لقاعدة معرفة المساعد.
 */
class HealthContentController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', HealthContent::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(HealthContentStatus::class)],
            'category' => ['nullable', Rule::enum(HealthContentCategory::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return view('content.index', [
            'contents' => HealthContent::query()
                ->with('author')
                ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
                ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
                ->when($filters['q'] ?? null, fn ($query, string $search) => $query->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'))
                ->latest('updated_at')
                ->paginate(20)
                ->withQueryString(),
            'counts' => HealthContent::query()->toBase()->groupBy('status')->selectRaw('status, count(*) as total')->pluck('total', 'status'),
            'publishedCount' => HealthContent::query()->published()->count(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', HealthContent::class);

        return view('content.create');
    }

    public function store(HealthContentRequest $request): RedirectResponse
    {
        $content = new HealthContent($request->validated());
        $content->author_id = $request->user()->id;
        $content->save();

        AuditLog::record(AuditAction::ContentCreated, $content);

        return redirect()->route('content.show', $content)->with('status', 'حُفظت المسودة. قدّمها للمراجعة الطبية حين تكتمل.');
    }

    public function show(HealthContent $content): View
    {
        Gate::authorize('view', $content);

        return view('content.show', ['content' => $content->load(['author', 'reviews.reviewer'])]);
    }

    public function edit(HealthContent $content): View
    {
        Gate::authorize('update', $content);

        return view('content.edit', ['content' => $content]);
    }

    /**
     * أي تعديل على معتمد أو مرفوض أو مسحوب يعيده مسودة تحتاج مراجعة؛ النسخة
     * المنشورة (إن وُجدت) تبقى كما هي حتى يُعتمد التعديل.
     */
    public function update(HealthContentRequest $request, HealthContent $content): RedirectResponse
    {
        $content->fill($request->validated());

        if ($content->isDirty() && $content->status !== HealthContentStatus::Draft) {
            $content->status = HealthContentStatus::Draft;
        }

        $content->save();

        return redirect()->route('content.show', $content)->with('status', $content->isPublished()
            ? 'حُفظ التعديل مسودةً؛ النسخة المعتمدة تبقى منشورة حتى يُعتمد التعديل.'
            : 'حُفظت المسودة.');
    }

    public function destroy(HealthContent $content): RedirectResponse
    {
        Gate::authorize('delete', $content);

        AuditLog::record(AuditAction::ContentDeleted, $content);
        $content->delete();

        return redirect()->route('content.index')->with('status', 'حُذفت المسودة.');
    }

    public function submit(Request $request, HealthContent $content): RedirectResponse
    {
        Gate::authorize('submit', $content);

        $content->submit($request->user());

        return back()->with('status', 'قُدّم المحتوى للمراجعة الطبية.');
    }

    public function withdraw(Request $request, HealthContent $content): RedirectResponse
    {
        Gate::authorize('withdraw', $content);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']], ['reason.required' => 'اذكر سبب السحب؛ يبقى في السجل.']);
        $content->withdraw($request->user(), $validated['reason']);

        return back()->with('status', 'سُحب المحتوى من النشر. احذفه أيضاً من قاعدة معرفة المساعد (التعليمات في صفحة المكتبة).');
    }
}
