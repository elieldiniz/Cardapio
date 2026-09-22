{{--
    Global toast stack. Trigger from Livewire with
    $this->dispatch('toast', message: 'Salvo!', type: 'success'), or from a
    redirect with session()->flash('toast', ['message' => '...', 'type' => 'success']).
--}}
@php
    $flashed = session('toast');
@endphp

<div
    x-data="{
        toasts: [],
        push(detail) {
            const toast = { id: Date.now() + Math.random(), message: detail.message, type: detail.type ?? 'success' };
            this.toasts.push(toast);
            setTimeout(() => this.toasts = this.toasts.filter(t => t.id !== toast.id), 4000);
        },
    }"
    x-init="@if ($flashed) push(@js($flashed)) @endif"
    x-on:toast.window="push(Array.isArray($event.detail) ? $event.detail[0] : $event.detail)"
    class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex flex-col items-center gap-2 px-4 sm:bottom-auto sm:top-5 sm:right-5 sm:left-auto sm:items-end"
    aria-live="polite"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-transition
            class="pointer-events-auto flex w-full max-w-sm items-center gap-2.5 rounded-xl px-4 py-3 text-[13.5px] font-semibold text-white shadow-lg"
            :class="toast.type === 'error' ? 'bg-danger' : 'bg-night'"
            role="status"
        >
            <span class="flex size-5 items-center justify-center rounded-full" :class="toast.type === 'error' ? 'bg-white/20' : 'bg-success'">
                <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </span>
            <span x-text="toast.message"></span>
        </div>
    </template>
</div>
