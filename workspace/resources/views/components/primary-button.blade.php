<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 px-4 py-2 bg-brand-800 border border-transparent rounded-lg font-semibold text-sm text-white shadow-sm hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2 disabled:opacity-50 transition']) }}>
    {{ $slot }}
</button>
