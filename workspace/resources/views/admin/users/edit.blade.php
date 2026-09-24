<x-app-layout>
    <x-slot:title>{{ $user->name }}</x-slot:title>

    <x-page-header :title="$user->name">
        <x-slot:breadcrumb><a href="{{ route('users.index') }}" class="hover:underline">الأدوار والصلاحيات</a></x-slot:breadcrumb>
        <x-slot:actions>
            <x-badge :color="$user->status()->color()">{{ $user->status()->label() }}</x-badge>
        </x-slot:actions>
    </x-page-header>

    <div class="grid [&>*]:min-w-0 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="بيانات الحساب">
                <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-6">
                    @csrf
                    @method('PUT')
                    @include('admin.users._fields', ['user' => $user])

                    <x-button type="submit">حفظ التعديلات</x-button>
                </form>
            </x-card>

            <x-card title="آخر ما سُجّل على الحساب" :padding="false">
                @if ($activity->isEmpty())
                    <p class="p-5 text-sm text-gray-500">لا شيء بعد.</p>
                @else
                    <ul class="divide-y divide-gray-100" role="list">
                        @foreach ($activity as $entry)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-badge :color="$entry->action->color()">{{ $entry->action->label() }}</x-badge>
                                    @if ($entry->details())
                                        <span class="text-gray-600">{{ $entry->details() }}</span>
                                    @endif
                                    <span class="text-gray-500">— {{ $entry->user?->name ?? 'النظام' }}</span>
                                </div>
                                <x-date :value="$entry->created_at" time class="text-xs text-gray-500" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            @if ($user->status() === \App\Enums\AccountStatus::Pending)
                <x-card title="الدعوة">
                    <p class="text-sm text-gray-600">
                        أُرسلت <x-date :value="$user->invited_at" relative />، وتنتهي صلاحية رابطها
                        <x-date :value="$user->invited_at?->copy()->addDays(\App\Models\User::INVITATION_VALID_DAYS)" time />.
                    </p>

                    <form method="POST" action="{{ route('users.invitation', $user) }}" class="mt-4">
                        @csrf
                        <x-button type="submit" variant="secondary">إعادة إرسال الدعوة</x-button>
                    </form>

                    <div class="mt-4" x-data="{ copied: false }">
                        <label for="invitation-link" class="block text-sm font-medium text-gray-700">رابط الدعوة الحالي</label>
                        <input id="invitation-link" type="text" readonly dir="ltr" value="{{ $user->invitationUrl() }}"
                               class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 text-xs" @focus="$el.select()">
                        <p class="mt-1 text-xs text-gray-500">لإرساله بنفسك إن لم يصل البريد. يبطل عند إعادة الإرسال.</p>
                    </div>
                </x-card>
            @endif

            <x-card title="حالة الحساب">
                @if ($user->isDeactivated())
                    <p class="text-sm text-gray-600">موقوف منذ <x-date :value="$user->deactivated_at" />. لا يستطيع الدخول، وتبقى أعماله منسوبة إليه.</p>
                    <form method="POST" action="{{ route('users.reactivate', $user) }}" class="mt-4">
                        @csrf
                        <x-button type="submit" variant="success">إعادة التفعيل</x-button>
                    </form>
                @elseif ($user->is(auth()->user()))
                    <p class="text-sm text-gray-600">هذا حسابك؛ لا يمكنك إيقافه بنفسك.</p>
                @else
                    <p class="text-sm text-gray-600">الإيقاف يمنع الدخول فوراً ويُخرج الجلسات المفتوحة، ويبقي الحساب منسوباً إليه كل ما أنشأه أو قرّره.</p>
                    <form method="POST" action="{{ route('users.deactivate', $user) }}" class="mt-4"
                          onsubmit="return confirm(@js('إيقاف حساب '.$user->name.'؟'))">
                        @csrf
                        <x-button type="submit" variant="danger">إيقاف الحساب</x-button>
                    </form>
                @endif
            </x-card>

            <x-card title="معلومات">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">أُنشئ</dt><dd><x-date :value="$user->created_at" /></dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">قبول الدعوة</dt><dd><x-date :value="$user->invitation_accepted_at" /></dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">آخر دخول</dt><dd><x-date :value="$user->last_login_at" time /></dd></div>
                </dl>
            </x-card>
        </div>
    </div>
</x-app-layout>
