@php
    $tabs = $role['tabs'] ?? [];
    $firstTabLabel = $tabs ? reset($tabs) : 'لوحة التحكم';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-base font-bold text-brand-ink sm:text-lg" x-text="tabLabel || @js($firstTabLabel)">{{ $firstTabLabel }}</h1>
    </x-slot>

    <div x-init="initTabs(@js($tabs))" class="space-y-6">

        {{-- بطاقة الترحيب --}}
        <section class="relative isolate overflow-hidden rounded-3xl bg-brand-hero px-6 py-7 text-white shadow-xl shadow-brand-primary/20 sm:px-8">
            <div class="pointer-events-none absolute -end-16 -top-24 -z-10 size-64 rounded-full border-[36px] border-white/5" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-24 start-1/3 -z-10 size-72 rounded-full bg-brand-secondary/40 blur-3xl" aria-hidden="true"></div>

            <div class="flex flex-wrap items-center justify-between gap-6">
                <div class="min-w-0">
                    <h2 class="text-2xl font-extrabold leading-snug sm:text-3xl">مرحباً {{ Str::before(auth()->user()->name, ' ') ?: auth()->user()->name }}</h2>
                    <p class="mt-1.5 text-sm text-white/80">
                        @if ($role)
                            هذي لوحتك كـ{{ $role['label'] }} — تنقّل بين أقسامك من القائمة الجانبية.
                        @else
                            حسابك مفعّل وبانتظار إسناد دور من مدير النظام.
                        @endif
                    </p>
                    <div class="mt-4 flex flex-wrap items-center gap-3 text-xs text-white/75">
                        @if ($role)
                            <span class="rounded-full border border-white/25 bg-white/15 px-3 py-1 font-bold text-white">{{ $role['label'] }}</span>
                        @endif
                        <span>{{ now()->translatedFormat('l j F Y') }}</span>
                    </div>
                </div>

                @if ($tabs)
                    <dl class="grid grid-cols-2 divide-x divide-white/15 rounded-2xl border border-white/20 bg-white/10 py-3 text-center backdrop-blur rtl:divide-x-reverse">
                        <div class="px-6">
                            <dt class="text-[11px] text-white/70">أقسام لوحتك</dt>
                            <dd class="mt-1 text-2xl font-bold tabular-nums">{{ count($tabs) }}</dd>
                        </div>
                        <div class="px-6">
                            <dt class="text-[11px] text-white/70">القسم الحالي</dt>
                            <dd class="mt-2 max-w-[9rem] truncate text-sm font-bold" x-text="tabLabel">{{ $firstTabLabel }}</dd>
                        </div>
                    </dl>
                @endif
            </div>
        </section>

        @unless ($role)
            {{-- لا دور مُسنداً — لا مسار تسجيل ذاتي هنا، فهذه حالة تحتاج
                 تدخّل مدير النظام، لا افتراض دور افتراضي غير معتمَد. --}}
            <div class="rounded-2xl border border-brand-border bg-white p-8 text-center shadow-sm">
                <div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
                    <x-sidebar-icon name="sensitive_alerts" class="size-6" />
                </div>
                <p class="font-bold text-brand-ink">لا يوجد دور مُسنَد لحسابك بعد.</p>
                <p class="mt-2 text-sm text-brand-muted">تواصل مع مدير النظام لتفعيل صلاحياتك.</p>
            </div>
        @else
            {{-- وصول سريع لكل أقسام الدور --}}
            <section aria-labelledby="quick-access-title">
                <h2 id="quick-access-title" class="mb-3 text-sm font-bold text-brand-muted">وصول سريع</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    @foreach ($tabs as $key => $label)
                        <button type="button" @click="setTab(@js($key))"
                            :class="tab === @js($key) ? 'border-brand-secondary/60 ring-2 ring-brand-secondary/15' : 'border-brand-border'"
                            class="group flex items-center gap-3 rounded-2xl border bg-white p-4 text-start shadow-sm transition hover:-translate-y-0.5 hover:border-brand-secondary/50 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-sky">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-secondary/10 text-brand-tertiary transition group-hover:bg-brand-gradient group-hover:text-white">
                                <x-sidebar-icon :name="$key" class="size-[18px]" />
                            </span>
                            <span class="min-w-0 text-sm font-semibold leading-snug text-brand-ink">{{ $label }}</span>
                        </button>
                    @endforeach
                </div>
            </section>

            {{-- محتوى كل تبويب — نظرة عامة فقط في هذه المرحلة، بلا وحدات فعلية بعد --}}
            @foreach ($tabs as $key => $label)
                <section x-show="tab === @js($key)" @unless ($loop->first) x-cloak @endunless
                    id="tab-{{ $key }}" aria-labelledby="tab-title-{{ $key }}"
                    class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm">
                    <header class="flex items-center justify-between gap-3 border-b border-brand-border px-5 py-4 sm:px-6">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-gradient text-white">
                                <x-sidebar-icon :name="$key" />
                            </span>
                            <h3 id="tab-title-{{ $key }}" class="truncate text-lg font-bold text-brand-ink">{{ $label }}</h3>
                        </div>
                        <span class="shrink-0 rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">قيد البناء</span>
                    </header>
                    <div class="flex flex-col items-center px-6 py-14 text-center">
                        <div class="mb-4 flex size-14 items-center justify-center rounded-2xl bg-brand-surface text-brand-secondary">
                            <x-sidebar-icon :name="$key" class="size-7" />
                        </div>
                        <p class="max-w-md text-sm leading-7 text-brand-muted">هذا التبويب قيد البناء — الوحدة الفعلية (البيانات والإجراءات) تُضاف في مرحلة لاحقة من خطة التنفيذ.</p>
                    </div>
                </section>
            @endforeach
        @endunless

    </div>
</x-app-layout>
