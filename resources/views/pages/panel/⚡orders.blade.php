<?php

use App\Actions\Couriers\AssignCourier;
use App\Actions\Orders\AcceptOrder;
use App\Actions\Orders\AdjustOrderValues;
use App\Actions\Orders\AdvanceOrder;
use App\Actions\Orders\CancelFinalizedOrder;
use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\RefuseOrder;
use App\Actions\Orders\UpdateOrderPayment;
use App\Actions\Settings\SaveOrderSettings;
use App\Models\Order;
use App\Support\BusinessHours;
use App\Support\Money;
use App\Support\StorePeriod;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * Gestor de pedidos (RF-08, RF-08b, UI-01, UI-03): quatro colunas, do pedido novo ao
 * finalizado. A página é fina: as mudanças de etapa ficam nas Actions e cada pedido é
 * sempre buscado pela loja do dono (RNF-01).
 */
new #[Layout('layouts::panel')] #[Title('Pedidos')] class extends Component
{
    public const COLUMNS = [
        'novo' => ['label' => 'Novos', 'icon' => '🆕', 'empty' => 'Nenhum pedido esperando.'],
        'em_preparo' => ['label' => 'Em preparo', 'icon' => '🍳', 'empty' => 'Nada no fogo agora.'],
        'em_rota' => ['label' => 'Pronto / Em rota', 'icon' => '🛵', 'empty' => 'Nenhum pedido pronto.'],
        'finalizado' => ['label' => 'Finalizados', 'icon' => '✔️', 'empty' => 'Os pedidos concluídos aparecem aqui.'],
    ];

    #[Url(as: 'periodo', except: 'hoje')]
    public string $period = 'hoje';

    #[Url(as: 'busca', except: '')]
    public string $search = '';

    /**
     * The fee the dono types before accepting an order whose delivery is "a combinar".
     *
     * @var array<int, string>
     */
    public array $fees = [];

    // Finalize / edit payment modal (RF-09)
    public bool $showPay = false;

    public ?int $payingId = null;

    public bool $payingEdit = false;

    /** pix | dinheiro | cartao | fiado | dividir */
    public string $payMode = 'pix';

    public string $received = '';

    public string $fiadoName = '';

    public string $fiadoPhone = '';

    /** @var array<string, string> */
    public array $split = ['pix' => '', 'dinheiro' => '', 'cartao' => ''];

    // Adjust the value after weighing (RF-15)
    public bool $showAdjust = false;

    public ?int $adjustingId = null;

    public string $adjustProducts = '';

    public string $adjustFee = '';

    // Cancel a finalized order (RF-08c)
    public bool $showCancel = false;

    public ?int $cancellingId = null;

    public string $cancelReason = '';

    #[Computed]
    public function restaurant()
    {
        return auth()->user()->restaurant;
    }

    /**
     * Orders of the chosen period, newest first, with what the cards need (RF-08b).
     */
    #[Computed]
    public function orders()
    {
        $key = in_array($this->period, ['hoje', 'ontem_hoje', '7dias'], true) ? $this->period : 'hoje';
        $range = StorePeriod::range($this->restaurant, $key);
        $term = trim($this->search);

        return $this->restaurant->orders()
            ->with(['items', 'status', 'paymentMethod', 'customer', 'payments.method', 'courier'])
            ->where('created_at', '>=', $range['from'])
            ->where('created_at', '<', $range['to'])
            ->when($term !== '', fn ($query) => $query->where(function ($inner) use ($term) {
                $inner->where('code', 'like', "%{$term}%")
                    ->orWhere('customer_name', 'like', "%{$term}%")
                    ->orWhereHas('items', fn ($items) => $items->where('name', 'like', "%{$term}%"));
            }))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * The four columns; refused and cancelled orders stay visible, marked, in "Finalizados".
     *
     * @return array<string, \Illuminate\Support\Collection>
     */
    #[Computed]
    public function columns(): array
    {
        $columns = [];

        foreach (array_keys(self::COLUMNS) as $slug) {
            $columns[$slug] = $this->orders->filter(fn (Order $order) => $slug === 'finalizado'
                ? in_array($order->status->slug, ['finalizado', 'recusado', 'cancelado'], true)
                : $order->status->slug === $slug)->values();
        }

        return $columns;
    }

    /**
     * Value of the period: orders that were not refused or cancelled.
     */
    #[Computed]
    public function periodTotal(): string
    {
        return Money::brl($this->orders->reject(fn (Order $order) => in_array($order->status->slug, ['recusado', 'cancelado'], true))->sum(fn (Order $order) => (float) $order->total));
    }

    /**
     * Orders waiting for the dono's first look, whatever the chosen period (RF-08, UI-03).
     */
    #[Computed]
    public function newCount(): int
    {
        return $this->restaurant->orders()->whereHas('status', fn ($status) => $status->where('slug', 'novo'))->count();
    }

    public function render()
    {
        // The menu badge and the alert sound listen to this on every poll.
        $this->dispatch('orders-count', count: $this->newCount);

        // Four columns need the whole width.
        return $this->view()->layoutData(['wide' => true]);
    }

    #[Computed]
    public function storeOpen(): bool
    {
        return (bool) $this->restaurant->orderSettings()->accepts_orders;
    }

    #[Computed]
    public function state(): array
    {
        return BusinessHours::state($this->restaurant->fresh());
    }

    public function toggleStore(SaveOrderSettings $saveOrderSettings): void
    {
        $saveOrderSettings->handle($this->restaurant, ['accepts_orders' => ! $this->storeOpen]);

        $this->restaurant->unsetRelation('orderSetting');
        unset($this->storeOpen, $this->state);

        $this->dispatch('toast', message: $this->storeOpen ? 'Loja aberta para pedidos.' : 'Loja fechada para pedidos.');
    }

    public function accept(int $orderId, AcceptOrder $acceptOrder): void
    {
        $order = $this->find($orderId);

        $this->guarded(fn () => $acceptOrder->handle($order, $this->fees[$orderId] ?? null, auth()->user()), 'Pedido aceito.');
        unset($this->fees[$orderId]);
    }

    /** Active couriers offered on the delivery cards (RF-22). */
    #[Computed]
    public function couriers()
    {
        return $this->restaurant->hasFeature('couriers')
            ? $this->restaurant->couriers()->where('is_active', true)->orderBy('name')->get()
            : collect();
    }

    public function assignCourier(int $orderId, string $courierId, AssignCourier $assignCourier): void
    {
        $order = $this->find($orderId);
        $courier = $courierId === '' ? null : $this->restaurant->couriers()->findOrFail((int) $courierId);

        $this->guarded(fn () => $assignCourier->handle($order, $courier), $courier ? "Pedido com {$courier->firstName()}." : 'Motoboy removido do pedido.');
    }

    public function refuse(int $orderId, RefuseOrder $refuseOrder): void
    {
        $this->guarded(fn () => $refuseOrder->handle($this->find($orderId), null, auth()->user()), 'Pedido recusado.');
    }

    public function advance(int $orderId, string $to, AdvanceOrder $advanceOrder): void
    {
        // Finishing an order always asks how it was paid (RF-09).
        if ($to === 'finalizado') {
            $this->openFinalize($orderId);

            return;
        }

        $this->guarded(fn () => $advanceOrder->handle($this->find($orderId), $to, auth()->user()));
    }

    /**
     * Drag and drop between columns: the column the card was dropped on is the target status.
     */
    public function sortOrder(int|string $orderId, int $position, string $group = '', ?AdvanceOrder $advanceOrder = null): void
    {
        if (! array_key_exists($group, self::COLUMNS)) {
            return;
        }

        $order = $this->find((int) $orderId);

        if ($order->status->slug === $group) {
            return;
        }

        if ($group === 'finalizado' && $order->status->slug === 'em_rota') {
            $this->openFinalize($order->id);

            return;
        }

        $this->guarded(fn () => ($advanceOrder ?? app(AdvanceOrder::class))->handle($order, $group, auth()->user()));
    }

    #[Computed]
    public function openCash()
    {
        return $this->restaurant->openCashSession();
    }

    #[Computed]
    public function payingOrder(): ?Order
    {
        return $this->payingId ? $this->restaurant->orders()->with(['items', 'status', 'paymentMethod', 'payments.method'])->find($this->payingId) : null;
    }

    public function openFinalize(int $orderId): void
    {
        $this->openPayment($orderId, false);
    }

    public function openEditPayment(int $orderId): void
    {
        $this->openPayment($orderId, true);
    }

    private function openPayment(int $orderId, bool $edit): void
    {
        $order = $this->find($orderId);

        $this->resetErrorBag();
        $this->payingId = $order->id;
        $this->payingEdit = $edit;
        $this->payMode = match ($order->paymentMethod?->slug) {
            'dinheiro' => 'dinheiro',
            'debito', 'credito' => 'cartao',
            default => 'pix',
        };
        $this->received = $order->change_for ? Money::input($order->change_for) : '';
        $this->fiadoName = (string) $order->customer_name;
        $this->fiadoPhone = (string) $order->customer_phone;
        $this->split = ['pix' => '', 'dinheiro' => '', 'cartao' => ''];
        unset($this->payingOrder, $this->openCash);
        $this->showPay = true;
    }

    /**
     * Finalizes (or corrects the payment of) the order chosen in the modal (RF-09).
     */
    public function confirmPayment(FinalizeOrder $finalizeOrder, UpdateOrderPayment $updateOrderPayment, bool $withoutPayment = false): void
    {
        $order = $this->find((int) $this->payingId);

        try {
            if ($this->payingEdit) {
                $recorded = $updateOrderPayment->handle($order, $this->payments($order), auth()->user());
                $message = 'Pagamento atualizado.';
            } else {
                ['cash_recorded' => $recorded] = $finalizeOrder->handle($order, $withoutPayment ? [] : $this->payments($order), auth()->user(), $withoutPayment);
                $message = $withoutPayment ? 'Pedido finalizado sem registrar pagamento.' : 'Pedido finalizado.';
            }
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return;
        }

        if (! $withoutPayment && ! $recorded && $this->payMode !== 'fiado') {
            $message .= ' O caixa está fechado: o pagamento ficou no pedido, mas não entrou no caixa.';
        }

        $this->showPay = false;
        $this->payingId = null;
        unset($this->orders, $this->columns, $this->periodTotal, $this->newCount, $this->payingOrder);

        $this->dispatch('toast', message: $message);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function payments(Order $order): array
    {
        $total = Money::input($order->total);

        return match ($this->payMode) {
            'dividir' => collect($this->split)
                ->filter(fn ($amount) => filled($amount))
                ->map(fn ($amount, $method) => ['method' => $method, 'amount' => $amount])
                ->values()
                ->all(),
            'dinheiro' => [['method' => 'dinheiro', 'amount' => $total, 'received' => $this->received]],
            'fiado' => [['method' => 'fiado', 'amount' => $total, 'customer_name' => $this->fiadoName, 'customer_phone' => $this->fiadoPhone]],
            default => [['method' => $this->payMode, 'amount' => $total]],
        };
    }

    public function openAdjust(int $orderId): void
    {
        $order = $this->find($orderId);

        $this->resetErrorBag();
        $this->adjustingId = $order->id;
        $this->adjustProducts = Money::input($order->subtotal);
        $this->adjustFee = $order->isDelivery() && $order->delivery_fee !== null ? Money::input($order->delivery_fee) : '';
        $this->showAdjust = true;
    }

    public function confirmAdjust(AdjustOrderValues $adjustOrderValues): void
    {
        $order = $this->find((int) $this->adjustingId);

        try {
            $adjustOrderValues->handle($order, $this->adjustProducts, $this->adjustFee, auth()->user());
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return;
        }

        $this->showAdjust = false;
        $this->adjustingId = null;
        unset($this->orders, $this->columns, $this->periodTotal);

        $this->dispatch('toast', message: 'Valor do pedido ajustado.');
    }

    public function openCancel(int $orderId): void
    {
        $order = $this->find($orderId);

        $this->resetErrorBag();
        $this->cancellingId = $order->id;
        $this->cancelReason = '';
        $this->showCancel = true;
    }

    public function confirmCancel(CancelFinalizedOrder $cancelFinalizedOrder): void
    {
        $order = $this->find((int) $this->cancellingId);

        try {
            $cancelFinalizedOrder->handle($order, $this->cancelReason, auth()->user());
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return;
        }

        $this->showCancel = false;
        $this->cancellingId = null;
        unset($this->orders, $this->columns, $this->periodTotal, $this->newCount);

        $this->dispatch('toast', message: 'Pedido cancelado e estornado.');
    }

    private function find(int $orderId): Order
    {
        $order = $this->restaurant->orders()->with('status')->findOrFail($orderId);

        Gate::authorize('update', $order);

        return $order;
    }

    /**
     * Runs a change, refreshes the board and reports a refusal as a toast, never a crash.
     */
    private function guarded(callable $change, ?string $success = null): void
    {
        try {
            $change();
        } catch (ValidationException $exception) {
            $this->dispatch('toast', message: collect($exception->errors())->flatten()->first(), type: 'error');
            unset($this->orders, $this->columns, $this->periodTotal, $this->newCount);

            return;
        }

        unset($this->orders, $this->columns, $this->periodTotal, $this->newCount);

        if ($success) {
            $this->dispatch('toast', message: $success);
        }
    }
};
?>

<div class="flex flex-col gap-[18px]" wire:poll.8s x-data="{ column: 'novo', desktop: false, init() { try { this.desktop = localStorage.getItem('degusta-wa-desktop') === '1'; } catch {} }, toggleDesktop() { this.desktop = !this.desktop; try { localStorage.setItem('degusta-wa-desktop', this.desktop ? '1' : '0'); } catch {} }, openWhatsApp(event) { const link = event.target.closest('a[data-wa]'); if (this.desktop && link) { event.preventDefault(); window.location.href = link.href.replace('https://api.whatsapp.com/send', 'whatsapp://send'); } } }" x-on:click="openWhatsApp($event)">
    <x-ui.page-header title="Pedidos" subtitle="Gestor de pedidos — atualiza sozinho quando chega um novo.">
        <div class="flex flex-wrap items-center gap-2" x-data="orderAlerts" x-on:orders-count.window="seen($event.detail.count)">
            <button type="button" x-on:click="toggle()" :aria-pressed="enabled" data-sound-toggle class="inline-flex items-center gap-2 rounded-[9px] border border-ink/15 px-3.5 py-2 text-[13px] font-bold text-ink/70" x-show="supported">
                <span x-text="enabled ? '🔔 Som ligado' : '🔕 Ativar som'"></span>
            </button>
            <button
                type="button"
                wire:click="toggleStore"
                data-store-toggle
                aria-pressed="{{ $this->storeOpen ? 'true' : 'false' }}"
                class="inline-flex items-center gap-2 rounded-[9px] border px-3.5 py-2 text-[13px] font-bold {{ $this->storeOpen ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : 'border-amber-300 bg-amber-50 text-amber-900' }}"
            >
                <span class="size-2 rounded-full {{ $this->storeOpen ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                {{ $this->storeOpen ? 'Loja aberta' : 'Loja fechada' }}
            </button>
        </div>
    </x-ui.page-header>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-1.5" role="tablist" aria-label="Período">
            @foreach (['hoje' => 'Hoje', 'ontem_hoje' => 'Ontem e hoje', '7dias' => '7 dias'] as $key => $label)
                <button type="button" role="tab" wire:click="$set('period', '{{ $key }}')" data-period="{{ $key }}" aria-selected="{{ $period === $key ? 'true' : 'false' }}" class="rounded-full px-3.5 py-1.5 text-[12.5px] font-bold transition {{ $period === $key ? 'bg-ink text-white' : 'bg-ink/[0.06] text-ink/60 hover:bg-ink/10' }}">{{ $label }}</button>
            @endforeach
        </div>
        <div class="flex items-center gap-3">
            <x-ui.input type="search" wire:model.live.debounce.300ms="search" placeholder="Código, cliente ou produto" aria-label="Buscar pedido" class="!w-[220px]" />
            <span class="text-[13px] text-ink/60">Valor do período: <strong class="text-ink" data-period-total>{{ $this->periodTotal }}</strong></span>
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-[12px] text-ink/45">💡 Arraste os cartões entre as colunas ou use os botões.</p>
        <label class="flex cursor-pointer items-center gap-1.5 text-[12px] font-semibold text-ink/55">
            <input type="checkbox" x-bind:checked="desktop" x-on:change="toggleDesktop()" class="size-4 accent-[var(--accent)]" data-wa-desktop>
            Abrir no aplicativo WhatsApp deste computador
        </label>
    </div>

    {{-- Phones: one column at a time, picked on the tabs. Large screens: the four side by side. --}}
    <div class="flex gap-1.5 overflow-x-auto lg:hidden" role="tablist" aria-label="Etapas">
        @foreach (self::COLUMNS as $slug => $column)
            <button type="button" role="tab" x-on:click="column = '{{ $slug }}'" :aria-selected="column === '{{ $slug }}'" :class="column === '{{ $slug }}' ? 'bg-ink text-white' : 'bg-ink/[0.06] text-ink/60'" class="shrink-0 rounded-full px-3.5 py-1.5 text-[12.5px] font-bold">
                {{ $column['icon'] }} {{ $column['label'] }} <span data-count="{{ $slug }}">{{ $this->columns[$slug]->count() }}</span>
            </button>
        @endforeach
    </div>

    <div class="grid gap-3.5 lg:grid-cols-4" data-board>
        @foreach (self::COLUMNS as $slug => $column)
            <section class="min-w-0 flex-col gap-2.5" :class="column === '{{ $slug }}' ? 'flex' : 'hidden lg:flex'" data-column="{{ $slug }}" aria-label="{{ $column['label'] }}">
                <h2 class="hidden items-center gap-2 text-[13px] font-extrabold uppercase tracking-wide text-ink lg:flex">
                    <span class="size-2.5 rounded-full {{ $slug === 'finalizado' ? 'bg-ink/70' : ($slug === 'em_rota' ? 'bg-emerald-600' : 'bg-accent') }}"></span>
                    {{ $column['label'] }}
                    <span class="rounded-full bg-ink/[0.07] px-2 py-0.5 text-[11px]" data-count="{{ $slug }}">{{ $this->columns[$slug]->count() }}</span>
                </h2>

                <div wire:sort="sortOrder" wire:sort:group="orders" wire:sort:group-id="{{ $slug }}" class="flex min-h-[120px] flex-col gap-2.5 rounded-2xl bg-ink/[0.04] p-2" data-drop="{{ $slug }}">
                    @forelse ($this->columns[$slug] as $order)
                        <article wire:key="order-{{ $order->id }}" wire:sort:item="{{ $order->id }}" data-order="{{ $order->code }}">
                            @include('pages.panel.partials.order-card', ['order' => $order])
                        </article>
                    @empty
                        <p class="rounded-xl border border-dashed border-ink/15 px-3 py-4 text-center text-[12.5px] text-ink/45" wire:sort:ignore>{{ $column['empty'] }}</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>

    @php
        $paying = $this->payingOrder;
    @endphp
    <x-ui.modal wire:model="showPay" :title="$payingEdit ? 'Editar pagamento' : 'Finalizar pedido'" max-width="max-w-[460px]" data-pay-modal>
        @if ($paying)
            @php
                $total = (float) $paying->total;
                $totalLabel = \App\Support\Money::brl($paying->total);
                $receivedValue = $payMode === 'dinheiro' && filled($received) ? \App\Support\Money::parse($received) : null;
                $changeLabel = $receivedValue !== null && (float) $receivedValue >= $total ? \App\Support\Money::brl((float) $receivedValue - $total) : null;
                $splitSum = collect($split)->sum(fn ($amount) => (float) (\App\Support\Money::parse((string) $amount) ?? 0));
                $remaining = $total - $splitSum;
            @endphp

            <p class="text-[13.5px] text-ink/65">Pedido <strong class="text-ink">{{ $paying->code }}</strong> · {{ $paying->customer_name }} · Total <strong class="text-ink" data-pay-total>{{ $totalLabel }}</strong></p>

            @unless ($this->openCash)
                <p class="rounded-lg bg-amber-50 px-3 py-2 text-[12.5px] font-medium text-amber-900" data-cash-closed-notice>O caixa está fechado. O pagamento fica registrado no pedido, mas não entra no caixa. <a href="{{ \Illuminate\Support\Facades\Route::has('panel.cash') ? route('panel.cash') : '#' }}" class="font-bold underline" wire:navigate>Abrir caixa</a></p>
            @endunless

            <p class="text-[12.5px] font-bold text-ink/60">Como o cliente pagou?</p>
            <div class="grid grid-cols-5 gap-1.5" data-pay-modes>
                @foreach (['pix' => ['⚡', 'Pix'], 'dinheiro' => ['💵', 'Dinheiro'], 'cartao' => ['💳', 'Cartão'], 'fiado' => ['📒', 'Fiado'], 'dividir' => ['🔀', 'Dividir']] as $mode => [$icon, $label])
                    <button type="button" wire:click="$set('payMode', '{{ $mode }}')" data-pay-mode="{{ $mode }}" aria-pressed="{{ $payMode === $mode ? 'true' : 'false' }}" class="flex flex-col items-center gap-0.5 rounded-xl border px-1 py-2 text-[11.5px] font-bold {{ $payMode === $mode ? 'border-accent bg-accent/10 text-ink' : 'border-ink/15 text-ink/60' }}">
                        <span class="text-[17px]">{{ $icon }}</span>{{ $label }}
                    </button>
                @endforeach
            </div>

            @if ($payMode === 'dinheiro')
                <x-ui.field label="Cliente pagou com (opcional)" for="received" error="payments">
                    <x-ui.input id="received" wire:model.live.debounce.250ms="received" placeholder="Ex.: 50,00" inputmode="decimal" />
                </x-ui.field>
                @if ($changeLabel) <p class="text-[13px] font-bold text-emerald-700" data-change>Troco: {{ $changeLabel }}</p> @endif
            @elseif ($payMode === 'fiado')
                <x-ui.field label="Quem ficou devendo" for="fiadoName" error="customer_name"><x-ui.input id="fiadoName" wire:model="fiadoName" /></x-ui.field>
                <x-ui.field label="WhatsApp (para cobrar)" for="fiadoPhone"><x-ui.input id="fiadoPhone" wire:model="fiadoPhone" inputmode="tel" /></x-ui.field>
            @elseif ($payMode === 'dividir')
                <div class="grid gap-2" data-split>
                    @foreach (['pix' => 'Pix', 'dinheiro' => 'Dinheiro', 'cartao' => 'Cartão'] as $method => $label)
                        <label class="flex items-center gap-3 text-[13px] font-semibold text-ink/70">
                            <span class="w-20">{{ $label }}</span>
                            <x-ui.input wire:model.live.debounce.250ms="split.{{ $method }}" placeholder="0,00" inputmode="decimal" aria-label="Valor em {{ $label }}" />
                        </label>
                    @endforeach
                </div>
                <p class="text-[12.5px] font-bold {{ abs($remaining) < 0.005 ? 'text-emerald-700' : 'text-amber-800' }}" data-split-remaining>{{ abs($remaining) < 0.005 ? 'Soma certa.' : 'Falta '.\App\Support\Money::brl($remaining).' para fechar o total.' }}</p>
                @error('payments') <p class="text-[12.5px] text-danger" role="alert">{{ $message }}</p> @enderror
            @endif

            @if ($payMode !== 'dinheiro' && $payMode !== 'dividir') @error('payments') <p class="text-[12.5px] text-danger" role="alert">{{ $message }}</p> @enderror @endif
            @error('status') <p class="text-[12.5px] text-danger" role="alert">{{ $message }}</p> @enderror

            <x-slot:footer>
                <x-ui.button class="flex-1" wire:click="confirmPayment" wire:loading.attr="disabled" wire:target="confirmPayment" data-pay-confirm>{{ $payingEdit ? 'Salvar pagamento' : 'Finalizar' }} · {{ $totalLabel }}</x-ui.button>
            </x-slot:footer>
            @unless ($payingEdit)
                <button type="button" wire:click="confirmPayment(true)" wire:confirm="Finalizar sem registrar o pagamento?" class="text-center text-[12.5px] font-bold text-ink/55 underline" data-pay-skip>Finalizar sem registrar pagamento</button>
            @endunless
        @endif
    </x-ui.modal>

    <x-ui.modal wire:model="showAdjust" title="Ajustar valor" max-width="max-w-[420px]" data-adjust-modal>
        <p class="text-[13px] text-ink/60">Depois de pesar, lance o valor real. O cliente vê o novo total e o valor original fica guardado no pedido.</p>
        <x-ui.field label="Valor dos produtos" for="adjustProducts" error="products">
            <x-ui.input id="adjustProducts" wire:model="adjustProducts" inputmode="decimal" placeholder="R$ 0,00" />
        </x-ui.field>
        <x-ui.field label="Taxa de entrega" for="adjustFee" error="fee" hint="Cobrada do cliente. Vazio mantém a taxa atual.">
            <x-ui.input id="adjustFee" wire:model="adjustFee" inputmode="decimal" placeholder="R$ 0,00" />
        </x-ui.field>
        @error('status') <p class="text-[12.5px] text-danger" role="alert">{{ $message }}</p> @enderror
        <x-slot:footer>
            <x-ui.button class="flex-1" wire:click="confirmAdjust" wire:loading.attr="disabled" wire:target="confirmAdjust" data-adjust-confirm>Salvar valor</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal wire:model="showCancel" title="Cancelar pedido" max-width="max-w-[440px]" data-cancel-modal>
        <p class="text-[13.5px] leading-relaxed text-ink/65">O pedido fica como cancelado e o dinheiro recebido no caixa é estornado (a venda continua no histórico). Se houver fiado em aberto, ele é cancelado.</p>
        <x-ui.field label="Motivo do cancelamento" for="cancelReason" error="reason">
            <x-ui.textarea id="cancelReason" wire:model="cancelReason" rows="2" maxlength="255" placeholder="Ex.: cliente desistiu, pedido em duplicidade" />
        </x-ui.field>
        @error('cash') <p class="text-[12.5px] text-danger" role="alert">{{ $message }}</p> @enderror
        @error('status') <p class="text-[12.5px] text-danger" role="alert">{{ $message }}</p> @enderror
        <x-slot:footer>
            <x-ui.button variant="secondary" class="flex-1" wire:click="$set('showCancel', false)">Voltar</x-ui.button>
            <x-ui.button class="flex-1 !bg-danger" wire:click="confirmCancel" wire:loading.attr="disabled" wire:target="confirmCancel" data-cancel-confirm>Cancelar pedido</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>

@script
<script>
    // Sound alert for a new order (RF-08a, UI-03): only after the dono turns it on, because
    // browsers do not allow audio before a tap. The choice stays on this device.
    Alpine.data('orderAlerts', () => ({
        enabled: false,
        supported: Boolean(window.AudioContext || window.webkitAudioContext),
        last: null,
        context: null,
        init() {
            try {
                this.enabled = this.supported && localStorage.getItem('degusta-order-sound') === '1';
            } catch {}
        },
        toggle() {
            this.enabled = !this.enabled;

            try {
                localStorage.setItem('degusta-order-sound', this.enabled ? '1' : '0');
            } catch {}

            if (this.enabled) {
                this.beep();
            }
        },
        seen(count) {
            if (this.last !== null && count > this.last) {
                navigator.vibrate?.(200);

                if (this.enabled) {
                    this.beep();
                }
            }

            this.last = count;
        },
        beep() {
            const Context = window.AudioContext || window.webkitAudioContext;

            if (!Context) {
                return;
            }

            this.context ??= new Context();
            this.context.resume?.();

            [0, 0.18].forEach((offset, index) => {
                const oscillator = this.context.createOscillator();
                const gain = this.context.createGain();
                const start = this.context.currentTime + offset;

                oscillator.frequency.value = index === 0 ? 880 : 1175;
                gain.gain.setValueAtTime(0.0001, start);
                gain.gain.exponentialRampToValueAtTime(0.25, start + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.16);
                oscillator.connect(gain).connect(this.context.destination);
                oscillator.start(start);
                oscillator.stop(start + 0.18);
            });
        },
    }));
</script>
@endscript
