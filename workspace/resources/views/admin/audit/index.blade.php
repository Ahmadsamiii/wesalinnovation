<x-app-layout>
    <x-slot:title>السجلات والتدقيق</x-slot:title>

    <x-page-header title="السجلات والتدقيق" description="سجل إلحاقي لكل دخول وإجراء إداري وقرار في النظام، لا يُعدَّل ولا يُحذف من الواجهة." />

    <x-card :padding="false">
        <form method="GET" action="{{ route('audit.index') }}" class="grid gap-4 border-b border-gray-100 p-5 sm:grid-cols-2 lg:grid-cols-6" role="search">
            <x-form.select name="group" label="الفئة" :options="$groups" :value="$filters['group'] ?? null" placeholder="الكل" />
            <x-form.select name="action" label="الإجراء" :options="$actionOptions" :value="$filters['action'] ?? null" placeholder="الكل" />
            <x-form.select name="user" label="المنفّذ" :options="$userOptions" :value="$filters['user'] ?? null" placeholder="الكل" />
            <x-form.input name="from" label="من تاريخ" type="date" :value="$filters['from'] ?? null" />
            <x-form.input name="to" label="إلى تاريخ" type="date" :value="$filters['to'] ?? null" />
            <div class="flex items-end gap-2">
                <x-button type="submit">تصفية</x-button>
                @if (array_filter($filters))
                    <x-button variant="ghost" :href="route('audit.index')">مسح</x-button>
                @endif
            </div>
        </form>

        @if ($logs->isEmpty())
            <div class="p-5"><x-empty-state title="لا سجلات مطابقة." /></div>
        @else
            <x-table caption="سجل التدقيق">
                <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-start">الوقت</th>
                        <th scope="col" class="px-5 py-3 text-start">المنفّذ</th>
                        <th scope="col" class="px-5 py-3 text-start">الإجراء</th>
                        <th scope="col" class="px-5 py-3 text-start">على</th>
                        <th scope="col" class="px-5 py-3 text-start">التفاصيل</th>
                        <th scope="col" class="px-5 py-3 text-start">عنوان IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap px-5 py-3 text-gray-600">
                                <x-date :value="$log->created_at" time />
                            </td>
                            <td class="px-5 py-3">{{ $log->user?->name ?? '—' }}</td>
                            <td class="px-5 py-3"><x-badge :color="$log->action->color()">{{ $log->action->label() }}</x-badge></td>
                            <td class="px-5 py-3">{{ $log->subjectLabel() ?? '—' }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $log->details() ?? '—' }}</td>
                            <td class="px-5 py-3 text-xs text-gray-500" dir="ltr">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>

            @if ($logs->hasPages())
                <div class="border-t border-gray-100 px-5 py-3">{{ $logs->links() }}</div>
            @endif
        @endif
    </x-card>
</x-app-layout>
