<?php

use App\Actions\Menu\SaveDish;
use App\Models\Badge;
use App\Models\Dish;
use App\Support\Money;
use Illuminate\Validation\Rule;
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
                'variants' => $dish->variants->map(fn ($variant) => [
                    'name' => $variant->name,
                    'price' => Money::input($variant->price),
                ])->all(),
            ]);
        } else {
            abort_unless($restaurant->canAddDish(), 403, 'Limite de pratos do plano atingido.');

            $this->category_id = $restaurant->categories()->orderBy('display_order')->value('id');
        }
    }

    public function render()
    {
        return $this->view()->title($this->dishId ? 'Editar prato' : 'Novo prato');
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
    public function badges()
    {
        return Badge::query()->orderBy('id')->get();
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
            'price' => ['required', 'string', $this->priceRule()],
            'short_description' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'badge_ids' => ['array'],
            'badge_ids.*' => ['integer', Rule::exists('badges', 'id')],
            'variants' => ['array'],
            'variants.*.name' => ['required', 'string', 'max:40'],
            'variants.*.price' => ['required', 'string', $this->priceRule()],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do prato.',
            'category_id.required' => 'Escolha uma categoria.',
            'price.required' => 'Informe o preço.',
            'variants.*.name.required' => 'Dê um nome para a variação.',
            'variants.*.price.required' => 'Informe o preço da variação.',
        ];
    }

    private function priceRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (Money::parse((string) $value) === null) {
                $fail('Preço inválido. Use o formato 32,90.');
            }
        };
    }

    public function save(SaveDish $saveDish)
    {
        $this->validate();

        if (! $this->dishId && ! $this->restaurant->canAddDish()) {
            $this->dispatch('toast', message: 'Limite de pratos do plano atingido.', type: 'error');

            return;
        }

        $dish = $saveDish->handle($this->restaurant, [
            'category_id' => $this->category_id,
            'name' => trim($this->name),
            'price' => Money::parse($this->price),
            'short_description' => trim($this->short_description),
            'description' => trim($this->description),
            'badge_ids' => array_map('intval', $this->badge_ids),
            'variants' => array_map(fn (array $variant) => [
                'name' => trim($variant['name']),
                'price' => Money::parse($variant['price']),
            ], $this->variants),
        ], $this->dish);

        session()->flash('toast', ['message' => 'Prato salvo.', 'type' => 'success']);

        return $this->redirectRoute('panel.dishes.edit', $dish, navigate: true);
    }
};
?>

<form wire:submit="save" class="flex flex-col gap-[18px]">
    <x-ui.page-header :title="$dishId ? 'Editar prato' : 'Novo prato'">
        <div class="flex gap-2">
            @if ($dishId)
                <x-ui.button :href="route('panel.dishes.video', $dishId)" wire:navigate>
                    <x-ui.icon name="media" class="size-4" />
                    Vídeo do prato
                </x-ui.button>
            @endif
            <x-ui.button variant="secondary" :href="route('panel.dishes')" wire:navigate>Voltar</x-ui.button>
        </div>
    </x-ui.page-header>

    <x-ui.card title="Dados do prato">
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

            <x-ui.field label="Preço" for="price" error="price">
                <x-ui.input id="price" wire:model="price" placeholder="R$ 0,00" inputmode="decimal" required />
            </x-ui.field>

            <x-ui.field label="Descrição curta" for="short_description" error="short_description" hint="Aparece sobre o vídeo no feed." class="sm:col-span-2">
                <x-ui.input id="short_description" wire:model="short_description" maxlength="160" />
            </x-ui.field>

            <x-ui.field label="Descrição completa" for="description" error="description" hint="Aparece no painel de detalhes do prato." class="sm:col-span-2">
                <x-ui.textarea id="description" wire:model="description" rows="4" maxlength="2000" />
            </x-ui.field>
        </div>
    </x-ui.card>

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

    @if ($dishId)
        <x-ui.card title="Foto e vídeo do prato">
            <p class="text-[13.5px] leading-relaxed text-ink/60">A foto do prato agora é enviada junto com o vídeo, em <strong class="text-ink">Vídeo do prato</strong>: ela é usada para criar o vídeo e vira a capa dele no cardápio.</p>
            <div class="mt-3">
                <x-ui.button size="sm" variant="secondary" :href="route('panel.dishes.video', $dishId)" wire:navigate>Ir para o vídeo do prato</x-ui.button>
            </div>
        </x-ui.card>
    @endif

    <div class="flex gap-2.5">
        <x-ui.button type="submit" size="lg" wire:loading.attr="disabled" wire:target="save">Salvar prato</x-ui.button>
        <x-ui.button variant="secondary" size="lg" :href="route('panel.dishes')" wire:navigate>Cancelar</x-ui.button>
    </div>
</form>
