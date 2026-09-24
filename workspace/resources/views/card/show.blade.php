<x-app-layout>
    <x-slot:title>بطاقتي الرقمية وشهاداتي</x-slot:title>

    <x-page-header title="بطاقتي الرقمية وشهاداتي" description="بطاقتك تثبت انتسابك لوصال الابتكار برمز يتحقق منه أي طرف؛ تبطل تلقائياً إن أُوقف حسابك." />

    <div class="grid gap-6 lg:grid-cols-2">
        <section aria-labelledby="card-heading">
            <h2 id="card-heading" class="sr-only">البطاقة الرقمية</h2>
            {{-- مقاس بطاقة الهوية (٨٥٫٦ × ٥٤ ملم) نسبةً، لتُطبع بحجمها. --}}
            <div id="employee-card" class="mx-auto aspect-[85.6/54] w-full max-w-md overflow-hidden rounded-2xl bg-gradient-to-br from-brand-900 via-brand-800 to-accent p-6 text-white shadow-lg">
                <div class="flex h-full flex-col justify-between">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs text-white/70">{{ config('workspace.company.name') }}</p>
                            <p class="mt-3 text-2xl font-bold leading-tight">{{ $user->name }}</p>
                            <p class="mt-1 text-sm text-white/85">{{ $user->job_title ?? $user->roleLabel() }}</p>
                            @if ($user->department)
                                <p class="text-xs text-white/70">{{ $user->department }}</p>
                            @endif
                        </div>
                        <img src="{{ asset('images/logo-white.png') }}" alt="" class="h-10 w-auto">
                    </div>
                    <div class="flex items-end justify-between gap-4 text-xs">
                        <div>
                            <p class="text-white/70">الرقم الوظيفي</p>
                            <p class="font-mono text-sm font-semibold" dir="ltr">{{ $user->employeeNumber() }}</p>
                        </div>
                        <div class="text-end">
                            <p class="text-white/70">رمز التحقق</p>
                            <p class="font-mono text-sm font-semibold" dir="ltr">{{ \App\Models\Certificate::formatVerificationCode($code) }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <p class="mt-4 text-center text-sm text-gray-600">
                يتحقق منها أي طرف على <a href="{{ route('verify.show', $code) }}" class="text-brand-800 hover:underline" dir="ltr">{{ route('verify.show', $code) }}</a>
            </p>
            <div class="mt-3 flex justify-center">
                <x-button variant="secondary" size="sm" onclick="window.print()">طباعة البطاقة</x-button>
            </div>
        </section>

        <x-card title="شهاداتي" :padding="false">
            @if ($certificates->isEmpty())
                <p class="p-5 text-sm text-gray-500">تصدر لك شهادة مشاركة حين يُنجز مشروع كنت في فريقه.</p>
            @else
                <ul class="divide-y divide-gray-100 text-sm" role="list">
                    @foreach ($certificates as $certificate)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                            <div>
                                <a href="{{ route('certificates.show', $certificate) }}" class="font-medium text-brand-800 hover:underline">{{ $certificate->title }}</a>
                                <p class="text-xs text-gray-500"><span dir="ltr">{{ $certificate->number }}</span> — <x-date :value="$certificate->issued_at" /></p>
                            </div>
                            @if ($certificate->isRevoked())
                                <x-badge color="red">ملغاة</x-badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    {{-- طباعة البطاقة وحدها بمقاسها الفعلي. --}}
    <style>
        @media print {
            @page { size: 85.6mm 54mm; margin: 0; }
            body * { visibility: hidden; }
            #employee-card, #employee-card * { visibility: visible; }
            #employee-card { position: fixed; inset: 0; width: 85.6mm; max-width: none; border-radius: 0; box-shadow: none; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</x-app-layout>
