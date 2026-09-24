<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\ContractStatus;
use App\Http\Requests\ContractRequest;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «العقود» للمالية، و«العقود وأوامر الشراء» لمدير المشاريع، و«عقودي» للعميل.
 */
class ContractController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Contract::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ContractStatus::class)],
            'project' => ['nullable', 'integer'],
        ]);

        $contracts = Contract::query()
            ->visibleTo($request->user())
            ->with(['project', 'client'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['project'] ?? null, fn (Builder $query, string $project) => $query->where('project_id', $project))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('contracts.index', [
            'contracts' => $contracts,
            'filters' => $filters,
            'isClient' => $request->user()->hasRole('client'),
            'projects' => Project::query()->visibleTo($request->user())->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Contract::class);

        return view('contracts.create', [
            'projects' => ContractRequest::projectOptions($request->user()),
            'selectedProject' => $request->integer('project') ?: null,
        ]);
    }

    public function store(ContractRequest $request): RedirectResponse
    {
        $contract = new Contract($request->validated());
        $contract->created_by = $request->user()->id;
        $contract->save();

        AuditLog::record(AuditAction::ContractCreated, $contract);

        return redirect()->route('contracts.show', $contract)->with('status', "أُنشئ العقد {$contract->number} كمسودة.");
    }

    public function show(Contract $contract): View
    {
        Gate::authorize('view', $contract);

        $contract->load(['project.pm', 'client', 'creator', 'attachments.uploader', 'invoices' => fn ($query) => $query->latest('id')]);

        return view('contracts.show', ['contract' => $contract]);
    }

    public function edit(Request $request, Contract $contract): View
    {
        Gate::authorize('update', $contract);

        return view('contracts.edit', [
            'contract' => $contract,
            'projects' => ContractRequest::projectOptions($request->user()),
        ]);
    }

    public function update(ContractRequest $request, Contract $contract): RedirectResponse
    {
        $contract->fill($request->validated());

        if ($contract->isDirty('project_id')) {
            $contract->client_id = Project::find($contract->project_id)->client_id;
        }

        $contract->save();

        return redirect()->route('contracts.show', $contract)->with('status', 'حُفظ العقد.');
    }

    public function destroy(Contract $contract): RedirectResponse
    {
        Gate::authorize('delete', $contract);

        $contract->attachments->each->deleteWithFile();
        $contract->delete();

        return redirect()->route('contracts.index')->with('status', 'حُذفت مسودة العقد.');
    }

    /**
     * التوقيع: يصير العقد سارياً ويراه العميل. رفع النسخة الموقّعة من قسم المرفقات.
     */
    public function activate(Request $request, Contract $contract): RedirectResponse
    {
        Gate::authorize('activate', $contract);

        $validated = $request->validate(['signed_on' => ['required', 'date', 'before_or_equal:today']]);

        $contract->activate($request->user(), Carbon::parse($validated['signed_on']));

        return back()->with('status', 'فُعّل العقد بتاريخ توقيعه، وصار ظاهراً للعميل.');
    }

    public function close(Request $request, Contract $contract): RedirectResponse
    {
        Gate::authorize('close', $contract);

        $validated = $request->validate([
            'status' => ['required', Rule::in([ContractStatus::Completed->value, ContractStatus::Terminated->value])],
            'reason' => ['nullable', 'required_if:status,terminated', 'string', 'max:2000'],
        ], ['reason.required_if' => 'فسخ العقد يحتاج سبباً مكتوباً.']);

        $status = ContractStatus::from($validated['status']);
        $contract->close($status, $request->user(), $validated['reason'] ?? null);

        return back()->with('status', 'أُغلق العقد: '.$status->label().'.');
    }
}
