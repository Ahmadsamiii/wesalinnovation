<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        {{-- حالة القائمة المصغّرة تُطبَّق قبل أول رسم حتى لا تومض موسّعة --}}
        <script>try{if(localStorage.getItem('wesal_ws_sidebar_collapsed')==='1'){document.documentElement.classList.add('sb-collapsed');}}catch(e){}</script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-brand-bg text-brand-text">
        @php
            $currentUser = auth()->user();
            $currentRoleName = $currentUser?->roles->first()?->name;
            $currentRole = $currentRoleName ? config("roles.{$currentRoleName}") : null;
        @endphp
        <div x-data="appShell" x-effect="document.body.classList.toggle('overflow-hidden', mobileOpen)" @keydown.escape.window="closeMobile()" class="min-h-screen">
            @include('layouts.sidebar')

            <div class="flex min-h-screen flex-col transition-[padding] duration-300 ease-out lg:ps-72 lg:sb-collapsed:ps-20">
                @include('layouts.navigation')

                <!-- Page Content -->
                <main class="flex-1">
                    <div class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
