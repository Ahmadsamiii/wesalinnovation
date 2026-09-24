<x-app-layout>
    <x-slot:title>{{ $contract->number }}</x-slot:title>

    <x-page-header :title="$contract->title">
        <x-slot:breadcrumb>
            <a href="{{ route('contracts.index') }}" class="hover:underline">العقود</a> / <span dir="ltr">{{ $contract->number }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <x-badge :color="$contract->status->color()">{{ $contract->status->label() }}</x-badge>
            @can('update', $contract)
                <x-button variant="secondary" size="sm" :href="route('contracts.edit', $contract)">تعديل</x-button>
            @endcan
            @can('delete', $contract)
                <form method="POST" action="{{ route('contracts.destroy', $contract) }}" onsubmit="return confirm('حذف مسودة العقد؟')">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="danger" size="sm">حذف المسودة</x-button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid [&>*]:min-w-0 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @can('activate', $contract)
                <x-card title="التوقيع والتفعيل">
                    <p class="mb-4 text-sm text-gray-600">بعد التفعيل يصير العقد سارياً ويظهر للعميل، ولا تُعدَّل بنوده. ارفع النسخة الموقّعة من قسم الملفات.</p>
                    <form method="POST" action="{{ route('contracts.activate', $contract) }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        <x-form.input name="signed_on" label="تاريخ التوقيع" type="date" :value="today()->format('Y-m-d')" required />
                        <x-button type="submit" variant="success">تفعيل العقد</x-button>
                    </form>
                </x-card>
            @endcan

            @can('close', $contract)
                <x-card title="إغلاق العقد">
                    <form method="POST" action="{{ route('contracts.close', $contract) }}" class="space-y-4">
                        @csrf
                        <fieldset>
                            <legend class="text-sm font-medium text-gray-700">نوع الإغلاق</legend>
                            <div class="mt-2 flex flex-wrap gap-4 text-sm">
                                <label class="flex items-center gap-2"><input type="radio" name="status" value="completed" required class="border-gray-300 text-brand-800 focus:ring-brand-600"> انتهى بإتمام الالتزامات</label>
                                <label class="flex items-center gap-2"><input type="radio" name="status" value="terminated" class="border-gray-300 text-brand-800 focus:ring-brand-600"> فُسخ قبل إتمامه</label>
                            </div>
                        </fieldset>
                        <x-form.textarea name="reason" label="السبب" rows="2" hint="مطلوب عند الفسخ." />
                        <x-button type="submit" variant="secondary">إغلاق العقد</x-button>
                    </form>
                </x-card>
            @endcan

            <x-card title="الفواتير على العقد" :padding="false">
                @if (auth()->user()->can('create', \App\Models\Invoice::class) && in_array($contract->status, [\App\Enums\ContractStatus::Active, \App\Enums\ContractStatus::Completed], true))
                    <x-slot:actions>
                        <x-button size="sm" :href="route('invoices.create', ['project' => $contract->project_id, 'contract' => $contract->id])">فاتورة على العقد</x-button>
                    </x-slot:actions>
                @endif
                @php($visibleInvoices = $contract->invoices->filter(fn ($invoice) => auth()->user()->can('view', $invoice)))
                @if ($visibleInvoices->isEmpty())
                    <p class="p-5 text-sm text-gray-500">لا فواتير بعد.</p>
                @else
                    <ul class="divide-y divide-gray-100 text-sm" role="list">
                        @foreach ($visibleInvoices as $invoice)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                                <a href="{{ route('invoices.show', $invoice) }}" class="font-medium text-brand-800 hover:underline">{{ $invoice->number ?? 'مسودة' }}</a>
                                <span class="flex items-center gap-2"><x-money :amount="$invoice->total" /> <x-badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-badge></span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card title="الملفات">
                @php($files = $contract->attachments)
                <x-attachment-list :attachments="$files" />
                @can('upload', $contract)
                    <x-upload-form :action="route('contracts.files.store', $contract)" class="mt-4 space-y-3 border-t border-gray-100 pt-4" />
                @endcan
            </x-card>
        </div>

        <x-card title="بيانات العقد">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-2"><dt class="text-gray-500">الرقم</dt><dd dir="ltr">{{ $contract->number }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">المشروع</dt><dd>
                    @can('view', $contract->project)
                        <a href="{{ route('projects.show', $contract->project) }}" class="text-brand-800 hover:underline">{{ $contract->project->name }}</a>
                    @else
                        {{ $contract->project->name }}
                    @endcan
                </dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">العميل</dt><dd>{{ $contract->client?->name ?? '-' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">القيمة قبل الضريبة</dt><dd><x-money :amount="$contract->value" /></dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">المفوتر منها</dt><dd><x-money :amount="$contract->invoicedTotal()" /></dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">السريان</dt><dd><x-date :value="$contract->start_date" /> ← <x-date :value="$contract->end_date" /></dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">التوقيع</dt><dd><x-date :value="$contract->signed_on" /></dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">أعدّه</dt><dd>{{ $contract->creator->name }}</dd></div>
            </dl>
            @if ($contract->notes)
                <p class="mt-4 whitespace-pre-line border-t border-gray-100 pt-4 text-sm leading-7 text-gray-700">{{ $contract->notes }}</p>
            @endif
        </x-card>
    </div>
</x-app-layout>
