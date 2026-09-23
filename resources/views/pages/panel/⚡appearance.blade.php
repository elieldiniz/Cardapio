<?php

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::panel')] #[Title('Aparência')] class extends Component
{
    use WithFileUploads;

    public string $accent_color = '';

    public string $font = '';

    public string $description = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $logo = null;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $cover = null;

    public function mount(): void
    {
        $restaurant = $this->restaurant;

        $this->accent_color = $restaurant->accentColor();
        $this->font = $restaurant->font ?: config('feed.default_font');
        $this->description = (string) $restaurant->description;
    }

    #[Computed]
    public function restaurant()
    {
        return auth()->user()->restaurant;
    }

    public function updatedLogo(): void
    {
        $this->validateOnly('logo');
    }

    public function updatedCover(): void
    {
        $this->validateOnly('cover');
    }

    protected function rules(): array
    {
        return [
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'font' => ['required', Rule::in(array_keys(config('feed.fonts')))],
            'logo' => ['nullable', 'image', 'max:4096'],
            'cover' => ['nullable', 'image', 'max:8192'],
            'description' => ['nullable', 'string', 'max:200'],
        ];
    }

    protected function messages(): array
    {
        return [
            'accent_color.regex' => 'Use uma cor no formato #RRGGBB.',
            'logo.image' => 'O logo precisa ser uma imagem.',
            'logo.max' => 'O logo pode ter no máximo 4 MB.',
            'cover.image' => 'A capa precisa ser uma imagem.',
            'cover.max' => 'A capa pode ter no máximo 8 MB.',
            'description.max' => 'A descrição pode ter no máximo 200 caracteres.',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $attributes = [
            'accent_color' => strtoupper($this->accent_color),
            'font' => $this->font,
            'description' => trim($this->description) ?: null,
        ];

        foreach (['logo' => 'logos', 'cover' => 'covers'] as $field => $directory) {
            if ($this->{$field}) {
                $previous = $this->restaurant->{$field.'_path'};
                $attributes[$field.'_path'] = $this->{$field}->store("{$directory}/{$this->restaurant->id}", 'public');

                if ($previous) {
                    Storage::disk('public')->delete($previous);
                }
            }
        }

        $this->restaurant->update($attributes);
        $this->reset('logo', 'cover');

        $this->dispatch('toast', message: 'Aparência salva! Seu cardápio já está com a nova cara.');
    }
};
?>

@php
    $logoPreview = $logo?->isPreviewable() ? $logo->temporaryUrl() : ($this->restaurant->logo_path ? Storage::disk('public')->url($this->restaurant->logo_path) : null);
    $coverPreview = $cover?->isPreviewable() ? $cover->temporaryUrl() : ($this->restaurant->cover_path ? Storage::disk('public')->url($this->restaurant->cover_path) : null);
@endphp

<div class="flex flex-col gap-[18px]">
    <x-ui.page-header title="Aparência" subtitle="Logo, capa, descrição, cor e fonte do seu cardápio. A prévia mostra o resultado antes de salvar." />

    <div
        class="flex flex-wrap gap-[18px]"
        x-data="{
            send() {
                this.$refs.preview?.contentWindow?.postMessage({
                    type: 'appearance',
                    accent: $wire.accent_color,
                    font: $wire.font,
                    logoUrl: this.$root.dataset.logoUrl || null,
                    coverUrl: this.$root.dataset.coverUrl || null,
                    description: $wire.description,
                }, window.location.origin);
            },
        }"
        x-init="$watch('$wire.accent_color', () => send()); $watch('$wire.font', () => send()); $watch('$wire.description', () => send())"
        data-logo-url="{{ $logoPreview }}"
        data-cover-url="{{ $coverPreview }}"
    >
        {{-- Re-created whenever the logo or cover changes, pushing the new images into the preview. --}}
        <span hidden wire:key="images-{{ md5($logoPreview.'|'.$coverPreview) }}" x-init="$nextTick(() => send())"></span>

        <x-ui.card class="min-w-[280px] flex-1">
            <form wire:submit="save" class="flex flex-col gap-5">
                <div class="flex flex-col gap-2.5">
                    <span class="text-[14px] font-bold text-ink">Logo</span>
                    <div class="flex items-center gap-4">
                        @if ($logoPreview)
                            <img src="{{ $logoPreview }}" alt="" class="size-20 rounded-full object-cover shadow-[0_0_0_3px_var(--accent)]" style="--accent: {{ $accent_color }}">
                        @else
                            <span class="flex size-20 items-center justify-center rounded-full bg-ink/[0.06] text-[12px] font-semibold text-ink/40">Logo</span>
                        @endif
                        <label class="cursor-pointer">
                            <span class="inline-flex rounded-[9px] border border-ink/15 px-4 py-2 text-[13px] font-bold text-ink hover:bg-ink/5">Enviar logo</span>
                            <input type="file" wire:model="logo" accept="image/*" class="sr-only">
                        </label>
                    </div>
                    <div wire:loading wire:target="logo" class="text-[12.5px] text-ink/55">Enviando…</div>
                    @error('logo') <span class="text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-2.5">
                    <span class="text-[14px] font-bold text-ink">Foto de capa</span>
                    <label class="relative flex h-24 cursor-pointer items-center justify-center overflow-hidden rounded-[10px] border-2 border-dashed border-ink/15 bg-ink/[0.03] text-[12.5px] font-semibold text-ink/50 hover:border-accent hover:text-accent" style="{{ $coverPreview ? "background: url('{$coverPreview}') center / cover; border-style: solid;" : '' }}">
                        <span class="{{ $coverPreview ? 'rounded-md bg-ink/60 px-2.5 py-1 text-white' : '' }}">{{ $coverPreview ? 'Trocar capa' : 'Enviar foto de capa' }}</span>
                        <input type="file" wire:model="cover" accept="image/*" class="sr-only">
                    </label>
                    <span class="text-[12px] text-ink/45">Aparece no topo do cardápio, atrás do logo. Use uma foto horizontal do salão ou de um prato.</span>
                    <div wire:loading wire:target="cover" class="text-[12.5px] text-ink/55">Enviando…</div>
                    @error('cover') <span class="text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>

                <x-ui.field label="Descrição do restaurante" for="description" error="description" hint="Aparece abaixo do nome, no topo do cardápio. Até 200 caracteres.">
                    <x-ui.textarea id="description" wire:model.live.debounce.300ms="description" rows="2" maxlength="200" placeholder="Ex.: Churrascaria & fumeiro. Carnes defumadas 12h na casa." />
                </x-ui.field>

                <div class="flex flex-col gap-2.5">
                    <span class="text-[14px] font-bold text-ink">Cor de destaque</span>
                    <div class="flex flex-wrap items-center gap-2.5">
                        @foreach (config('feed.accent_swatches') as $swatch)
                            <button
                                type="button"
                                wire:click="$set('accent_color', '{{ $swatch }}')"
                                class="size-[34px] rounded-full {{ strtoupper($accent_color) === $swatch ? 'ring-3 ring-ink ring-offset-2' : 'ring-1 ring-ink/15' }}"
                                style="background: {{ $swatch }}"
                                aria-label="Cor {{ $swatch }}"
                            ></button>
                        @endforeach
                        <label class="flex items-center gap-2 text-[12.5px] font-semibold text-ink/60">
                            <input type="color" wire:model.live.debounce.150ms="accent_color" class="size-[34px] cursor-pointer rounded-full border-0 bg-transparent p-0" aria-label="Outra cor">
                            Outra
                        </label>
                    </div>
                    @error('accent_color') <span class="text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>

                <x-ui.field label="Fonte dos títulos" for="font" error="font">
                    <x-ui.select id="font" wire:model.live="font">
                        @foreach (array_keys(config('feed.fonts')) as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.button type="submit" class="self-start" wire:loading.attr="disabled">Salvar aparência</x-ui.button>
            </form>
        </x-ui.card>

        <div class="flex flex-1 flex-col items-center gap-2.5 rounded-[14px] bg-night p-5">
            <span class="text-[12px] font-bold" style="color: {{ $accent_color }}">Pré-visualização</span>
            <iframe
                x-ref="preview"
                x-on:load="send()"
                src="{{ route('panel.appearance.preview') }}"
                title="Prévia do cardápio"
                class="h-[560px] w-[290px] rounded-[28px] border-4 border-black bg-black"
                data-appearance-preview
                wire:ignore
            ></iframe>
        </div>
    </div>
</div>
