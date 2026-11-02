<?php

use App\Actions\Menu\DeleteDish;
use App\Actions\Menu\DuplicateDish;
use App\Actions\Menu\SavePromoBanner;
use App\Actions\Menu\SetDishFlag;
use App\Actions\Menu\UpdateDishPrice;
use App\Models\Dish;
use App\Models\DishStatus;
use App\Support\Money;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::panel')] #[Title('Produtos')] class extends Component
{
    use WithFileUploads;

    // Promotional banner (RF-16)
    public bool $showBanner = false;

    public string $bannerTitle = '';

    public string $bannerText = '';

    public string $bannerEnds = '';

    public bool $bannerActive = true;

    public $bannerImage = null;

    public bool $bannerRemoveImage = false;

    /**
     * Inline-editable prices, keyed by dish id (US-2.3).
     *
     * @var array<int, string>
     */
    public array $prices = [];

    #[Url(as: 'busca', except: '')]
    public string $search = '';

    #[Url(as: 'categoria', except: '')]
    public string $category = '';

    /** todos | ativos | indisponiveis | ocultos */
    #[Url(as: 'status', except: 'todos')]
    public string $tab = 'todos';

    public function mount(): void
    {
        $this->prices = $this->dishes->mapWithKeys(fn (Dish $dish) => [$dish->id => Money::input($dish->price)])->all();
    }

    #[Computed]
    public function restaurant()
    {
        return auth()->user()->restaurant->load('plan');
    }

    /**
     * Every product of the store, for the plan counters; the filtered list is `visibleDishes`.
     */
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

    /**
     * The list after search, category and status tab (RF-06f).
     */
    #[Computed]
    public function visibleDishes()
    {
        $term = mb_strtolower(trim($this->search));
        $slugs = ['ativos' => 'ativo', 'indisponiveis' => 'esgotado', 'ocultos' => 'oculto'];

        return $this->dishes
            ->when($term !== '', fn ($dishes) => $dishes->filter(fn (Dish $dish) => str_contains(mb_strtolower($dish->name), $term)))
            ->when($this->category === 'sem', fn ($dishes) => $dishes->whereNull('category_id'))
            ->when(ctype_digit($this->category), fn ($dishes) => $dishes->where('category_id', (int) $this->category))
            ->when(isset($slugs[$this->tab]), fn ($dishes) => $dishes->filter(fn (Dish $dish) => $dish->status->slug === $slugs[$this->tab]))
            ->values();
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        $bySlug = $this->dishes->countBy(fn (Dish $dish) => $dish->status->slug);

        return [
            'todos' => $this->dishes->count(),
            'ativos' => $bySlug['ativo'] ?? 0,
            'indisponiveis' => $bySlug['esgotado'] ?? 0,
            'ocultos' => $bySlug['oculto'] ?? 0,
        ];
    }

    #[Computed]
    public function statuses()
    {
        return DishStatus::query()->slug('ativo', 'esgotado', 'oculto')->get();
    }

    public function updatePrice(int $dishId, UpdateDishPrice $updateDishPrice): void
    {
        $dish = $this->findDish($dishId);

        try {
            $price = $updateDishPrice->handle($dish, $this->prices[$dishId] ?? null);
        } catch (ValidationException) {
            $this->prices[$dishId] = Money::input($dish->price);
            $this->addError("prices.{$dishId}", 'Preço inválido.');

            return;
        }

        $this->prices[$dishId] = Money::input($price);
        unset($this->dishes, $this->visibleDishes, $this->counts);

        $this->dispatch('toast', message: "Preço de {$dish->name} atualizado.");
    }

    #[Computed]
    public function banner()
    {
        return $this->restaurant->promoBanner()->first();
    }

    public function openBanner(): void
    {
        $banner = $this->banner;

        $this->resetErrorBag();
        $this->bannerTitle = (string) $banner?->title;
        $this->bannerText = (string) $banner?->text;
        $this->bannerEnds = $banner?->ends_on?->toDateString() ?? '';
        $this->bannerActive = $banner?->is_active ?? true;
        $this->bannerImage = null;
        $this->bannerRemoveImage = false;
        $this->showBanner = true;
    }

    public function saveBanner(SavePromoBanner $savePromoBanner): void
    {
        try {
            $savePromoBanner->handle($this->restaurant, [
                'title' => $this->bannerTitle,
                'text' => $this->bannerText,
                'ends_on' => $this->bannerEnds,
                'is_active' => $this->bannerActive,
            ], $this->bannerImage, $this->bannerRemoveImage);
        } catch (ValidationException $exception) {
            $this->setErrorBag(collect($exception->errors())->mapWithKeys(fn ($messages, $key) => [$key === 'title' ? 'bannerTitle' : ($key === 'text' ? 'bannerText' : ($key === 'ends_on' ? 'bannerEnds' : 'bannerImage')) => $messages])->all());

            return;
        }

        $this->showBanner = false;
        $this->bannerImage = null;
        unset($this->banner);

        $this->dispatch('toast', message: 'Banner salvo.');
    }

    public function removeBanner(SavePromoBanner $savePromoBanner): void
    {
        $savePromoBanner->remove($this->restaurant);

        $this->showBanner = false;
        unset($this->banner);

        $this->dispatch('toast', message: 'Banner removido.');
    }

    public function toggleFlag(int $dishId, string $flag, SetDishFlag $setDishFlag): void
    {
        $dish = $this->findDish($dishId);
        $on = $flag === 'available' ? $dish->status->slug === 'ativo' : $dish->status->slug !== 'oculto';

        try {
            $setDishFlag->handle($this->restaurant, $dish, $flag, ! $on);
        } catch (ValidationException $exception) {
            $this->dispatch('toast', message: collect($exception->errors())->flatten()->first(), type: 'error');
            unset($this->dishes, $this->visibleDishes, $this->counts);

            return;
        }

        unset($this->dishes, $this->visibleDishes, $this->counts);
    }

    public function duplicate(int $dishId, DuplicateDish $duplicateDish): void
    {
        try {
            $copy = $duplicateDish->handle($this->restaurant, $this->findDish($dishId));
        } catch (ValidationException $exception) {
            $this->dispatch('toast', message: collect($exception->errors())->flatten()->first(), type: 'error');

            return;
        }

        $this->prices[$copy->id] = Money::input($copy->price);
        unset($this->dishes, $this->visibleDishes, $this->counts);

        $this->dispatch('toast', message: "{$copy->name} criado.");
    }

    public function delete(int $dishId, DeleteDish $deleteDish): void
    {
        $dish = $this->findDish($dishId);

        try {
            $deleteDish->handle($this->restaurant, $dish);
        } catch (ValidationException $exception) {
            $this->dispatch('toast', message: collect($exception->errors())->flatten()->first(), type: 'error');

            return;
        }

        unset($this->prices[$dishId], $this->dishes, $this->visibleDishes, $this->counts);

        $this->dispatch('toast', message: "{$dish->name} foi excluído.");
    }

    public function updateStatus(int $dishId, string $slug): void
    {
        $dish = $this->findDish($dishId);

        if ($dish->status->slug === 'oculto' && $slug !== 'oculto' && ! $this->restaurant->canShowAnotherDish()) {
            $this->dispatch('toast', message: 'O plano '.$this->restaurant->plan->name.' permite '.$this->restaurant->plan->dish_limit.' produtos visíveis. Faça upgrade ou oculte outro produto.', type: 'error');
            unset($this->dishes, $this->visibleDishes, $this->counts);

            return;
        }

        $dish->update(['status_id' => DishStatus::idFor($slug)]);
        unset($this->dishes, $this->visibleDishes, $this->counts);

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
    <x-ui.page-header title="Produtos" :subtitle="$limit ? $this->dishes->count().' de '.$limit.' produtos no plano '.$this->restaurant->plan->name.'.' : null">
        @if ($canAdd)
            <x-ui.button :href="route('panel.dishes.create')" wire:navigate>+ Novo produto</x-ui.button>
        @else
            <x-ui.button disabled title="Limite de produtos do plano atingido">+ Novo produto</x-ui.button>
        @endif
    </x-ui.page-header>

    @unless ($canAdd)
        <div class="rounded-xl bg-accent/10 px-4 py-3 text-[13.5px] font-medium text-ink/75">
            Você atingiu o limite de {{ $limit }} produtos do plano {{ $this->restaurant->plan->name }}.
            @if (Route::has('panel.subscription'))
                <a href="{{ route('panel.subscription') }}" class="font-bold text-accent hover:underline" wire:navigate>Faça upgrade</a> para cadastrar mais.
            @endif
        </div>
    @endunless

    <x-ui.card data-banner-card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-[15px] font-bold text-ink">🏷️ Banner de promoção</h2>
                <p class="text-[12.5px] text-ink/55" data-banner-status>
                    @if ($this->banner?->isLive())
                        No ar: “{{ $this->banner->title }}”{{ $this->banner->ends_on ? ' até '.$this->banner->ends_on->format('d/m') : '' }}.
                    @elseif ($this->banner)
                        Desligado ou vencido.
                    @else
                        Uma faixa chamativa no início do cardápio.
                    @endif
                </p>
            </div>
            <x-ui.button size="sm" variant="secondary" wire:click="openBanner" data-open-banner>{{ $this->banner ? 'Editar banner' : 'Criar banner' }}</x-ui.button>
        </div>
    </x-ui.card>

    <x-ui.card :padding="false">
        @if ($this->restaurant->categories()->doesntExist())
            <x-ui.empty-state icon="categories" title="Crie uma categoria primeiro" description="Todo produto pertence a uma categoria do cardápio.">
                <x-ui.button :href="route('panel.categories')" wire:navigate>Criar categoria</x-ui.button>
            </x-ui.empty-state>
        @elseif ($this->dishes->isEmpty())
            <x-ui.empty-state icon="dishes" title="Nenhum produto ainda" description="Cadastre o primeiro produto para ele aparecer no seu cardápio em vídeo.">
                <x-ui.button :href="route('panel.dishes.create')" wire:navigate>+ Novo produto</x-ui.button>
            </x-ui.empty-state>
        @else
            <div class="flex flex-col gap-3 border-b border-ink/[0.06] px-4 py-3 sm:px-[18px]" data-dish-filters>
                <div class="flex flex-wrap gap-2">
                    <x-ui.input type="search" wire:model.live.debounce.250ms="search" placeholder="Buscar produto" aria-label="Buscar produto" class="!w-auto min-w-[200px] flex-1" />
                    <x-ui.select wire:model.live="category" aria-label="Filtrar por categoria" class="!w-auto">
                        <option value="">Todas as categorias</option>
                        @foreach ($this->restaurant->categories()->orderBy('display_order')->get() as $filterCategory)
                            <option value="{{ $filterCategory->id }}">{{ $filterCategory->name }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
                <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="Status">
                    @foreach (['todos' => 'Todos', 'ativos' => 'Ativos', 'indisponiveis' => 'Indisponíveis', 'ocultos' => 'Ocultos'] as $key => $label)
                        <button type="button" role="tab" wire:click="$set('tab', '{{ $key }}')" data-tab="{{ $key }}" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" class="rounded-full px-3.5 py-1.5 text-[12.5px] font-bold transition {{ $tab === $key ? 'bg-ink text-white' : 'bg-ink/[0.06] text-ink/60 hover:bg-ink/10' }}">
                            {{ $label }} <span class="opacity-60">{{ $this->counts[$key] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            @if ($this->visibleDishes->isEmpty())
                <x-ui.empty-state icon="dishes" title="Nenhum produto encontrado" description="Mude a busca ou o filtro para ver outros produtos." />
            @else
            <ul data-dish-list>
                @foreach ($this->visibleDishes as $dish)
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

                        <div class="flex w-full flex-wrap items-center gap-2.5 sm:w-auto sm:flex-nowrap">
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

                            <label class="flex cursor-pointer items-center gap-1.5 text-[11.5px] font-semibold text-ink/60">
                                <input type="checkbox" wire:click="toggleFlag({{ $dish->id }}, 'available')" @checked($dish->status->slug === 'ativo') @disabled($dish->status->slug === 'oculto') aria-label="{{ $dish->name }} disponível" class="size-4 accent-[var(--accent)]" data-flag="available">
                                Disponível
                            </label>
                            <label class="flex cursor-pointer items-center gap-1.5 text-[11.5px] font-semibold text-ink/60">
                                <input type="checkbox" wire:click="toggleFlag({{ $dish->id }}, 'visible')" @checked($dish->status->slug !== 'oculto') aria-label="{{ $dish->name }} visível no catálogo" class="size-4 accent-[var(--accent)]" data-flag="visible">
                                No cardápio
                            </label>

                            <x-ui.button size="sm" variant="secondary" :href="route('panel.dishes.video', $dish)" wire:navigate class="ml-auto sm:ml-0" aria-label="Vídeo de {{ $dish->name }}">
                                <x-ui.icon name="media" class="size-4" />
                            </x-ui.button>
                            <x-ui.button size="sm" variant="secondary" :href="route('panel.dishes.edit', $dish)" wire:navigate aria-label="Editar {{ $dish->name }}">Editar</x-ui.button>
                            <x-ui.button size="sm" variant="ghost" wire:click="duplicate({{ $dish->id }})" aria-label="Duplicar {{ $dish->name }}">Duplicar</x-ui.button>
                            <x-ui.button size="sm" variant="danger" wire:click="delete({{ $dish->id }})" wire:confirm="Excluir {{ $dish->name }}? O vídeo e as fotos também serão apagados." aria-label="Excluir {{ $dish->name }}">Excluir</x-ui.button>
                        </div>

                        @error("prices.{$dish->id}")
                            <span class="w-full text-[12px] text-danger">{{ $message }}</span>
                        @enderror
                    </li>
                @endforeach
            </ul>
            @endif
        @endif
    </x-ui.card>

    <x-ui.modal wire:model="showBanner" title="Banner de promoção" max-width="max-w-[480px]" data-banner-modal>
        <form wire:submit="saveBanner" class="flex flex-col gap-3.5">
            <x-ui.field label="Título" for="bannerTitle" error="bannerTitle" hint="{{ mb_strlen($bannerTitle) }}/{{ \App\Actions\Menu\SavePromoBanner::TITLE_LIMIT }} — ex.: Sábado é dia de feijoada!">
                <x-ui.input id="bannerTitle" wire:model.live="bannerTitle" maxlength="{{ \App\Actions\Menu\SavePromoBanner::TITLE_LIMIT }}" />
            </x-ui.field>
            <x-ui.field label="Texto (opcional)" for="bannerText" error="bannerText" hint="{{ mb_strlen($bannerText) }}/{{ \App\Actions\Menu\SavePromoBanner::TEXT_LIMIT }}">
                <x-ui.textarea id="bannerText" wire:model.live="bannerText" rows="2" maxlength="{{ \App\Actions\Menu\SavePromoBanner::TEXT_LIMIT }}" />
            </x-ui.field>
            <x-ui.field label="Mostrar até (opcional)" for="bannerEnds" error="bannerEnds" hint="Vazio = fica até você desligar. Some sozinho no fim do dia escolhido.">
                <x-ui.input id="bannerEnds" type="date" wire:model="bannerEnds" />
            </x-ui.field>
            <x-ui.field label="Foto (opcional)" for="bannerImage" error="bannerImage" hint="Fotos na horizontal ficam melhores. Sem foto, o banner usa o vidro da loja.">
                <input id="bannerImage" type="file" wire:model="bannerImage" accept="image/*" class="text-[13px]">
            </x-ui.field>
            @if ($this->banner?->image_path)
                <label class="flex items-center gap-2 text-[12.5px] font-semibold text-ink/60"><input type="checkbox" wire:model="bannerRemoveImage" class="size-4 accent-[var(--accent)]"> Remover a foto atual</label>
            @endif
            <label class="flex items-center gap-2 text-[13px] font-semibold text-ink"><input type="checkbox" wire:model="bannerActive" class="size-4 accent-[var(--accent)]"> Banner ligado</label>
            <div class="flex gap-2">
                <x-ui.button type="submit" class="flex-1" wire:loading.attr="disabled" wire:target="saveBanner" data-save-banner>Salvar banner</x-ui.button>
                @if ($this->banner)
                    <x-ui.button type="button" variant="danger" wire:click="removeBanner" wire:confirm="Remover o banner?" data-remove-banner>Remover</x-ui.button>
                @endif
            </div>
        </form>
    </x-ui.modal>
</div>
