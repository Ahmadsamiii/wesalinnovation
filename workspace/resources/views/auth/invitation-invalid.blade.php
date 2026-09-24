<x-guest-layout>
    <x-slot:title>رابط الدعوة غير صالح</x-slot:title>

    <h1 class="text-lg font-bold text-ink">رابط الدعوة غير صالح</h1>
    <p class="mt-2 text-sm leading-7 text-gray-600">
        قد يكون الرابط منتهياً (صلاحيته {{ \App\Models\User::INVITATION_VALID_DAYS }} أيام)، أو استُخدم من قبل، أو أُرسلت
        بعده دعوة أحدث. اطلب من مدير النظام إعادة إرسال الدعوة.
    </p>
    <p class="mt-4 text-sm">
        فعّلت حسابك من قبل؟ <a href="{{ route('login') }}" class="font-semibold text-brand-800 hover:underline">سجّل الدخول</a>
    </p>
</x-guest-layout>
