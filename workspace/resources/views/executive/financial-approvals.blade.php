<x-app-layout>
    <x-slot:title>الاعتمادات المالية</x-slot:title>

    <x-page-header title="الاعتمادات المالية" />
    <p class="-mt-4 mb-6 text-sm text-gray-600">أوامر الشراء التي تجاوز إجماليها <x-money :amount="$threshold" /> واعتمدتها المالية، وتنتظر قرارك.</p>

    <section aria-labelledby="pending-po" class="mb-8">
        <h2 id="pending-po" class="mb-3 flex items-center gap-2 text-lg font-semibold">بانتظار قرارك <x-badge color="yellow">{{ $pending->count() }}</x-badge></h2>

        @forelse ($pending as $order)
            @php($budget = $order->project->budget)
            @php($committed = $order->project->committedSpend())
            <x-card class="mb-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <h3 class="font-semibold">
                            <a href="{{ route('purchase-orders.show', $order) }}" class="text-brand-800 hover:underline"><span dir="ltr">{{ $order->number }}</span> — {{ $order->vendor_name }}</a>
                        </h3>
                        <p class="mt-1 text-xs text-gray-500">{{ $order->project->name }} — طلبه {{ $order->requester->name }} — <x-date :value="$order->submitted_at" relative /></p>
                        <ul class="mt-3 list-inside list-disc text-sm text-gray-700">
                            @foreach ($order->items->take(4) as $item)
                                <li>{{ $item->description }} — <x-money :amount="$item->total" /></li>
                            @endforeach
                            @if ($order->items->count() > 4)
                                <li class="text-gray-500">و{{ $order->items->count() - 4 }} بنود أخرى</li>
                            @endif
                        </ul>
                        <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                            <div><dt class="inline text-gray-500">الإجمالي:</dt> <dd class="inline font-semibold"><x-money :amount="$order->total" /></dd></div>
                            <div><dt class="inline text-gray-500">ميزانية المشروع:</dt> <dd class="inline">@if ($budget !== null)<x-money :amount="$budget" />@else — @endif</dd></div>
                            <div><dt class="inline text-gray-500">الملتزَم به:</dt> <dd class="inline"><x-money :amount="$committed" /></dd></div>
                        </dl>
                        @if ($budget !== null && (float) $committed > (float) $budget)
                            <p class="mt-2 text-xs font-semibold text-red-700">الالتزامات تتجاوز ميزانية المشروع.</p>
                        @endif
                        @php($financeNote = $order->approvals->firstWhere('stage', \App\Enums\ApprovalStage::Finance)?->note)
                        @if ($financeNote)
                            <p class="mt-2 rounded-lg bg-surface p-3 text-xs text-gray-700">ملاحظة المالية: {{ $financeNote }}</p>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('purchase-orders.decide', $order) }}" class="w-full space-y-3 sm:w-80">
                        @csrf
                        <x-form.textarea name="note" label="التعليل" rows="2" :id="'po-note-'.$order->id" hint="مطلوب عند الرفض." />
                        <div class="flex gap-2">
                            <x-button type="submit" name="decision" value="approved" variant="success">اعتماد</x-button>
                            <x-button type="submit" name="decision" value="rejected" variant="danger">رفض</x-button>
                        </div>
                    </form>
                </div>
            </x-card>
        @empty
            <x-empty-state title="لا أوامر شراء تنتظر قرارك." />
        @endforelse
    </section>

    <section aria-labelledby="recent-po">
        <div class="mb-3 flex items-center justify-between">
            <h2 id="recent-po" class="text-lg font-semibold">آخر القرارات المالية</h2>
            <x-button variant="ghost" size="sm" :href="route('decisions.index', ['kind' => 'finance'])">السجل كاملاً</x-button>
        </div>
        @include('executive._financial-decisions', ['approvals' => $recent])
    </section>
</x-app-layout>
