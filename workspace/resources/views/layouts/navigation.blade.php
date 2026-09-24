{{-- الشريط العلوي: زر القائمة (جوال) · القسم الحالي · حساب المستخدم --}}
<header class="no-print sticky top-0 z-20 flex h-16 shrink-0 items-center gap-3 border-b border-brand-border bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-8">
    <button type="button" x-ref="menuButton" @click="openMobile()" aria-controls="app-sidebar" :aria-expanded="mobileOpen.toString()" aria-expanded="false" aria-label="فتح القائمة"
        class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl border border-brand-border bg-white text-brand-ink transition hover:bg-brand-surface hover:text-brand-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-sky lg:hidden">
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    </button>

    <p class="min-w-0 flex-1 truncate text-base font-bold text-brand-ink sm:text-lg">{{ $section }}</p>

    <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
        <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-expanded="false" aria-haspopup="true" aria-controls="user-menu"
            class="flex items-center gap-2 rounded-full py-1 pe-2 ps-1 transition hover:bg-brand-surface focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-sky">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-gradient text-sm font-bold text-white" aria-hidden="true">{{ mb_substr(Auth::user()->name, 0, 1) }}</span>
            <span class="hidden min-w-0 flex-col items-start leading-tight sm:flex">
                <span class="max-w-[10rem] truncate text-sm font-bold text-brand-ink">{{ Auth::user()->name }}</span>
                <span class="mt-0.5 rounded-full bg-brand-secondary/10 px-2 py-0.5 text-[10.5px] font-bold text-brand-tertiary">{{ Auth::user()->roleLabel() ?? 'بلا دور' }}</span>
            </span>
            <svg class="size-4 text-brand-muted" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
        </button>

        <div id="user-menu" x-show="open" x-cloak x-transition.opacity class="absolute end-0 z-40 mt-2 w-60 rounded-2xl border border-brand-border bg-white py-1.5 shadow-lg">
            <div class="border-b border-brand-border px-4 pb-2.5 pt-1.5">
                <p class="truncate text-sm font-bold text-brand-ink">{{ Auth::user()->name }}</p>
                <p class="truncate text-xs text-brand-muted" dir="ltr">{{ Auth::user()->email }}</p>
            </div>
            <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-brand-text hover:bg-brand-surface focus:bg-brand-surface focus:outline-none">الملف الشخصي</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="block w-full px-4 py-2 text-start text-sm text-red-700 hover:bg-red-50 focus:bg-red-50 focus:outline-none">تسجيل الخروج</button>
            </form>
        </div>
    </div>
</header>
