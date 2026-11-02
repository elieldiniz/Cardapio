<?php

use App\Actions\Cash\SettleReceivable;
use App\Models\Receivable;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * Fiado (RF-11, UI-01): quem está devendo, quanto, e a baixa quando paga. A página é fina:
 * o recebimento e a entrada no caixa ficam em SettleReceivable.
 */
new #[Layout('layouts::panel')] #[Title('Fiado')] class extends Component
{
    /** abertos | pagos */
    #[Url(as: 'ver', except: 'abertos')]
    public string $tab = 'abertos';

    public bool $showReceive = false;

    public ?int $receivingId = null;

    public string $receiveMethod = 'dinheiro';

    #[Computed]
    public function restaurant()
    {
        return auth()->user()->restaurant;
    }

    #[Computed]
    public function open()
    {
        return $this->restaurant->receivables()->with('order')->whereNull('paid_at')->whereNull('cancelled_at')->orderBy('created_at')->get();
    }

    #[Computed]
    public function paid()
    {
        return $this->restaurant->receivables()->with('order')->whereNotNull('paid_at')->latest('paid_at')->limit(100)->get();
    }

    /**
     * What the customers owe in total: the sum of every fiado not yet paid or cancelled.
     */
    #[Computed]
    public function totalOwed(): string
    {
        return number_format($this->open->sum(fn (Receivable $receivable) => (float) $receivable->amount), 2, '.', '');
    }

    public function startReceive(int $receivableId): void
    {
        $receivable = $this->restaurant->receivables()->findOrFail($receivableId);

        $this->resetErrorBag();
        $this->receivingId = $receivable->id;
        $this->receiveMethod = 'dinheiro';
        $this->showReceive = true;
    }

    public function receive(SettleReceivable $settleReceivable): void
    {
        $receivable = $this->restaurant->receivables()->findOrFail((int) $this->receivingId);

        try {
            $settleReceivable->handle($receivable, $this->receiveMethod, auth()->user());
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return;
        }

        $this->showReceive = false;
        $this->receivingId = null;
        unset($this->open, $this->paid, $this->totalOwed);

        $this->dispatch('toast', message: 'Fiado recebido e lançado no caixa.');
    }
};
?>

<div class="flex flex-col gap-[18px]">
    <x-ui.page-header title="Fiado" subtitle="Quem ainda deve para a loja. Quando pagar, dê baixa e o valor entra no caixa." />

    <div class="flex flex-wrap gap-3.5">
        <x-ui.stat-card label="A receber no fiado" :value="Money::brl($this->totalOwed)" data-total-owed />
        <x-ui.stat-card label="Clientes devendo" :value="(string) $this->open->count()" data-owing-count />
    </div>

    <div class="flex gap-1.5" role="tablist">
        @foreach (['abertos' => 'Em aberto', 'pagos' => 'Pagos'] as $key => $label)
            <button type="button" role="tab" wire:click="$set('tab', '{{ $key }}')" data-tab="{{ $key }}" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" class="rounded-full px-3.5 py-1.5 text-[12.5px] font-bold {{ $tab === $key ? 'bg-ink text-white' : 'bg-ink/[0.06] text-ink/60 hover:bg-ink/10' }}">{{ $label }}</button>
        @endforeach
    </div>

    @php
        $rows = $tab === 'pagos' ? $this->paid : $this->open;
        $timezone = $this->restaurant->orderSettings()->timezone;
    @endphp

    <x-ui.card :padding="false">
        @if ($rows->isEmpty())
            <x-ui.empty-state icon="receipt" :title="$tab === 'pagos' ? 'Nenhum fiado pago ainda' : 'Ninguém devendo'" description="Quando finalizar um pedido como “Fiado”, o cliente aparece aqui até pagar." />
        @else
            <ul data-receivables>
                @foreach ($rows as $receivable)
                    <li wire:key="receivable-{{ $receivable->id }}" class="flex flex-wrap items-center justify-between gap-3 border-b border-ink/[0.06] px-5 py-3.5 last:border-b-0" data-receivable="{{ $receivable->customer_name }}">
                        <div class="min-w-0">
                            <p class="text-[14px] font-bold text-ink">{{ $receivable->customer_name }}</p>
                            <p class="text-[12px] text-ink/50">
                                {{ $receivable->order?->code ? 'Pedido '.$receivable->order->code.' · ' : '' }}{{ $receivable->created_at->copy()->setTimezone($timezone)->format('d/m/Y') }}
                                @if ($receivable->paid_at) · pago em {{ $receivable->paid_at->copy()->setTimezone($timezone)->format('d/m') }} @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-[15px] font-extrabold text-ink">{{ Money::brl($receivable->amount) }}</span>
                            @if ($receivable->isOpen())
                                @if ($receivable->customer_phone)
                                    <a href="https://api.whatsapp.com/send?phone={{ $receivable->customer_phone }}&text={{ rawurlencode('Olá, '.\Illuminate\Support\Str::before($receivable->customer_name, ' ').'! Passando para lembrar do valor de '.Money::brl($receivable->amount).' que ficou em aberto'.($receivable->order ? ' do pedido '.$receivable->order->code : '').'. 😊') }}" target="_blank" rel="noopener" class="text-[12.5px] font-bold text-emerald-700 hover:underline" data-charge>Cobrar</a>
                                @endif
                                <x-ui.button size="sm" wire:click="startReceive({{ $receivable->id }})" data-receive>Receber</x-ui.button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <x-ui.modal wire:model="showReceive" title="Receber fiado" data-receive-modal>
        <p class="text-[13px] text-ink/60">Como o cliente pagou? O valor entra no caixa aberto.</p>
        <div class="grid grid-cols-3 gap-2">
            @foreach (['dinheiro' => '💵 Dinheiro', 'pix' => '⚡ Pix', 'cartao' => '💳 Cartão'] as $method => $label)
                <button type="button" wire:click="$set('receiveMethod', '{{ $method }}')" aria-pressed="{{ $receiveMethod === $method ? 'true' : 'false' }}" class="rounded-xl border px-2 py-3 text-[13px] font-bold {{ $receiveMethod === $method ? 'border-accent bg-accent/10 text-ink' : 'border-ink/15 text-ink/60' }}">{{ $label }}</button>
            @endforeach
        </div>
        @error('cash') <p class="text-[12.5px] text-danger" role="alert">{{ $message }}</p> @enderror
        @error('receivable') <p class="text-[12.5px] text-danger" role="alert">{{ $message }}</p> @enderror
        <x-slot:footer>
            <x-ui.button class="flex-1" wire:click="receive" wire:loading.attr="disabled" wire:target="receive" data-confirm-receive>Dar baixa</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
