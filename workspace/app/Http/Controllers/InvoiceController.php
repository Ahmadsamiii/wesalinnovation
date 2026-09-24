<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\ContractStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\InvoiceRequest;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «الفواتير» للمالية و«فواتيري» للعميل.
 */
class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Invoice::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in([...array_column(InvoiceStatus::cases(), 'value'), 'overdue'])],
            'project' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $visible = Invoice::query()->visibleTo($user);

        $invoices = (clone $visible)
            ->with(['project', 'client'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $status === 'overdue'
                ? $query->overdue()
                : $query->where('status', $status))
            ->when($filters['project'] ?? null, fn (Builder $query, string $project) => $query->where('project_id', $project))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $open = (clone $visible)->where('status', InvoiceStatus::Issued);

        return view('invoices.index', [
            'invoices' => $invoices,
            'filters' => $filters,
            'isClient' => $user->hasRole('client'),
            'outstanding' => (clone $open)->selectRaw('coalesce(sum(total - paid_amount), 0) as balance')->value('balance'),
            'overdueCount' => (clone $visible)->overdue()->count(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Invoice::class);

        return view('invoices.create', [
            ...$this->formOptions(),
            'selectedProject' => $request->integer('project') ?: null,
            'selectedContract' => $request->integer('contract') ?: null,
        ]);
    }

    public function store(InvoiceRequest $request): RedirectResponse
    {
        $invoice = DB::transaction(function () use ($request): Invoice {
            $invoice = new Invoice(Arr::except($request->validated(), 'items'));
            $invoice->created_by = $request->user()->id;
            $invoice->save();
            $invoice->syncItems($request->validated('items'));

            AuditLog::record(AuditAction::InvoiceCreated, $invoice);

            return $invoice;
        });

        return redirect()->route('invoices.show', $invoice)->with('status', 'أُنشئت مسودة الفاتورة. راجعها ثم أصدرها ليأخذ رقمها التسلسلي.');
    }

    public function show(Invoice $invoice): View
    {
        Gate::authorize('view', $invoice);

        $invoice->load(['project', 'contract', 'client', 'creator', 'items', 'payments.recorder']);

        return view('invoices.show', [
            'invoice' => $invoice,
            'methods' => collect(PaymentMethod::cases())->mapWithKeys(fn (PaymentMethod $method): array => [$method->value => $method->label()])->all(),
        ]);
    }

    /**
     * نسخة للطباعة أو الحفظ PDF من المتصفح، بعربية صحيحة الاتجاه والتشكيل.
     */
    public function print(Invoice $invoice): View
    {
        Gate::authorize('view', $invoice);

        abort_if($invoice->status === InvoiceStatus::Draft && ! request()->user()->hasRole('finance'), 404);

        return view('invoices.print', ['invoice' => $invoice->load(['project', 'contract', 'client', 'items'])]);
    }

    public function edit(Invoice $invoice): View
    {
        Gate::authorize('update', $invoice);

        return view('invoices.edit', ['invoice' => $invoice->load('items'), ...$this->formOptions()]);
    }

    public function update(InvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        DB::transaction(function () use ($request, $invoice): void {
            $invoice->fill(Arr::except($request->validated(), 'items'));

            if ($invoice->isDirty('project_id')) {
                $invoice->client_id = $invoice->project()->first()->client_id;
            }

            $invoice->save();
            $invoice->syncItems($request->validated('items'));
        });

        return redirect()->route('invoices.show', $invoice)->with('status', 'حُفظت المسودة.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('delete', $invoice);

        $invoice->delete();

        return redirect()->route('invoices.index')->with('status', 'حُذفت مسودة الفاتورة.');
    }

    public function issue(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('issue', $invoice);

        if ($invoice->client_id === null) {
            return back()->with('error', 'المشروع بلا عميل؛ اربط المشروع بحساب عميل قبل إصدار فاتورة له.');
        }

        $invoice->issue($request->user());

        return back()->with('status', "صدرت الفاتورة برقم {$invoice->number} وصارت ظاهرة للعميل.");
    }

    public function cancel(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('cancel', $invoice);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $invoice->cancel($request->user(), $validated['reason']);

        return back()->with('status', 'أُلغيت الفاتورة.');
    }

    /**
     * @return array{projects: array<int, string>, contracts: array<int, string>}
     */
    private function formOptions(): array
    {
        return [
            'projects' => InvoiceRequest::projectOptions(),
            'contracts' => Contract::query()
                ->whereIn('status', [ContractStatus::Active, ContractStatus::Completed])
                ->with('project')
                ->latest('id')
                ->get()
                ->mapWithKeys(fn (Contract $contract): array => [$contract->id => $contract->number.' — '.$contract->title.' ('.$contract->project->name.')'])
                ->all(),
        ];
    }
}
