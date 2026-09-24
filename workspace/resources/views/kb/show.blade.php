<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $content->published_title }} | {{ config('workspace.company.name') }}</title>
    @if ($content->published_summary)
        <meta name="description" content="{{ $content->published_summary }}">
    @endif
    @vite(['resources/css/app.css'])
</head>
<body class="bg-surface font-sans text-ink antialiased">
    <header class="border-b border-gray-200 bg-white">
        <div class="mx-auto flex max-w-3xl items-center gap-3 px-4 py-4">
            <x-application-logo class="h-9 w-auto" />
            <span class="text-sm font-semibold text-gray-500">محتوى صحي معتمد</span>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 py-8">
        <article class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm sm:p-10">
            <p class="text-sm text-brand-800">{{ $content->category->label() }}</p>
            <h1 class="mt-1 text-2xl font-bold leading-snug text-ink sm:text-3xl">{{ $content->published_title }}</h1>
            @if ($content->published_summary)
                <p class="mt-3 text-base leading-8 text-gray-700">{{ $content->published_summary }}</p>
            @endif

            <x-prose :text="$content->published_body" class="mt-6 text-base leading-9 text-gray-900" />

            @if ($content->source_url)
                <p class="mt-8 text-sm text-gray-600">المرجع الرسمي: <a href="{{ $content->source_url }}" class="text-brand-800 hover:underline" dir="ltr" rel="noopener noreferrer">{{ $content->source_url }}</a></p>
            @endif

            <footer class="mt-8 border-t border-gray-200 pt-4 text-xs leading-6 text-gray-500">
                راجعه واعتمده المدير الطبي في {{ config('workspace.company.name') }} بتاريخ <x-date :value="$content->published_at" />.
                محتوى توعوي عام لا يغني عن استشارة الطبيب؛ في الحالات الطارئة اتصل بالإسعاف (٩٩٧).
            </footer>
        </article>
    </main>
</body>
</html>
