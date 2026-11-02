<?php

use App\Actions\Marketing\SavePromoCode;
use App\Models\PromoCode;
use App\Support\Money;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Cupons de desconto (RF-17): a loja cria códigos de % ou valor fixo, com mínimo, limite de usos e
 * validade. A página só coleta os campos; as regras ficam em SavePromoCode e ApplyPromoCode.
 */
new #[Layout('layouts::panel')] #[Title('Cupons')] class extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $type = 'percentual';

    public string $value = '';

    public string $min_order = '';

    public string $max_uses = '';

    public string $starts_on = '';

    public string $ends_on = '';

    public string $description = '';

    #[Computed]
    public function promoCodes()
    {
        return auth()->user()->restaurant->promoCodes()->orderByDesc('is_active')->latest('id')->get();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->code = strtoupper(Str::random(6));
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $promo = auth()->user()->restaurant->promoCodes()->findOrFail($id);

        $this->resetForm();
        $this->editingId = $promo->id;
        $this->code = $promo->code;
        $this->type = $promo->type;
        $this->value = $promo->isPercent() ? rtrim(rtrim(Money::input($promo->value), '0'), ',') : Money::input($promo->value);
        $this->min_order = $promo->min_order ? Money::input($promo->min_order) : '';
        $this->max_uses = (string) ($promo->max_uses ?? '');
        $this->starts_on = $promo->starts_on?->toDateString() ?? '';
        $this->ends_on = $promo->ends_on?->toDateString() ?? '';
        $this->description = (string) $promo->description;
        $this->showForm = true;
    }

    public function save(SavePromoCode $savePromoCode): void
    {
        $restaurant = auth()->user()->restaurant;
        $promo = $this->editingId ? $restaurant->promoCodes()->findOrFail($this->editingId) : null;

        $savePromoCode->handle($restaurant, [
            'code' => $this->code,
            'type' => $this->type,
            'value' => $this->value,
            'min_order' => $this->min_order,
            'max_uses' => $this->max_uses,
            'starts_on' => $this->starts_on,
            'ends_on' => $this->ends_on,
            'description' => $this->description,
            'is_active' => $promo?->is_active ?? true,
        ], $promo);

        $this->showForm = false;
        unset($this->promoCodes);
        $this->dispatch('toast', message: 'Cupom salvo.');
    }

    public function toggle(int $id): void
    {
        $promo = auth()->user()->restaurant->promoCodes()->findOrFail($id);
        $promo->update(['is_active' => ! $promo->is_active]);
        unset($this->promoCodes);
    }

    public function delete(int $id): void
    {
        auth()->user()->restaurant->promoCodes()->findOrFail($id)->delete();
        unset($this->promoCodes);
        $this->dispatch('toast', message: 'Cupom excluído.');
    }

    private function resetForm(): void
    {
        $this->resetErrorBag();
        $this->reset('editingId', 'code', 'value', 'min_order', 'max_uses', 'starts_on', 'ends_on', 'description');
        $this->type = 'percentual';
    }
};
?>

<div class="flex flex-col gap-[18px]">
    <x-ui.page-header title="Cupons" subtitle="Códigos de desconto que o cliente digita no pedido. Compartilhe o link do cupom no Instagram ou no WhatsApp.">
        <x-ui.button wire:click="create" data-new-promo>Novo cupom</x-ui.button>
    </x-ui.page-header>

    @php($restaurant = auth()->user()->restaurant)

    <x-ui.card :padding="false">
        @if ($this->promoCodes->isEmpty())
            <x-ui.empty-state icon="tag" title="Nenhum cupom ainda" description="Crie um cupom como BEMVINDO10 para dar 10% na primeira compra." />
        @else
            <ul data-promo-list>
                @foreach ($this->promoCodes as $promo)
                    @php($link = route('feed.show', $restaurant).'?cupom='.$promo->code)
                    <li wire:key="promo-{{ $promo->id }}" class="flex flex-wrap items-center justify-between gap-3 border-b border-ink/[0.06] px-5 py-3.5 last:border-b-0 {{ $promo->is_active ? '' : 'opacity-60' }}" data-promo="{{ $promo->code }}">
                        <div class="min-w-0">
                            <p class="text-[14px] font-extrabold tracking-wide text-ink">{{ $promo->code }}
                                <span class="ml-1 rounded-full bg-accent/15 px-2 py-0.5 text-[11.5px] font-bold">{{ $promo->isPercent() ? rtrim(rtrim($promo->value, '0'), '.').'%' : Money::brl($promo->value) }}</span>
                            </p>
                            <p class="text-[12px] text-ink/50">
                                {{ $promo->uses }}{{ $promo->max_uses ? ' de '.$promo->max_uses : '' }} {{ $promo->uses === 1 ? 'uso' : 'usos' }}
                                @if ($promo->min_order) · mínimo {{ Money::brl($promo->min_order) }} @endif
                                @if ($promo->ends_on) · até {{ $promo->ends_on->format('d/m/Y') }} @endif
                                @if ($promo->description) · {{ $promo->description }} @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" class="text-[12.5px] font-bold text-accent hover:underline" x-data x-on:click="copyText(@js($link)).then((ok) => $dispatch('toast', { message: ok ? 'Link do cupom copiado.' : 'Não deu para copiar. Selecione e copie: ' + @js($link) }))" data-copy-link>Copiar link</button>
                            <x-ui.button size="sm" variant="ghost" wire:click="toggle({{ $promo->id }})" data-toggle>{{ $promo->is_active ? 'Pausar' : 'Ativar' }}</x-ui.button>
                            <x-ui.button size="sm" variant="ghost" wire:click="edit({{ $promo->id }})" data-edit>Editar</x-ui.button>
                            <x-ui.button size="sm" variant="ghost" wire:click="delete({{ $promo->id }})" wire:confirm="Excluir o cupom {{ $promo->code }}?" data-delete>Excluir</x-ui.button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Editar cupom' : 'Novo cupom'" data-promo-modal>
        <div class="flex flex-col gap-3">
            <x-ui.field label="Código" for="promo-code" error="code">
                <x-ui.input id="promo-code" wire:model="code" maxlength="30" class="uppercase" placeholder="BEMVINDO10" />
            </x-ui.field>
            <div class="grid grid-cols-2 gap-3">
                <x-ui.field label="Tipo" for="promo-type" error="type">
                    <x-ui.select id="promo-type" wire:model="type">
                        <option value="percentual">Porcentagem (%)</option>
                        <option value="valor">Valor fixo (R$)</option>
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field :label="$type === 'percentual' ? 'Desconto (%)' : 'Desconto (R$)'" for="promo-value" error="value">
                    <x-ui.input id="promo-value" wire:model="value" inputmode="decimal" placeholder="{{ $type === 'percentual' ? '10' : '5,00' }}" />
                </x-ui.field>
            </div>
            <x-ui.field label="Pedido mínimo (opcional)" for="promo-min" error="min_order">
                <x-ui.input id="promo-min" wire:model="min_order" inputmode="decimal" placeholder="Ex.: 40,00" />
            </x-ui.field>
            <x-ui.field label="Limite de usos (opcional)" for="promo-max" error="max_uses" hint="Deixe vazio para não limitar.">
                <x-ui.input id="promo-max" wire:model="max_uses" inputmode="numeric" placeholder="Ex.: 100" />
            </x-ui.field>
            <div class="grid grid-cols-2 gap-3">
                <x-ui.field label="Válido a partir de" for="promo-start" error="starts_on">
                    <x-ui.input id="promo-start" type="date" wire:model="starts_on" />
                </x-ui.field>
                <x-ui.field label="Válido até" for="promo-end" error="ends_on">
                    <x-ui.input id="promo-end" type="date" wire:model="ends_on" />
                </x-ui.field>
            </div>
            <x-ui.field label="Descrição (opcional, só você vê)" for="promo-desc" error="description">
                <x-ui.input id="promo-desc" wire:model="description" maxlength="120" placeholder="Ex.: Campanha do Instagram" />
            </x-ui.field>
        </div>
        <x-slot:footer>
            <x-ui.button class="flex-1" wire:click="save" wire:loading.attr="disabled" wire:target="save" data-save-promo>Salvar cupom</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
