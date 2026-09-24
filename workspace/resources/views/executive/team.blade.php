<x-app-layout>
    <x-slot:title>الفريق</x-slot:title>

    <x-page-header title="الفريق" description="الحسابات الداخلية النشطة وحملها الحالي من المشاريع والمهام." />

    <form method="GET" action="{{ route('team.index') }}" class="mb-6 flex flex-wrap items-end gap-3" role="search">
        <x-form.select name="role" label="الدور" :options="$roles" :value="$filters['role'] ?? null" placeholder="كل الأدوار الداخلية" />
        <x-button type="submit" variant="secondary">تصفية</x-button>
    </form>

    <x-card :padding="false">
        <x-table caption="الفريق">
            <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                <tr>
                    <th scope="col" class="px-5 py-3 text-start">الاسم</th>
                    <th scope="col" class="px-5 py-3 text-start">الدور والقسم</th>
                    <th scope="col" class="px-5 py-3 text-start">مشاريع يديرها</th>
                    <th scope="col" class="px-5 py-3 text-start">مشاريع يعمل فيها</th>
                    <th scope="col" class="px-5 py-3 text-start">مهام مفتوحة</th>
                    <th scope="col" class="px-5 py-3 text-start">متأخرة</th>
                    <th scope="col" class="px-5 py-3 text-start">أُنجزت آخر ٣٠ يوماً</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @foreach ($people as $person)
                    <tr>
                        <td class="px-5 py-3">
                            <div class="font-medium">{{ $person->name }}</div>
                            <div class="text-xs text-gray-500">{{ $person->job_title }}</div>
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ $person->roleLabel() }}{{ $person->department ? '، '.$person->department : '' }}</td>
                        <td class="px-5 py-3">{{ $managedProjects[$person->id] ?? 0 }}</td>
                        <td class="px-5 py-3">{{ $memberProjects[$person->id] ?? 0 }}</td>
                        <td class="px-5 py-3">{{ $person->open_tasks_count }}</td>
                        <td @class(['px-5 py-3', 'font-semibold text-red-700' => $person->overdue_tasks_count > 0])>{{ $person->overdue_tasks_count }}</td>
                        <td class="px-5 py-3">{{ $person->done_last_30_count }}</td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
    </x-card>
</x-app-layout>
