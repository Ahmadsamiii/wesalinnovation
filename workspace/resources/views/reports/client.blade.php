<x-app-layout>
    <x-slot:title>تقرير حالة مشروعي</x-slot:title>

    <x-page-header title="تقرير حالة مشروعي" description="ملخص واحد للتقدّم والمراحل والعقود والفواتير، جاهز للطباعة أو الحفظ PDF." class="no-print">
        @if ($project)
            <x-slot:actions>
                <x-button onclick="window.print()">طباعة / حفظ PDF</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if ($projects->count() > 1)
        <form method="GET" action="{{ route('reports.client') }}" class="no-print mb-6 flex flex-wrap items-end gap-3">
            <x-form.select name="project" label="المشروع" :options="$projects->pluck('name', 'id')->all()" :value="$project?->id" class="w-full sm:w-80" />
            <x-button type="submit" variant="secondary">عرض التقرير</x-button>
        </form>
    @endif

    @if (! $project)
        <x-empty-state title="لا مشاريع بعد" description="يظهر تقرير الحالة هنا حين يُسجَّل لك مشروع." />
    @else
        <article class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm sm:p-10 print:rounded-none print:border-0 print:p-0 print:shadow-none">
            <header class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-200 pb-6">
                <div>
                    <p class="text-sm text-gray-500">{{ config('workspace.company.name') }} — تقرير حالة مشروع</p>
                    <h2 class="mt-1 text-2xl font-bold text-ink">{{ $project->name }}</h2>
                    <p class="mt-1 text-sm text-gray-500">حتى تاريخ <x-date :value="today()" /></p>
                </div>
                <x-badge :color="$project->status->color()">{{ $project->status->label() }}</x-badge>
            </header>

            <section class="mt-6 grid gap-6 sm:grid-cols-2" aria-label="التقدّم والمواعيد">
                <div>
                    <h3 class="text-sm font-semibold text-gray-700">نسبة الإنجاز</h3>
                    <p class="mt-1 text-3xl font-bold text-ink">{{ $project->progress() }}٪</p>
                    <x-progress :value="$project->progress()" :show-value="false" class="mt-2" />
                </div>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <div><dt class="text-gray-500">البداية</dt><dd class="mt-0.5 font-medium"><x-date :value="$project->actual_start_date ?? $project->start_date" empty="لم يبدأ بعد" /></dd></div>
                    @if ($project->status === \App\Enums\ProjectStatus::Completed)
                        <div><dt class="text-gray-500">تاريخ الإنجاز</dt><dd class="mt-0.5 font-medium"><x-date :value="$project->actual_end_date" /></dd></div>
                    @else
                        <div><dt class="text-gray-500">النهاية المتوقعة</dt><dd class="mt-0.5 font-medium"><x-date :value="$project->end_date" empty="تُحدَّد لاحقاً" /></dd></div>
                    @endif
                    <div class="col-span-2"><dt class="text-gray-500">مدير المشروع</dt><dd class="mt-0.5 font-medium">{{ $project->pm->name }} — <span dir="ltr">{{ $project->pm->email }}</span></dd></div>
                </dl>
            </section>

            <section class="mt-8">
                <h3 class="mb-2 text-base font-semibold text-ink">المراحل</h3>
                @if ($project->milestones->isEmpty())
                    <p class="text-sm text-gray-500">لم تُحدَّد المراحل بعد.</p>
                @else
                    <x-table caption="المراحل">
                        <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-start">المرحلة</th>
                                <th scope="col" class="px-4 py-2 text-start">الموعد</th>
                                <th scope="col" class="px-4 py-2 text-start">الحالة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($project->milestones as $milestone)
                                <tr>
                                    <td class="px-4 py-2 font-medium">{{ $milestone->title }}</td>
                                    <td class="px-4 py-2"><x-date :value="$milestone->due_date" empty="يُحدَّد لاحقاً" /></td>
                                    <td class="px-4 py-2">
                                        @if ($milestone->isReached())
                                            <x-badge color="green">أُنجزت</x-badge> <x-date :value="$milestone->completed_at" class="text-xs text-gray-500" />
                                        @elseif ($milestone->isOverdue())
                                            <x-badge color="orange">تجاوزت موعدها</x-badge>
                                        @else
                                            <x-badge>قادمة</x-badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </section>

            <section class="mt-8">
                <h3 class="mb-2 text-base font-semibold text-ink">العقود</h3>
                @if ($contracts->isEmpty())
                    <p class="text-sm text-gray-500">لا عقود مسجّلة لهذا المشروع.</p>
                @else
                    <x-table caption="العقود">
                        <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-start">رقم العقد</th>
                                <th scope="col" class="px-4 py-2 text-start">العنوان</th>
                                <th scope="col" class="px-4 py-2 text-start">القيمة قبل الضريبة</th>
                                <th scope="col" class="px-4 py-2 text-start">الحالة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 tabular-nums">
                            @foreach ($contracts as $contract)
                                <tr>
                                    <td class="px-4 py-2 font-medium">{{ $contract->number }}</td>
                                    <td class="px-4 py-2">{{ $contract->title }}</td>
                                    <td class="px-4 py-2"><x-money :amount="$contract->value" /></td>
                                    <td class="px-4 py-2"><x-badge :color="$contract->status->color()">{{ $contract->status->label() }}</x-badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </section>

            <section class="mt-8">
                <h3 class="mb-2 text-base font-semibold text-ink">الفواتير</h3>
                @if ($invoices->isEmpty())
                    <p class="text-sm text-gray-500">لم تصدر فواتير لهذا المشروع بعد.</p>
                @else
                    <x-table caption="الفواتير">
                        <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-start">رقم الفاتورة</th>
                                <th scope="col" class="px-4 py-2 text-start">الإصدار</th>
                                <th scope="col" class="px-4 py-2 text-start">الاستحقاق</th>
                                <th scope="col" class="px-4 py-2 text-start">الإجمالي</th>
                                <th scope="col" class="px-4 py-2 text-start">المتبقي</th>
                                <th scope="col" class="px-4 py-2 text-start">الحالة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 tabular-nums">
                            @foreach ($invoices as $invoice)
                                <tr>
                                    <td class="px-4 py-2 font-medium">{{ $invoice->number }}</td>
                                    <td class="px-4 py-2"><x-date :value="$invoice->issue_date" /></td>
                                    <td class="px-4 py-2"><x-date :value="$invoice->due_date" /></td>
                                    <td class="px-4 py-2"><x-money :amount="$invoice->total" /></td>
                                    <td class="px-4 py-2"><x-money :amount="$invoice->balance()" /></td>
                                    <td class="px-4 py-2">
                                        @if ($invoice->isOverdue())
                                            <x-badge color="red">متأخرة السداد</x-badge>
                                        @else
                                            <x-badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </section>

            <section class="mt-8 rounded-lg bg-surface p-5" aria-label="الملخص المالي">
                <h3 class="mb-3 text-base font-semibold text-ink">الملخص المالي</h3>
                <dl class="grid gap-4 text-sm sm:grid-cols-4">
                    <div><dt class="text-gray-500">قيمة العقود قبل الضريبة</dt><dd class="mt-1 font-semibold"><x-money :amount="$totals['contracted']" /></dd></div>
                    <div><dt class="text-gray-500">المفوتر شاملاً الضريبة</dt><dd class="mt-1 font-semibold"><x-money :amount="$totals['invoiced']" /></dd></div>
                    <div><dt class="text-gray-500">المدفوع</dt><dd class="mt-1 font-semibold"><x-money :amount="$totals['paid']" /></dd></div>
                    <div><dt class="text-gray-500">المتبقي عليكم</dt><dd class="mt-1 font-semibold"><x-money :amount="$totals['outstanding']" /></dd></div>
                </dl>
            </section>

            @if ($certificates->isNotEmpty())
                <section class="mt-8">
                    <h3 class="mb-2 text-base font-semibold text-ink">الشهادات</h3>
                    <ul class="space-y-1 text-sm" role="list">
                        @foreach ($certificates as $certificate)
                            <li>{{ $certificate->title }} — رقم {{ $certificate->number }}، رمز التحقق <span dir="ltr" class="font-mono">{{ \App\Models\Certificate::formatVerificationCode($certificate->verification_code) }}</span></li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-xs text-gray-500">يتحقق أي طرف من صحتها عبر <span dir="ltr">{{ route('verify.show') }}</span></p>
                </section>
            @endif

            <footer class="mt-10 border-t border-gray-200 pt-4 text-xs text-gray-500">
                أُعدّ هذا التقرير آلياً من مساحة عمل {{ config('workspace.company.name') }}. للاستفسار تواصل مع مدير المشروع.
            </footer>
        </article>
    @endif
</x-app-layout>
