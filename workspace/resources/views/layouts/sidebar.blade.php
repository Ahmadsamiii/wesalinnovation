{{--
    القائمة الجانبية — تبويبات الدور من config/roles.php وحدها (AppLayout)،
    كل تبويب رابط لصفحته، وتحتها خدمات الحساب التي ليست تبويباً للدور.
--}}
@php
    $itemBase = 'group relative flex min-h-[42px] w-full items-center gap-3 rounded-xl px-3 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-sky lg:sb-collapsed:justify-center lg:sb-collapsed:px-0';
    $itemIdle = 'text-brand-text hover:bg-brand-surface hover:text-brand-primary';
    $itemActive = 'bg-brand-gradient text-white shadow-lg shadow-brand-tertiary/25';
    $groups = array_filter([
        auth()->user()?->roleLabel() ?? '' => $tabs,
        'خدمات' => $services,
    ]);
@endphp

<div x-show="mobileOpen" x-cloak x-transition.opacity @click="closeMobile()" class="fixed inset-0 z-30 bg-brand-ink/40 lg:hidden" aria-hidden="true"></div>

<aside id="app-sidebar" x-ref="sidebar" :data-open="mobileOpen" aria-label="القائمة الرئيسية"
    class="no-print invisible fixed inset-y-0 start-0 z-40 flex w-72 max-w-[85vw] flex-col border-e border-brand-border bg-white shadow-2xl transition-[transform,width,visibility] duration-300 ease-out ltr:-translate-x-full rtl:translate-x-full data-[open=true]:visible data-[open=true]:translate-x-0 lg:visible lg:max-w-none lg:!translate-x-0 lg:shadow-none lg:sb-collapsed:w-20">

    <div class="relative flex h-16 shrink-0 items-center gap-2 border-b border-brand-border px-5 lg:sb-collapsed:justify-center lg:sb-collapsed:px-0">
        <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-sky">
            <x-application-logo class="h-10 w-auto lg:sb-collapsed:hidden" />
            {{-- العلامة وحدها للوضع المصغّر — صورة مستقلة بدل قص الشعار فلا تنقص --}}
            <img src="{{ asset('images/wesal-mark.png') }}" alt="{{ config('app.name') }}" class="hidden size-10 lg:sb-collapsed:block" width="40" height="40">
        </a>

        <button type="button" @click="toggleCollapsed()" aria-controls="app-sidebar"
            :aria-expanded="(!collapsed).toString()" :aria-label="collapsed ? 'توسيع القائمة' : 'تصغير القائمة'" aria-label="تصغير القائمة"
            class="absolute top-1/2 -end-4 z-10 hidden size-8 -translate-y-1/2 items-center justify-center rounded-full border border-brand-border bg-white text-brand-muted shadow-sm transition hover:border-brand-secondary hover:text-brand-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-sky lg:flex">
            <svg class="size-4 transition-transform duration-300 rtl:-scale-x-100 sb-collapsed:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </button>

        <button type="button" @click="closeMobile()" aria-label="إغلاق القائمة" class="ms-auto rounded-lg p-2 text-brand-muted hover:bg-brand-surface hover:text-brand-ink lg:hidden">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
    </div>

    <nav class="flex flex-1 flex-col gap-4 overflow-y-auto overflow-x-hidden px-3 py-4" aria-label="التنقل الرئيسي">
        @forelse ($groups as $heading => $items)
            <div class="flex flex-col gap-0.5">
                <p class="px-3 pb-1 text-[11.5px] font-bold text-brand-muted lg:sb-collapsed:sr-only">{{ $heading }}</p>
                @foreach ($items as $item)
                    <a href="{{ $item['url'] }}" @if ($item['current']) aria-current="{{ $item['current'] }}" @endif
                        data-tab="{{ $item['key'] }}" :title="collapsed ? @js($item['label']) : null"
                        class="{{ $itemBase }} {{ $item['active'] ? $itemActive : $itemIdle }}">
                        <x-sidebar-icon :name="$item['key']" />
                        <span class="truncate lg:sb-collapsed:sr-only">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @empty
            <a href="{{ route('dashboard') }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif :title="collapsed ? 'لوحة التحكم' : null"
                class="{{ $itemBase }} {{ request()->routeIs('dashboard') ? $itemActive : $itemIdle }}">
                <x-sidebar-icon name="dashboard" />
                <span class="truncate lg:sb-collapsed:sr-only">لوحة التحكم</span>
            </a>
        @endforelse
    </nav>

    <div class="shrink-0 space-y-0.5 border-t border-brand-border p-3">
        <a href="{{ route('profile.edit') }}" @if (request()->routeIs('profile.*')) aria-current="page" @endif :title="collapsed ? 'الملف الشخصي' : null"
            class="{{ $itemBase }} {{ request()->routeIs('profile.*') ? $itemActive : $itemIdle }}">
            <x-sidebar-icon name="profile" />
            <span class="truncate lg:sb-collapsed:sr-only">الملف الشخصي</span>
        </a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" :title="collapsed ? 'تسجيل الخروج' : null" class="{{ $itemBase }} text-red-700 hover:bg-red-50">
                <x-sidebar-icon name="logout" />
                <span class="truncate lg:sb-collapsed:sr-only">تسجيل الخروج</span>
            </button>
        </form>
    </div>
</aside>
