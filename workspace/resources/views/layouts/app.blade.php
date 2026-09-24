<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">

        <title>{{ isset($title) ? $title.' | ' : '' }}{{ config('app.name') }}</title>

        {{-- حالة القائمة المصغّرة تُطبَّق قبل أول رسم حتى لا تومض موسّعة --}}
        <script>try{if(localStorage.getItem('wesal_ws_sidebar_collapsed')==='1'){document.documentElement.classList.add('sb-collapsed');}}catch(e){}</script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-brand-bg text-brand-text print:bg-white">
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:start-2 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow focus:ring-2 focus:ring-brand-600">
            تخطَّ إلى المحتوى
        </a>

        <div x-data="appShell" x-effect="document.body.classList.toggle('overflow-hidden', mobileOpen)" @keydown.escape.window="closeMobile()" class="min-h-screen">
            @include('layouts.sidebar')

            <div class="flex min-h-screen flex-col transition-[padding] duration-300 ease-out lg:ps-72 lg:sb-collapsed:ps-20 print:!ps-0">
                @include('layouts.navigation')

                <main id="main" tabindex="-1" class="flex-1 focus:outline-none">
                    <div class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8 print:p-0">
                        <x-flash />

                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
