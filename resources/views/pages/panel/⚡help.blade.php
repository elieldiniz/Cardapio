<?php

use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * Central de ajuda (RF-24): vídeos curtos com busca por termo e o WhatsApp do suporte.
 * A lista vem de config/help_videos.php.
 */
new #[Layout('layouts::panel')] #[Title('Ajuda')] class extends Component
{
    #[Url(as: 'busca', except: '')]
    public string $search = '';

    /** @return array<int, array{title: string, description: string, tags: array<int, string>, url: ?string}> */
    #[Computed]
    public function videos(): array
    {
        $term = Str::ascii(mb_strtolower(trim($this->search)));

        return collect(config('help_videos.videos'))
            ->filter(fn (array $video) => $term === '' || str_contains(
                Str::ascii(mb_strtolower($video['title'].' '.$video['description'].' '.implode(' ', $video['tags']))),
                $term,
            ))
            ->values()
            ->all();
    }

    #[Computed]
    public function supportUrl(): ?string
    {
        $number = preg_replace('/\D/', '', (string) config('help_videos.support_whatsapp'));

        return $number === '' ? null : 'https://api.whatsapp.com/send?phone='.$number.'&text='.rawurlencode('Olá! Preciso de ajuda no painel do Degusta.');
    }
};
?>

<div class="flex flex-col gap-[18px]">
    <x-ui.page-header title="Ajuda" subtitle="Vídeos curtos para cada parte do painel.">
        @if ($this->supportUrl)
            <x-ui.button :href="$this->supportUrl" target="_blank" rel="noopener" data-support-whatsapp>Falar com o suporte</x-ui.button>
        @else
            <x-ui.button :href="route('panel.support')" wire:navigate variant="secondary">Abrir um chamado</x-ui.button>
        @endif
    </x-ui.page-header>

    <x-ui.input type="search" wire:model.live.debounce.250ms="search" placeholder="Buscar: caixa, pizza, cupom, entrega…" aria-label="Buscar na ajuda" data-help-search />

    <div class="grid gap-3.5 sm:grid-cols-2" data-help-videos>
        @forelse ($this->videos as $video)
            <x-ui.card data-help-video="{{ $video['title'] }}">
                <h2 class="text-[14.5px] font-bold text-ink">{{ $video['title'] }}</h2>
                <p class="mt-1 text-[13px] text-ink/60">{{ $video['description'] }}</p>
                <div class="mt-3">
                    @if ($video['url'] && str_ends_with($video['url'], '.mp4'))
                        <video controls preload="metadata" playsinline class="w-full rounded-lg bg-black" src="{{ $video['url'] }}" data-help-player></video>
                    @elseif ($video['url'])
                        <x-ui.button size="sm" :href="$video['url']" target="_blank" rel="noopener">▶ Assistir</x-ui.button>
                    @else
                        <span class="text-[12px] font-semibold text-ink/40">Vídeo em breve</span>
                    @endif
                </div>
            </x-ui.card>
        @empty
            <div class="sm:col-span-2"><x-ui.empty-state icon="help" title="Nada encontrado" description="Tente outra palavra ou fale com o suporte." /></div>
        @endforelse
    </div>
</div>
