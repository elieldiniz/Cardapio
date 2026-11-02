<?php

use App\Actions\Loyalty\RedeemLoyaltyReward;
use App\Actions\Loyalty\SaveLoyaltyProgram;
use App\Models\LoyaltyCard;
use App\Support\Money;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Fidelidade (RF-18): cartão de selos. A página só coleta a configuração e mostra quem já
 * completou; os selos nascem em FinalizeOrder e voltam em CancelFinalizedOrder.
 */
new #[Layout('layouts::panel')] #[Title('Fidelidade')] class extends Component
{
    public bool $is_active = false;

    public string $stamps_required = '10';

    public string $min_order = '';

    public string $reward_type = 'brinde';

    public string $reward_value = '';

    public string $reward_label = '';

    public function mount(): void
    {
        $program = auth()->user()->restaurant->loyaltyProgram;

        if ($program !== null) {
            $this->is_active = $program->is_active;
            $this->stamps_required = (string) $program->stamps_required;
            $this->min_order = $program->min_order ? Money::input($program->min_order) : '';
            $this->reward_type = $program->reward_type;
            $this->reward_value = $program->reward_value ? ($program->reward_type === 'percentual' ? rtrim(rtrim(Money::input($program->reward_value), '0'), ',') : Money::input($program->reward_value)) : '';
            $this->reward_label = (string) $program->reward_label;
        }
    }

    #[Computed]
    public function program()
    {
        return auth()->user()->restaurant->loyaltyProgram()->first();
    }

    /** @return array{open: int, ready: int, redeemed: int} */
    #[Computed]
    public function counters(): array
    {
        $cards = LoyaltyCard::query()->where('loyalty_program_id', $this->program?->id ?? 0);

        return [
            'open' => (clone $cards)->whereNull('redeemed_at')->whereNull('completed_at')->count(),
            'ready' => (clone $cards)->whereNull('redeemed_at')->whereNotNull('completed_at')->count(),
            'redeemed' => (clone $cards)->whereNotNull('redeemed_at')->count(),
        ];
    }

    #[Computed]
    public function readyCards()
    {
        return LoyaltyCard::query()
            ->with('customer')
            ->where('loyalty_program_id', $this->program?->id ?? 0)
            ->whereNull('redeemed_at')->whereNotNull('completed_at')
            ->latest('completed_at')->limit(50)->get();
    }

    public function save(SaveLoyaltyProgram $saveProgram): void
    {
        $saveProgram->handle(auth()->user()->restaurant, [
            'is_active' => $this->is_active,
            'stamps_required' => $this->stamps_required,
            'min_order' => $this->min_order,
            'reward_type' => $this->reward_type,
            'reward_value' => $this->reward_value,
            'reward_label' => $this->reward_label,
        ]);

        unset($this->program, $this->counters);
        $this->dispatch('toast', message: 'Programa de fidelidade salvo.');
    }

    public function redeem(int $cardId, RedeemLoyaltyReward $redeem): void
    {
        $card = LoyaltyCard::query()
            ->whereHas('program', fn ($query) => $query->where('restaurant_id', auth()->user()->restaurant_id))
            ->findOrFail($cardId);

        $redeem->handle($card);

        unset($this->counters, $this->readyCards);
        $this->dispatch('toast', message: 'Prêmio entregue.');
    }

    private function rewardPreview(): string
    {
        return match ($this->reward_type) {
            'percentual' => (trim($this->reward_value) !== '' ? $this->reward_value : '10').'% de desconto',
            'valor' => 'R$ '.(trim($this->reward_value) !== '' ? $this->reward_value : '10,00').' de desconto',
            default => trim($this->reward_label) !== '' ? $this->reward_label : 'um brinde da casa',
        };
    }
};
?>

<div class="flex flex-col gap-[18px]">
    <x-ui.page-header title="Fidelidade" subtitle="O cliente junta um selo a cada pedido finalizado e ganha um prêmio ao completar o cartão." />

    <div class="grid grid-cols-3 gap-3" data-loyalty-counters>
        <x-ui.stat-card label="Cartões em andamento" :value="$this->counters['open']" />
        <x-ui.stat-card label="Prêmios para entregar" :value="$this->counters['ready']" />
        <x-ui.stat-card label="Prêmios entregues" :value="$this->counters['redeemed']" />
    </div>

    <div class="grid gap-[18px] lg:grid-cols-[1fr_320px]">
        <x-ui.card>
            <div class="flex flex-col gap-3">
                <label class="flex items-center gap-2 text-[14px] font-bold text-ink">
                    <input type="checkbox" wire:model.live="is_active" class="size-4 accent-[var(--color-accent)]" data-loyalty-active> Programa ativo
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.field label="Compras para completar" for="loyalty-stamps" error="stamps_required">
                        <x-ui.input id="loyalty-stamps" wire:model.live="stamps_required" inputmode="numeric" />
                    </x-ui.field>
                    <x-ui.field label="Pedido mínimo por selo (opcional)" for="loyalty-min" error="min_order">
                        <x-ui.input id="loyalty-min" wire:model="min_order" inputmode="decimal" placeholder="Ex.: 30,00" />
                    </x-ui.field>
                </div>
                <x-ui.field label="Prêmio" for="loyalty-type" error="reward_type">
                    <x-ui.select id="loyalty-type" wire:model.live="reward_type">
                        <option value="brinde">Brinde (ex.: sobremesa grátis)</option>
                        <option value="percentual">Desconto em %</option>
                        <option value="valor">Desconto em R$</option>
                    </x-ui.select>
                </x-ui.field>
                @if ($reward_type === 'brinde')
                    <x-ui.field label="Qual é o brinde?" for="loyalty-label" error="reward_label">
                        <x-ui.input id="loyalty-label" wire:model.live="reward_label" maxlength="80" placeholder="Sobremesa grátis" />
                    </x-ui.field>
                @else
                    <x-ui.field :label="$reward_type === 'percentual' ? 'Quantos % de desconto?' : 'Quantos reais de desconto?'" for="loyalty-value" error="reward_value">
                        <x-ui.input id="loyalty-value" wire:model.live="reward_value" inputmode="decimal" placeholder="{{ $reward_type === 'percentual' ? '10' : '15,00' }}" />
                    </x-ui.field>
                @endif
                <x-ui.button wire:click="save" wire:loading.attr="disabled" wire:target="save" data-save-loyalty>Salvar programa</x-ui.button>
            </div>
        </x-ui.card>

        <x-ui.card>
            <p class="text-[12px] font-bold uppercase tracking-wide text-ink/45">O cliente vê</p>
            <p class="mt-2 text-[14px] font-bold text-ink" data-loyalty-preview>
                Complete {{ max((int) $stamps_required, 2) }} pedidos e ganhe {{ $this->rewardPreview() }}.
            </p>
            <div class="mt-3 flex flex-wrap gap-1.5" aria-hidden="true">
                @for ($i = 1; $i <= min(max((int) $stamps_required, 2), 20); $i++)
                    <span class="grid size-7 place-items-center rounded-full border border-dashed border-ink/25 text-[11px] text-ink/35">{{ $i }}</span>
                @endfor
            </div>
        </x-ui.card>
    </div>

    <x-ui.card :padding="false">
        @if ($this->readyCards->isEmpty())
            <x-ui.empty-state icon="star" title="Nenhum prêmio esperando" description="Quando um cliente completar o cartão, ele aparece aqui para você entregar o prêmio." />
        @else
            <ul data-loyalty-ready>
                @foreach ($this->readyCards as $card)
                    <li wire:key="card-{{ $card->id }}" class="flex items-center justify-between gap-3 border-b border-ink/[0.06] px-5 py-3.5 last:border-b-0">
                        <div>
                            <p class="text-[14px] font-bold text-ink">{{ $card->customer->erased_at ? 'Cliente removido' : $card->customer->name }}</p>
                            <p class="text-[12px] text-ink/50">Completou em {{ $card->completed_at->format('d/m/Y') }} · {{ $this->program->rewardText() }}</p>
                        </div>
                        <x-ui.button size="sm" wire:click="redeem({{ $card->id }})" data-redeem>Entregar prêmio</x-ui.button>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</div>
