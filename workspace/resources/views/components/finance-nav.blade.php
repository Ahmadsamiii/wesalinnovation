@props(['active'])

{{-- «العقود وأوامر الشراء» تبويب واحد لمدير المشاريع؛ التنقل بين نصفيه هنا. --}}
@if (auth()->user()->can('viewAny', \App\Models\PurchaseOrder::class) && auth()->user()->can('viewAny', \App\Models\Contract::class))
    <nav aria-label="المستندات المالية" class="mb-6 inline-flex rounded-lg border border-gray-200 bg-white p-1 text-sm">
        @foreach (['contracts' => ['العقود', 'contracts.index'], 'purchase-orders' => ['أوامر الشراء', 'purchase-orders.index'], 'invoices' => ['الفواتير', 'invoices.index']] as $key => [$label, $route])
            <a href="{{ route($route) }}" @if ($key === $active) aria-current="page" @endif
               @class(['rounded-md px-3 py-1.5 font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600',
                       'bg-brand-800 text-white' => $key === $active, 'text-gray-600 hover:bg-gray-50' => $key !== $active])>{{ $label }}</a>
        @endforeach
    </nav>
@endif
