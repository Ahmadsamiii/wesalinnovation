<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-base font-bold text-brand-ink sm:text-lg">
            {{ __('Profile') }}
        </h1>
    </x-slot>

    <div>
        <div class="space-y-6">
            <div class="p-4 sm:p-8 bg-white border border-brand-border shadow-sm rounded-2xl">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white border border-brand-border shadow-sm rounded-2xl">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white border border-brand-border shadow-sm rounded-2xl">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
