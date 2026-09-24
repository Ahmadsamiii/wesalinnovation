<x-print-layout :title="$certificate->number" :back="route('certificates.index')" back-label="الشهادات" landscape>
    <x-slot:tools>
        @can('revoke', $certificate)
            <details class="relative">
                <summary class="cursor-pointer rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-700">إلغاء الشهادة</summary>
                <form method="POST" action="{{ route('certificates.revoke', $certificate) }}" class="absolute end-0 z-10 mt-2 w-80 space-y-3 rounded-xl border border-gray-200 bg-white p-4 shadow-lg">
                    @csrf
                    <x-form.textarea name="reason" label="سبب الإلغاء" rows="2" required />
                    <x-button type="submit" variant="danger" size="sm">تأكيد الإلغاء</x-button>
                </form>
            </details>
        @endcan
    </x-slot:tools>

    @if (session('status') || $errors->any())
        <div class="no-print mx-auto mt-4 max-w-4xl px-4">
            <x-flash />
            @foreach ($errors->all() as $error)
                <p class="rounded-lg bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <main class="mx-auto my-6 max-w-4xl p-4 print:m-0 print:max-w-none print:p-0">
        <article class="relative overflow-hidden rounded-2xl border-[10px] border-double border-brand-800 bg-white px-12 py-10 text-center shadow-sm print:shadow-none">
            @if ($certificate->isRevoked())
                <p class="absolute inset-x-0 top-1/2 -translate-y-1/2 -rotate-12 text-6xl font-black text-red-600/25" aria-hidden="true">ملغاة</p>
            @endif

            <img src="{{ asset('images/logo-color.png') }}" alt="وصال" class="mx-auto h-16 w-auto">
            <p class="mt-4 text-sm tracking-wide text-gray-500">{{ config('workspace.company.name') }}</p>
            <h1 class="mt-2 text-3xl font-black text-brand-800">{{ $certificate->type->label() }}</h1>

            <p class="mt-8 text-lg text-gray-700">تُمنح هذه الشهادة إلى</p>
            <p class="mt-2 text-4xl font-bold text-ink">{{ $certificate->recipient->name }}</p>

            <p class="mx-auto mt-6 max-w-2xl text-lg leading-9 text-gray-700">
                @if ($certificate->type === \App\Enums\CertificateType::Completion)
                    تقديراً لإتمام مشروع «{{ $certificate->project->name }}» بنجاح
                    @if ($certificate->project->actual_end_date)
                        بتاريخ <x-date :value="$certificate->project->actual_end_date" />
                    @endif
                @else
                    تقديراً للمشاركة الفاعلة في مشروع «{{ $certificate->project->name }}»
                @endif
            </p>
            @if ($certificate->body)
                <p class="mx-auto mt-4 max-w-2xl whitespace-pre-line leading-8 text-gray-600">{{ $certificate->body }}</p>
            @endif

            <div class="mt-12 grid grid-cols-2 gap-8 text-sm">
                <div>
                    <p class="border-t border-gray-300 pt-2 font-semibold">{{ $certificate->project->pm->name }}</p>
                    <p class="text-gray-500">مدير المشروع</p>
                </div>
                <div>
                    <p class="border-t border-gray-300 pt-2 font-semibold">{{ $certificate->issuer->name }}</p>
                    <p class="text-gray-500">مُصدِر الشهادة</p>
                </div>
            </div>

            <footer class="mt-10 flex flex-wrap items-end justify-between gap-4 border-t border-gray-100 pt-4 text-start text-xs text-gray-500">
                <div>
                    <p>رقم الشهادة: <span dir="ltr" class="font-semibold text-gray-700">{{ $certificate->number }}</span></p>
                    <p>تاريخ الإصدار: <x-date :value="$certificate->issued_at" /></p>
                </div>
                <div class="text-end">
                    <p>للتحقق: <span dir="ltr">{{ route('verify.show') }}</span></p>
                    <p>رمز التحقق: <span dir="ltr" class="font-mono text-sm font-semibold text-gray-700">{{ \App\Models\Certificate::formatVerificationCode($certificate->verification_code) }}</span></p>
                </div>
            </footer>
        </article>

        @if ($certificate->isRevoked())
            <p class="no-print mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                أُلغيت هذه الشهادة <x-date :value="$certificate->revoked_at" />: {{ $certificate->revocation_reason }}
            </p>
        @endif
    </main>
</x-print-layout>
