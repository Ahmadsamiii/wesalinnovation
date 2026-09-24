@if ($decisions->isEmpty())
    <x-empty-state title="لا قرارات مسجّلة بعد." />
@else
    <x-card :padding="false">
        <x-table caption="القرارات">
            <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                <tr>
                    <th scope="col" class="px-5 py-3 text-start">التاريخ</th>
                    <th scope="col" class="px-5 py-3 text-start">المشروع</th>
                    <th scope="col" class="px-5 py-3 text-start">القرار</th>
                    <th scope="col" class="px-5 py-3 text-start">التعليل</th>
                    <th scope="col" class="px-5 py-3 text-start">متخذه</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @foreach ($decisions as $decision)
                    <tr>
                        <td class="whitespace-nowrap px-5 py-3 text-gray-600"><x-date :value="$decision->decided_at" time /></td>
                        <td class="px-5 py-3"><a href="{{ route('projects.show', $decision->project) }}" class="font-medium text-brand-800 hover:underline">{{ $decision->project->name }}</a></td>
                        <td class="px-5 py-3"><x-badge :color="$decision->type->color()">{{ $decision->type->label() }}</x-badge></td>
                        <td class="px-5 py-3 text-gray-600">{{ $decision->note ?? '-' }}</td>
                        <td class="px-5 py-3">{{ $decision->decider->name }}</td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
    </x-card>
@endif
