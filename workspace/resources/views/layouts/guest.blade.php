<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">

        <title>{{ isset($title) ? $title.' | ' : '' }}{{ config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-brand-text antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center px-4 pt-10 sm:pt-0 bg-brand-bg">
            <a href="{{ url('/') }}" class="rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600">
                <x-application-logo class="h-16 w-auto" />
            </a>

            <main class="w-full sm:max-w-md mt-6 px-6 py-6 bg-white border border-brand-border shadow-sm overflow-hidden rounded-2xl">
                {{ $slot }}
            </main>

            <p class="mt-6 text-xs text-brand-muted">{{ config('app.name') }}، نظام داخلي لوصال الابتكار</p>
        </div>
    </body>
</html>
