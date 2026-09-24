<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InvoicePaymentController extends Controller
{
    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('recordPayment', $invoice);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$invoice->balance(), 'decimal:0,2'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:255'],
        ], ['amount.max' => 'المبلغ يتجاوز المتبقي على الفاتورة ('.$invoice->balance().' ر.س).']);

        $invoice->recordPayment(
            (string) $validated['amount'],
            Carbon::parse($validated['paid_on']),
            PaymentMethod::from($validated['method']),
            $validated['reference'] ?? null,
            $request->user(),
        );

        return back()->with('status', $invoice->status->value === 'paid' ? 'سُجّلت الدفعة واكتمل سداد الفاتورة.' : 'سُجّلت الدفعة.');
    }
}
