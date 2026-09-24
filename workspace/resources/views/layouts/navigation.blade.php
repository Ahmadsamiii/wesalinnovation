@php($user = Auth::user())

<nav class="bg-white border-b border-gray-200" aria-label="التنقل الرئيسي">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between gap-4 h-16">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600">
                <x-application-logo class="h-9 w-auto" />
                <span class="hidden sm:inline text-sm font-semibold text-gray-500 border-s border-gray-200 ps-3">مساحة العمل</span>
            </a>

            <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
                <button type="button"
                        class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600"
                        @click="open = ! open"
                        :aria-expanded="open.toString()"
                        aria-haspopup="true"
                        aria-controls="user-menu">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-brand-800 font-bold" aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>
                    <span class="text-start leading-tight">
                        <span class="block font-semibold">{{ $user->name }}</span>
                        <span class="block text-xs text-gray-500">{{ $user->roleLabel() ?? 'بلا دور' }}</span>
                    </span>
                    <svg class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>

                <div id="user-menu" x-show="open" x-cloak x-transition.opacity
                     class="absolute end-0 z-40 mt-2 w-56 rounded-xl border border-gray-200 bg-white py-1 shadow-lg">
                    <p class="px-4 py-2 text-xs text-gray-500 border-b border-gray-100" dir="ltr">{{ $user->email }}</p>
                    <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                        {{ __('Profile') }}
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full px-4 py-2 text-start text-sm text-gray-700 hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                            {{ __('Log Out') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <x-role-tabs />
</nav>
