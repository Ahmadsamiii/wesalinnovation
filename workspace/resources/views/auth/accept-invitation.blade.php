<x-guest-layout>
    <x-slot:title>تفعيل الحساب</x-slot:title>

    <h1 class="text-lg font-bold text-ink">مرحباً {{ $user->name }}</h1>
    <p class="mt-1 text-sm text-gray-600">
        عيّن كلمة مرور لحسابك <span dir="ltr" class="font-medium">{{ $user->email }}</span> لتدخل مساحة عمل وصال.
    </p>

    <form method="POST" action="{{ request()->fullUrl() }}" class="mt-6 space-y-4">
        @csrf

        <x-form.input name="password" :label="__('Password')" type="password" required autocomplete="new-password" autofocus
                      :hint="app()->isProduction() ? 'عشرة أحرف على الأقل، فيها حروف وأرقام.' : 'ثمانية أحرف على الأقل.'" />
        <x-form.input name="password_confirmation" :label="__('Confirm Password')" type="password" required autocomplete="new-password" />

        <x-button type="submit" class="w-full">تفعيل الحساب</x-button>
    </form>
</x-guest-layout>
