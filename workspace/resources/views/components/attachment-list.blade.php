@props(['attachments', 'showSource' => false])

@if ($attachments->isEmpty())
    <p {{ $attributes->merge(['class' => 'text-sm text-gray-500']) }}>لا ملفات بعد.</p>
@else
    <ul {{ $attributes->merge(['class' => 'divide-y divide-gray-100 text-sm']) }} role="list">
        @foreach ($attachments as $attachment)
            <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                <div class="min-w-0">
                    <a href="{{ route('attachments.show', $attachment) }}" class="font-medium text-brand-800 hover:underline" dir="auto">{{ $attachment->original_name }}</a>
                    <p class="text-xs text-gray-500">
                        {{ $attachment->humanSize() }} — {{ $attachment->uploader->name }} — <x-date :value="$attachment->created_at" />
                        @if ($showSource && $attachment->attachable instanceof \App\Models\Task)
                            — مهمة: <a href="{{ route('tasks.show', $attachment->attachable) }}" class="hover:underline">{{ $attachment->attachable->title }}</a>
                        @endif
                    </p>
                </div>
                @can('delete', $attachment)
                    <form method="POST" action="{{ route('attachments.destroy', $attachment) }}" onsubmit="return confirm(@js('حذف «'.$attachment->original_name.'»؟'))">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="ghost" size="sm" class="text-red-700">حذف<span class="sr-only"> {{ $attachment->original_name }}</span></x-button>
                    </form>
                @endcan
            </li>
        @endforeach
    </ul>
@endif
