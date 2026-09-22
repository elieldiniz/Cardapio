<?php

use App\Support\QrCode;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::panel')] #[Title('QR Code')] class extends Component
{
    /** Printed on the table display only — the QR code itself is the same for every table. */
    public string $table = '';

    #[Computed]
    public function restaurant()
    {
        return auth()->user()->restaurant;
    }

    #[Computed]
    public function feedUrl(): string
    {
        return $this->restaurant->feedUrl();
    }

    #[Computed]
    public function svg(): string
    {
        return QrCode::svg($this->feedUrl);
    }
};
?>

<div class="flex flex-col gap-[18px]">
    <x-ui.page-header title="QR Code das mesas" />

    <div class="flex flex-wrap items-start gap-[18px]">
        <x-ui.card class="flex flex-col items-center gap-3.5">
            <div class="flex size-[190px] items-center justify-center rounded-xl border border-ink/10 bg-white p-2 [&_svg]:size-full" data-qr x-ref="qr" x-data>
                {!! $this->svg !!}
            </div>
            <span class="text-[15px] font-bold text-ink">{{ $table !== '' ? 'Mesa '.$table.' · ' : '' }}{{ $this->restaurant->name }}</span>
            <a href="{{ $this->feedUrl }}" target="_blank" rel="noopener" class="max-w-[240px] truncate text-[12px] font-semibold text-accent hover:underline" data-feed-url>{{ $this->feedUrl }}</a>

            <x-ui.field label="Mesa (opcional, só aparece no display)" for="table" class="w-full">
                <x-ui.input id="table" wire:model.live.debounce.300ms="table" maxlength="10" placeholder="Ex.: 12" />
            </x-ui.field>

            <div class="flex gap-2" x-data="{
                download() {
                    const svg = document.querySelector('[data-qr] svg');
                    const size = 1024;
                    const data = new XMLSerializer().serializeToString(svg);
                    const image = new Image();
                    image.onload = () => {
                        const canvas = document.createElement('canvas');
                        canvas.width = canvas.height = size;
                        const context = canvas.getContext('2d');
                        context.fillStyle = '#fff';
                        context.fillRect(0, 0, size, size);
                        context.drawImage(image, 0, 0, size, size);
                        const link = document.createElement('a');
                        link.download = 'qr-code-cardapio.png';
                        link.href = canvas.toDataURL('image/png');
                        link.click();
                    };
                    image.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(data);
                },
            }">
                <x-ui.button size="sm" x-on:click="download()">Baixar PNG</x-ui.button>
                <x-ui.button size="sm" variant="secondary" :href="route('panel.qr-code.print', ['mesa' => $table ?: null])" target="_blank">Imprimir display</x-ui.button>
            </div>
        </x-ui.card>

        <x-ui.card title="Como funciona" class="min-w-[260px] flex-1">
            <p class="text-[13.5px] leading-relaxed text-ink/60">O cliente escaneia e abre o cardápio em vídeo direto no navegador, sem instalar nada.</p>
            <p class="mt-2 text-[13.5px] leading-relaxed text-ink/60">O QR Code é o mesmo para todas as mesas e <strong class="text-ink">nunca muda</strong>: você pode editar pratos, categorias e aparência à vontade sem reimprimir.</p>
        </x-ui.card>
    </div>
</div>
