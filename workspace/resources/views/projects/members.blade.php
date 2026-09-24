<x-app-layout>
    <x-slot:title>فريق {{ $project->name }}</x-slot:title>

    <x-project-header :project="$project" active="members" />

    <div class="grid [&>*]:min-w-0 gap-6 lg:grid-cols-3">
        <x-card title="الفريق" :padding="false" class="lg:col-span-2">
            <x-table caption="أعضاء الفريق">
                <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-start">الاسم</th>
                        <th scope="col" class="px-5 py-3 text-start">الدور في المشروع</th>
                        <th scope="col" class="px-5 py-3 text-start">مهام مفتوحة</th>
                        <th scope="col" class="px-5 py-3"><span class="sr-only">إجراءات</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <tr>
                        <td class="px-5 py-3 font-medium">{{ $project->pm->name }}</td>
                        <td class="px-5 py-3"><x-badge color="brand">مدير المشروع</x-badge></td>
                        <td class="px-5 py-3">{{ $openTasks[$project->pm_id] ?? 0 }}</td>
                        <td></td>
                    </tr>
                    @foreach ($members as $member)
                        <tr>
                            <td class="px-5 py-3">
                                <div class="font-medium">{{ $member->user->name }}</div>
                                <div class="text-xs text-gray-500">{{ $member->user->roleLabel() }}{{ $member->user->job_title ? '، '.$member->user->job_title : '' }}</div>
                            </td>
                            <td class="px-5 py-3">
                                @can('manage', $project)
                                    <form method="POST" action="{{ route('projects.members.update', [$project, $member]) }}" class="flex items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <label for="role-{{ $member->id }}" class="sr-only">دور {{ $member->user->name }}</label>
                                        <select id="role-{{ $member->id }}" name="role" class="rounded-lg border-gray-300 py-1 text-sm focus:border-brand-600 focus:ring-brand-600">
                                            @foreach ($roles as $value => $label)
                                                <option value="{{ $value }}" @selected($member->role->value === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <x-button type="submit" variant="secondary" size="sm">تحديث</x-button>
                                    </form>
                                @else
                                    {{ $member->role->label() }}
                                @endcan
                            </td>
                            <td class="px-5 py-3">{{ $openTasks[$member->user_id] ?? 0 }}</td>
                            <td class="px-5 py-3 text-end">
                                @can('manage', $project)
                                    <form method="POST" action="{{ route('projects.members.destroy', [$project, $member]) }}"
                                          onsubmit="return confirm(@js('إزالة '.$member->user->name.' من الفريق؟ مهامه المفتوحة ستصير بلا إسناد.'))">
                                        @csrf
                                        @method('DELETE')
                                        <x-button type="submit" variant="ghost" size="sm" class="text-red-700">إزالة<span class="sr-only"> {{ $member->user->name }}</span></x-button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
            @if ($members->isEmpty())
                <p class="border-t border-gray-100 p-5 text-sm text-gray-500">لا أعضاء بعد غير مدير المشروع.</p>
            @endif
        </x-card>

        @can('manage', $project)
            <x-card title="إضافة عضو">
                @if ($candidates === [])
                    <p class="text-sm text-gray-500">كل الحسابات الداخلية النشطة في الفريق بالفعل.</p>
                @else
                    <form method="POST" action="{{ route('projects.members.store', $project) }}" class="space-y-4">
                        @csrf
                        <x-form.select name="user_id" label="الحساب" :options="$candidates" placeholder="اختر" required />
                        <x-form.select name="role" label="الدور في المشروع" :options="$roles" :value="\App\Enums\ProjectMemberRole::Member" required
                                       hint="قائد الفريق ينشئ المهام ويسندها كمدير المشروع." />
                        <x-button type="submit">إضافة</x-button>
                    </form>
                @endif
            </x-card>
        @endcan
    </div>
</x-app-layout>
