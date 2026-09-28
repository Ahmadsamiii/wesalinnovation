<x-app-layout>
    <x-slot:title>العملاء</x-slot:title>

    <x-page-header title="العملاء" description="حسابات العملاء والمشاريع المرتبطة بكل حساب. للقراءة فقط." />

    <x-card :padding="false">
        <form method="GET" action="{{ route('crm.clients') }}" class="grid gap-4 border-b border-gray-100 p-5 sm:grid-cols-3" role="search">
            <x-form.input name="q" label="بحث بالاسم أو البريد" :value="$filters['q'] ?? null" type="search" />
            <div class="flex items-end gap-2">
                <x-button type="submit">تصفية</x-button>
                @if (array_filter($filters))
                    <x-button variant="ghost" :href="route('crm.clients')">مسح</x-button>
                @endif
            </div>
        </form>

        @if ($clients->isEmpty())
            <div class="p-5">
                <x-empty-state title="لا عملاء مطابقين." />
            </div>
        @else
            <x-table caption="العملاء">
                <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-start">الاسم</th>
                        <th scope="col" class="px-5 py-3 text-start">البريد الإلكتروني</th>
                        <th scope="col" class="px-5 py-3 text-start">الجوال</th>
                        <th scope="col" class="px-5 py-3 text-start">الحالة</th>
                        <th scope="col" class="px-5 py-3 text-start">المشاريع</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($clients as $client)
                        <tr>
                            <td class="px-5 py-3 font-medium text-ink">{{ $client->name }}</td>
                            <td class="px-5 py-3 text-gray-600" dir="ltr">{{ $client->email }}</td>
                            <td class="px-5 py-3 text-gray-600" dir="ltr">{{ $client->phone ?? '-' }}</td>
                            <td class="px-5 py-3"><x-badge :color="$client->status()->color()">{{ $client->status()->label() }}</x-badge></td>
                            <td class="px-5 py-3">
                                @forelse ($client->clientProjects as $project)
                                    <div class="flex flex-wrap items-center gap-2 py-0.5">
                                        @can('view', $project)
                                            <a href="{{ route('projects.show', $project) }}" class="text-brand-800 hover:underline">{{ $project->name }}</a>
                                        @else
                                            <span>{{ $project->name }}</span>
                                        @endcan
                                        <x-badge :color="$project->status->color()">{{ $project->status->label() }}</x-badge>
                                    </div>
                                @empty
                                    <span class="text-gray-500">لا مشاريع</span>
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>

            @if ($clients->hasPages())
                <div class="border-t border-gray-100 px-5 py-3">{{ $clients->links() }}</div>
            @endif
        @endif
    </x-card>
</x-app-layout>
