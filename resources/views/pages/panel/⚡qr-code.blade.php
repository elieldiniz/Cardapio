<?php

use App\Actions\Panel\SaveQrSettings;
use App\Support\QrStyle;
use App\Support\RestaurantQr;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::panel')] #[Title('QR Code')] class extends Component
{
    /** Printed on the table display only — the QR code itself is the same for every table. */
    public string $table = '';

    // The dono's choices. Each change is saved right away when it is valid.
    public string $color = '';

    public string $style = 'square';

    public bool $logo = false;

    public string $template = 'classico';

    /** Empty = the restaurant color. */
    public string $displayColor = '';

    /** Card background. Empty = the model's own. */
    public string $displayBg = '';

    /** Color of the letters. Empty = automatic. */
    public string $displayText = '';

    /** Font of the name and headline. Empty = the card's own. */
    public string $displayFont = '';

    public string $headline = '';

    public string $subtitle = '';

    public bool $showLogo = true;

    public bool $showName = true;


    public function mount(): void
    {
        $settings = RestaurantQr::settings($this->restaurant);

        $this->color = $settings['color'];
        $this->style = $settings['style'];
        $this->logo = $settings['logo'];
        $this->template = $settings['template'];
        $this->displayColor = (string) $settings['display_color'];
        $this->displayBg = (string) $settings['display_bg'];
        $this->displayText = (string) $settings['display_text'];
        $this->displayFont = (string) $settings['display_font'];
        $this->headline = $settings['headline'];
        $this->subtitle = $settings['subtitle'];
        $this->showLogo = $settings['show_logo'];
        $this->showName = $settings['show_name'];
    }

    /**
     * This page is wide enough for controls, preview and code side by side.
     */
    public function render()
    {
        return $this->view()->layoutData(['wide' => true]);
    }

    #[Computed]
    public function restaurant()
    {
        return auth()->user()->restaurant->load('plan');
    }

    #[Computed]
    public function feedUrl(): string
    {
        return $this->restaurant->feedUrl();
    }

    #[Computed]
    public function canCustomize(): bool
    {
        return RestaurantQr::canCustomize($this->restaurant);
    }

    /**
     * What is on screen now (saved or not yet), resolved for the plan.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function settings(): array
    {
        return RestaurantQr::settings($this->restaurant, $this->input());
    }

    #[Computed]
    public function svg(): string
    {
        return RestaurantQr::svg($this->restaurant, $this->input());
    }

    public function updated(string $property): void
    {
        if ($property === 'table') {
            return;
        }

        // The letters follow the card: if its color changed and the chosen letter color no longer reads, go back to automatic.
        if (in_array($property, ['displayBg', 'displayColor', 'template'], true) && $this->displayText !== '') {
            $background = RestaurantQr::background($this->restaurant, RestaurantQr::settings($this->restaurant, $this->input()));

            if (QrStyle::isHex($this->displayText) && QrStyle::contrast($this->displayText, $background) < QrStyle::MIN_CONTRAST) {
                $this->displayText = '';
            }
        }

        // An invalid choice (e.g. a color too light to scan) shows its error and is not saved.
        app(SaveQrSettings::class)->handle($this->restaurant, $this->input());

        unset($this->restaurant);
    }

    /**
     * @return array<string, mixed>
     */
    private function input(): array
    {
        return [
            'color' => $this->color,
            'style' => $this->style,
            'logo' => $this->logo,
            'template' => $this->template,
            'display_color' => $this->displayColor === '' ? null : $this->displayColor,
            'display_bg' => $this->displayBg === '' ? null : $this->displayBg,
            'display_text' => $this->displayText === '' ? null : $this->displayText,
            'display_font' => $this->displayFont === '' ? null : $this->displayFont,
            'headline' => $this->headline,
            'subtitle' => $this->subtitle,
            'show_logo' => $this->showLogo,
            'show_name' => $this->showName,
        ];
    }
};
?>

@php
    $restaurantColor = QrStyle::readable($this->restaurant->accentColor());
    $swatches = [
        [QrStyle::DEFAULT_COLOR, 'Preto'],
        [$restaurantColor, 'Cor do restaurante'],
        ['#0B5D3B', 'Verde'],
        ['#1D4ED8', 'Azul'],
        ['#7A1F2B', 'Vinho'],
        ['#5B21B6', 'Roxo'],
    ];
    $displaySwatches = [
        [$this->restaurant->accentColor(), 'Cor do restaurante', true],
        ['#161412', 'Grafite', false],
        ['#B91C1C', 'Vermelho', false],
        ['#C2410C', 'Laranja', false],
        ['#CA8A04', 'Dourado', false],
        ['#15803D', 'Verde', false],
        ['#0F766E', 'Verde-água', false],
        ['#1D4ED8', 'Azul', false],
        ['#6D28D9', 'Roxo', false],
        ['#BE185D', 'Rosa', false],
    ];
    $bgSwatches = [
        ['#FFFFFF', 'Branco'],
        ['#FBF3E4', 'Creme'],
        ['#F3F4F6', 'Cinza claro'],
        ['#161412', 'Grafite'],
        ['#0B2A4A', 'Azul-marinho'],
        ['#14452F', 'Verde-escuro'],
        ['#5B1A24', 'Vinho'],
        ['#3B1F6E', 'Roxo-escuro'],
    ];
    $textSwatches = [
        ['#FFFFFF', 'Branco'],
        ['#1A1815', 'Preto'],
        ['#B91C1C', 'Vermelho'],
        ['#CA8A04', 'Dourado'],
        ['#15803D', 'Verde'],
        ['#1D4ED8', 'Azul'],
        ['#6D28D9', 'Roxo'],
    ];
    $fonts = (array) config('feed.fonts');
    $locked = ! $this->canCustomize;
@endphp

<div class="flex flex-col gap-[18px]">
    <x-ui.page-header title="QR Code das mesas" subtitle="Deixe o QR Code e o display com a cara do seu restaurante." />

    @include('panel.partials.table-display-css')
    @foreach ($fonts as $family => $slug)
        @if ($slug)
            <link rel="stylesheet" href="https://fonts.bunny.net/css?family={{ $slug }}&display=swap">
        @endif
    @endforeach

    {{-- Side by side and using the whole width: the code and downloads | the controls | the live preview next to them.
         On narrower screens the preview and the code share the right column; on phones the preview comes first. --}}
    <div class="grid items-stretch gap-[18px] lg:grid-cols-[minmax(0,1fr)_320px] xl:grid-cols-[270px_minmax(0,1.25fr)_minmax(320px,1fr)]" data-qr-layout>
        <div class="order-2 flex flex-col lg:order-none lg:col-start-1 lg:row-span-2 lg:row-start-1 xl:col-start-2 xl:row-span-1">
            <x-ui.card title="Personalizar" class="flex-1">
                <div class="flex flex-col gap-5">
                    <div>
                        <p class="mb-2 text-[12px] font-semibold text-ink/60">Cor do QR Code</p>
                        <div class="flex flex-wrap items-center gap-2.5" data-qr-colors>
                            @foreach ($swatches as [$hex, $label])
                                <button
                                    type="button"
                                    wire:key="swatch-{{ $loop->index }}"
                                    wire:click="$set('color', '{{ $hex }}')"
                                    title="{{ $label }}"
                                    aria-label="{{ $label }}"
                                    aria-pressed="{{ strcasecmp($color, $hex) === 0 ? 'true' : 'false' }}"
                                    class="size-9 rounded-full ring-offset-2 transition {{ strcasecmp($color, $hex) === 0 ? 'ring-2 ring-accent' : 'ring-1 ring-ink/15 hover:ring-ink/40' }}"
                                    style="background: {{ $hex }}"
                                ></button>
                            @endforeach
                            <label class="flex items-center gap-2 text-[12.5px] font-semibold text-ink/60">
                                <input type="color" wire:model.live.debounce.400ms="color" class="size-9 cursor-pointer rounded-full border-0 bg-transparent p-0" aria-label="Outra cor">
                                Outra cor
                            </label>
                        </div>
                        <p class="mt-2 text-[12px] leading-relaxed text-ink/50">Use uma cor escura: o QR Code precisa de contraste com o fundo branco para ser lido.</p>
                        @error('color') <p class="mt-1.5 text-[12.5px] font-medium text-danger" role="alert">{{ $message }}</p> @enderror
                    </div>

                    @if ($locked)
                        <div class="flex flex-col gap-2 rounded-xl bg-accent/10 p-4 text-[13.5px] leading-relaxed text-ink/75" data-qr-locked>
                            <span><strong class="text-ink">Quer ir além da cor?</strong> Estilo do QR, logo no centro, modelos de display e textos livres estão disponíveis a partir do plano Básico.</span>
                            <a href="{{ route('panel.subscription') }}" wire:navigate class="w-fit font-bold text-accent hover:underline">Ver planos</a>
                        </div>
                    @endif

                    <fieldset @disabled($locked) class="flex flex-col gap-5 {{ $locked ? 'pointer-events-none opacity-45' : '' }}" data-qr-options>
                        <div>
                            <p class="mb-2 text-[12px] font-semibold text-ink/60">Estilo do QR Code</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach (QrStyle::STYLES as $value => $label)
                                    <label class="cursor-pointer">
                                        <input type="radio" wire:model.live="style" value="{{ $value }}" class="peer sr-only">
                                        <span class="inline-flex rounded-full border border-ink/15 px-4 py-2 text-[13px] font-bold text-ink/70 peer-checked:border-accent peer-checked:bg-accent peer-checked:text-white">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <label class="flex items-start gap-3 text-[13.5px] text-ink/75">
                            <input type="checkbox" wire:model.live="logo" class="mt-1 size-4 accent-[var(--accent)]" @disabled($locked || ! $this->restaurant->logo_path)>
                            <span>
                                <strong class="text-ink">Logo no centro do QR Code</strong>
                                <span class="block text-[12px] text-ink/50">{{ $this->restaurant->logo_path ? 'Usa a logo do seu restaurante.' : 'Envie a logo em Aparência para usar.' }}</span>
                            </span>
                        </label>

                        <div>
                            <p class="mb-2 text-[12px] font-semibold text-ink/60">Modelo do display</p>
                            <div class="grid grid-cols-3 gap-3" data-templates>
                                @foreach (RestaurantQr::TEMPLATES as $value => $label)
                                    <label class="cursor-pointer" wire:key="template-{{ $value }}">
                                        <input type="radio" wire:model.live="template" value="{{ $value }}" class="peer sr-only">
                                        <span class="flex flex-col items-center gap-2 rounded-xl border border-ink/15 p-2.5 text-center text-[12.5px] font-bold text-ink/70 peer-checked:border-accent peer-checked:bg-accent/5 peer-checked:text-accent">
                                            <span class="relative block h-14 w-10 overflow-hidden rounded-md ring-1 ring-ink/15 {{ $value === 'escuro' ? 'bg-night' : ($value === 'moldura' ? 'bg-accent' : 'bg-white') }}">
                                                @if ($value !== 'moldura')<span class="absolute inset-x-0 top-0 h-1.5 bg-accent"></span>@endif
                                                <span class="absolute inset-x-1.5 top-4 h-5 rounded-sm {{ $value === 'classico' ? 'bg-ink/80' : 'bg-white' }}"></span>
                                            </span>
                                            {{ $label }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div data-display-colors>
                            <p class="mb-2 text-[12px] font-semibold text-ink/60">Cor do display</p>
                            <div class="flex flex-wrap items-center gap-2.5">
                                @foreach ($displaySwatches as [$hex, $label, $isRestaurant])
                                    @php $selected = $isRestaurant ? $displayColor === '' : strcasecmp($displayColor, $hex) === 0; @endphp
                                    <button
                                        type="button"
                                        wire:key="display-swatch-{{ $loop->index }}"
                                        wire:click="$set('displayColor', '{{ $isRestaurant ? '' : $hex }}')"
                                        title="{{ $label }}"
                                        aria-label="{{ $label }}"
                                        aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                        class="size-9 rounded-full ring-offset-2 transition {{ $selected ? 'ring-2 ring-accent' : 'ring-1 ring-ink/15 hover:ring-ink/40' }}"
                                        style="background: {{ $hex }}"
                                    ></button>
                                @endforeach
                                <label class="flex items-center gap-2 text-[12.5px] font-semibold text-ink/60">
                                    <input type="color" wire:model.live.debounce.400ms="displayColor" class="size-9 cursor-pointer rounded-full border-0 bg-transparent p-0" aria-label="Outra cor do display">
                                    Outra cor
                                </label>
                            </div>
                            <p class="mt-2 text-[12px] leading-relaxed text-ink/50">É a cor principal do cartão (faixa, fundo ou detalhe, conforme o modelo). O texto sobre ela se ajusta sozinho para continuar legível.</p>
                            @error('display_color') <p class="mt-1.5 text-[12.5px] font-medium text-danger" role="alert">{{ $message }}</p> @enderror
                        </div>

                        <div data-display-bg>
                            <p class="mb-2 text-[12px] font-semibold text-ink/60">Fundo do cartão</p>
                            <div class="flex flex-wrap items-center gap-2.5">
                                <button
                                    type="button"
                                    wire:click="$set('displayBg', '')"
                                    title="Padrão do modelo"
                                    aria-pressed="{{ $displayBg === '' ? 'true' : 'false' }}"
                                    class="flex h-9 items-center rounded-full px-3.5 text-[12.5px] font-bold transition {{ $displayBg === '' ? 'bg-accent text-white' : 'border border-ink/15 text-ink/70 hover:border-ink/40' }}"
                                >Padrão do modelo</button>
                                @foreach ($bgSwatches as [$hex, $label])
                                    @php $selected = strcasecmp($displayBg, $hex) === 0; @endphp
                                    <button
                                        type="button"
                                        wire:key="bg-swatch-{{ $loop->index }}"
                                        wire:click="$set('displayBg', '{{ $hex }}')"
                                        title="{{ $label }}"
                                        aria-label="{{ $label }}"
                                        aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                        class="size-9 rounded-full ring-offset-2 transition {{ $selected ? 'ring-2 ring-accent' : 'ring-1 ring-ink/15 hover:ring-ink/40' }}"
                                        style="background: {{ $hex }}"
                                    ></button>
                                @endforeach
                                <label class="flex items-center gap-2 text-[12.5px] font-semibold text-ink/60">
                                    <input type="color" wire:model.live.debounce.400ms="displayBg" class="size-9 cursor-pointer rounded-full border-0 bg-transparent p-0" aria-label="Outra cor de fundo">
                                    Outra cor
                                </label>
                            </div>
                            <p class="mt-2 text-[12px] leading-relaxed text-ink/50">A cor de dentro do cartão. Os textos, a etiqueta da mesa e a marca se ajustam sozinhos para continuar legíveis; o QR Code fica sempre sobre branco, para ler bem.</p>
                            @error('display_bg') <p class="mt-1.5 text-[12.5px] font-medium text-danger" role="alert">{{ $message }}</p> @enderror
                        </div>

                        <div data-display-letters>
                            <p class="mb-2 text-[12px] font-semibold text-ink/60">Cor das letras</p>
                            <div class="flex flex-wrap items-center gap-2.5">
                                <button
                                    type="button"
                                    wire:click="$set('displayText', '')"
                                    aria-pressed="{{ $displayText === '' ? 'true' : 'false' }}"
                                    class="flex h-9 items-center rounded-full px-3.5 text-[12.5px] font-bold transition {{ $displayText === '' ? 'bg-accent text-white' : 'border border-ink/15 text-ink/70 hover:border-ink/40' }}"
                                >Automática</button>
                                @foreach ($textSwatches as [$hex, $label])
                                    @php $selected = strcasecmp($displayText, $hex) === 0; @endphp
                                    <button
                                        type="button"
                                        wire:key="text-swatch-{{ $loop->index }}"
                                        wire:click="$set('displayText', '{{ $hex }}')"
                                        title="{{ $label }}"
                                        aria-label="{{ $label }}"
                                        aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                        class="size-9 rounded-full ring-offset-2 transition {{ $selected ? 'ring-2 ring-accent' : 'ring-1 ring-ink/15 hover:ring-ink/40' }}"
                                        style="background: {{ $hex }}"
                                    ></button>
                                @endforeach
                                <label class="flex items-center gap-2 text-[12.5px] font-semibold text-ink/60">
                                    <input type="color" wire:model.live.debounce.400ms="displayText" class="size-9 cursor-pointer rounded-full border-0 bg-transparent p-0" aria-label="Outra cor de letra">
                                    Outra cor
                                </label>
                            </div>
                            <p class="mt-2 text-[12px] leading-relaxed text-ink/50">Em "Automática" as letras ficam pretas ou brancas, conforme o fundo. Uma cor que não se leia bem sobre o fundo é recusada.</p>
                            @error('display_text') <p class="mt-1.5 text-[12.5px] font-medium text-danger" role="alert">{{ $message }}</p> @enderror
                        </div>

                        <div data-display-fonts>
                            <p class="mb-2 text-[12px] font-semibold text-ink/60">Estilo das letras (nome e chamada)</p>
                            <div class="flex flex-wrap gap-2">
                                <label class="cursor-pointer">
                                    <input type="radio" wire:model.live="displayFont" value="" class="peer sr-only">
                                    <span class="inline-flex rounded-full border border-ink/15 px-4 py-2 text-[13px] font-bold text-ink/70 peer-checked:border-accent peer-checked:bg-accent peer-checked:text-white">Padrão do modelo</span>
                                </label>
                                @foreach (array_keys($fonts) as $font)
                                    <label class="cursor-pointer" wire:key="font-{{ $loop->index }}">
                                        <input type="radio" wire:model.live="displayFont" value="{{ $font }}" class="peer sr-only">
                                        <span class="inline-flex rounded-full border border-ink/15 px-4 py-2 text-[15px] text-ink/70 peer-checked:border-accent peer-checked:bg-accent peer-checked:text-white" style="font-family: '{{ $font }}', serif">{{ $font }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="grid gap-3.5 sm:grid-cols-2">
                            <x-ui.field label="Chamada principal" for="headline" error="headline" class="sm:col-span-2">
                                <x-ui.input id="headline" wire:model.live.debounce.600ms="headline" maxlength="70" placeholder="Aponte a câmera e veja o cardápio em vídeo" />
                            </x-ui.field>
                            <x-ui.field label="Texto de apoio (opcional)" for="subtitle" error="subtitle" class="sm:col-span-2">
                                <x-ui.input id="subtitle" wire:model.live.debounce.600ms="subtitle" maxlength="90" placeholder="Sem app, sem cadastro. Peça ao garçom." />
                            </x-ui.field>
                            <div class="flex flex-col gap-2.5 text-[13.5px] text-ink/75 sm:col-span-2 sm:flex-row sm:gap-6">
                                <label class="flex items-center gap-2.5"><input type="checkbox" wire:model.live="showLogo" class="size-4 accent-[var(--accent)]" @disabled($locked || ! $this->restaurant->logo_path)> Mostrar a logo</label>
                                <label class="flex items-center gap-2.5"><input type="checkbox" wire:model.live="showName" class="size-4 accent-[var(--accent)]" @disabled($locked)> Mostrar o nome</label>
                            </div>
                        </div>
                    </fieldset>

                    <p class="text-[12px] text-ink/45">As alterações são salvas sozinhas. Sem escolher uma cor do display, vale a cor do restaurante, em Aparência.</p>
                </div>
            </x-ui.card>
        </div>

        <div class="order-1 flex flex-col lg:order-none lg:col-start-2 lg:row-start-1 xl:col-start-3" data-qr-preview-col>
            <x-ui.card title="Prévia do display" class="flex flex-1 flex-col">
                <div class="overflow-hidden rounded-xl bg-sand p-4">
                    <div
                        x-data="{ s: 0.6, fit() { this.s = Math.min(1.15, this.$el.clientWidth / 396.9) } }"
                        x-init="fit(); new ResizeObserver(() => fit()).observe($el)"
                        class="mx-auto w-full max-w-[460px]"
                        data-display-preview
                    >
                        <div :style="{ height: (559.4 * s) + 'px' }" style="height: 336px">
                            <div :style="{ transform: 'scale(' + s + ')' }" style="transform: scale(.6); transform-origin: top left; width: 105mm; height: 148mm">
                                @include('panel.partials.table-display', ['restaurant' => $this->restaurant, 'svg' => $this->svg, 'settings' => $this->settings, 'table' => $table])
                            </div>
                        </div>
                    </div>
                </div>
                <p class="mt-3 text-center text-[12px] text-ink/50">Cartão A6, como vai sair na impressão.</p>
            </x-ui.card>
        </div>

        <div class="order-3 flex flex-col lg:order-none lg:col-start-2 lg:row-start-2 xl:col-start-1 xl:row-start-1" data-qr-code-col>
            <x-ui.card class="flex flex-1 flex-col items-center gap-3.5">
                <div id="qr-main" class="flex size-[150px] items-center justify-center rounded-xl border border-ink/10 bg-white p-2 [&_svg]:size-full" data-qr>
                    {!! $this->svg !!}
                </div>
                <span class="text-[15px] font-bold text-ink">{{ $table !== '' ? 'Mesa '.$table.' · ' : '' }}{{ $this->restaurant->name }}</span>
                <a href="{{ $this->feedUrl }}" target="_blank" rel="noopener" class="max-w-[250px] truncate text-[12px] font-semibold text-accent hover:underline" data-feed-url>{{ $this->feedUrl }}</a>

                <x-ui.field label="Mesa (opcional, só aparece no display)" for="table" class="w-full">
                    <x-ui.input id="table" wire:model.live.debounce.300ms="table" maxlength="10" placeholder="Ex.: 12" />
                </x-ui.field>

                <div class="flex flex-wrap justify-center gap-2" x-data="{
                    download() {
                        const svg = document.querySelector('#qr-main svg');
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
                    <x-ui.button size="sm" variant="secondary" :href="route('panel.qr-code.svg')" data-download-svg>Baixar SVG</x-ui.button>
                    <x-ui.button size="sm" variant="secondary" :href="route('panel.qr-code.print', ['mesa' => $table ?: null])" target="_blank">Imprimir display</x-ui.button>
                </div>
                <p class="text-center text-[11.5px] leading-relaxed text-ink/45">O SVG é o arquivo vetorial que a gráfica pede. O endereço do QR Code <strong class="text-ink/70">nunca muda</strong>: você pode personalizar o visual sem reimprimir o que já está nas mesas.</p>
            </x-ui.card>
        </div>
    </div>
</div>
</div>
