<?php

use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Unread in-panel notices for the dono (e.g. failed payment, downgrade — US-5.5).
 */
new class extends Component
{
    #[Computed]
    public function notices()
    {
        return auth()->user()->unreadNotifications()->latest()->limit(5)->get();
    }

    public function dismiss(string $id): void
    {
        auth()->user()->unreadNotifications()->whereKey($id)->update(['read_at' => now()]);
        unset($this->notices);
    }
};
?>

<div class="flex flex-col gap-2.5 empty:hidden" data-panel-notices>
    @foreach ($this->notices as $notice)
        <div wire:key="notice-{{ $notice->id }}" class="flex items-start gap-3 rounded-xl border p-4 {{ ($notice->data['level'] ?? '') === 'error' ? 'border-danger/25 bg-danger/5' : 'border-accent/25 bg-accent/5' }}" role="alert">
            <x-ui.icon name="alert" class="mt-0.5 size-5 {{ ($notice->data['level'] ?? '') === 'error' ? 'text-danger' : 'text-accent' }}" />
            <div class="flex flex-1 flex-col gap-1">
                <p class="text-[14px] font-bold text-ink">{{ $notice->data['title'] ?? 'Aviso' }}</p>
                <p class="text-[13px] leading-relaxed text-ink/70">{{ $notice->data['message'] ?? '' }}</p>
                @if ($url = $notice->data['action_url'] ?? null)
                    <a href="{{ $url }}" wire:navigate class="mt-1 w-fit text-[13px] font-bold text-accent hover:underline">Ver detalhes</a>
                @elseif (($route = $notice->data['action_route'] ?? null) && Route::has($route) && ! request()->routeIs($route))
                    <a href="{{ route($route) }}" wire:navigate class="mt-1 w-fit text-[13px] font-bold text-accent hover:underline">Ver detalhes</a>
                @endif
            </div>
            <button type="button" wire:click="dismiss('{{ $notice->id }}')" class="rounded-md px-2 py-1 text-[12px] font-bold text-ink/50 hover:bg-ink/5">Entendi</button>
        </div>
    @endforeach
</div>
