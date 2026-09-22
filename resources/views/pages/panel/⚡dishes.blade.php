<?php

use App\Models\Dish;
use App\Models\DishStatus;
use App\Support\Money;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::panel')] #[Title('Pratos')] class extends Component
{
    /**
     * Inline-editable prices, keyed by dish id (US-2.3).
     *
     * @var array<int, string>
     */
    public array $prices = [];

    public function mount(): void
    {
        $this->prices = $this->dishes->mapWithKeys(fn (Dish $dish) => [$dish->id => Money::input($dish->price)])->all();
    }

    #[Computed]
    public function restaurant()
    {
        return auth()->user()->restaurant->load('plan');
    }

    #[Computed]
    public function dishes()
    {
        return $this->restaurant->dishes()
            ->with(['category', 'status', 'photos' => fn ($query) => $query->orderBy('display_order')])
            ->join('categories', 'categories.id', '=', 'dishes.category_id')
            ->orderBy('categories.display_order')
            ->orderBy('dishes.display_order')
            ->select('dishes.*')
            ->get();
    }

    #[Computed]
    public function statuses()
    {
        return DishStatus::query()->slug('ativo', 'esgotado', 'oculto')->get();
    }

    public function updatePrice(int $dishId): void
    {
        $price = Money::parse($this->prices[$dishId] ?? null);

        if ($price === null) {
            $this->addError("prices.{$dishId}", 'Preço inválido.');

            return;
        }

        $dish = $this->findDish($dishId);
        $dish->update(['price' => $price]);
        $this->prices[$dishId] = Money::input($price);
        unset($this->dishes);

        $this->dispatch('toast', message: "Preço de {$dish->name} atualizado.");
    }

    public function updateStatus(int $dishId, string $slug): void
    {
        $dish = $this->findDish($dishId);
        $dish->update(['status_id' => DishStatus::idFor($slug)]);
        unset($this->dishes);

        $messages = [
            'ativo' => "{$dish->name} está disponível.",
            'esgotado' => "{$dish->name} marcado como esgotado.",
            'oculto' => "{$dish->name} foi ocultado do cardápio.",
        ];

        $this->dispatch('toast', message: $messages[$slug] ?? 'Status atualizado.');
    }

    private function findDish(int $dishId): Dish
    {
        return $this->restaurant->dishes()->findOrFail($dishId);
    }
};
?>

@php
    $statusStyles = [
        'ativo' => 'bg-success/12 text-success',
        'esgotado' => 'bg-danger/12 text-danger',
        'oculto' => 'bg-ink/8 text-ink/55',
    ];
    $limit = $this->restaurant->plan?->dish_limit;
    $canAdd = $this->restaurant->canAddDish();
@endphp

<div class="flex flex-col gap-[18px]">
    <x-ui.page-header title="Pratos" :subtitle="$limit ? $this->dishes->count().' de '.$limit.' pratos no plano '.$this->restaurant->plan->name.'.' : null">
        @if ($canAdd)
            <x-ui.button :href="route('panel.dishes.create')" wire:navigate>+ Novo prato</x-ui.button>
        @else
            <x-ui.button disabled title="Limite de pratos do plano atingido">+ Novo prato</x-ui.button>
        @endif
    </x-ui.page-header>

    @unless ($canAdd)
        <div class="rounded-xl bg-accent/10 px-4 py-3 text-[13.5px] font-medium text-ink/75">
            Você atingiu o limite de {{ $limit }} pratos do plano {{ $this->restaurant->plan->name }}.
            @if (Route::has('panel.subscription'))
                <a href="{{ route('panel.subscription') }}" class="font-bold text-accent hover:underline" wire:navigate>Faça upgrade</a> para cadastrar mais.
            @endif
        </div>
    @endunless

    <x-ui.card :padding="false">
        @if ($this->restaurant->categories()->doesntExist())
            <x-ui.empty-state icon="categories" title="Crie uma categoria primeiro" description="Todo prato pertence a uma categoria do cardápio.">
                <x-ui.button :href="route('panel.categories')" wire:navigate>Criar categoria</x-ui.button>
            </x-ui.empty-state>
        @elseif ($this->dishes->isEmpty())
            <x-ui.empty-state icon="dishes" title="Nenhum prato ainda" description="Cadastre o primeiro prato para ele aparecer no seu cardápio em vídeo.">
                <x-ui.button :href="route('panel.dishes.create')" wire:navigate>+ Novo prato</x-ui.button>
            </x-ui.empty-state>
        @else
            <ul>
                @foreach ($this->dishes as $dish)
                    <li wire:key="dish-{{ $dish->id }}" class="flex flex-wrap items-center gap-x-3.5 gap-y-2 border-b border-ink/[0.06] px-4 py-3 last:border-b-0 sm:flex-nowrap sm:px-[18px]">
                        <div class="size-[52px] shrink-0 overflow-hidden rounded-lg bg-ink/[0.06]">
                            @if ($photo = $dish->photos->first())
                                <img src="{{ Storage::disk('public')->url($photo->file_path) }}" alt="" class="size-full object-cover" loading="lazy">
                            @endif
                        </div>

                        <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                            <span class="truncate text-[14px] font-semibold text-ink">{{ $dish->name }}</span>
                            <span class="text-[12px] text-ink/50">{{ $dish->category->name }}{{ $dish->active_video_id ? '' : ' · sem vídeo aprovado' }}</span>
                        </div>

                        <div class="flex w-full items-center gap-2.5 sm:w-auto">
                            <label class="flex w-[104px] items-center gap-1 rounded-lg border border-ink/10 px-2 focus-within:border-accent">
                                <span class="text-[12px] font-semibold text-ink/45">R$</span>
                                <input
                                    wire:model="prices.{{ $dish->id }}"
                                    wire:change="updatePrice({{ $dish->id }})"
                                    inputmode="decimal"
                                    aria-label="Preço de {{ $dish->name }}"
                                    class="w-full border-none bg-transparent py-1.5 text-[14px] font-bold text-ink outline-none"
                                />
                            </label>

                            <select
                                wire:change="updateStatus({{ $dish->id }}, $event.target.value)"
                                aria-label="Status de {{ $dish->name }}"
                                class="w-[104px] cursor-pointer appearance-none rounded-full border-none px-3 py-1.5 text-center text-[11px] font-bold tracking-wide uppercase outline-none {{ $statusStyles[$dish->status->slug] ?? '' }}"
                            >
                                @foreach ($this->statuses as $status)
                                    <option value="{{ $status->slug }}" @selected($status->id === $dish->status_id)>{{ $status->name }}</option>
                                @endforeach
                            </select>

                            <x-ui.button size="sm" variant="secondary" :href="route('panel.dishes.video', $dish)" wire:navigate class="ml-auto sm:ml-0" aria-label="Vídeo de {{ $dish->name }}">
                                <x-ui.icon name="media" class="size-4" />
                            </x-ui.button>
                            <x-ui.button size="sm" variant="secondary" :href="route('panel.dishes.edit', $dish)" wire:navigate>Editar</x-ui.button>
                        </div>

                        @error("prices.{$dish->id}")
                            <span class="w-full text-[12px] text-danger">{{ $message }}</span>
                        @enderror
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</div>
