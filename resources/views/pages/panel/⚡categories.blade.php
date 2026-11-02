<?php

use App\Actions\Menu\MoveCategory;
use App\Actions\Menu\SaveCategory;
use App\Models\Category;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::panel')] #[Title('Categorias')] class extends Component
{
    public string $newCategoryName = '';

    /**
     * Inline-editable names, keyed by category id.
     *
     * @var array<int, string>
     */
    public array $names = [];

    // Detalhes (ícone e horário próprio) da categoria que está sendo editada.
    public ?int $editingId = null;

    public string $icon = '';

    public string $shows_from = '';

    public string $shows_until = '';

    public function mount(): void
    {
        $this->names = $this->categories->pluck('name', 'id')->all();
    }

    public function editDetails(int $categoryId): void
    {
        if ($this->editingId === $categoryId) {
            $this->editingId = null;

            return;
        }

        $category = $this->findCategory($categoryId);

        $this->resetErrorBag();
        $this->editingId = $category->id;
        $this->icon = (string) $category->icon;
        $this->shows_from = $category->shows_from ? substr($category->shows_from, 0, 5) : '';
        $this->shows_until = $category->shows_until ? substr($category->shows_until, 0, 5) : '';
    }

    public function saveDetails(SaveCategory $saveCategory): void
    {
        $category = $this->findCategory((int) $this->editingId);

        $saveCategory->handle(auth()->user()->restaurant, [
            'name' => $this->names[$category->id] ?? $category->name,
            'icon' => $this->icon,
            'shows_from' => $this->shows_from,
            'shows_until' => $this->shows_until,
        ], $category);

        $this->editingId = null;
        unset($this->categories);

        $this->dispatch('toast', message: 'Categoria atualizada.');
    }

    #[Computed]
    public function categories()
    {
        return auth()->user()->restaurant->categories()
            ->withCount('dishes')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    public function addCategory(): void
    {
        $this->validate(['newCategoryName' => ['required', 'string', 'max:60']], [
            'newCategoryName.required' => 'Dê um nome para a categoria.',
        ]);

        $restaurant = auth()->user()->restaurant;

        $category = $restaurant->categories()->create([
            'name' => trim($this->newCategoryName),
            'display_order' => (int) $restaurant->categories()->max('display_order') + 1,
        ]);

        $this->names[$category->id] = $category->name;
        $this->reset('newCategoryName');
        unset($this->categories);

        $this->dispatch('toast', message: 'Categoria criada.');
    }

    public function rename(int $categoryId): void
    {
        $this->validate(["names.{$categoryId}" => ['required', 'string', 'max:60']], [
            "names.{$categoryId}.required" => 'O nome não pode ficar vazio.',
        ]);

        $this->findCategory($categoryId)->update(['name' => trim($this->names[$categoryId])]);
        unset($this->categories);

        $this->dispatch('toast', message: 'Categoria renomeada.');
    }

    public function toggleVisibility(int $categoryId): void
    {
        $category = $this->findCategory($categoryId);
        $category->update(['is_visible' => ! $category->is_visible]);
        unset($this->categories);

        $this->dispatch('toast', message: $category->is_visible ? 'Categoria visível no cardápio.' : 'Categoria oculta do cardápio.');
    }

    public function sortCategory(int $categoryId, int $position, MoveCategory $moveCategory): void
    {
        $moveCategory->handle($this->findCategory($categoryId), $position);
        unset($this->categories);
    }

    private function findCategory(int $categoryId): Category
    {
        return auth()->user()->restaurant->categories()->findOrFail($categoryId);
    }
};
?>

<div class="flex flex-col gap-[18px]">
    <x-ui.page-header title="Categorias" subtitle="Arraste para mudar a ordem em que aparecem no cardápio." />

    <x-ui.card>
        @if ($this->categories->isEmpty())
            <x-ui.empty-state icon="categories" title="Nenhuma categoria ainda" description="Crie categorias como Burgers, Bebidas ou Sobremesas para organizar os produtos no feed." />
        @else
            <ul wire:sort="sortCategory" class="flex flex-col" data-category-list>
                @foreach ($this->categories as $category)
                    <li
                        wire:key="category-{{ $category->id }}"
                        wire:sort:item="{{ $category->id }}"
                        class="flex items-center gap-2.5 border-b border-ink/[0.06] px-1 py-2 last:border-b-0 {{ $category->is_visible ? '' : 'opacity-60' }}"
                    >
                        <button type="button" wire:sort:handle class="cursor-grab touch-none p-1 text-ink/30 active:cursor-grabbing" aria-label="Arrastar para reordenar">
                            <x-ui.icon name="grip" />
                        </button>
                        <span class="w-5 text-[13px] font-semibold text-ink/35">{{ $loop->iteration }}</span>
                        @if ($category->icon)
                            <span class="text-[18px] leading-none" aria-hidden="true">{{ $category->icon }}</span>
                        @endif

                        <div class="flex min-w-0 flex-1 flex-col">
                            <input
                                wire:model="names.{{ $category->id }}"
                                wire:change="rename({{ $category->id }})"
                                wire:keydown.enter="rename({{ $category->id }})"
                                aria-label="Nome da categoria"
                                class="w-full rounded-md border-none bg-transparent px-1 py-1.5 text-[14px] font-semibold text-ink outline-none focus:bg-ink/[0.04]"
                            />
                            @error("names.{$category->id}")
                                <span class="px-1 text-[12px] text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <span class="hidden text-[12px] font-medium text-ink/40 sm:inline">{{ $category->dishes_count }} {{ $category->dishes_count === 1 ? 'produto' : 'produtos' }}</span>

                        @if ($category->shows_from && $category->shows_until)
                            <span class="hidden rounded-full bg-ink/[0.06] px-2.5 py-1 text-[11.5px] font-semibold text-ink/55 sm:inline" data-category-window>{{ substr($category->shows_from, 0, 5) }}–{{ substr($category->shows_until, 0, 5) }}</span>
                        @endif

                        <x-ui.button size="sm" variant="ghost" wire:click="editDetails({{ $category->id }})" data-edit-details>Detalhes</x-ui.button>

                        <x-ui.button size="sm" variant="ghost" wire:click="toggleVisibility({{ $category->id }})">
                            {{ $category->is_visible ? 'Ocultar' : 'Mostrar' }}
                        </x-ui.button>
                    </li>
                    @if ($editingId === $category->id)
                        <li wire:key="category-details-{{ $category->id }}" class="border-b border-ink/[0.06] bg-ink/[0.03] px-3 py-3" data-category-details>
                            <form wire:submit="saveDetails" class="grid gap-3 sm:grid-cols-4">
                                <x-ui.field label="Ícone" for="icon-{{ $category->id }}" error="icon" hint="Um emoji, ex.: 🍕">
                                    <x-ui.input id="icon-{{ $category->id }}" wire:model="icon" maxlength="16" />
                                </x-ui.field>
                                <x-ui.field label="Mostrar a partir de" for="from-{{ $category->id }}" error="shows_from" hint="Vazio = sempre.">
                                    <x-ui.input id="from-{{ $category->id }}" type="time" wire:model="shows_from" />
                                </x-ui.field>
                                <x-ui.field label="Mostrar até" for="until-{{ $category->id }}" error="shows_until" hint="Se for antes do início, passa da meia-noite.">
                                    <x-ui.input id="until-{{ $category->id }}" type="time" wire:model="shows_until" />
                                </x-ui.field>
                                <div class="flex items-end">
                                    <x-ui.button type="submit" size="sm">Salvar detalhes</x-ui.button>
                                </div>
                            </form>
                            <p class="mt-2 text-[12px] text-ink/50">Ex.: cardápio do almoço das 11:00 às 15:00. Fora desse horário a categoria some do cardápio.</p>
                        </li>
                    @endif
                @endforeach
            </ul>
        @endif

        <form wire:submit="addCategory" class="mt-3 flex gap-2 pt-1.5">
            <div class="flex flex-1 flex-col gap-1">
                <x-ui.input wire:model="newCategoryName" placeholder="Nova categoria" aria-label="Nova categoria" />
                @error('newCategoryName')
                    <span class="text-[12px] text-danger">{{ $message }}</span>
                @enderror
            </div>
            <x-ui.button type="submit" class="self-start">Adicionar</x-ui.button>
        </form>
    </x-ui.card>
</div>
