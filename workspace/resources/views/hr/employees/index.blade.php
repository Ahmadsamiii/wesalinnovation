<x-app-layout>
    <x-slot:title>الموظفون</x-slot:title>

    <x-page-header title="الموظفون" description="كل الحسابات عدا العملاء وبياناتهم الوظيفية. الأدوار وحالة الحسابات تُدار من صفحة المستخدمين في المنصة." />

    <x-card :padding="false">
        <form method="GET" action="{{ route('hr.employees') }}" class="grid gap-4 border-b border-gray-100 p-5 sm:grid-cols-3" role="search">
            <x-form.input name="q" label="بحث بالاسم" :value="$filters['q'] ?? null" type="search" />
            <x-form.select name="role" label="الدور" :options="$roles" :value="$filters['role'] ?? null" placeholder="كل الأدوار" />
            <div class="flex items-end gap-2">
                <x-button type="submit">تصفية</x-button>
                @if (array_filter($filters))
                    <x-button variant="ghost" :href="route('hr.employees')">مسح</x-button>
                @endif
            </div>
        </form>

        @if ($employees->isEmpty())
            <div class="p-5">
                <x-empty-state title="لا موظفين مطابقين." />
            </div>
        @else
            <x-table caption="الموظفون">
                <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-start">الاسم</th>
                        <th scope="col" class="px-5 py-3 text-start">الدور</th>
                        <th scope="col" class="px-5 py-3 text-start">الرقم الوظيفي</th>
                        <th scope="col" class="px-5 py-3 text-start">القسم</th>
                        <th scope="col" class="px-5 py-3 text-start">المسمى الوظيفي</th>
                        <th scope="col" class="px-5 py-3 text-start">تاريخ الانضمام</th>
                        <th scope="col" class="px-5 py-3 text-start">الحالة</th>
                        @if ($canEdit)
                            <th scope="col" class="px-5 py-3"><span class="sr-only">إجراءات</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($employees as $employee)
                        <tr>
                            <td class="px-5 py-3 font-medium text-ink">{{ $employee->name }}</td>
                            <td class="px-5 py-3">{{ $employee->roleLabel() ?? '-' }}</td>
                            <td class="px-5 py-3 text-gray-600" dir="ltr">{{ $employee->employeeNumber() }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $employee->department ?? '-' }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $employee->job_title ?? '-' }}</td>
                            <td class="px-5 py-3 text-gray-600"><x-date :value="$employee->joined_at" empty="-" /></td>
                            <td class="px-5 py-3"><x-badge :color="$employee->status()->color()">{{ $employee->status()->label() }}</x-badge></td>
                            @if ($canEdit)
                                <td class="px-5 py-3 text-end">
                                    <x-button variant="ghost" size="sm" :href="route('hr.employees.edit', $employee)">تعديل<span class="sr-only"> {{ $employee->name }}</span></x-button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </x-table>

            @if ($employees->hasPages())
                <div class="border-t border-gray-100 px-5 py-3">{{ $employees->links() }}</div>
            @endif
        @endif
    </x-card>
</x-app-layout>
