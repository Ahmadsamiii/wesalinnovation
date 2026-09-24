@if ($tabs)
    <div class="border-t border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <ul class="flex gap-1 overflow-x-auto" role="list">
                @foreach ($tabs as $tab)
                    <li class="shrink-0">
                        <a href="{{ $tab['url'] }}"
                           @if ($tab['active']) aria-current="page" @endif
                           @class([
                               'inline-flex items-center whitespace-nowrap border-b-2 px-3 py-3 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-600',
                               'border-brand-800 text-brand-800' => $tab['active'],
                               'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-800' => ! $tab['active'],
                           ])>
                            {{ $tab['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
