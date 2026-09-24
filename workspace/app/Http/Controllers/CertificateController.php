<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\CertificateType;
use App\Enums\ProjectStatus;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * «شهادات الإنجاز» لمدير المشاريع و«شهادة إنجازي» للعميل.
 */
class CertificateController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Certificate::class);

        $user = $request->user();

        return view('certificates.index', [
            'certificates' => Certificate::query()
                ->visibleTo($user)
                ->with(['project', 'recipient'])
                ->latest('issued_at')
                ->paginate(20),
            // مشاريع منجزة تنتظر شهادة إنجازها، ليصدرها مديرها بنقرة.
            'awaiting' => $user->can('create', Certificate::class)
                ? $this->completedProjects($request)
                    ->whereNotNull('client_id')
                    ->whereDoesntHave('certificates', fn (Builder $query) => $query->where('type', CertificateType::Completion)->valid())
                    ->get()
                : collect(),
            'isRecipientView' => ! $user->can('create', Certificate::class),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Certificate::class);

        $projects = $this->completedProjects($request)->with(['client', 'members.user'])->get();
        $project = $projects->firstWhere('id', $request->integer('project')) ?? $projects->first();

        return view('certificates.create', [
            'projects' => $projects,
            'project' => $project,
            'type' => CertificateType::tryFrom((string) $request->query('type')) ?? CertificateType::Completion,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Certificate::class);

        $validated = $request->validate([
            'project_id' => ['required', Rule::in($this->completedProjects($request)->pluck('id')->all())],
            'type' => ['required', Rule::enum(CertificateType::class)],
            'recipients' => ['required_if:type,participation', 'array'],
            'recipients.*' => ['integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:2000'],
        ], [
            'project_id.in' => 'الشهادات تصدر للمشاريع المنجزة التي تديرها.',
            'recipients.required_if' => 'اختر عضواً واحداً على الأقل من الفريق.',
        ]);

        $project = Project::with('members')->findOrFail($validated['project_id']);
        $type = CertificateType::from($validated['type']);

        $recipientIds = $type === CertificateType::Completion
            ? array_filter([$project->client_id])
            : array_values(array_intersect(array_map('intval', $validated['recipients'] ?? []), $project->members->pluck('user_id')->all()));

        if ($recipientIds === []) {
            throw ValidationException::withMessages(['recipients' => $type === CertificateType::Completion
                ? 'المشروع بلا عميل تصدر له شهادة الإنجاز.'
                : 'المستلمون يجب أن يكونوا من فريق المشروع.']);
        }

        // شهادة سارية واحدة لكل مستلم ونوع ومشروع؛ إعادة الإصدار بعد إلغاء فقط.
        $alreadyHolding = Certificate::query()
            ->where('project_id', $project->id)
            ->where('type', $type)
            ->whereIn('recipient_id', $recipientIds)
            ->valid()
            ->pluck('recipient_id')
            ->all();
        $recipientIds = array_values(array_diff($recipientIds, $alreadyHolding));

        if ($recipientIds === []) {
            return back()->with('error', 'كل المختارين يحملون هذه الشهادة سارية بالفعل.');
        }

        $issued = DB::transaction(fn () => collect($recipientIds)->map(function (int $recipientId) use ($project, $type, $validated, $request): Certificate {
            $certificate = new Certificate([
                'type' => $type,
                'project_id' => $project->id,
                'recipient_id' => $recipientId,
                'title' => ($validated['title'] ?? null) ?: Certificate::defaultTitle($type, $project),
                'body' => $validated['body'] ?? null,
            ]);
            $certificate->issued_by = $request->user()->id;
            $certificate->save();

            AuditLog::record(AuditAction::CertificateIssued, $certificate);

            return $certificate;
        }));

        return $issued->count() === 1
            ? redirect()->route('certificates.show', $issued->first())->with('status', 'صدرت الشهادة '.$issued->first()->number.'.')
            : redirect()->route('certificates.index')->with('status', 'صدرت '.$issued->count().' شهادات.');
    }

    public function show(Certificate $certificate): View
    {
        Gate::authorize('view', $certificate);

        return view('certificates.show', ['certificate' => $certificate->load(['project.pm', 'recipient', 'issuer'])]);
    }

    public function revoke(Request $request, Certificate $certificate): RedirectResponse
    {
        Gate::authorize('revoke', $certificate);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $certificate->revoke($request->user(), $validated['reason']);

        return back()->with('status', 'أُلغيت الشهادة. رمز تحققها يجيب الآن بأنها ملغاة.');
    }

    /**
     * @return Builder<Project>
     */
    private function completedProjects(Request $request): Builder
    {
        return Project::query()
            ->where('status', ProjectStatus::Completed)
            ->when(! $request->user()->hasRole('executive'), fn (Builder $query) => $query->where('pm_id', $request->user()->id))
            ->orderByDesc('actual_end_date');
    }
}
