@php
    $record = $getRecord()->loadMissing('messages.user');
@endphp

<div class="flex flex-col gap-3" data-thread>
    @foreach ($record->messages as $message)
        <div @class(['flex', 'justify-end' => $message->from_staff, 'justify-start' => ! $message->from_staff])>
            <div @class([
                'flex max-w-[80%] flex-col gap-1 rounded-xl px-4 py-3 ring-1',
                'bg-primary-50 ring-primary-200 dark:bg-primary-500/10 dark:ring-primary-500/30' => $message->from_staff,
                'bg-gray-50 ring-gray-200 dark:bg-white/5 dark:ring-white/10' => ! $message->from_staff,
            ])>
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                    {{ $message->from_staff ? 'Suporte' : ($message->user?->name ?? 'Dono') }} · {{ $message->created_at->format('d/m/Y H:i') }}
                </span>
                <p class="whitespace-pre-line text-sm text-gray-950 dark:text-white">{{ $message->body }}</p>
            </div>
        </div>
    @endforeach
</div>
