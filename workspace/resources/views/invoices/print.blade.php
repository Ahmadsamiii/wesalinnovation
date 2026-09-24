<x-print-layout :title="$invoice->number ?? 'مسودة فاتورة'" :back="route('invoices.show', $invoice)" back-label="العودة للفاتورة">
    <main class="mx-auto my-6 max-w-3xl rounded-xl bg-white p-10 shadow-sm print:my-0 print:max-w-none print:rounded-none print:p-0 print:shadow-none">
            <header class="flex items-start justify-between gap-6 border-b border-gray-200 pb-6">
                <div>
                    <h1 class="text-2xl font-bold">
                        @if ($invoice->status === \App\Enums\InvoiceStatus::Draft)
                            مسودة فاتورة — ليست للتحصيل
                        @else
                            فاتورة
                        @endif
                    </h1>
                    <dl class="mt-3 space-y-1 text-sm">
                        <div><dt class="inline text-gray-500">رقم الفاتورة:</dt> <dd class="inline font-semibold" dir="ltr">{{ $invoice->number ?? '—' }}</dd></div>
                        <div><dt class="inline text-gray-500">تاريخ الإصدار:</dt> <dd class="inline"><x-date :value="$invoice->issue_date" /></dd></div>
                        <div><dt class="inline text-gray-500">تاريخ الاستحقاق:</dt> <dd class="inline"><x-date :value="$invoice->due_date" /></dd></div>
                        @if ($invoice->contract)
                            <div><dt class="inline text-gray-500">العقد:</dt> <dd class="inline" dir="ltr">{{ $invoice->contract->number }}</dd></div>
                        @endif
                    </dl>
                </div>
                <div class="text-end text-sm">
                    <img src="{{ asset('images/logo-color.png') }}" alt="" class="ms-auto h-12 w-auto">
                    <p class="mt-2 font-semibold">{{ config('workspace.company.name') }}</p>
                    @foreach (['vat_number' => 'الرقم الضريبي', 'cr_number' => 'السجل التجاري', 'address' => 'العنوان', 'phone' => 'الهاتف', 'email' => 'البريد'] as $key => $label)
                        @if (config("workspace.company.{$key}"))
                            <p class="text-gray-600">{{ $label }}: {{ config("workspace.company.{$key}") }}</p>
                        @endif
                    @endforeach
                </div>
            </header>
    
            <section class="grid grid-cols-2 gap-6 py-6 text-sm">
                <div>
                    <h2 class="mb-1 text-xs font-semibold text-gray-500">فاتورة إلى</h2>
                    <p class="font-semibold">{{ $invoice->client?->name ?? '—' }}</p>
                    @if ($invoice->client)
                        <p class="text-gray-600" dir="ltr">{{ $invoice->client->email }}</p>
                    @endif
                </div>
                <div>
                    <h2 class="mb-1 text-xs font-semibold text-gray-500">المشروع</h2>
                    <p class="font-semibold">{{ $invoice->project->name }}</p>
                </div>
            </section>
    
            <div class="overflow-hidden rounded-lg border border-gray-200">@include('invoices._items-table')</div>
    
            <section class="mt-6 grid grid-cols-2 gap-6 text-sm">
                <div>
                    @if ($invoice->notes)
                        <h2 class="mb-1 text-xs font-semibold text-gray-500">ملاحظات</h2>
                        <p class="whitespace-pre-line leading-7">{{ $invoice->notes }}</p>
                    @endif
                </div>
                <dl class="space-y-1">
                    <div class="flex justify-between"><dt class="text-gray-500">المدفوع</dt><dd><x-money :amount="$invoice->paid_amount" /></dd></div>
                    <div class="flex justify-between text-base font-bold"><dt>المستحق</dt><dd><x-money :amount="$invoice->balance()" /></dd></div>
                </dl>
            </section>
    
            @if ($invoice->status === \App\Enums\InvoiceStatus::Cancelled)
                <p class="mt-8 rounded-lg border-2 border-red-300 p-4 text-center font-bold text-red-700">فاتورة ملغاة — {{ $invoice->cancellation_reason }}</p>
            @endif
        </main>
</x-print-layout>
