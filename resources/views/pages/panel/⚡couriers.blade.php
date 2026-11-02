<?php

use App\Actions\Couriers\RotateCourierLink;
use App\Actions\Couriers\SaveCourier;
use App\Actions\Couriers\SettleCourierRun;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Motoboys (RF-22): cadastro, link do entregador e acerto. As regras ficam em SaveCourier,
 * RotateCourierLink e SettleCourierRun.
 */
new #[Layout('layouts::panel')] #[Title('Motoboys')] class extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $phone = '';

    public string $fee = '';

    #[Computed]
    public function couriers()
    {
        return auth()->user()->restaurant->couriers()->orderByDesc('is_active')->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $courier = $this->find($id);

        $this->resetForm();
        $this->editingId = $courier->id;
        $this->name = $courier->name;
        $this->phone = (string) $courier->phone;
        $this->fee = (float) $courier->fee_per_delivery > 0 ? Money::input($courier->fee_per_delivery) : '';
        $this->showForm = true;
    }

    public function save(SaveCourier $saveCourier): void
    {
        $restaurant = auth()->user()->restaurant;

        $saveCourier->handle($restaurant, ['name' => $this->name, 'phone' => $this->phone, 'fee_per_delivery' => $this->fee], $this->editingId ? $this->find($this->editingId) : null);

        $this->showForm = false;
        unset($this->couriers);
        $this->dispatch('toast', message: 'Motoboy salvo.');
    }

    public function toggle(int $id): void
    {
        $courier = $this->find($id);
        $courier->update(['is_active' => ! $courier->is_active]);
        unset($this->couriers);
    }

    public function revoke(int $id, RotateCourierLink $rotate): void
    {
        $rotate->revoke($this->find($id));
        unset($this->couriers);
        $this->dispatch('toast', message: 'Link revogado. Ele não abre mais.');
    }

    public function regenerate(int $id, RotateCourierLink $rotate): void
    {
        $rotate->regenerate($this->find($id));
        unset($this->couriers);
        $this->dispatch('toast', message: 'Novo link criado. O anterior deixou de valer.');
    }

    public function settle(int $id, SettleCourierRun $settle): void
    {
        try {
            $movement = $settle->handle($this->find($id), auth()->user());
        } catch (ValidationException $exception) {
            $this->dispatch('toast', message: collect($exception->errors())->flatten()->first(), type: 'error');

            return;
        }

        unset($this->couriers);
        $this->dispatch('toast', message: $movement ? 'Acerto lançado no caixa.' : 'Nada a acertar com este motoboy.');
    }

    private function find(int $id)
    {
        return auth()->user()->restaurant->couriers()->findOrFail($id);
    }

    private function resetForm(): void
    {
        $this->resetErrorBag();
        $this->reset('editingId', 'name', 'phone', 'fee');
    }
};
?>

<div class="flex flex-col gap-[18px]">
    <x-ui.page-header title="Motoboys" subtitle="Quem faz suas entregas. Cada um recebe um link só dele para ver os pedidos e marcar como entregue.">
        <x-ui.button wire:click="create" data-new-courier>Novo motoboy</x-ui.button>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        @if ($this->couriers->isEmpty())
            <x-ui.empty-state icon="bike" title="Nenhum motoboy ainda" description="Cadastre quem entrega para escolher no pedido e fazer o acerto no caixa." />
        @else
            @php($settler = app(\App\Actions\Couriers\SettleCourierRun::class))
            <ul data-couriers>
                @foreach ($this->couriers as $courier)
                    @php($pending = $settler->pending($courier))
                    @php($link = route('courier.show', $courier->token))
                    <li wire:key="courier-{{ $courier->id }}" class="flex flex-col gap-2 border-b border-ink/[0.06] px-5 py-3.5 last:border-b-0 {{ $courier->is_active ? '' : 'opacity-60' }}" data-courier="{{ $courier->name }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-[14px] font-bold text-ink">{{ $courier->name }}
                                    @if ($courier->revoked_at) <span class="ml-1 rounded-full bg-danger/10 px-2 py-0.5 text-[10.5px] font-bold uppercase text-danger">Link revogado</span> @endif
                                </p>
                                <p class="text-[12px] text-ink/50">{{ Money::brl($courier->fee_per_delivery) }} por entrega · a acertar: {{ $pending['count'] }} {{ $pending['count'] === 1 ? 'entrega' : 'entregas' }} ({{ Money::brl($pending['amount']) }})</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($pending['count'] > 0)
                                    <x-ui.button size="sm" wire:click="settle({{ $courier->id }})" wire:confirm="Lançar {{ Money::brl($pending['amount']) }} como despesa no caixa?" data-settle>Acertar</x-ui.button>
                                @endif
                                <x-ui.button size="sm" variant="ghost" wire:click="edit({{ $courier->id }})" data-edit>Editar</x-ui.button>
                                <x-ui.button size="sm" variant="ghost" wire:click="toggle({{ $courier->id }})" data-toggle>{{ $courier->is_active ? 'Pausar' : 'Ativar' }}</x-ui.button>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 text-[12.5px] font-bold">
                            @unless ($courier->revoked_at)
                                <button type="button" class="text-accent hover:underline" x-data x-on:click="copyText(@js($link)).then((ok) => $dispatch('toast', { message: ok ? 'Link do motoboy copiado.' : 'Não deu para copiar. Selecione e copie: ' + @js($link) }))" data-copy-link>Copiar link</button>
                                <button type="button" class="text-danger hover:underline" wire:click="revoke({{ $courier->id }})" wire:confirm="Revogar o link de {{ $courier->name }}? Ele deixa de abrir." data-revoke>Revogar link</button>
                            @endunless
                            <button type="button" class="text-ink/60 hover:underline" wire:click="regenerate({{ $courier->id }})" data-regenerate>{{ $courier->revoked_at ? 'Criar novo link' : 'Trocar link' }}</button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Editar motoboy' : 'Novo motoboy'" data-courier-modal>
        <div class="flex flex-col gap-3">
            <x-ui.field label="Nome" for="courier-name" error="name"><x-ui.input id="courier-name" wire:model="name" maxlength="80" /></x-ui.field>
            <x-ui.field label="WhatsApp (opcional)" for="courier-phone" error="phone"><x-ui.input id="courier-phone" wire:model="phone" inputmode="tel" placeholder="(69) 99999-9999" /></x-ui.field>
            <x-ui.field label="Valor por entrega" for="courier-fee" error="fee_per_delivery"><x-ui.input id="courier-fee" wire:model="fee" inputmode="decimal" placeholder="Ex.: 6,00" /></x-ui.field>
        </div>
        <x-slot:footer>
            <x-ui.button class="flex-1" wire:click="save" wire:loading.attr="disabled" wire:target="save" data-save-courier>Salvar</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
