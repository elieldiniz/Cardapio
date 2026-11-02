<?php

use App\Actions\Cash\CloseCashSession;
use App\Actions\Cash\OpenCashSession;
use App\Actions\Cash\RecordMovement;
use App\Models\CashSession;
use App\Services\Cash\CashSummary;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Caixa (RF-10, UI-01): abre com o troco da gaveta, mostra o que deve ter em dinheiro, as
 * vendas por forma de pagamento e os movimentos do dia, e fecha contando o dinheiro. A
 * página é fina: as regras ficam nas Actions e na fórmula do CashSummary.
 */
new #[Layout('layouts::panel')] #[Title('Caixa')] class extends Component
{
    public string $openingFloat = '';

    // Movement modal (despesa | retirada | reforco)
    public bool $showMovement = false;

    public string $movementType = 'despesa';

    public string $movementAmount = '';

    public string $movementMethod = 'dinheiro';

    public string $movementCategory = 'Outros';

    public string $movementDescription = '';

    // Close modal
    public bool $showClose = false;

    public string $countedCash = '';

    public string $closeNotes = '';

    #[Computed]
    public function restaurant()
    {
        return auth()->user()->restaurant;
    }

    #[Computed]
    public function session(): ?CashSession
    {
        return $this->restaurant->openCashSession();
    }

    #[Computed]
    public function summary(): ?array
    {
        return $this->session ? app(CashSummary::class)->for($this->session) : null;
    }

    /**
     * The last session that was closed, so the dono sees how yesterday ended.
     */
    #[Computed]
    public function lastClosed(): ?CashSession
    {
        return $this->restaurant->cashSessions()->whereNotNull('closed_at')->latest('closed_at')->first();
    }

    #[Computed]
    public function movements()
    {
        return $this->session
            ? $this->session->movements()->with(['type', 'paymentMethod', 'order'])->get()->reverse()->values()
            : collect();
    }

    public function openCash(OpenCashSession $openCashSession): void
    {
        try {
            $openCashSession->handle($this->restaurant, $this->openingFloat, auth()->user());
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return;
        }

        $this->reset('openingFloat');
        $this->refreshData();
        $this->dispatch('toast', message: 'Caixa aberto.');
    }

    public function startMovement(string $type): void
    {
        abort_unless(in_array($type, ['despesa', 'retirada', 'reforco'], true), 404);

        $this->resetErrorBag();
        $this->reset('movementAmount', 'movementDescription');
        $this->movementType = $type;
        $this->movementMethod = 'dinheiro';
        $this->movementCategory = 'Outros';
        $this->showMovement = true;
    }

    public function saveMovement(RecordMovement $recordMovement): void
    {
        try {
            $recordMovement->handle($this->restaurant, $this->movementType, $this->movementAmount, $this->movementMethod, [
                'category' => $this->movementCategory,
                'description' => $this->movementDescription,
                'user' => auth()->user(),
            ]);
        } catch (ValidationException $exception) {
            $this->setErrorBag(collect($exception->errors())->mapWithKeys(fn ($messages, $key) => [$key === 'method' ? 'movementMethod' : ($key === 'amount' ? 'movementAmount' : $key) => $messages])->all());

            return;
        }

        $this->showMovement = false;
        $this->refreshData();
        $this->dispatch('toast', message: 'Movimento lançado.');
    }

    public function startClose(): void
    {
        $this->resetErrorBag();
        $this->reset('countedCash', 'closeNotes');
        $this->showClose = true;
    }

    public function closeCash(CloseCashSession $closeCashSession): void
    {
        try {
            $closed = $closeCashSession->handle($this->session, $this->countedCash, $this->closeNotes, auth()->user());
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return;
        }

        $this->showClose = false;
        $this->refreshData();

        $difference = (float) $closed->difference;

        $this->dispatch('toast', message: abs($difference) < 0.005
            ? 'Caixa fechado. O dinheiro bateu certinho!'
            : 'Caixa fechado com '.($difference > 0 ? 'sobra' : 'falta').' de '.Money::brl(abs($difference)).'.');
    }

    private function refreshData(): void
    {
        unset($this->session, $this->summary, $this->movements, $this->lastClosed);
    }

    /**
     * "+" when the movement brings money in, "−" when it takes it out.
     */
    public function sign(string $type): string
    {
        return in_array($type, ['venda', 'recebimento_fiado', 'reforco'], true) ? '+' : '−';
    }
};
?>

<div class="flex flex-col gap-[18px]">
    <x-ui.page-header title="Caixa" subtitle="O dinheiro do dia: o que entrou, o que saiu e o que deve estar na gaveta.">
        @if ($this->session)
            <x-ui.button variant="secondary" wire:click="startClose" data-start-close>Fechar caixa</x-ui.button>
        @endif
    </x-ui.page-header>

    @if (! $this->session)
        <x-ui.card data-cash-closed>
            <div class="flex flex-col gap-4">
                <div>
                    <h2 class="text-[16px] font-bold text-ink">Caixa fechado</h2>
                    <p class="mt-1 text-[13.5px] text-ink/60">Abra o caixa no começo do expediente informando o troco que está na gaveta. Os pedidos finalizados entram sozinhos.</p>
                </div>
                <form wire:submit="openCash" class="flex flex-wrap items-end gap-3">
                    <x-ui.field label="Troco na gaveta agora" for="openingFloat" error="opening_float" hint="Deixe vazio se começar sem troco." class="w-48">
                        <x-ui.input id="openingFloat" wire:model="openingFloat" placeholder="R$ 0,00" inputmode="decimal" />
                    </x-ui.field>
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="openCash" data-open-cash>Abrir caixa</x-ui.button>
                </form>
                @error('cash') <p class="text-[12.5px] text-danger" role="alert">{{ $message }}</p> @enderror
            </div>
        </x-ui.card>

        @if ($closed = $this->lastClosed)
            <x-ui.card title="Último fechamento" data-last-closed>
                <dl class="grid gap-3 text-[13.5px] sm:grid-cols-4">
                    <div><dt class="text-ink/50">Fechado em</dt><dd class="font-bold text-ink">{{ $closed->closed_at->copy()->setTimezone($this->restaurant->orderSettings()->timezone)->format('d/m H:i') }}</dd></div>
                    <div><dt class="text-ink/50">Esperado</dt><dd class="font-bold text-ink">{{ Money::brl($closed->expected_cash) }}</dd></div>
                    <div><dt class="text-ink/50">Contado</dt><dd class="font-bold text-ink">{{ Money::brl($closed->counted_cash) }}</dd></div>
                    <div><dt class="text-ink/50">Diferença</dt><dd class="font-bold {{ (float) $closed->difference === 0.0 ? 'text-emerald-700' : 'text-danger' }}">{{ Money::brl($closed->difference) }}</dd></div>
                </dl>
            </x-ui.card>
        @endif
    @else
        @php
            $summary = $this->summary;
            $timezone = $this->restaurant->orderSettings()->timezone;
        @endphp

        <div class="flex flex-col gap-4" data-cash-open>
            <p class="text-[13px] text-ink/55">Aberto às {{ $this->session->opened_at->copy()->setTimezone($timezone)->format('H:i') }} · troco inicial {{ Money::brl($this->session->opening_float) }}</p>

            <div class="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.card class="sm:col-span-2" data-expected>
                    <p class="text-[12px] font-semibold text-ink/50">💵 Dinheiro que deve estar na gaveta</p>
                    <p class="mt-1 text-[30px] font-extrabold leading-none text-ink" data-expected-value>{{ Money::brl($summary['expected_cash']) }}</p>
                    <p class="mt-1.5 text-[12px] text-ink/50">Troco {{ Money::brl($summary['opening_float']) }} + dinheiro {{ Money::brl($summary['sales']['dinheiro']) }}@if ((float) $summary['top_ups'] > 0) + reforços {{ Money::brl($summary['top_ups']) }}@endif @if ((float) $summary['withdrawals'] + (float) $summary['expenses_cash'] > 0) − saídas {{ Money::brl((float) $summary['withdrawals'] + (float) $summary['expenses_cash']) }}@endif</p>
                </x-ui.card>
                <x-ui.stat-card label="Vendas" :value="Money::brl($summary['sales_total'])" :hint="$summary['sales_count'].' '.($summary['sales_count'] === 1 ? 'venda' : 'vendas')" data-sales />
                <x-ui.stat-card label="⚡ Pix" :value="Money::brl($summary['sales']['pix'])" data-pix />
                <x-ui.stat-card label="💳 Cartão" :value="Money::brl($summary['sales']['cartao'])" data-card />
                <x-ui.stat-card label="📒 Fiado" :value="Money::brl($summary['fiado_sold'])" hint="vendido no fiado" data-fiado />
            </div>

            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4" data-cash-actions>
                @if (Route::has('panel.pdv'))
                    <a href="{{ route('panel.pdv') }}" wire:navigate class="flex flex-col items-center gap-1 rounded-[14px] bg-white p-3.5 text-center text-[13px] font-bold text-ink shadow-[0_1px_3px_rgba(0,0,0,0.06)] hover:bg-ink/[0.03]"><span class="text-[20px]">🛒</span>Venda no balcão</a>
                @endif
                <button type="button" wire:click="startMovement('despesa')" data-movement="despesa" class="flex flex-col items-center gap-1 rounded-[14px] bg-white p-3.5 text-center text-[13px] font-bold text-ink shadow-[0_1px_3px_rgba(0,0,0,0.06)] hover:bg-ink/[0.03]"><span class="text-[20px]">🧾</span>Despesa</button>
                <button type="button" wire:click="startMovement('retirada')" data-movement="retirada" class="flex flex-col items-center gap-1 rounded-[14px] bg-white p-3.5 text-center text-[13px] font-bold text-ink shadow-[0_1px_3px_rgba(0,0,0,0.06)] hover:bg-ink/[0.03]"><span class="text-[20px]">📤</span>Retirada<small class="font-medium text-ink/50">sangria, pagar motoboy</small></button>
                <button type="button" wire:click="startMovement('reforco')" data-movement="reforco" class="flex flex-col items-center gap-1 rounded-[14px] bg-white p-3.5 text-center text-[13px] font-bold text-ink shadow-[0_1px_3px_rgba(0,0,0,0.06)] hover:bg-ink/[0.03]"><span class="text-[20px]">📥</span>Reforço de troco</button>
            </div>

            <x-ui.card title="Movimentos do caixa" :padding="false">
                @if ($this->movements->isEmpty())
                    <p class="px-5 pb-5 text-[13.5px] text-ink/50" data-no-movements>Nenhum movimento ainda. Os pedidos finalizados aparecem aqui sozinhos.</p>
                @else
                    <ul class="px-5 pb-3" data-movements>
                        @foreach ($this->movements as $movement)
                            @php
                                $slug = $movement->type->slug;
                                $label = match ($slug) {
                                    'venda' => ($movement->order?->code ?? 'Venda').' · '.$movement->paymentMethod?->name,
                                    'recebimento_fiado' => 'Fiado recebido · '.$movement->paymentMethod?->name,
                                    'estorno' => 'Estorno '.($movement->order?->code ?? '').' · '.$movement->paymentMethod?->name,
                                    'despesa' => 'Despesa · '.$movement->category.($movement->description ? ' — '.$movement->description : ''),
                                    'retirada' => 'Retirada'.($movement->description ? ' — '.$movement->description : ''),
                                    'reforco' => 'Reforço de troco'.($movement->description ? ' — '.$movement->description : ''),
                                    default => $movement->type->name,
                                };
                                $in = $this->sign($slug) === '+';
                            @endphp
                            <li wire:key="movement-{{ $movement->id }}" class="flex items-center justify-between gap-3 border-b border-ink/[0.06] py-2.5 text-[13.5px] last:border-b-0" data-movement-row="{{ $slug }}">
                                <span class="min-w-0">
                                    <span class="text-ink/45">{{ $movement->occurred_at->copy()->setTimezone($timezone)->format('H:i') }}</span>
                                    <span class="ml-2 font-semibold text-ink">{{ $label }}</span>
                                </span>
                                <span class="shrink-0 font-bold {{ $in ? 'text-emerald-700' : 'text-danger' }}">{{ $this->sign($slug) }}{{ Money::brl($movement->amount) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>
    @endif

    <x-ui.modal wire:model="showMovement" :title="['despesa' => 'Despesa', 'retirada' => 'Retirada do caixa', 'reforco' => 'Reforço de troco'][$movementType]" data-movement-modal>
        <p class="text-[13px] text-ink/60">
            {{ ['despesa' => 'Compra ou conta paga pela loja.', 'retirada' => 'Dinheiro tirado da gaveta (ex.: pagar motoboy, depósito no banco).', 'reforco' => 'Dinheiro colocado na gaveta para ter troco.'][$movementType] }}
        </p>
        <form wire:submit="saveMovement" class="flex flex-col gap-3.5">
            <x-ui.field label="Valor" for="movementAmount" error="movementAmount">
                <x-ui.input id="movementAmount" wire:model="movementAmount" placeholder="R$ 0,00" inputmode="decimal" />
            </x-ui.field>

            @if ($movementType === 'despesa')
                <x-ui.field label="Pago com" for="movementMethod" error="movementMethod" hint="{{ $movementMethod === 'dinheiro' ? 'Sai do dinheiro da gaveta.' : 'Não mexe no dinheiro da gaveta.' }}">
                    <x-ui.select id="movementMethod" wire:model.live="movementMethod">
                        <option value="dinheiro">💵 Dinheiro</option>
                        <option value="pix">⚡ Pix</option>
                        <option value="cartao">💳 Cartão</option>
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="Categoria" for="movementCategory" error="category">
                    <x-ui.select id="movementCategory" wire:model="movementCategory">
                        @foreach (\App\Actions\Cash\RecordMovement::EXPENSE_CATEGORIES as $category)
                            <option value="{{ $category }}">{{ $category }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <p class="text-[12px] text-ink/50">O pagamento dos motoboys entra sozinho pelo valor de cada entrega — não lance aqui.</p>
            @endif

            <x-ui.field label="Descrição (opcional)" for="movementDescription" error="description">
                <x-ui.input id="movementDescription" wire:model="movementDescription" maxlength="255" />
            </x-ui.field>
            @error('cash') <p class="text-[12.5px] text-danger" role="alert">{{ $message }}</p> @enderror

            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="saveMovement" data-save-movement>Lançar</x-ui.button>
        </form>
    </x-ui.modal>

    <x-ui.modal wire:model="showClose" title="Fechar caixa" max-width="max-w-[440px]" data-close-modal>
        @if ($this->session)
            @php
                $closing = $this->summary;
            @endphp
            <dl class="flex flex-col gap-2 text-[13.5px]">
                <div class="flex justify-between"><dt class="text-ink/60">Vendas no período</dt><dd class="font-bold">{{ Money::brl($closing['sales_total']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-ink/60">⚡ Pix</dt><dd>{{ Money::brl($closing['sales']['pix']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-ink/60">💳 Cartão</dt><dd>{{ Money::brl($closing['sales']['cartao']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-ink/60">💵 Dinheiro</dt><dd>{{ Money::brl($closing['sales']['dinheiro']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-ink/60">📒 Vendido no fiado</dt><dd>{{ Money::brl($closing['fiado_sold']) }}</dd></div>
                <div class="flex justify-between border-t border-ink/10 pt-2"><dt class="font-semibold text-ink">Dinheiro esperado na gaveta</dt><dd class="font-extrabold text-ink" data-close-expected>{{ Money::brl($closing['expected_cash']) }}</dd></div>
            </dl>
        @endif
        <form wire:submit="closeCash" class="flex flex-col gap-3.5">
            <x-ui.field label="Quanto tem na gaveta? (conte o dinheiro)" for="countedCash" error="counted_cash">
                <x-ui.input id="countedCash" wire:model="countedCash" placeholder="R$ 0,00" inputmode="decimal" />
            </x-ui.field>
            <x-ui.field label="Observação (opcional)" for="closeNotes" error="notes">
                <x-ui.input id="closeNotes" wire:model="closeNotes" maxlength="255" />
            </x-ui.field>
            @error('cash') <p class="text-[12.5px] text-danger" role="alert">{{ $message }}</p> @enderror
            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="closeCash" data-confirm-close>Fechar caixa</x-ui.button>
        </form>
    </x-ui.modal>
</div>
