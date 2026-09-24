<x-app-layout>
    <x-slot:title>{{ $order->number }}</x-slot:title>

    <x-page-header :title="'أمر شراء — '.$order->vendor_name">
        <x-slot:breadcrumb>
            <a href="{{ route('purchase-orders.index') }}" class="hover:underline">أوامر الشراء</a> — <span dir="ltr">{{ $order->number }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <x-badge :color="$order->status->color()">{{ $order->status->label() }}</x-badge>
            @can('update', $order)
                <x-button variant="secondary" size="sm" :href="route('purchase-orders.edit', $order)">تعديل</x-button>
            @endcan
            @can('delete', $order)
                <form method="POST" action="{{ route('purchase-orders.destroy', $order) }}" onsubmit="return confirm('حذف مسودة أمر الشراء؟')">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="danger" size="sm">حذف المسودة</x-button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @php($lastRejection = $order->approvals->firstWhere('decision', \App\Enums\ApprovalDecision::Rejected))
            @if ($order->status === \App\Enums\PurchaseOrderStatus::Rejected && $lastRejection)
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="note">
                    <p class="font-semibold">رُفض في {{ $lastRejection->stage->label() }} — {{ $lastRejection->decider->name }}</p>
                    <p class="mt-1">{{ $lastRejection->note }}</p>
                    <p class="mt-2 text-xs">عدّل الأمر ثم أعد تقديمه.</p>
                </div>
            @endif

            @if (auth()->user()->can('submit', $order) || auth()->user()->can('review', $order) || auth()->user()->can('decide', $order) || auth()->user()->can('receive', $order))
                <x-card title="الخطوة التالية">
                    @can('submit', $order)
                        <form method="POST" action="{{ route('purchase-orders.submit', $order) }}">
                            @csrf
                            <x-button type="submit">{{ $order->status === \App\Enums\PurchaseOrderStatus::Rejected ? 'إعادة التقديم للمالية' : 'تقديم لمراجعة المالية' }}</x-button>
                        </form>
                    @endcan

                    @foreach (['review' => 'purchase-orders.review', 'decide' => 'purchase-orders.decide'] as $ability => $route)
                        @can($ability, $order)
                            @if ($ability === 'review' && $order->requiresExecutiveApproval())
                                <p class="mb-3 text-sm text-orange-700">الإجمالي يتجاوز حد اعتماد المالية؛ اعتمادك ينقله للمدير التنفيذي.</p>
                            @endif
                            <form method="POST" action="{{ route($route, $order) }}" class="space-y-3">
                                @csrf
                                <x-form.textarea name="note" label="التعليل" rows="2" hint="مطلوب عند الرفض، ويراه مقدّم الطلب." />
                                <div class="flex gap-2">
                                    <x-button type="submit" name="decision" value="approved" variant="success">اعتماد</x-button>
                                    <x-button type="submit" name="decision" value="rejected" variant="danger">رفض</x-button>
                                </div>
                            </form>
                        @endcan
                    @endforeach

                    @can('receive', $order)
                        <form method="POST" action="{{ route('purchase-orders.receive', $order) }}">
                            @csrf
                            <p class="mb-3 text-sm text-gray-600">سجّل الاستلام حين يصل ما طُلب كاملاً، وارفع فاتورة المورّد في الملفات.</p>
                            <x-button type="submit" variant="success">تسجيل الاستلام</x-button>
                        </form>
                    @endcan
                </x-card>
            @endif

            <x-card title="البنود" :padding="false">
                <x-table caption="بنود أمر الشراء">
                    <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                        <tr>
                            <th scope="col" class="px-5 py-3 text-start">الوصف</th>
                            <th scope="col" class="px-5 py-3 text-start">الكمية</th>
                            <th scope="col" class="px-5 py-3 text-start">سعر الوحدة</th>
                            <th scope="col" class="px-5 py-3 text-start">الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @foreach ($order->items as $item)
                            <tr>
                                <td class="px-5 py-3">{{ $item->description }}</td>
                                <td class="px-5 py-3 tabular-nums">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</td>
                                <td class="px-5 py-3"><x-money :amount="$item->unit_price" /></td>
                                <td class="px-5 py-3"><x-money :amount="$item->total" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 text-sm">
                        <tr><th scope="row" colspan="3" class="px-5 py-2 text-start font-normal text-gray-600">المجموع قبل الضريبة</th><td class="px-5 py-2"><x-money :amount="$order->subtotal" /></td></tr>
                        <tr><th scope="row" colspan="3" class="px-5 py-2 text-start font-normal text-gray-600">ضريبة القيمة المضافة ({{ (float) $order->vat_rate }}٪)</th><td class="px-5 py-2"><x-money :amount="$order->vat_amount" /></td></tr>
                        <tr><th scope="row" colspan="3" class="px-5 py-2 text-start">الإجمالي</th><td class="px-5 py-2 font-semibold"><x-money :amount="$order->total" /></td></tr>
                    </tfoot>
                </x-table>
            </x-card>

            <x-card title="مسار الاعتماد" :padding="false">
                @if ($order->approvals->isEmpty())
                    <p class="p-5 text-sm text-gray-500">لا قرارات بعد.</p>
                @else
                    <ul class="divide-y divide-gray-100 text-sm" role="list">
                        @foreach ($order->approvals as $approval)
                            <li class="px-5 py-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="flex items-center gap-2">
                                        <x-badge :color="$approval->decision->color()">{{ $approval->decision->label() }}</x-badge>
                                        {{ $approval->stage->label() }} — {{ $approval->decider->name }}
                                    </span>
                                    <x-date :value="$approval->decided_at" time class="text-xs text-gray-500" />
                                </div>
                                @if ($approval->note)
                                    <p class="mt-2 text-gray-600">{{ $approval->note }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card title="الملفات" >
                <x-attachment-list :attachments="$order->attachments" />
                @can('upload', $order)
                    <x-upload-form :action="route('purchase-orders.files.store', $order)" class="mt-4 space-y-3 border-t border-gray-100 pt-4" />
                @endcan
            </x-card>

            @can('cancel', $order)
                <details class="rounded-xl border border-gray-200 bg-white p-5">
                    <summary class="cursor-pointer text-sm font-medium text-red-700">إلغاء أمر الشراء</summary>
                    <form method="POST" action="{{ route('purchase-orders.cancel', $order) }}" class="mt-4 space-y-3">
                        @csrf
                        <x-form.textarea name="reason" label="سبب الإلغاء" rows="2" required />
                        <x-button type="submit" variant="danger" size="sm">إلغاء الأمر</x-button>
                    </form>
                </details>
            @endcan
        </div>

        <div class="space-y-6">
            <x-card title="البيانات">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">المشروع</dt><dd><a href="{{ route('projects.show', $order->project) }}" class="text-brand-800 hover:underline">{{ $order->project->name }}</a></dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">طلبه</dt><dd>{{ $order->requester->name }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">المورّد</dt><dd>{{ $order->vendor_name }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">التواصل</dt><dd>{{ $order->vendor_contact ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">مطلوب قبل</dt><dd><x-date :value="$order->needed_by" /></dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">قُدّم</dt><dd><x-date :value="$order->submitted_at" /></dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">استُلم</dt><dd><x-date :value="$order->received_at" /></dd></div>
                </dl>
                @if ($order->description)
                    <p class="mt-4 whitespace-pre-line border-t border-gray-100 pt-4 text-sm leading-7 text-gray-700">{{ $order->description }}</p>
                @endif
            </x-card>

            <x-card title="ميزانية المشروع">
                @if ($budget === null)
                    <p class="text-sm text-gray-500">المشروع بلا ميزانية محددة.</p>
                @else
                    @php($usedPercent = (float) $budget > 0 ? (int) min(100, round((float) $committed * 100 / (float) $budget)) : 100)
                    <x-progress :value="$usedPercent" label="نسبة الالتزام من الميزانية" />
                    <dl class="mt-3 space-y-1 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">الميزانية</dt><dd><x-money :amount="$budget" /></dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">الملتزَم به</dt><dd><x-money :amount="$committed" /></dd></div>
                    </dl>
                    @if ((float) $committed > (float) $budget)
                        <p class="mt-3 rounded-lg bg-red-50 p-3 text-xs text-red-800" role="alert">أوامر الشراء المقدّمة والمعتمدة تتجاوز ميزانية المشروع.</p>
                    @endif
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
