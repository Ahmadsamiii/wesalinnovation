{{-- الشريط العلوي: زر القائمة (جوال) · عنوان الصفحة · حساب المستخدم --}}
<header class="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-3 border-b border-brand-border bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-8">
    <button type="button" x-ref="menuButton" @click="openMobile()" aria-controls="app-sidebar" :aria-expanded="mobileOpen.toString()" aria-expanded="false" aria-label="فتح القائمة"
        class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl border border-brand-border bg-white text-brand-ink transition hover:bg-brand-surface hover:text-brand-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-sky lg:hidden">
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    </button>

    <div class="min-w-0 flex-1">
        @isset($header)
            {{ $header }}
        @endisset
    </div>

    <x-dropdown align="right" width="w-60" content-classes="py-1.5 bg-white rounded-2xl">
        <x-slot name="trigger">
            <button type="button" class="flex items-center gap-2 rounded-full py-1 pe-2 ps-1 transition hover:bg-brand-surface focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-sky">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-gradient text-sm font-bold text-white" aria-hidden="true">{{ mb_substr(Auth::user()->name, 0, 1) }}</span>
                <span class="hidden min-w-0 flex-col items-start leading-tight sm:flex">
                    <span class="max-w-[10rem] truncate text-sm font-bold text-brand-ink">{{ Auth::user()->name }}</span>
                    @if ($currentRole)
                        <span class="mt-0.5 rounded-full bg-brand-secondary/10 px-2 py-0.5 text-[10.5px] font-bold text-brand-tertiary">{{ $currentRole['label'] }}</span>
                    @endif
                </span>
                <svg class="size-4 text-brand-muted" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
            </button>
        </x-slot>

        <x-slot name="content">
            <div class="border-b border-brand-border px-4 pb-2.5 pt-1.5">
                <p class="truncate text-sm font-bold text-brand-ink">{{ Auth::user()->name }}</p>
                <p class="truncate text-xs text-brand-muted" dir="ltr">{{ Auth::user()->email }}</p>
            </div>

            <x-dropdown-link :href="route('profile.edit')">
                الملف الشخصي
            </x-dropdown-link>

            <!-- Authentication -->
            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <x-dropdown-link :href="route('logout')" class="!text-red-700"
                        onclick="event.preventDefault();
                                    this.closest('form').submit();">
                    تسجيل الخروج
                </x-dropdown-link>
            </form>
        </x-slot>
    </x-dropdown>
</header>
