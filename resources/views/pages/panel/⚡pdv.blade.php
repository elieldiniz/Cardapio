<?php

use App\Actions\Orders\CreatePdvSale;
use App\Exceptions\OrderRejectedException;
use App\Models\Category;
use App\Models\Dish;
use App\Services\Orders\OrderPricer;
use App\Services\Orders\SelectionDescriber;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * PDV Balcão (RF-13, UI-01): monta o pedido de quem compra na loja e cobra na hora, no mesmo
 * caixa do delivery. A página é fina: o preço sai do OrderPricer, a venda do CreatePdvSale.
 */
new #[Layout('layouts::panel')] #[Title('PDV Balcão')] class extends Component
{
    public string $search = '';

    public string $category = 'all';

    /**
     * Lines of the sale: key, dish_id, qty, notes, variant_id, options (option id => quantity).
     *
     * @var array<int, array<string, mixed>>
     */
    public array $cart = [];

    // Choices of a product (variation, complements, note, quantity)
    public bool $showChoose = false;

    public ?int $choosingId = null;

    public ?string $editingKey = null;

    public float $draftQty = 1;

    public ?int $draftVariant = null;

    public ?int $draftSize = null;

    /** @var array<int, int> */
    public array $draftFlavors = [];

    /** @var array<int|string, int> */
    public array $draftOptions = [];

    public string $draftNotes = '';

    // The sale
    public string $customerName = '';

    public string $discount = '';

    public string $orderNotes = '';

    public bool $sendToKitchen = false;

    // Payment
    public bool $showPay = false;

    /** pix | dinheiro | cartao | fiado | dividir */
    public string $payMode = 'dinheiro';

    public string $received = '';

    public string $fiadoName = '';

    public string $fiadoPhone = '';

    /** @var array<string, string> */
    public array $split = ['pix' => '', 'dinheiro' => '', 'cartao' => ''];

    #[Computed]
    public function restaurant()
    {
        return auth()->user()->restaurant;
    }

    #[Computed]
    public function openCash()
    {
        return $this->restaurant->openCashSession();
    }

    #[Computed]
    public function categories()
    {
        return $this->restaurant->categories()->visible()->orderBy('display_order')->get();
    }

    /**
     * Everything the counter can sell right now: active products of visible categories.
     */
    #[Computed]
    public function dishes()
    {
        $term = mb_strtolower(trim($this->search));

        return $this->restaurant->dishes()
            ->with(['variants', 'optionGroups.options', 'category', 'pizzaSizes', 'pizzaFlavors.prices'])
            ->visible()
            ->whereHas('status', fn ($status) => $status->where('slug', 'ativo'))
            ->when($this->category !== 'all', fn ($query) => $query->where('dishes.category_id', (int) $this->category))
            ->when($term !== '', fn ($query) => $query->whereRaw('LOWER(dishes.name) LIKE ?', ["%{$term}%"]))
            ->orderBy('dishes.display_order')
            ->orderBy('dishes.id')
            ->get();
    }

    /**
     * The products in the cart, loaded once with their choices and keyed by id.
     */
    #[Computed]
    public function cartDishes()
    {
        return $this->restaurant->dishes()
            ->whereIn('id', array_column($this->cart, 'dish_id'))
            ->with(['variants', 'optionGroups.options', 'pizzaSizes', 'pizzaFlavors'])
            ->get()
            ->keyBy('id');
    }

    /**
     * The cart priced by the server; null while it is empty. A product that stopped being
     * sellable shows as a problem instead of a price (RF-05).
     *
     * @return array{subtotal: string, discount: string, total: string, lines: array<int, array<string, mixed>>, issue: ?string}|null
     */
    #[Computed]
    public function pricing(): ?array
    {
        if ($this->cart === []) {
            return null;
        }

        try {
            $priced = app(OrderPricer::class)->price($this->restaurant, $this->items(), ['fulfillment' => 'balcao']);
        } catch (OrderRejectedException $exception) {
            return ['subtotal' => '0.00', 'discount' => '0.00', 'total' => '0.00', 'lines' => [], 'issue' => $exception->getMessage()];
        }

        $discount = filled($this->discount) ? Money::parse($this->discount) : '0.00';
        $valid = $discount !== null && (float) $discount <= (float) $priced->subtotal;
        $applied = $valid ? $discount : '0.00';

        return [
            'subtotal' => $priced->subtotal,
            'discount' => $applied,
            'total' => number_format((float) $priced->subtotal - (float) $applied, 2, '.', ''),
            'lines' => $priced->lines,
            'issue' => $valid ? null : 'Desconto inválido: não pode passar do valor da venda.',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function items(): array
    {
        return array_map(fn (array $line) => [
            'dish_id' => $line['dish_id'],
            'quantity' => $line['qty'],
            'notes' => $line['notes'],
            'variant_id' => $line['variant_id'],
            'size_id' => $line['size_id'] ?? null,
            'flavor_ids' => $line['flavor_ids'] ?? [],
            'options' => collect($line['options'])->map(fn ($quantity, $id) => ['id' => (int) $id, 'quantity' => $quantity])->values()->all(),
        ], $this->cart);
    }

    // ---- Products ----

    public function pick(int $dishId): void
    {
        $dish = $this->restaurant->dishes()->with(['variants', 'optionGroups'])->findOrFail($dishId);

        if (! $dish->isPizza() && $dish->variants->isEmpty() && $dish->optionGroups->where('is_active', true)->isEmpty()) {
            $this->addLine($dish->id, $dish->isWeighed() ? 0.5 : 1, null, [], '');

            return;
        }

        $this->resetErrorBag();
        $this->choosingId = $dish->id;
        $this->editingKey = null;
        $this->draftQty = 1;
        $this->draftVariant = $dish->variants->count() === 1 ? $dish->variants->first()->id : null;
        $this->draftOptions = [];
        $this->draftNotes = '';
        $this->draftSize = null;
        $this->draftFlavors = [];
        $this->showChoose = true;
    }

    public function editLine(string $key): void
    {
        $line = collect($this->cart)->firstWhere('key', $key);
        abort_if($line === null, 404);

        $this->resetErrorBag();
        $this->choosingId = $line['dish_id'];
        $this->editingKey = $key;
        $this->draftQty = $line['qty'];
        $this->draftVariant = $line['variant_id'];
        $this->draftOptions = $line['options'];
        $this->draftNotes = (string) $line['notes'];
        $this->draftSize = $line['size_id'] ?? null;
        $this->draftFlavors = $line['flavor_ids'] ?? [];
        $this->showChoose = true;
    }

    public function toggleOption(int $groupId, int $optionId): void
    {
        $group = $this->choosingDish()->optionGroups->firstWhere('id', $groupId);
        abort_if($group === null, 404);

        $selected = ($this->draftOptions[$optionId] ?? 0) > 0;

        if ($group->mode === 'one') {
            foreach ($group->options as $option) {
                unset($this->draftOptions[$option->id]);
            }

            if (! $selected || $group->is_required) {
                $this->draftOptions[$optionId] = 1;
            }

            return;
        }

        $count = $group->options->sum(fn ($option) => $this->draftOptions[$option->id] ?? 0);

        if ($selected) {
            unset($this->draftOptions[$optionId]);
        } elseif ($count < $group->max_choices) {
            $this->draftOptions[$optionId] = 1;
        }
    }

    public function stepOption(int $groupId, int $optionId, int $delta): void
    {
        $group = $this->choosingDish()->optionGroups->firstWhere('id', $groupId);
        abort_if($group === null, 404);

        $current = $this->draftOptions[$optionId] ?? 0;
        $count = $group->options->sum(fn ($option) => $this->draftOptions[$option->id] ?? 0);
        $next = $current + $delta;

        if ($next < 0 || ($delta > 0 && $count >= $group->max_choices)) {
            return;
        }

        $next === 0 ? $this->draftOptions = array_diff_key($this->draftOptions, [$optionId => true]) : $this->draftOptions[$optionId] = $next;
    }

    public function pickSize(int $sizeId): void
    {
        $size = $this->choosingDish()->pizzaSizes->firstWhere('id', $sizeId);
        abort_if($size === null, 404);

        $this->draftSize = $size->id;
        // Flavors that do not exist in the new size, or do not fit it, are dropped.
        $available = $this->choosingDish()->pizzaFlavors->filter(fn ($flavor) => $flavor->prices->contains('pizza_size_id', $size->id))->pluck('id')->all();
        $this->draftFlavors = array_slice(array_values(array_intersect($this->draftFlavors, $available)), 0, $size->max_flavors);
    }

    public function toggleFlavor(int $flavorId): void
    {
        $size = $this->choosingDish()->pizzaSizes->firstWhere('id', $this->draftSize);

        if ($size === null) {
            return;
        }

        if (in_array($flavorId, $this->draftFlavors, true)) {
            $this->draftFlavors = array_values(array_diff($this->draftFlavors, [$flavorId]));
        } elseif (count($this->draftFlavors) < $size->max_flavors) {
            $this->draftFlavors[] = $flavorId;
        } elseif ($size->max_flavors === 1) {
            $this->draftFlavors = [$flavorId];
        }
    }

    public function stepDraftQty(int $delta): void
    {
        $step = $this->choosingDish()->isWeighed() ? 0.5 : 1;

        $this->draftQty = max($step, min($this->choosingDish()->isWeighed() ? 50 : 99, $this->draftQty + $delta * $step));
    }

    public function confirmChoose(): void
    {
        $dish = $this->choosingDish();

        $issue = $this->checkLine($dish->id, $this->draftQty, $this->draftVariant, $this->draftOptions, $this->draftNotes, $this->draftSize, $this->draftFlavors);

        if ($issue !== null) {
            $this->addError('choose', $issue);

            return;
        }

        if ($this->editingKey !== null) {
            $this->cart = array_map(fn (array $line) => $line['key'] === $this->editingKey
                ? array_merge($line, ['qty' => $this->draftQty, 'variant_id' => $this->draftVariant, 'size_id' => $this->draftSize, 'flavor_ids' => $this->draftFlavors, 'options' => $this->draftOptions, 'notes' => trim($this->draftNotes)])
                : $line, $this->cart);
        } else {
            $this->addLine($dish->id, $this->draftQty, $this->draftVariant, $this->draftOptions, $this->draftNotes, $this->draftSize, $this->draftFlavors);
        }

        $this->showChoose = false;
        $this->choosingId = null;
        unset($this->pricing, $this->cartDishes);
    }

    public function choosingDish(): Dish
    {
        return $this->restaurant->dishes()->with(['variants', 'optionGroups.options'])->findOrFail((int) $this->choosingId);
    }

    /**
     * Asks the pricer whether a line is valid, so a problem shows where it was made.
     */
    private function checkLine(int $dishId, float $qty, ?int $variantId, array $options, string $notes, ?int $sizeId = null, array $flavorIds = []): ?string
    {
        try {
            app(OrderPricer::class)->price($this->restaurant, [[
                'dish_id' => $dishId, 'quantity' => $qty, 'notes' => $notes, 'variant_id' => $variantId, 'size_id' => $sizeId, 'flavor_ids' => $flavorIds,
                'options' => collect($options)->map(fn ($quantity, $id) => ['id' => (int) $id, 'quantity' => $quantity])->values()->all(),
            ]], ['fulfillment' => 'balcao']);
        } catch (OrderRejectedException $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    private function addLine(int $dishId, float $qty, ?int $variantId, array $options, string $notes, ?int $sizeId = null, array $flavorIds = []): void
    {
        sort($flavorIds);
        $options = array_filter($options, fn ($quantity) => $quantity > 0);
        ksort($options);
        $notes = trim($notes);

        foreach ($this->cart as $index => $line) {
            $same = $line['dish_id'] === $dishId && $line['variant_id'] === $variantId && ($line['size_id'] ?? null) === $sizeId && ($line['flavor_ids'] ?? []) == $flavorIds && $line['notes'] === $notes && $line['options'] == $options;

            if ($same) {
                $this->cart[$index]['qty'] = min(99, $line['qty'] + $qty);
                unset($this->pricing, $this->cartDishes);

                return;
            }
        }

        $this->cart[] = ['key' => bin2hex(random_bytes(4)), 'dish_id' => $dishId, 'qty' => $qty, 'notes' => $notes, 'variant_id' => $variantId, 'size_id' => $sizeId, 'flavor_ids' => $flavorIds, 'options' => $options];
        unset($this->pricing, $this->cartDishes);
    }

    public function stepLine(string $key, int $delta): void
    {
        $this->cart = collect($this->cart)
            ->map(fn (array $line) => $line['key'] === $key ? array_merge($line, ['qty' => max(0, min(99, $line['qty'] + $delta))]) : $line)
            ->reject(fn (array $line) => $line['qty'] <= 0)
            ->values()
            ->all();

        unset($this->pricing, $this->cartDishes);
    }

    public function removeLine(string $key): void
    {
        $this->cart = collect($this->cart)->reject(fn (array $line) => $line['key'] === $key)->values()->all();
        unset($this->pricing, $this->cartDishes);
    }

    public function clear(): void
    {
        $this->reset('cart', 'customerName', 'discount', 'orderNotes', 'sendToKitchen');
        unset($this->pricing, $this->cartDishes);
    }

    public function updatedDiscount(): void
    {
        unset($this->pricing, $this->cartDishes);
    }

    // ---- Charge ----

    public function charge(): void
    {
        if ($this->openCash === null) {
            $this->addError('cash', 'Abra o caixa para vender no balcão.');

            return;
        }

        $pricing = $this->pricing;

        if ($pricing === null || $pricing['issue'] !== null) {
            $this->addError('cart', $pricing['issue'] ?? 'Adicione produtos à venda.');

            return;
        }

        $this->resetErrorBag();
        $this->payMode = 'dinheiro';
        $this->received = '';
        $this->fiadoName = $this->customerName;
        $this->fiadoPhone = '';
        $this->split = ['pix' => '', 'dinheiro' => '', 'cartao' => ''];
        $this->showPay = true;
    }

    public function finishSale(CreatePdvSale $createPdvSale): void
    {
        $pricing = $this->pricing;

        if ($pricing === null) {
            return;
        }

        $total = Money::input($pricing['total']);

        $payments = match ($this->payMode) {
            'dividir' => collect($this->split)->filter(fn ($amount) => filled($amount))->map(fn ($amount, $method) => ['method' => $method, 'amount' => $amount])->values()->all(),
            'dinheiro' => [['method' => 'dinheiro', 'amount' => $total, 'received' => $this->received]],
            'fiado' => [['method' => 'fiado', 'amount' => $total, 'customer_name' => $this->fiadoName, 'customer_phone' => $this->fiadoPhone]],
            default => [['method' => $this->payMode, 'amount' => $total]],
        };

        try {
            $order = $createPdvSale->handle($this->restaurant, $this->items(), $payments, [
                'discount' => $this->discount,
                'customer_name' => $this->customerName,
                'notes' => $this->orderNotes,
                'send_to_kitchen' => $this->sendToKitchen,
            ], auth()->user());
        } catch (OrderRejectedException $exception) {
            $this->addError('payments', $exception->getMessage());

            return;
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return;
        }

        $kitchen = $this->sendToKitchen;

        $this->showPay = false;
        $this->clear();
        unset($this->openCash);

        $this->dispatch('toast', message: $kitchen ? "Venda {$order->code} paga e enviada para a cozinha." : "Venda {$order->code} concluída.");
        $this->dispatch('pdv-sale-done', code: $order->code, orderId: $order->id, kitchen: $kitchen);
    }

    public function hasChoices(array $line): bool
    {
        $dish = $this->cartDishes->get($line['dish_id']);

        return $dish !== null && ($dish->isPizza() || $dish->variants->isNotEmpty() || $dish->optionGroups->where('is_active', true)->isNotEmpty());
    }

    public function describe(array $line): string
    {
        $dish = $this->cartDishes->get($line['dish_id']);

        if ($dish === null) {
            return '';
        }

        $pizzaSize = $dish->pizzaSizes->firstWhere('id', $line['size_id'] ?? null);

        $selections = [
            'pizza' => $pizzaSize ? [
                'size' => ['name' => $pizzaSize->name, 'slices' => $pizzaSize->slices],
                'flavors' => $dish->pizzaFlavors->whereIn('id', $line['flavor_ids'] ?? [])->map(fn ($flavor) => ['name' => $flavor->name])->values()->all(),
            ] : null,
            'variant' => ($variant = $dish->variants->firstWhere('id', $line['variant_id'])) ? ['name' => $variant->name] : null,
            'complements' => $dish->optionGroups->flatMap(fn ($group) => $group->options
                ->filter(fn ($option) => ($line['options'][$option->id] ?? 0) > 0)
                ->map(fn ($option) => ['group' => $group->name, 'name' => $option->name, 'price' => $option->price, 'quantity' => $line['options'][$option->id]]))->values()->all(),
        ];

        return implode(' · ', SelectionDescriber::lines($selections));
    }
};
?>

<div class="flex flex-col gap-[18px]" id="pdv">
    <x-ui.page-header title="PDV Balcão" subtitle="Monte o pedido de quem compra na loja e receba na hora." />

    @unless ($this->openCash)
        <div class="rounded-xl bg-amber-50 px-4 py-3 text-[13.5px] font-medium text-amber-900" data-pdv-closed>
            O caixa está fechado. Abra o caixa para vender no balcão.
            @if (Route::has('panel.cash')) <a href="{{ route('panel.cash') }}" wire:navigate class="font-bold underline">Abrir caixa</a> @endif
        </div>
    @endunless

    <div class="grid gap-[18px] lg:grid-cols-[minmax(0,1fr)_380px]">
        <div class="flex flex-col gap-3.5">
            <div class="flex flex-wrap gap-2">
                <x-ui.input type="search" wire:model.live.debounce.250ms="search" placeholder="Buscar produto" aria-label="Buscar produto" class="!w-auto min-w-[200px] flex-1" />
            </div>
            <div class="flex gap-1.5 overflow-x-auto" role="tablist" aria-label="Categorias">
                <button type="button" wire:click="$set('category', 'all')" data-category="all" aria-selected="{{ $category === 'all' ? 'true' : 'false' }}" class="shrink-0 rounded-full px-3.5 py-1.5 text-[12.5px] font-bold {{ $category === 'all' ? 'bg-ink text-white' : 'bg-ink/[0.06] text-ink/60' }}">Todos</button>
                @foreach ($this->categories as $item)
                    <button type="button" wire:click="$set('category', '{{ $item->id }}')" data-category="{{ $item->id }}" aria-selected="{{ $category === (string) $item->id ? 'true' : 'false' }}" class="shrink-0 rounded-full px-3.5 py-1.5 text-[12.5px] font-bold {{ $category === (string) $item->id ? 'bg-ink text-white' : 'bg-ink/[0.06] text-ink/60' }}">{{ $item->icon }} {{ $item->name }}</button>
                @endforeach
            </div>

            @if ($this->dishes->isEmpty())
                <x-ui.card><x-ui.empty-state icon="dishes" title="Nenhum produto encontrado" description="Cadastre produtos ou mude a busca." /></x-ui.card>
            @else
                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3" data-pdv-products>
                    @foreach ($this->dishes as $dish)
                        <button type="button" wire:click="pick({{ $dish->id }})" wire:key="dish-{{ $dish->id }}" data-pick="{{ $dish->name }}" class="flex min-h-[84px] flex-col justify-between gap-1 rounded-[14px] bg-white p-3.5 text-left shadow-[0_1px_3px_rgba(0,0,0,0.06)] transition hover:ring-2 hover:ring-accent/40">
                            <span class="text-[14px] font-bold leading-tight text-ink">{{ $dish->name }}</span>
                            <span class="text-[12.5px] font-semibold text-ink/55">{{ $dish->variants->isNotEmpty() ? 'a partir de '.Money::brl($dish->variants->min('price')) : Money::brl($dish->price) }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <aside class="flex flex-col gap-3 lg:sticky lg:top-6 lg:self-start" data-pdv-cart>
            <x-ui.card title="Pedido">
                @if ($cart === [])
                    <p class="text-[13.5px] text-ink/50" data-cart-empty>🛒 Toque nos produtos para montar o pedido.</p>
                @else
                    <ul class="flex flex-col gap-2.5" data-cart-lines>
                        @foreach ($cart as $line)
                            @php
                                $priced = collect($this->pricing['lines'] ?? [])->firstWhere('display_order', $loop->iteration);
                                $dishName = $this->cartDishes->get($line['dish_id'])?->name;
                            @endphp
                            <li wire:key="line-{{ $line['key'] }}" class="flex flex-col gap-1 border-b border-ink/[0.06] pb-2.5 last:border-b-0 last:pb-0" data-cart-line>
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-[13.5px] font-bold text-ink">{{ $dishName }}</p>
                                        @if ($details = $this->describe($line)) <p class="text-[12px] text-ink/55">{{ $details }}</p> @endif
                                        @if ($line['notes']) <p class="text-[12px] italic text-ink/55">Obs.: {{ $line['notes'] }}</p> @endif
                                    </div>
                                    <span class="shrink-0 text-[13.5px] font-bold text-ink">{{ $priced ? Money::brl($priced['total']) : '' }}</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex items-center gap-2">
                                        <button type="button" wire:click="stepLine('{{ $line['key'] }}', -1)" class="size-7 rounded-full border border-ink/20 text-[16px] leading-none" aria-label="Diminuir {{ $dishName }}">−</button>
                                        <b class="min-w-[22px] text-center text-[13.5px]" data-line-qty>{{ (int) $line['qty'] }}</b>
                                        <button type="button" wire:click="stepLine('{{ $line['key'] }}', 1)" class="size-7 rounded-full border border-ink/20 text-[16px] leading-none" aria-label="Aumentar {{ $dishName }}">+</button>
                                    </span>
                                    @if ($this->hasChoices($line))
                                        <button type="button" wire:click="editLine('{{ $line['key'] }}')" class="text-[12px] font-bold text-accent">Editar</button>
                                    @endif
                                    <button type="button" wire:click="removeLine('{{ $line['key'] }}')" class="text-[12px] font-bold text-danger">Remover</button>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-3 flex flex-col gap-2.5 border-t border-ink/10 pt-3">
                        <x-ui.field label="Nome do cliente (opcional)" for="customerName"><x-ui.input id="customerName" wire:model="customerName" maxlength="120" /></x-ui.field>
                        <x-ui.field label="Desconto (R$)" for="discount" error="discount"><x-ui.input id="discount" wire:model.live.debounce.300ms="discount" placeholder="0,00" inputmode="decimal" /></x-ui.field>
                        <x-ui.field label="Observação" for="orderNotes"><x-ui.input id="orderNotes" wire:model="orderNotes" maxlength="300" /></x-ui.field>
                        <label class="flex cursor-pointer items-start gap-2.5 text-[13px] font-semibold text-ink">
                            <input type="checkbox" wire:model="sendToKitchen" class="mt-0.5 size-4 accent-[var(--accent)]" data-kitchen>
                            <span>Enviar para a cozinha <span class="block text-[12px] font-normal text-ink/55">Entra em “Em preparo” na tela de Pedidos.</span></span>
                        </label>
                    </div>

                    @if ($pricing = $this->pricing)
                        <dl class="mt-3 flex flex-col gap-1.5 text-[13.5px]" data-pdv-totals>
                            <div class="flex justify-between"><dt class="text-ink/60">Produtos</dt><dd>{{ Money::brl($pricing['subtotal']) }}</dd></div>
                            @if ((float) $pricing['discount'] > 0) <div class="flex justify-between"><dt class="text-ink/60">Desconto</dt><dd>−{{ Money::brl($pricing['discount']) }}</dd></div> @endif
                            <div class="flex justify-between border-t border-ink/10 pt-2 text-[16px] font-extrabold"><dt>Total</dt><dd data-pdv-total>{{ Money::brl($pricing['total']) }}</dd></div>
                        </dl>
                        @if ($pricing['issue']) <p class="mt-2 text-[12.5px] text-danger" role="alert">{{ $pricing['issue'] }}</p> @endif
                    @endif

                    @error('cash') <p class="mt-2 text-[12.5px] text-danger" role="alert">{{ $message }}</p> @enderror
                    @error('cart') <p class="mt-2 text-[12.5px] text-danger" role="alert">{{ $message }}</p> @enderror

                    <div class="mt-3 flex gap-2">
                        <x-ui.button variant="secondary" wire:click="clear" wire:confirm="Limpar o pedido?">Limpar</x-ui.button>
                        <x-ui.button class="flex-1" wire:click="charge" data-charge>Cobrar {{ $pricing ? Money::brl($pricing['total']) : '' }}</x-ui.button>
                    </div>
                @endif
            </x-ui.card>
        </aside>
    </div>

    {{-- Choices of a product --}}
    <x-ui.modal wire:model="showChoose" :title="$choosingId ? $this->choosingDish()->name : 'Produto'" max-width="max-w-[460px]" data-pdv-choose>
        @if ($choosingId)
            @php
                $choosing = $this->choosingDish();
            @endphp

            @if ($choosing->isPizza())
                @php
                    $chosenSize = $choosing->pizzaSizes->firstWhere('id', $draftSize);
                @endphp
                <div class="flex flex-col gap-1.5" data-pizza-sizes>
                    <p class="text-[13px] font-bold text-ink">Tamanho <span class="rounded-full bg-accent px-2 py-0.5 text-[10px] uppercase text-white">Obrigatório</span></p>
                    @foreach ($choosing->pizzaSizes->where('is_available', true) as $size)
                        <button type="button" wire:click="pickSize({{ $size->id }})" data-pizza-size="{{ $size->name }}" aria-pressed="{{ $draftSize === $size->id ? 'true' : 'false' }}" class="flex justify-between rounded-xl border px-3.5 py-2.5 text-[13.5px] font-semibold {{ $draftSize === $size->id ? 'border-accent bg-accent/10' : 'border-ink/15' }}">
                            <span>{{ $size->name }} <small class="text-ink/50">{{ $size->slices ? $size->slices.' fatias · ' : '' }}{{ $size->max_flavors === 1 ? '1 sabor' : 'até '.$size->max_flavors.' sabores' }}</small></span>
                        </button>
                    @endforeach
                </div>
                <div class="flex flex-col gap-1.5" data-pizza-flavors>
                    <p class="text-[13px] font-bold text-ink">Sabores <small class="font-medium text-ink/50">{{ $chosenSize ? 'Escolha até '.$chosenSize->max_flavors.' · '.count($draftFlavors).'/'.$chosenSize->max_flavors : 'Escolha o tamanho primeiro' }}</small></p>
                    @if ($chosenSize)
                        @foreach ($choosing->pizzaFlavors->where('is_available', true) as $flavor)
                            @php
                                $flavorPrice = $flavor->prices->firstWhere('pizza_size_id', $chosenSize->id);
                            @endphp
                            @if ($flavorPrice)
                                <button type="button" wire:click="toggleFlavor({{ $flavor->id }})" data-pizza-flavor="{{ $flavor->name }}" aria-pressed="{{ in_array($flavor->id, $draftFlavors, true) ? 'true' : 'false' }}" class="flex justify-between rounded-xl border px-3.5 py-2.5 text-[13.5px] font-semibold {{ in_array($flavor->id, $draftFlavors, true) ? 'border-accent bg-accent/10' : 'border-ink/15' }}">
                                    <span>{{ $flavor->name }} @if ($flavor->ingredients) <small class="block font-normal text-ink/50">{{ $flavor->ingredients }}</small> @endif</span><span class="text-ink/55">{{ Money::brl($flavorPrice->price) }}</span>
                                </button>
                            @endif
                        @endforeach
                    @endif
                </div>
            @endif

            @if ($choosing->variants->isNotEmpty())
                <div class="flex flex-col gap-1.5" data-variants>
                    <p class="text-[13px] font-bold text-ink">Escolha uma opção <span class="rounded-full bg-accent px-2 py-0.5 text-[10px] uppercase text-white">Obrigatório</span></p>
                    @foreach ($choosing->variants as $variant)
                        <button type="button" wire:click="$set('draftVariant', {{ $variant->id }})" data-variant="{{ $variant->name }}" aria-pressed="{{ $draftVariant === $variant->id ? 'true' : 'false' }}" class="flex justify-between rounded-xl border px-3.5 py-2.5 text-[13.5px] font-semibold {{ $draftVariant === $variant->id ? 'border-accent bg-accent/10' : 'border-ink/15' }}">
                            <span>{{ $variant->name }}</span><span class="text-ink/55">{{ Money::brl($variant->price) }}</span>
                        </button>
                    @endforeach
                </div>
            @endif

            @foreach ($choosing->optionGroups->where('is_active', true) as $group)
                <div class="flex flex-col gap-1.5" data-group="{{ $group->name }}">
                    <p class="text-[13px] font-bold text-ink">{{ $group->name }} <small class="font-medium text-ink/50">{{ $group->mode === 'one' ? 'Escolha 1' : 'Escolha até '.$group->max_choices }}</small> <span class="rounded-full px-2 py-0.5 text-[10px] uppercase {{ $group->is_required ? 'bg-accent text-white' : 'bg-ink/10 text-ink/55' }}">{{ $group->is_required ? 'Obrigatório' : 'Opcional' }}</span></p>
                    @foreach ($group->options->where('is_available', true) as $option)
                        @if ($group->mode === 'many' && $group->allow_repeat)
                            <div class="flex items-center justify-between rounded-xl border border-ink/15 px-3.5 py-2 text-[13.5px] font-semibold" data-option="{{ $option->name }}">
                                <span>{{ $option->name }} @if ($option->price) <small class="text-ink/50">+ {{ Money::brl($option->price) }}</small> @endif</span>
                                <span class="inline-flex items-center gap-2">
                                    <button type="button" wire:click="stepOption({{ $group->id }}, {{ $option->id }}, -1)" class="size-7 rounded-full border border-ink/20" aria-label="Menos {{ $option->name }}">−</button>
                                    <b class="min-w-[18px] text-center">{{ $draftOptions[$option->id] ?? 0 }}</b>
                                    <button type="button" wire:click="stepOption({{ $group->id }}, {{ $option->id }}, 1)" class="size-7 rounded-full border border-ink/20" aria-label="Mais {{ $option->name }}">+</button>
                                </span>
                            </div>
                        @else
                            <button type="button" wire:click="toggleOption({{ $group->id }}, {{ $option->id }})" data-option="{{ $option->name }}" aria-pressed="{{ ($draftOptions[$option->id] ?? 0) > 0 ? 'true' : 'false' }}" class="flex justify-between rounded-xl border px-3.5 py-2.5 text-[13.5px] font-semibold {{ ($draftOptions[$option->id] ?? 0) > 0 ? 'border-accent bg-accent/10' : 'border-ink/15' }}">
                                <span>{{ $option->name }}</span>@if ($option->price) <span class="text-ink/55">+ {{ Money::brl($option->price) }}</span> @endif
                            </button>
                        @endif
                    @endforeach
                </div>
            @endforeach

            <x-ui.field label="Observação" for="draftNotes"><x-ui.input id="draftNotes" wire:model="draftNotes" maxlength="140" placeholder="Ex.: sem cebola" /></x-ui.field>

            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-2">
                    <button type="button" wire:click="stepDraftQty(-1)" class="size-9 rounded-full border border-ink/20 text-[18px]" aria-label="Diminuir">−</button>
                    <b class="min-w-[26px] text-center" data-draft-qty>{{ (int) $draftQty }}</b>
                    <button type="button" wire:click="stepDraftQty(1)" class="size-9 rounded-full border border-ink/20 text-[18px]" aria-label="Aumentar">+</button>
                </span>
                <x-ui.button class="flex-1" wire:click="confirmChoose" data-confirm-choose>{{ $editingKey ? 'Salvar' : 'Adicionar' }}</x-ui.button>
            </div>
            @error('choose') <p class="text-[12.5px] text-danger" role="alert" data-choose-error>{{ $message }}</p> @enderror
        @endif
    </x-ui.modal>

    {{-- Charge --}}
    <x-ui.modal wire:model="showPay" title="Receber" max-width="max-w-[460px]" data-pdv-pay>
        @php
            $due = $this->pricing['total'] ?? '0.00';
            $receivedValue = $payMode === 'dinheiro' && filled($received) ? Money::parse($received) : null;
            $change = $receivedValue !== null && (float) $receivedValue >= (float) $due ? Money::brl((float) $receivedValue - (float) $due) : null;
            $splitSum = collect($split)->sum(fn ($amount) => (float) (Money::parse((string) $amount) ?? 0));
            $remaining = (float) $due - $splitSum;
        @endphp
        <p class="text-[13.5px] text-ink/65">Total <strong class="text-[18px] text-ink">{{ Money::brl($due) }}</strong></p>

        <div class="grid grid-cols-5 gap-1.5">
            @foreach (['pix' => ['⚡', 'Pix'], 'dinheiro' => ['💵', 'Dinheiro'], 'cartao' => ['💳', 'Cartão'], 'fiado' => ['📒', 'Fiado'], 'dividir' => ['🔀', 'Dividir']] as $mode => [$icon, $label])
                <button type="button" wire:click="$set('payMode', '{{ $mode }}')" data-pay-mode="{{ $mode }}" aria-pressed="{{ $payMode === $mode ? 'true' : 'false' }}" class="flex flex-col items-center gap-0.5 rounded-xl border px-1 py-2 text-[11.5px] font-bold {{ $payMode === $mode ? 'border-accent bg-accent/10 text-ink' : 'border-ink/15 text-ink/60' }}"><span class="text-[17px]">{{ $icon }}</span>{{ $label }}</button>
            @endforeach
        </div>

        @if ($payMode === 'dinheiro')
            <x-ui.field label="Valor recebido (para calcular o troco)" for="received"><x-ui.input id="received" wire:model.live.debounce.250ms="received" placeholder="Ex.: 50,00" inputmode="decimal" /></x-ui.field>
            @if ($change) <p class="text-[14px] font-extrabold text-emerald-700" data-pdv-change>Troco: {{ $change }}</p> @endif
        @elseif ($payMode === 'fiado')
            <x-ui.field label="Quem ficou devendo" for="fiadoName" error="customer_name"><x-ui.input id="fiadoName" wire:model="fiadoName" /></x-ui.field>
            <x-ui.field label="WhatsApp (para cobrar)" for="fiadoPhone"><x-ui.input id="fiadoPhone" wire:model="fiadoPhone" inputmode="tel" /></x-ui.field>
        @elseif ($payMode === 'dividir')
            <div class="grid gap-2">
                @foreach (['pix' => 'Pix', 'dinheiro' => 'Dinheiro', 'cartao' => 'Cartão'] as $method => $label)
                    <label class="flex items-center gap-3 text-[13px] font-semibold text-ink/70"><span class="w-20">{{ $label }}</span><x-ui.input wire:model.live.debounce.250ms="split.{{ $method }}" placeholder="0,00" inputmode="decimal" aria-label="Valor em {{ $label }}" /></label>
                @endforeach
            </div>
            <p class="text-[12.5px] font-bold {{ abs($remaining) < 0.005 ? 'text-emerald-700' : 'text-amber-800' }}" data-pdv-remaining>{{ abs($remaining) < 0.005 ? 'Soma certa.' : 'Falta '.Money::brl($remaining).' para fechar o total.' }}</p>
        @endif
        @error('payments') <p class="text-[12.5px] text-danger" role="alert">{{ $message }}</p> @enderror
        @error('cash') <p class="text-[12.5px] text-danger" role="alert">{{ $message }}</p> @enderror
        @error('discount') <p class="text-[12.5px] text-danger" role="alert">{{ $message }}</p> @enderror

        <x-slot:footer>
            <x-ui.button class="flex-1" wire:click="finishSale" wire:loading.attr="disabled" wire:target="finishSale" data-finish-sale>Finalizar venda · {{ Money::brl($due) }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
