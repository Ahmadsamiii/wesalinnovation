@props(['title', 'back' => null, 'backLabel' => 'رجوع', 'landscape' => false])

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} | {{ config('workspace.company.name') }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4 {{ $landscape ? 'landscape' : 'portrait' }}; margin: 14mm; }
        @media print { body { background: #fff; } }
    </style>
</head>
<body class="bg-surface font-sans text-ink">
    {{-- أدوات الشاشة فقط؛ لا تظهر في الورقة المطبوعة ولا في PDF. --}}
    <div class="no-print mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-2 px-4 pt-6">
        @if ($back)
            <a href="{{ $back }}" class="text-sm text-brand-800 hover:underline">→ {{ $backLabel }}</a>
        @endif
        <div class="flex flex-wrap items-center gap-2">
            {{ $tools ?? '' }}
            <button type="button" onclick="window.print()" class="rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2">طباعة / حفظ PDF</button>
        </div>
    </div>

    {{ $slot }}
</body>
</html>
