<x-app-layout>
    <x-slot:title>{{ __('Profile') }}</x-slot:title>

    <x-page-header :title="__('Profile')" description="بريدك ودورك وقسمك يعدّلها مدير النظام؛ تواصل معه إن احتاجت تغييراً." />

    <div class="grid [&>*]:min-w-0 gap-6 lg:grid-cols-2">
        <x-card title="بيانات الحساب">
            <dl class="mb-6 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-gray-500">{{ __('Email') }}</dt>
                    <dd class="font-medium text-right" dir="ltr">{{ $user->email }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">الدور</dt>
                    <dd class="font-medium">{{ $user->roleLabel() ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">القسم</dt>
                    <dd class="font-medium">{{ $user->department ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">المسمى الوظيفي</dt>
                    <dd class="font-medium">{{ $user->job_title ?? '-' }}</dd>
                </div>
            </dl>

            <form method="post" action="{{ route('profile.update') }}" class="space-y-4">
                @csrf
                @method('patch')

                <x-form.input name="name" :label="__('Name')" :value="$user->name" required autocomplete="name" />
                <x-form.input name="phone" label="رقم الجوال" type="tel" :value="$user->phone" autocomplete="tel" dir="ltr" />

                <x-button type="submit">{{ __('Save') }}</x-button>
            </form>
        </x-card>

        <x-card :title="__('Update Password')">
            <p class="mb-4 text-sm text-gray-600">{{ __('Ensure your account is using a long, random password to stay secure.') }}</p>

            <form method="post" action="{{ route('password.update') }}" class="space-y-4">
                @csrf
                @method('put')

                <x-form.input name="current_password" :label="__('Current Password')" type="password" bag="updatePassword" autocomplete="current-password" required />
                <x-form.input name="password" :label="__('New Password')" type="password" bag="updatePassword" autocomplete="new-password" required />
                <x-form.input name="password_confirmation" :label="__('Confirm Password')" type="password" bag="updatePassword" autocomplete="new-password" required />

                <x-button type="submit">{{ __('Save') }}</x-button>
            </form>
        </x-card>
    </div>
</x-app-layout>
