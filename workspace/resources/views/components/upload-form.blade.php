@props(['action'])

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" {{ $attributes->merge(['class' => 'space-y-3']) }}>
    @csrf
    <x-form.input name="file" label="رفع ملف" type="file" required
                  :accept="collect(\App\Models\Attachment::ALLOWED_EXTENSIONS)->map(fn ($e) => '.'.$e)->implode(',')"
                  :hint="'حتى '.(\App\Models\Attachment::MAX_KILOBYTES / 1024).' م.ب: '.implode('، ', \App\Models\Attachment::ALLOWED_EXTENSIONS)" />
    <x-button type="submit" size="sm">رفع</x-button>
</form>
