<x-app-layout>
    <x-slot:title>{{ $invoice->number ?? 'مسودة فاتورة' }}</x-slot:title>

    <x-page-header :title="$invoice->number ? 'فاتورة '.$invoice->number : 'مسودة فاتورة'">
        <x-slot:breadcrumb><a href="{{ route('invoices.index') }}" class="hover:underline">الفواتير</a></x-slot:breadcrumb>
        <x-slot:actions>
            <x-badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-badge>
            @if ($invoice->isOverdue())
                <x-badge color="red">متأخرة</x-badge>
            @endif
            <x-button variant="secondary" size="sm" :href="route('invoices.print', $invoice)" target="_blank" rel="noopener">طباعة / PDF</x-button>
            @can('update', $invoice)
                <x-button variant="secondary" size="sm" :href="route('invoices.edit', $invoice)">تعديل</x-button>
            @endcan
            @can('delete', $invoice)
                <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" onsubmit="return confirm('حذف المسودة؟')">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="danger" size="sm">حذف المسودة</x-button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @can('issue', $invoice)
                <x-card title="الإصدار">
                    <p class="mb-4 text-sm text-gray-600">الإصدار يُسند الرقم التسلسلي ويُظهر الفاتورة للعميل، ولا تُعدَّل بعده. تصحيح فاتورة مصدرة يكون بإلغائها.</p>
                    <form method="POST" action="{{ route('invoices.issue', $invoice) }}">
                        @csrf
                        <x-button type="submit" variant="success">إصدار الفاتورة</x-button>
                    </form>
                </x-card>
            @endcan

            @if ($invoice->status === \App\Enums\InvoiceStatus::Cancelled)
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700" role="note">
                    أُلغيت <x-date :value="$invoice->cancelled_at" />: {{ $invoice->cancellation_reason }}
                </div>
            @endif

            <x-card title="البنود" :padding="false">
                <div class="overflow-x-auto">@include('invoices._items-table')</div>
            </x-card>

            <x-card title="الدفعات" :padding="false">
                @if ($invoice->payments->isEmpty())
                    <p class="p-5 text-sm text-gray-500">لا دفعات مسجّلة.</p>
                @else
                    <ul class="divide-y divide-gray-100 text-sm" role="list">
                        @foreach ($invoice->payments as $payment)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                                <span><x-money :amount="$payment->amount" class="font-semibold" /> — {{ $payment->method->label() }}{{ $payment->reference ? ' — '.$payment->reference : '' }}</span>
                                <span class="text-xs text-gray-500"><x-date :value="$payment->paid_on" /> — سجّلها {{ $payment->recorder->name }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @can('recordPayment', $invoice)
                    <form method="POST" action="{{ route('invoices.payments.store', $invoice) }}" class="grid gap-4 border-t border-gray-100 p-5 sm:grid-cols-2">
                        @csrf
                        <x-form.input name="amount" label="المبلغ (ريال)" type="number" step="0.01" min="0.01" :max="$invoice->balance()" :value="$invoice->balance()" required dir="ltr" />
                        <x-form.input name="paid_on" label="تاريخ الدفع" type="date" :value="today()->format('Y-m-d')" required />
                        <x-form.select name="method" label="طريقة الدفع" :options="$methods" :value="\App\Enums\PaymentMethod::BankTransfer" required />
                        <x-form.input name="reference" label="المرجع (رقم الحوالة...)" />
                        <div class="sm:col-span-2"><x-button type="submit">تسجيل الدفعة</x-button></div>
                    </form>
                @endcan
            </x-card>

            @can('cancel', $invoice)
                <details class="rounded-xl border border-gray-200 bg-white p-5">
                    <summary class="cursor-pointer text-sm font-medium text-red-700">إلغاء الفاتورة</summary>
                    <form method="POST" action="{{ route('invoices.cancel', $invoice) }}" class="mt-4 space-y-3">
                        @csrf
                        <x-form.textarea name="reason" label="سبب الإلغاء" rows="2" required />
                        <x-button type="submit" variant="danger" size="sm">إلغاء الفاتورة</x-button>
                    </form>
                </details>
            @endcan
        </div>

        <x-card title="البيانات">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-2"><dt class="text-gray-500">العميل</dt><dd>{{ $invoice->client?->name ?? '— (مشروع بلا عميل)' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">المشروع</dt><dd>
                    @can('view', $invoice->project)
                        <a href="{{ route('projects.show', $invoice->project) }}" class="text-brand-800 hover:underline">{{ $invoice->project->name }}</a>
                    @else
                        {{ $invoice->project->name }}
                    @endcan
                </dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">العقد</dt><dd>
                    @if ($invoice->contract)
                        <a href="{{ route('contracts.show', $invoice->contract) }}" class="text-brand-800 hover:underline" dir="ltr">{{ $invoice->contract->number }}</a>
                    @else
                        —
                    @endif
                </dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">الإصدار</dt><dd><x-date :value="$invoice->issue_date" /></dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">الاستحقاق</dt><dd><x-date :value="$invoice->due_date" /></dd></div>
                <div class="flex justify-between gap-2 border-t border-gray-100 pt-2"><dt class="text-gray-500">الإجمالي</dt><dd class="font-semibold"><x-money :amount="$invoice->total" /></dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">المدفوع</dt><dd><x-money :amount="$invoice->paid_amount" /></dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">المتبقي</dt><dd class="font-semibold"><x-money :amount="$invoice->balance()" /></dd></div>
            </dl>
            @if ($invoice->notes)
                <p class="mt-4 whitespace-pre-line border-t border-gray-100 pt-4 text-sm leading-7 text-gray-700">{{ $invoice->notes }}</p>
            @endif
        </x-card>
    </div>
</x-app-layout>
