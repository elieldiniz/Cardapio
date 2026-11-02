<?php

use App\Actions\Menu\SaveDish;
use App\Actions\Menu\SavePizza;
use App\Models\Badge;
use App\Models\Dish;
use App\Support\Money;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts::panel')] class extends Component
{
    #[Locked]
    public ?int $dishId = null;

    public string $name = '';

    public ?int $category_id = null;

    public string $price = '';

    public string $short_description = '';

    public string $description = '';

    /** @var array<int, int> */
    public array $badge_ids = [];

    /** @var array<int, array{name: string, price: string}> */
    public array $variants = [];

    /** @var array<int, int|string> complement groups, in the order they are asked */
    public array $option_group_ids = [];

    /** common | pizza (RF-14) */
    public string $kind = 'common';

    /** unit | kg (RF-15) */
    public string $sale_type = 'unit';

    public string $promo_price = '';

    public string $promo_ends_on = '';

    public bool $is_featured = false;

    /** @var array<int, array{id: ?int, name: string, slices: string, max_flavors: int, is_available: bool}> */
    public array $pizzaSizes = [];

    /** @var array<int, array{id: ?int, name: string, section: string, ingredients: string, is_available: bool, prices: array<int, string>}> */
    public array $pizzaFlavors = [];

    public function mount(?Dish $dish = null): void
    {
        $restaurant = auth()->user()->restaurant;

        if ($dish?->exists) {
            abort_unless($dish->restaurant_id === $restaurant->id, 404);

            $this->dishId = $dish->id;
            $this->fill([
                'name' => $dish->name,
                'category_id' => $dish->category_id,
                'price' => Money::input($dish->price),
                'short_description' => (string) $dish->short_description,
                'description' => (string) $dish->description,
                'badge_ids' => $dish->badges()->pluck('badges.id')->all(),
                'option_group_ids' => $dish->optionGroups()->pluck('option_groups.id')->all(),
                'kind' => $dish->kind ?? 'common',
                'sale_type' => $dish->sale_type ?? 'unit',
                'promo_price' => $dish->promo_price === null ? '' : Money::input($dish->promo_price),
                'promo_ends_on' => $dish->promo_ends_at?->copy()->setTimezone($restaurant->orderSettings()->timezone)->toDateString() ?? '',
                'is_featured' => (bool) $dish->is_featured,
                'variants' => $dish->variants->map(fn ($variant) => [
                    'name' => $variant->name,
                    'price' => Money::input($variant->price),
                ])->all(),
            ]);

            $this->loadPizza($dish);
        } else {
            abort_unless($restaurant->canAddDish(), 403, 'Limite de produtos do plano atingido.');

            $this->category_id = $restaurant->categories()->orderBy('display_order')->value('id');
        }
    }

    public function render()
    {
        return $this->view()->title($this->dishId ? 'Editar produto' : 'Novo produto');
    }

    #[Computed]
    public function restaurant()
    {
        return auth()->user()->restaurant;
    }

    #[Computed]
    public function dish(): ?Dish
    {
        return $this->dishId ? $this->restaurant->dishes()->find($this->dishId) : null;
    }

    #[Computed]
    public function categories()
    {
        return $this->restaurant->categories()->orderBy('display_order')->get();
    }

    #[Computed]
    public function optionGroups()
    {
        return $this->restaurant->optionGroups()->where('is_active', true)->orderBy('display_order')->orderBy('id')->get();
    }

    #[Computed]
    public function badges()
    {
        return Badge::query()->orderBy('id')->get();
    }

    private function loadPizza(Dish $dish): void
    {
        if (! $dish->isPizza()) {
            return;
        }

        $sizes = $dish->pizzaSizes()->get();
        $this->pizzaSizes = $sizes->map(fn ($size) => [
            'id' => $size->id, 'name' => $size->name, 'slices' => (string) $size->slices, 'max_flavors' => $size->max_flavors, 'is_available' => $size->is_available,
        ])->all();

        $this->pizzaFlavors = $dish->pizzaFlavors()->with('prices')->get()->map(fn ($flavor) => [
            'id' => $flavor->id,
            'name' => $flavor->name,
            'section' => $flavor->section,
            'ingredients' => (string) $flavor->ingredients,
            'is_available' => $flavor->is_available,
            'prices' => $sizes->map(fn ($size) => ($price = $flavor->prices->firstWhere('pizza_size_id', $size->id)) ? Money::input($price->price) : '')->all(),
        ])->all();
    }

    public function updatedKind(string $value): void
    {
        if ($value === 'pizza' && $this->pizzaSizes === []) {
            $this->useDefaultSizes();
            $this->addPizzaFlavor();
        }
    }

    public function useDefaultSizes(): void
    {
        $this->pizzaSizes = [
            ['id' => null, 'name' => 'Broto', 'slices' => '4', 'max_flavors' => 1, 'is_available' => true],
            ['id' => null, 'name' => 'Média', 'slices' => '6', 'max_flavors' => 2, 'is_available' => true],
            ['id' => null, 'name' => 'Grande', 'slices' => '8', 'max_flavors' => 3, 'is_available' => true],
            ['id' => null, 'name' => 'Família', 'slices' => '12', 'max_flavors' => 4, 'is_available' => true],
        ];

        $this->pizzaFlavors = array_map(fn (array $flavor) => array_merge($flavor, ['prices' => array_pad($flavor['prices'], count($this->pizzaSizes), '')]), $this->pizzaFlavors);
    }

    public function addPizzaSize(): void
    {
        $this->pizzaSizes[] = ['id' => null, 'name' => '', 'slices' => '', 'max_flavors' => 1, 'is_available' => true];

        foreach ($this->pizzaFlavors as $index => $flavor) {
            $this->pizzaFlavors[$index]['prices'][] = '';
        }
    }

    public function removePizzaSize(int $index): void
    {
        unset($this->pizzaSizes[$index]);
        $this->pizzaSizes = array_values($this->pizzaSizes);

        foreach ($this->pizzaFlavors as $flavorIndex => $flavor) {
            unset($this->pizzaFlavors[$flavorIndex]['prices'][$index]);
            $this->pizzaFlavors[$flavorIndex]['prices'] = array_values($this->pizzaFlavors[$flavorIndex]['prices']);
        }
    }

    public function addPizzaFlavor(): void
    {
        $this->pizzaFlavors[] = [
            'id' => null, 'name' => '', 'section' => SavePizza::SECTIONS[0], 'ingredients' => '', 'is_available' => true,
            'prices' => array_fill(0, count($this->pizzaSizes), ''),
        ];
    }

    public function removePizzaFlavor(int $index): void
    {
        unset($this->pizzaFlavors[$index]);
        $this->pizzaFlavors = array_values($this->pizzaFlavors);
    }

    public function addVariant(): void
    {
        $this->variants[] = ['name' => '', 'price' => ''];
    }

    public function removeVariant(int $index): void
    {
        unset($this->variants[$index]);
        $this->variants = array_values($this->variants);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('restaurant_id', $this->restaurant->id)],
            'kind' => ['required', 'in:common,pizza'],
            'sale_type' => ['required', 'in:unit,kg'],
            'price' => [$this->kind === 'pizza' ? 'nullable' : 'required', 'string', $this->priceRule()],
            'promo_price' => ['nullable', 'string', $this->priceRule()],
            'promo_ends_on' => ['nullable', 'date'],
            'is_featured' => ['boolean'],
            'short_description' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'badge_ids' => ['array'],
            'badge_ids.*' => ['integer', Rule::exists('badges', 'id')],
            'option_group_ids' => ['array'],
            'option_group_ids.*' => ['integer', Rule::exists('option_groups', 'id')->where('restaurant_id', $this->restaurant->id)],
            'variants' => ['array'],
            'variants.*.name' => ['required', 'string', 'max:40'],
            'variants.*.price' => ['required', 'string', $this->priceRule()],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do produto.',
            'category_id.required' => 'Escolha uma categoria.',
            'price.required' => 'Informe o preço.',
            'variants.*.name.required' => 'Dê um nome para a variação.',
            'variants.*.price.required' => 'Informe o preço da variação.',
        ];
    }

    private function priceRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (filled($value) && Money::parse((string) $value) === null) {
                $fail('Preço inválido. Use o formato 32,90.');
            }
        };
    }

    public function save(SaveDish $saveDish)
    {
        $this->validate();

        if (! $this->dishId && ! $this->restaurant->canAddDish()) {
            $this->dispatch('toast', message: 'Limite de produtos do plano atingido.', type: 'error');

            return;
        }

        if ($this->kind !== 'pizza' && filled($this->promo_price) && (float) Money::parse($this->promo_price) >= (float) Money::parse($this->price)) {
            $this->addError('promo_price', 'O preço promocional precisa ser menor que o preço.');

            return null;
        }

        $pizza = $this->kind === 'pizza' ? [
            'sizes' => array_map(fn (array $size) => [...$size, 'name' => trim($size['name'])], $this->pizzaSizes),
            'flavors' => $this->pizzaFlavors,
        ] : null;

        $promo = $this->kind !== 'pizza' && filled($this->promo_price);

        try {
            $dish = $saveDish->handle($this->restaurant, [
                'category_id' => $this->category_id,
                'name' => trim($this->name),
                'price' => $this->kind === 'pizza' ? '0.00' : Money::parse($this->price),
                'kind' => $this->kind,
                'sale_type' => $this->kind === 'pizza' ? 'unit' : $this->sale_type,
                'promo_price' => $promo ? Money::parse($this->promo_price) : null,
                'promo_ends_at' => $promo && filled($this->promo_ends_on)
                    ? \Illuminate\Support\Carbon::parse($this->promo_ends_on, $this->restaurant->orderSettings()->timezone)->endOfDay()->utc()
                    : null,
                'is_featured' => $this->is_featured,
                'pizza' => $pizza,
                'short_description' => trim($this->short_description),
                'description' => trim($this->description),
                'badge_ids' => array_map('intval', $this->badge_ids),
                'option_group_ids' => array_map('intval', $this->option_group_ids),
                'variants' => array_map(fn (array $variant) => [
                    'name' => trim($variant['name']),
                    'price' => Money::parse($variant['price']),
                ], $this->variants),
            ], $this->dish);
        } catch (ValidationException $exception) {
            // The pizza editor reports errors by its own field names.
            $this->setErrorBag(collect($exception->errors())->mapWithKeys(fn ($messages, $key) => [preg_replace(['/^sizes/', '/^flavors/'], ['pizzaSizes', 'pizzaFlavors'], $key) => $messages])->all());

            return null;
        }

        session()->flash('toast', ['message' => 'Produto salvo.', 'type' => 'success']);

        return $this->redirectRoute('panel.dishes.edit', $dish, navigate: true);
    }
};
?>

<form wire:submit="save" class="flex flex-col gap-[18px]">
    <x-ui.page-header :title="$dishId ? 'Editar produto' : 'Novo produto'">
        <div class="flex gap-2">
            @if ($dishId)
                <x-ui.button :href="route('panel.dishes.video', $dishId)" wire:navigate>
                    <x-ui.icon name="media" class="size-4" />
                    Vídeo do produto
                </x-ui.button>
            @endif
            <x-ui.button variant="secondary" :href="route('panel.dishes')" wire:navigate>Voltar</x-ui.button>
        </div>
    </x-ui.page-header>

    <x-ui.card title="Dados do produto">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.field label="Nome" for="name" error="name" class="sm:col-span-2">
                <x-ui.input id="name" wire:model="name" required maxlength="120" />
            </x-ui.field>

            <x-ui.field label="Categoria" for="category_id" error="category_id">
                <x-ui.select id="category_id" wire:model="category_id" required>
                    <option value="">Escolha…</option>
                    @foreach ($this->categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>

            @if ($kind !== 'pizza')
                <x-ui.field label="{{ $sale_type === 'kg' ? 'Preço por kg' : 'Preço' }}" for="price" error="price">
                    <x-ui.input id="price" wire:model="price" placeholder="R$ 0,00" inputmode="decimal" required />
                </x-ui.field>
            @endif

            <x-ui.field label="Descrição curta" for="short_description" error="short_description" hint="Aparece sobre o vídeo no feed." class="sm:col-span-2">
                <x-ui.input id="short_description" wire:model="short_description" maxlength="160" />
            </x-ui.field>

            <x-ui.field label="Descrição completa" for="description" error="description" hint="Aparece no painel de detalhes do produto." class="sm:col-span-2">
                <x-ui.textarea id="description" wire:model="description" rows="4" maxlength="2000" />
            </x-ui.field>
        </div>
    </x-ui.card>


    <x-ui.card title="Tipo de produto">
        <div class="grid gap-2 sm:grid-cols-2" data-kind>
            <label class="flex cursor-pointer flex-col rounded-xl border p-3 text-[13px] has-[:checked]:border-accent has-[:checked]:bg-accent/5">
                <input type="radio" wire:model.live="kind" value="common" class="sr-only">
                <span class="font-bold text-ink">Produto comum</span>
                <span class="text-ink/55">Preço único, por unidade ou por kg</span>
            </label>
            <label class="flex cursor-pointer flex-col rounded-xl border p-3 text-[13px] has-[:checked]:border-accent has-[:checked]:bg-accent/5">
                <input type="radio" wire:model.live="kind" value="pizza" class="sr-only">
                <span class="font-bold text-ink">🍕 Pizza</span>
                <span class="text-ink/55">Tamanhos, sabores e meio a meio</span>
            </label>
        </div>

        @if ($kind === 'common')
            <div class="mt-3 grid gap-2 sm:grid-cols-2" data-sale-type>
                <label class="flex cursor-pointer flex-col rounded-xl border p-3 text-[13px] has-[:checked]:border-accent has-[:checked]:bg-accent/5">
                    <input type="radio" wire:model.live="sale_type" value="unit" class="sr-only">
                    <span class="font-bold text-ink">Vendido por unidade</span>
                    <span class="text-ink/55">De 1 em 1 unidade</span>
                </label>
                <label class="flex cursor-pointer flex-col rounded-xl border p-3 text-[13px] has-[:checked]:border-accent has-[:checked]:bg-accent/5">
                    <input type="radio" wire:model.live="sale_type" value="kg" class="sr-only">
                    <span class="font-bold text-ink">Vendido por kg</span>
                    <span class="text-ink/55">De 0,5 em 0,5 kg. Você ajusta o valor depois de pesar.</span>
                </label>
            </div>
        @endif
    </x-ui.card>

    @if ($kind === 'pizza')
        <x-ui.card title="🍕 Tamanhos" data-pizza-sizes>
            <x-slot:actions>
                <x-ui.button size="sm" variant="secondary" type="button" wire:click="useDefaultSizes">Usar tamanhos padrão</x-ui.button>
            </x-slot:actions>
            @error('pizzaSizes') <p class="mb-2 text-[12.5px] text-danger" role="alert">{{ $message }}</p> @enderror
            <p class="mb-3 text-[12.5px] text-ink/55">Quantas fatias e até quantos sabores cada tamanho aceita.</p>
            <div class="flex flex-col gap-2.5">
                @foreach ($pizzaSizes as $index => $size)
                    <div wire:key="psize-{{ $index }}" class="flex flex-wrap items-start gap-2">
                        <x-ui.field error="pizzaSizes.{{ $index }}.name" class="min-w-[130px] flex-1"><x-ui.input wire:model="pizzaSizes.{{ $index }}.name" placeholder="Nome (ex.: Grande)" aria-label="Nome do tamanho {{ $index + 1 }}" /></x-ui.field>
                        <x-ui.field error="pizzaSizes.{{ $index }}.slices" class="w-24"><x-ui.input wire:model="pizzaSizes.{{ $index }}.slices" placeholder="Fatias" inputmode="numeric" aria-label="Fatias do tamanho {{ $index + 1 }}" /></x-ui.field>
                        <x-ui.field error="pizzaSizes.{{ $index }}.max_flavors" class="w-32">
                            <x-ui.select wire:model="pizzaSizes.{{ $index }}.max_flavors" aria-label="Sabores do tamanho {{ $index + 1 }}">
                                @foreach (range(1, \App\Actions\Menu\SavePizza::MAX_FLAVORS_PER_SIZE) as $count)
                                    <option value="{{ $count }}">{{ $count === 1 ? '1 sabor' : "até {$count} sabores" }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                        <x-ui.button size="sm" variant="danger" type="button" wire:click="removePizzaSize({{ $index }})" class="mt-1">Remover</x-ui.button>
                    </div>
                @endforeach
            </div>
            <div class="mt-3"><x-ui.button size="sm" variant="secondary" type="button" wire:click="addPizzaSize">Adicionar tamanho</x-ui.button></div>
        </x-ui.card>

        <x-ui.card title="🍕 Sabores e preços" data-pizza-flavors>
            @error('pizzaFlavors') <p class="mb-2 text-[12.5px] text-danger" role="alert">{{ $message }}</p> @enderror
            <p class="mb-3 text-[12.5px] text-ink/55">Deixe o preço vazio se o sabor não existir naquele tamanho. Meio a meio: a pizza é cobrada pelo preço do sabor escolhido de acordo com a regra em Configurações.</p>
            <div class="flex flex-col gap-4">
                @foreach ($pizzaFlavors as $fIndex => $flavor)
                    <div wire:key="pflavor-{{ $fIndex }}" class="flex flex-col gap-2 rounded-xl border border-ink/10 p-3">
                        <div class="flex flex-wrap items-start gap-2">
                            <x-ui.field error="pizzaFlavors.{{ $fIndex }}.name" class="min-w-[150px] flex-1"><x-ui.input wire:model="pizzaFlavors.{{ $fIndex }}.name" placeholder="Sabor (ex.: Calabresa)" aria-label="Nome do sabor {{ $fIndex + 1 }}" /></x-ui.field>
                            <x-ui.field class="w-40">
                                <x-ui.select wire:model="pizzaFlavors.{{ $fIndex }}.section" aria-label="Seção do sabor {{ $fIndex + 1 }}">
                                    @foreach (\App\Actions\Menu\SavePizza::SECTIONS as $section) <option value="{{ $section }}">{{ $section }}</option> @endforeach
                                </x-ui.select>
                            </x-ui.field>
                            <label class="flex items-center gap-1.5 pt-2.5 text-[12px] font-semibold text-ink/60"><input type="checkbox" wire:model="pizzaFlavors.{{ $fIndex }}.is_available" class="size-4 accent-[var(--accent)]"> Disponível</label>
                            <x-ui.button size="sm" variant="danger" type="button" wire:click="removePizzaFlavor({{ $fIndex }})" class="mt-1">Remover</x-ui.button>
                        </div>
                        <x-ui.input wire:model="pizzaFlavors.{{ $fIndex }}.ingredients" placeholder="Ingredientes (ex.: calabresa, cebola e azeitona)" aria-label="Ingredientes do sabor {{ $fIndex + 1 }}" />
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach ($pizzaSizes as $sIndex => $size)
                                <label class="flex flex-col gap-1 text-[11.5px] font-semibold text-ink/55">{{ $size['name'] !== '' ? $size['name'] : 'Tamanho '.($sIndex + 1) }}
                                    <x-ui.input wire:model="pizzaFlavors.{{ $fIndex }}.prices.{{ $sIndex }}" placeholder="R$ —" inputmode="decimal" aria-label="Preço de {{ $flavor['name'] !== '' ? $flavor['name'] : 'sabor '.($fIndex + 1) }} no tamanho {{ $size['name'] }}" />
                                    @error("pizzaFlavors.{$fIndex}.prices.{$sIndex}") <span class="font-medium text-danger">{{ $message }}</span> @enderror
                                </label>
                            @endforeach
                        </div>
                        @error("pizzaFlavors.{$fIndex}.prices") <p class="text-[12px] font-medium text-danger" role="alert">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
            <div class="mt-3"><x-ui.button size="sm" variant="secondary" type="button" wire:click="addPizzaFlavor">Adicionar sabor</x-ui.button></div>
            <p class="mt-3 text-[12.5px] text-ink/50">Dica: bordas e adicionais são feitos em <strong>Complementos</strong> e ligados a esta pizza na seção abaixo.</p>
        </x-ui.card>
    @endif

    @if ($kind === 'common')
        <x-ui.card title="🏷️ Promoção e destaque">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Preço promocional (opcional)" for="promo_price" error="promo_price" hint="Aparece “de R$ X por R$ Y” e entra nas ofertas.">
                    <x-ui.input id="promo_price" wire:model="promo_price" placeholder="R$ 0,00" inputmode="decimal" />
                </x-ui.field>
                <x-ui.field label="Promoção vale até (opcional)" for="promo_ends_on" error="promo_ends_on" hint="Vazio = até você desligar.">
                    <x-ui.input id="promo_ends_on" type="date" wire:model="promo_ends_on" />
                </x-ui.field>
            </div>
            <label class="mt-4 flex items-start gap-3 text-[13.5px] font-semibold text-ink">
                <input type="checkbox" wire:model="is_featured" class="mt-0.5 size-4 accent-[var(--accent)]" data-featured>
                <span>⭐ Destacar no topo do catálogo <span class="block text-[12px] font-normal text-ink/55">Aparece primeiro na grade do cardápio.</span></span>
            </label>
        </x-ui.card>
    @endif

    <x-ui.card title="Selos">
        <div class="flex flex-wrap gap-2">
            @foreach ($this->badges as $badge)
                <label wire:key="badge-{{ $badge->id }}" class="cursor-pointer">
                    <input type="checkbox" value="{{ $badge->id }}" wire:model="badge_ids" class="peer sr-only">
                    <span class="inline-flex rounded-full border border-ink/15 px-3.5 py-1.5 text-[12.5px] font-bold text-ink/70 transition peer-checked:border-accent peer-checked:bg-accent peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-accent/30">{{ $badge->name }}</span>
                </label>
            @endforeach
        </div>
    </x-ui.card>

    <x-ui.card title="Variações">
        <x-slot:actions>
            <x-ui.button size="sm" variant="secondary" wire:click="addVariant">+ Variação</x-ui.button>
        </x-slot:actions>

        @forelse ($variants as $index => $variant)
            <div wire:key="variant-{{ $index }}" class="mb-2.5 flex items-start gap-2">
                <x-ui.field error="variants.{{ $index }}.name" class="flex-1">
                    <x-ui.input wire:model="variants.{{ $index }}.name" placeholder="Ex.: Grande" aria-label="Nome da variação" />
                </x-ui.field>
                <x-ui.field error="variants.{{ $index }}.price" class="w-32">
                    <x-ui.input wire:model="variants.{{ $index }}.price" placeholder="R$ 0,00" inputmode="decimal" aria-label="Preço da variação" />
                </x-ui.field>
                <x-ui.button size="sm" variant="danger" wire:click="removeVariant({{ $index }})" class="mt-1.5">Remover</x-ui.button>
            </div>
        @empty
            <p class="text-[13px] text-ink/50">Sem variações. Use para tamanhos ou opções com preço próprio (ex.: P, M, G).</p>
        @endforelse
    </x-ui.card>

    <x-ui.card title="Complementos">
        <x-slot:actions>
            @if (\Illuminate\Support\Facades\Route::has('panel.option-groups'))
                <x-ui.button size="sm" variant="secondary" :href="route('panel.option-groups')" wire:navigate>Gerenciar</x-ui.button>
            @endif
        </x-slot:actions>

        @if ($this->optionGroups->isEmpty())
            <p class="text-[13px] text-ink/50">Sem grupos ainda. Crie tamanhos, adicionais, bordas ou molhos em Complementos e ligue aqui.</p>
        @else
            <p class="mb-3 text-[13px] text-ink/55">O cliente escolhe antes de adicionar o produto ao pedido, na ordem em que você marcar.</p>
            <div class="flex flex-wrap gap-2" data-option-groups>
                @foreach ($this->optionGroups as $group)
                    <label wire:key="option-group-{{ $group->id }}" class="cursor-pointer">
                        <input type="checkbox" value="{{ $group->id }}" wire:model="option_group_ids" class="peer sr-only">
                        <span class="inline-flex rounded-full border border-ink/15 px-3.5 py-1.5 text-[12.5px] font-bold text-ink/70 transition peer-checked:border-accent peer-checked:bg-accent peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-accent/30">{{ $group->name }}</span>
                    </label>
                @endforeach
            </div>
        @endif
        @error('option_group_ids') <p class="mt-2 text-[12px] text-danger">{{ $message }}</p> @enderror
    </x-ui.card>

    @if ($dishId)
        <x-ui.card title="Foto e vídeo do produto">
            <p class="text-[13.5px] leading-relaxed text-ink/60">A foto do produto agora é enviada junto com o vídeo, em <strong class="text-ink">Vídeo do produto</strong>: ela é usada para criar o vídeo e vira a capa dele no cardápio.</p>
            <div class="mt-3">
                <x-ui.button size="sm" variant="secondary" :href="route('panel.dishes.video', $dishId)" wire:navigate>Ir para o vídeo do produto</x-ui.button>
            </div>
        </x-ui.card>
    @endif

    <div class="flex gap-2.5">
        <x-ui.button type="submit" size="lg" wire:loading.attr="disabled" wire:target="save">Salvar produto</x-ui.button>
        <x-ui.button variant="secondary" size="lg" :href="route('panel.dishes')" wire:navigate>Cancelar</x-ui.button>
    </div>
</form>
