<x-app-layout>
    <x-slot:title>الأدوار والصلاحيات</x-slot:title>

    <x-page-header title="الأدوار والصلاحيات" description="كل حساب في النظام ينشئه مدير النظام ويرسل له دعوة؛ لا تسجيل ذاتي.">
        <x-slot:actions>
            <x-button :href="route('users.create')">إضافة حساب</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card title="الأدوار وما يراه كل منها" class="mb-6">
        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" role="list">
            @foreach (config('roles') as $key => $role)
                <li class="rounded-lg border border-gray-100 bg-surface p-4">
                    <div class="flex items-center justify-between gap-2">
                        <a href="{{ route('users.index', ['role' => $key]) }}" class="font-semibold text-ink hover:text-brand-800 focus:outline-none focus-visible:underline">{{ $role['label'] }}</a>
                        <x-badge color="brand">{{ $roleCounts[$key] ?? 0 }} حساب</x-badge>
                    </div>
                    <p class="mt-2 text-xs leading-6 text-gray-600">{{ collect($role['tabs'])->pluck('label')->implode('، ') }}</p>
                </li>
            @endforeach
        </ul>
    </x-card>

    <x-card :padding="false">
        <form method="GET" action="{{ route('users.index') }}" class="grid gap-4 border-b border-gray-100 p-5 sm:grid-cols-4" role="search">
            <x-form.input name="q" label="بحث بالاسم أو البريد" :value="$filters['q'] ?? null" type="search" />
            <x-form.select name="role" label="الدور" :options="$roleOptions" :value="$filters['role'] ?? null" placeholder="كل الأدوار" />
            <x-form.select name="status" label="الحالة" :options="collect(\App\Enums\AccountStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" :value="$filters['status'] ?? null" placeholder="كل الحالات" />
            <div class="flex items-end gap-2">
                <x-button type="submit">تصفية</x-button>
                @if (array_filter($filters))
                    <x-button variant="ghost" :href="route('users.index')">مسح</x-button>
                @endif
            </div>
        </form>

        @if ($users->isEmpty())
            <div class="p-5">
                <x-empty-state title="لا حسابات مطابقة." />
            </div>
        @else
            <x-table caption="الحسابات">
                <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-start">الاسم</th>
                        <th scope="col" class="px-5 py-3 text-start">الدور</th>
                        <th scope="col" class="px-5 py-3 text-start">القسم والمسمى</th>
                        <th scope="col" class="px-5 py-3 text-start">الحالة</th>
                        <th scope="col" class="px-5 py-3 text-start">آخر دخول</th>
                        <th scope="col" class="px-5 py-3"><span class="sr-only">إجراءات</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($users as $user)
                        <tr>
                            <td class="px-5 py-3">
                                <div class="font-medium text-ink">{{ $user->name }}</div>
                                <div class="text-xs text-gray-500" dir="ltr">{{ $user->email }}</div>
                            </td>
                            <td class="px-5 py-3">{{ $user->roleLabel() ?? '-' }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ collect([$user->department, $user->job_title])->filter()->implode('، ') ?: '-' }}</td>
                            <td class="px-5 py-3"><x-badge :color="$user->status()->color()">{{ $user->status()->label() }}</x-badge></td>
                            <td class="px-5 py-3 text-gray-600"><x-date :value="$user->last_login_at" relative empty="لم يدخل بعد" /></td>
                            <td class="px-5 py-3 text-end">
                                <x-button variant="ghost" size="sm" :href="route('users.edit', $user)">تعديل<span class="sr-only"> {{ $user->name }}</span></x-button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>

            @if ($users->hasPages())
                <div class="border-t border-gray-100 px-5 py-3">{{ $users->links() }}</div>
            @endif
        @endif
    </x-card>
</x-app-layout>
