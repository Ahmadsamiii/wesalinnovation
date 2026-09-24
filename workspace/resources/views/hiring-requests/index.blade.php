<x-app-layout>
    <x-slot:title>طلبات التوظيف</x-slot:title>

    <x-page-header title="طلبات التوظيف" :description="$isApprover ? 'احتياجات الفرق من الوظائف: ما ينتظر قرارك أولاً.' : 'اطلب وظيفة لفريقك بمبرراتها، ويقرّرها المدير التنفيذي.'">
        <x-slot:actions>
            @can('create', \App\Models\HiringRequest::class)
                <x-button :href="route('hiring-requests.create')">طلب توظيف جديد</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <nav aria-label="تصفية حسب الحالة" class="mb-6 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('hiring-requests.index') }}" @if (! ($filters['status'] ?? null)) aria-current="page" @endif
           @class(['rounded-full border px-3 py-1.5 font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600',
                   'border-brand-800 bg-brand-800 text-white' => ! ($filters['status'] ?? null), 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50' => (bool) ($filters['status'] ?? null)])>
            الكل <span class="tabular-nums">({{ $counts->sum() }})</span>
        </a>
        @foreach (\App\Enums\HiringRequestStatus::cases() as $status)
            @php($active = ($filters['status'] ?? null) === $status->value)
            <a href="{{ route('hiring-requests.index', ['status' => $status->value]) }}" @if ($active) aria-current="page" @endif
               @class(['rounded-full border px-3 py-1.5 font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600',
                       'border-brand-800 bg-brand-800 text-white' => $active, 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50' => ! $active])>
                {{ $status->label() }} <span class="tabular-nums">({{ $counts[$status->value] ?? 0 }})</span>
            </a>
        @endforeach
    </nav>

    @forelse ($hiringRequests as $hiringRequest)
        <x-card class="mb-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 flex-1 text-sm">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-badge :color="$hiringRequest->status->color()">{{ $hiringRequest->status->label() }}</x-badge>
                        <a href="{{ route('hiring-requests.show', $hiringRequest) }}" class="text-base font-semibold text-brand-800 hover:underline">{{ $hiringRequest->title }}</a>
                        <span class="text-gray-500" dir="ltr">{{ $hiringRequest->number }}</span>
                    </div>
                    <p class="mt-2 text-gray-700">
                        {{ $hiringRequest->headcount }} × {{ $hiringRequest->employment_type->label() }}
                        @if ($hiringRequest->department) — {{ $hiringRequest->department }} @endif
                        @if ($hiringRequest->project) — لمشروع {{ $hiringRequest->project->name }} @endif
                    </p>
                    <p class="mt-1 line-clamp-2 text-gray-600">{{ $hiringRequest->justification }}</p>
                    <p class="mt-2 text-xs text-gray-500">
                        @if ($isApprover) طلبه {{ $hiringRequest->requester->name }} — @endif
                        <x-date :value="$hiringRequest->created_at" relative />
                    </p>
                </div>
                <x-button variant="secondary" size="sm" :href="route('hiring-requests.show', $hiringRequest)">
                    {{ $isApprover && $hiringRequest->status === \App\Enums\HiringRequestStatus::Pending ? 'مراجعة وقرار' : 'التفاصيل' }}
                </x-button>
            </div>
        </x-card>
    @empty
        <x-empty-state :title="($filters['status'] ?? null) ? 'لا طلبات بهذه الحالة.' : 'لا طلبات توظيف بعد.'" />
    @endforelse

    @if ($hiringRequests->hasPages())
        <div class="mt-6">{{ $hiringRequests->links() }}</div>
    @endif
</x-app-layout>
