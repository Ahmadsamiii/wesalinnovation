<x-app-layout>
    <x-slot:title>تنبيهات الأسئلة عالية الحساسية</x-slot:title>

    <x-page-header title="تنبيهات الأسئلة عالية الحساسية" description="أسئلة مساعد المنصة في آخر ٣٠ يوماً التي تحوي كلمة تنبيه، مع جوابها لتحكم على سلامته. هوية السائل لا تظهر." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-4 lg:col-span-2">
            @if (! $connected)
                <x-card title="قاعدة المنصة العامة غير مربوطة">
                    <p class="text-sm leading-7 text-gray-700">التنبيهات تُقرأ من سجل أسئلة المساعد في قاعدة المنصة العامة (قراءة فقط). يربطها مدير النظام من إعدادات الخادم، وتفاصيل الربط في صفحة «تكامل الذكاء الاصطناعي» لديه.</p>
                </x-card>
            @elseif ($error ?? null)
                <p class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">تعذّرت قراءة سجل المنصة: <span dir="ltr">{{ $error }}</span></p>
            @elseif ($alerts === null)
                <x-empty-state title="قائمة كلمات التنبيه فارغة." description="أضف كلمات من القائمة الجانبية لتبدأ التنبيهات." />
            @else
                <nav aria-label="ما يُعرض" class="flex flex-wrap items-center gap-2 text-sm">
                    @foreach (['pending' => 'بانتظار مراجعتك ('.$pendingCount.')', 'all' => 'الكل'] as $value => $label)
                        @php
                            $active = ($showAll ? 'all' : 'pending') === $value;
                        @endphp
                        <a href="{{ route('medical.alerts', ['show' => $value]) }}" @if ($active) aria-current="page" @endif
                           @class(['rounded-full border px-3 py-1.5 font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600',
                                   'border-brand-800 bg-brand-800 text-white' => $active, 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50' => ! $active])>{{ $label }}</a>
                    @endforeach
                </nav>

                @forelse ($alerts as $alert)
                    @php
                        $review = $reviews[$alert->id] ?? null;
                        $matched = $terms->pluck('term')->filter(fn (string $term): bool => str_contains($alert->question, $term))->values();
                    @endphp
                    <x-card>
                        <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                            <x-date :value="$alert->created_at" time />
                            @foreach ($matched as $term)
                                <x-badge color="orange">{{ $term }}</x-badge>
                            @endforeach
                            @if ($review)
                                <x-badge :color="$review->outcome->color()">{{ $review->outcome->label() }}</x-badge>
                            @endif
                        </div>
                        <p class="mt-3 whitespace-pre-line text-sm font-medium leading-7 text-ink">{{ $alert->question }}</p>
                        <details class="mt-3">
                            <summary class="cursor-pointer text-sm text-brand-800">جواب المساعد @if ($alert->provider)<span class="text-xs text-gray-500" dir="ltr">({{ $alert->provider }})</span>@endif</summary>
                            <div class="mt-2 max-h-80 overflow-y-auto whitespace-pre-line rounded-lg bg-surface p-3 text-sm leading-7 text-gray-800">{{ $alert->answer ?? 'لم يُسجَّل جواب (فشل المزوّد أو انقطع البث).' }}</div>
                        </details>

                        @if ($review)
                            <p class="mt-3 text-xs text-gray-500">راجعه {{ $review->reviewer->name }} <x-date :value="$review->reviewed_at" relative />@if ($review->note): {{ $review->note }}@endif</p>
                        @endif

                        <details class="mt-3" @if ($errors->any() && (int) old('alert') === $alert->id) open @endif>
                            <summary class="cursor-pointer text-sm font-medium text-brand-800">{{ $review ? 'تعديل المراجعة' : 'تسجيل المراجعة' }}</summary>
                            <form method="POST" action="{{ route('medical.alerts.review', $alert->id) }}" class="mt-3 space-y-3">
                                @csrf
                                <input type="hidden" name="alert" value="{{ $alert->id }}">
                                <x-form.select name="outcome" label="الحكم" :id="'outcome-'.$alert->id" :value="$review?->outcome" required placeholder="اختر"
                                               :options="collect(\App\Enums\AlertOutcome::cases())->mapWithKeys(fn ($o) => [$o->value => $o->label()])->all()" />
                                <x-form.textarea name="note" label="ملاحظة" :id="'note-'.$alert->id" :value="$review?->note" rows="2"
                                                 hint="مطلوبة للجواب غير الآمن والتصعيد: ما الخطأ وما المطلوب." />
                                <x-button type="submit" size="sm">حفظ</x-button>
                            </form>
                        </details>
                    </x-card>
                @empty
                    <x-empty-state :title="$showAll ? 'لا أسئلة حساسة في آخر ٣٠ يوماً.' : 'لا شيء ينتظر مراجعتك.'" />
                @endforelse

                @if ($alerts->hasPages())
                    <div>{{ $alerts->links() }}</div>
                @endif
            @endif
        </div>

        <x-card title="كلمات التنبيه" class="min-w-0">
            <p class="text-xs text-gray-500">أي سؤال يحوي واحدة منها يظهر هنا. أضف صيغ الكتابة الشائعة (بهمزة وبلا همزة).</p>
            <form method="POST" action="{{ route('medical.terms.store') }}" class="mt-3 flex items-end gap-2">
                @csrf
                <x-form.input name="term" label="كلمة جديدة" class="min-w-0 flex-1" required />
                <x-button type="submit" variant="secondary">إضافة</x-button>
            </form>
            <ul class="mt-4 flex flex-wrap gap-2" role="list">
                @foreach ($terms as $term)
                    <li>
                        <form method="POST" action="{{ route('medical.terms.destroy', $term) }}" class="inline-flex items-center gap-1 rounded-full bg-gray-100 py-1 pe-1 ps-3 text-xs text-gray-800">
                            @csrf
                            @method('DELETE')
                            {{ $term->term }}
                            <button type="submit" class="rounded-full px-1.5 text-gray-500 hover:bg-gray-200 hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600" aria-label="حذف {{ $term->term }}">×</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </x-card>
    </div>
</x-app-layout>
