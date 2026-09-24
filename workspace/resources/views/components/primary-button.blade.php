<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-5 py-2.5 bg-brand-gradient border border-transparent rounded-xl font-bold text-sm text-white shadow-sm shadow-brand-tertiary/25 hover:brightness-110 active:brightness-95 focus:outline-none focus:ring-2 focus:ring-brand-sky focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
