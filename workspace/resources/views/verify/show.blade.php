<x-guest-layout>
    <x-slot:title>التحقق من مستند</x-slot:title>

    <h1 class="text-lg font-bold text-ink">التحقق من مستند صادر عن {{ config('workspace.company.name') }}</h1>
    <p class="mt-1 text-sm text-gray-600">أدخل رمز التحقق المطبوع على الشهادة أو الإفادة أو بطاقة الموظف.</p>

    <form method="GET" action="{{ route('verify.show') }}" class="mt-4 flex gap-2" role="search">
        <label for="code" class="sr-only">رمز التحقق</label>
        <input id="code" name="code" type="text" value="{{ $code ? \App\Models\Certificate::formatVerificationCode($code) : '' }}" dir="ltr" required
               autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="XXXX-XXXX-XXXX"
               class="block w-full rounded-lg border-gray-300 font-mono shadow-sm focus:border-brand-600 focus:ring-brand-600">
        <x-button type="submit">تحقق</x-button>
    </form>

    @if ($code)
        <div class="mt-6" role="status">
            @if ($result === null)
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <p class="font-semibold">لا يوجد مستند بهذا الرمز.</p>
                    <p class="mt-1">تأكد من كتابة الرمز كما هو مطبوع. المستند الذي لا يُعثر عليه هنا لم يصدر عن النظام.</p>
                </div>
            @else
                <div @class(['rounded-xl border p-4 text-sm', 'border-green-200 bg-green-50 text-green-900' => $result['valid'], 'border-red-200 bg-red-50 text-red-900' => ! $result['valid']])>
                    <p class="text-base font-bold">
                        {{ $result['valid'] ? '✓ مستند صحيح وساري' : '✗ مستند صحيح لكنه لم يعد سارياً' }}
                    </p>
                    <dl class="mt-3 space-y-1">
                        <div class="flex justify-between gap-2"><dt class="opacity-75">النوع</dt><dd class="font-medium">{{ $result['kind'] }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="opacity-75">صاحبه</dt><dd class="font-medium">{{ $result['holder'] }}</dd></div>
                        @foreach ($result['details'] as $label => $value)
                            @continue($value === null)
                            <div class="flex justify-between gap-2"><dt class="opacity-75">{{ $label }}</dt><dd>{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                </div>
            @endif
        </div>
    @endif
</x-guest-layout>
