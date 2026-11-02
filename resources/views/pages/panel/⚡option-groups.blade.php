<?php

use App\Actions\Menu\DeleteOptionGroup;
use App\Actions\Menu\SaveOptionGroup;
use App\Models\OptionGroup;
use App\Support\Money;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Complementos (RF-03, UI-01): grupos reutilizáveis de tamanhos, adicionais, bordas,
 * molhos… que o cliente escolhe antes de adicionar o produto. A página é fina: a
 * validação e a gravação ficam em SaveOptionGroup.
 */
new #[Layout('layouts::panel')] #[Title('Complementos')] class extends Component
{
    /** list | templates | form */
    public string $screen = 'list';

    public ?int $groupId = null;

    public string $name = '';

    public string $mode = 'one';

    public int $max_choices = 1;

    public bool $is_required = false;

    public bool $allow_repeat = false;

    public bool $is_active = true;

    /** @var array<int, array{id: ?int, name: string, price: string, is_available: bool}> */
    public array $options = [];

    /** @var array<int, int|string> */
    public array $dish_ids = [];

    #[Computed]
    public function restaurant()
    {
        return auth()->user()->restaurant;
    }

    #[Computed]
    public function groups()
    {
        return $this->restaurant->optionGroups()
            ->with('options')
            ->withCount('dishes')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function dishes()
    {
        return $this->restaurant->dishes()->orderBy('name')->limit(300)->get(['id', 'name']);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    #[Computed]
    public function templates(): array
    {
        return config('option_group_templates');
    }

    public function newGroup(): void
    {
        $this->resetErrorBag();
        $this->screen = 'templates';
    }

    public function chooseTemplate(string $key): void
    {
        $template = $this->templates[$key] ?? null;
        abort_if($template === null, 404);

        $this->groupId = null;
        $this->fill([
            'name' => $key === 'zero' ? '' : $template['label'],
            'mode' => $template['mode'],
            'max_choices' => $template['max_choices'],
            'is_required' => $template['is_required'],
            'allow_repeat' => $template['allow_repeat'],
            'is_active' => true,
            'dish_ids' => [],
            'options' => array_map(fn (array $option) => [
                'id' => null,
                'name' => $option['name'],
                'price' => Money::input($option['price']) === '' ? '' : Money::input($option['price']),
                'is_available' => true,
            ], $template['options']),
        ]);

        if ($this->options === []) {
            $this->addOption();
        }

        $this->resetErrorBag();
        $this->screen = 'form';
    }

    public function edit(int $groupId): void
    {
        $group = $this->restaurant->optionGroups()->with(['options', 'dishes:id'])->findOrFail($groupId);

        $this->groupId = $group->id;
        $this->fill([
            'name' => $group->name,
            'mode' => $group->mode,
            'max_choices' => $group->max_choices,
            'is_required' => $group->is_required,
            'allow_repeat' => $group->allow_repeat,
            'is_active' => $group->is_active,
            'dish_ids' => $group->dishes->pluck('id')->all(),
            'options' => $group->options->map(fn ($option) => [
                'id' => $option->id,
                'name' => $option->name,
                'price' => $option->price === null ? '' : Money::input($option->price),
                'is_available' => $option->is_available,
            ])->all(),
        ]);

        $this->resetErrorBag();
        $this->screen = 'form';
    }

    public function addOption(): void
    {
        $this->options[] = ['id' => null, 'name' => '', 'price' => '', 'is_available' => true];
    }

    public function removeOption(int $index): void
    {
        unset($this->options[$index]);
        $this->options = array_values($this->options);
    }

    public function updatedMode(string $value): void
    {
        if ($value === 'one') {
            $this->max_choices = 1;
            $this->allow_repeat = false;
        } elseif ($this->max_choices < 2) {
            $this->max_choices = 3;
        }
    }

    public function save(SaveOptionGroup $saveOptionGroup): void
    {
        $group = $this->groupId ? $this->restaurant->optionGroups()->findOrFail($this->groupId) : null;

        $saveOptionGroup->handle($this->restaurant, [
            'name' => $this->name,
            'mode' => $this->mode,
            'max_choices' => $this->max_choices,
            'is_required' => $this->is_required,
            'allow_repeat' => $this->allow_repeat,
            'is_active' => $this->is_active,
            'options' => $this->options,
            'dish_ids' => $this->dish_ids,
        ], $group);

        unset($this->groups);
        $this->screen = 'list';

        $this->dispatch('toast', message: 'Grupo salvo.');
    }

    public function remove(int $groupId, DeleteOptionGroup $deleteOptionGroup): void
    {
        $deleteOptionGroup->handle($this->restaurant->optionGroups()->findOrFail($groupId));

        unset($this->groups);
        $this->dispatch('toast', message: 'Grupo excluído.');
    }

    public function back(): void
    {
        $this->resetErrorBag();
        $this->screen = 'list';
    }

    public function summary(OptionGroup $group): string
    {
        $rule = $group->mode === 'one' ? 'Escolha 1' : 'Escolha até '.$group->max_choices;

        return $rule.($group->allow_repeat ? ' · pode repetir' : '').' · '.$group->options->count().' '.($group->options->count() === 1 ? 'opção' : 'opções');
    }
};
?>

<div class="flex flex-col gap-[18px]">
    @if ($screen === 'list')
        <x-ui.page-header title="Complementos" subtitle="Tamanhos, adicionais, bordas, molhos… Crie uma vez e use em vários produtos.">
            <x-ui.button wire:click="newGroup" data-new-group>Novo grupo</x-ui.button>
        </x-ui.page-header>

        @if ($this->groups->isEmpty())
            <x-ui.card>
                <x-ui.empty-state icon="layers" title="Nenhum complemento ainda" description="Comece por um modelo pronto, por exemplo “Adicionais” ou “Tamanhos”, e ajuste os preços.">
                    <x-ui.button wire:click="newGroup">Criar o primeiro grupo</x-ui.button>
                </x-ui.empty-state>
            </x-ui.card>
        @else
            <div class="flex flex-col gap-3" data-option-group-list>
                @foreach ($this->groups as $group)
                    <x-ui.card wire:key="group-{{ $group->id }}" class="{{ $group->is_active ? '' : 'opacity-60' }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h2 class="text-[15px] font-bold text-ink">{{ $group->name }}</h2>
                                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $group->is_required ? 'bg-accent/10 text-accent' : 'bg-ink/[0.06] text-ink/55' }}">{{ $group->is_required ? 'Obrigatório' : 'Opcional' }}</span>
                                </div>
                                <p class="mt-1 text-[12.5px] text-ink/55">{{ $this->summary($group) }}</p>
                                <p class="mt-1.5 text-[13px] text-ink/70">
                                    {{ $group->options->map(fn ($option) => $option->name.($option->price !== null ? ' +'.\App\Support\Money::brl($option->price) : ''))->implode(' · ') }}
                                </p>
                                <p class="mt-1.5 text-[12px] text-ink/45">
                                    {{ $group->dishes_count === 0 ? 'Ainda não está em nenhum produto' : 'Usado em '.$group->dishes_count.' '.($group->dishes_count === 1 ? 'produto' : 'produtos') }}
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <x-ui.button size="sm" variant="secondary" wire:click="edit({{ $group->id }})" data-edit-group>Editar</x-ui.button>
                                <x-ui.button size="sm" variant="danger" wire:click="remove({{ $group->id }})" wire:confirm="Excluir este grupo? Os produtos deixam de perguntar essas opções.">Excluir</x-ui.button>
                            </div>
                        </div>
                    </x-ui.card>
                @endforeach
            </div>
        @endif
    @elseif ($screen === 'templates')
        <x-ui.page-header title="Criar grupo de complementos" subtitle="Escolha um modelo para começar. Você pode mudar tudo depois.">
            <x-ui.button variant="secondary" wire:click="back">Voltar</x-ui.button>
        </x-ui.page-header>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" data-templates>
            @foreach ($this->templates as $key => $template)
                <button type="button" wire:click="chooseTemplate('{{ $key }}')" wire:key="template-{{ $key }}" data-template="{{ $key }}" class="flex cursor-pointer items-start gap-3 rounded-[14px] bg-white p-4 text-left shadow-[0_1px_3px_rgba(0,0,0,0.06)] transition hover:ring-2 hover:ring-accent/40">
                    <span class="text-[24px] leading-none" aria-hidden="true">{{ $template['icon'] }}</span>
                    <span class="flex flex-col">
                        <span class="text-[14px] font-bold text-ink">{{ $template['label'] }}</span>
                        <span class="text-[12.5px] text-ink/55">{{ $template['description'] }}</span>
                    </span>
                </button>
            @endforeach
        </div>
    @else
        <x-ui.page-header :title="$groupId ? 'Editar grupo' : 'Novo grupo'">
            <x-ui.button variant="secondary" wire:click="back">Voltar</x-ui.button>
        </x-ui.page-header>

        <form wire:submit="save" class="grid gap-[18px] lg:grid-cols-[minmax(0,1fr)_320px]" data-group-form>
            <div class="flex flex-col gap-[18px]">
                <x-ui.card title="Como o cliente escolhe">
                    <div class="grid gap-4">
                        <x-ui.field label="Nome do grupo (o cliente vê)" for="group-name" error="name">
                            <x-ui.input id="group-name" wire:model.live.debounce.300ms="name" maxlength="80" placeholder="Ex.: Adicionais, Tamanho, Borda" />
                        </x-ui.field>

                        <div class="grid gap-2 sm:grid-cols-2">
                            <label class="flex cursor-pointer flex-col rounded-xl border p-3 text-[13px] has-[:checked]:border-accent has-[:checked]:bg-accent/5">
                                <input type="radio" wire:model.live="mode" value="one" class="sr-only">
                                <span class="font-bold text-ink">Só 1 opção</span>
                                <span class="text-ink/55">Ex.: tamanho, borda</span>
                            </label>
                            <label class="flex cursor-pointer flex-col rounded-xl border p-3 text-[13px] has-[:checked]:border-accent has-[:checked]:bg-accent/5">
                                <input type="radio" wire:model.live="mode" value="many" class="sr-only">
                                <span class="font-bold text-ink">Várias opções</span>
                                <span class="text-ink/55">Ex.: adicionais, molhos</span>
                            </label>
                        </div>

                        @if ($mode === 'many')
                            <x-ui.field label="Até quantas escolhas?" for="max_choices" error="max_choices" hint="Somando todas as opções do grupo.">
                                <x-ui.input id="max_choices" type="number" min="1" max="{{ \App\Actions\Menu\SaveOptionGroup::MAX_CHOICES }}" wire:model.live="max_choices" class="!w-28" />
                            </x-ui.field>
                        @endif

                        <label class="flex items-start gap-3 text-[13.5px] font-semibold text-ink">
                            <input type="checkbox" wire:model.live="is_required" class="mt-0.5 size-4 accent-[var(--accent)]">
                            <span>Obrigatório <span class="block text-[12px] font-normal text-ink/55">O cliente só consegue adicionar depois de escolher.</span></span>
                        </label>

                        @if ($mode === 'many')
                            <label class="flex items-start gap-3 text-[13.5px] font-semibold text-ink">
                                <input type="checkbox" wire:model.live="allow_repeat" class="mt-0.5 size-4 accent-[var(--accent)]">
                                <span>Pode repetir a mesma opção <span class="block text-[12px] font-normal text-ink/55">Ex.: 2× bacon. Aparece um botão de quantidade.</span></span>
                            </label>
                        @endif

                        <label class="flex items-start gap-3 text-[13.5px] font-semibold text-ink">
                            <input type="checkbox" wire:model="is_active" class="mt-0.5 size-4 accent-[var(--accent)]">
                            <span>Grupo ativo <span class="block text-[12px] font-normal text-ink/55">Desligado, o grupo some dos produtos sem ser excluído.</span></span>
                        </label>
                    </div>
                </x-ui.card>

                <x-ui.card title="Opções">
                    <x-slot:actions><span class="text-[12px] text-ink/45">Preço extra (vazio = grátis)</span></x-slot:actions>

                    @error('options') <p class="mb-2 text-[12.5px] text-danger" role="alert">{{ $message }}</p> @enderror

                    <div class="flex flex-col gap-2.5" data-options>
                        @foreach ($options as $index => $option)
                            <div wire:key="option-{{ $index }}" class="flex flex-wrap items-start gap-2">
                                <x-ui.field error="options.{{ $index }}.name" class="min-w-[160px] flex-1">
                                    <x-ui.input wire:model.live.debounce.300ms="options.{{ $index }}.name" placeholder="Nome da opção" aria-label="Nome da opção {{ $index + 1 }}" wire:keydown.enter.prevent="addOption" />
                                </x-ui.field>
                                <x-ui.field error="options.{{ $index }}.price" class="w-32">
                                    <x-ui.input wire:model.live.debounce.300ms="options.{{ $index }}.price" placeholder="grátis" inputmode="decimal" aria-label="Preço da opção {{ $index + 1 }}" />
                                </x-ui.field>
                                <label class="flex items-center gap-1.5 pt-2.5 text-[12px] font-semibold text-ink/60">
                                    <input type="checkbox" wire:model.live="options.{{ $index }}.is_available" class="size-4 accent-[var(--accent)]"> Disponível
                                </label>
                                <x-ui.button size="sm" variant="danger" type="button" wire:click="removeOption({{ $index }})" class="mt-1">Remover</x-ui.button>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-3">
                        <x-ui.button size="sm" variant="secondary" type="button" wire:click="addOption">Adicionar opção</x-ui.button>
                        <p class="mt-2 text-[12px] text-ink/45">Dica: aperte Enter no nome para criar a próxima opção.</p>
                    </div>
                </x-ui.card>

                <x-ui.card title="Usar nos produtos">
                    @error('dish_ids') <p class="mb-2 text-[12.5px] text-danger" role="alert">{{ $message }}</p> @enderror
                    @if ($this->dishes->isEmpty())
                        <p class="text-[13px] text-ink/50">Cadastre produtos para ligar este grupo a eles.</p>
                    @else
                        <div class="flex flex-wrap gap-2" data-group-dishes>
                            @foreach ($this->dishes as $dish)
                                <label wire:key="dish-{{ $dish->id }}" class="cursor-pointer">
                                    <input type="checkbox" value="{{ $dish->id }}" wire:model="dish_ids" class="peer sr-only">
                                    <span class="inline-flex rounded-full border border-ink/15 px-3.5 py-1.5 text-[12.5px] font-bold text-ink/70 transition peer-checked:border-accent peer-checked:bg-accent peer-checked:text-white">{{ $dish->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </x-ui.card>

                <div class="flex gap-2.5">
                    <x-ui.button type="submit" size="lg" wire:loading.attr="disabled" wire:target="save">Salvar grupo</x-ui.button>
                    <x-ui.button variant="secondary" size="lg" type="button" wire:click="back">Cancelar</x-ui.button>
                </div>
            </div>

            {{-- Live preview of what the customer sees --}}
            <aside class="flex flex-col gap-2 lg:sticky lg:top-6 lg:self-start" data-group-preview>
                <p class="text-[12px] font-semibold text-ink/55">Como o cliente vê</p>
                <div class="overflow-hidden rounded-[14px] bg-white shadow-[0_1px_3px_rgba(0,0,0,0.06)]">
                    <div class="flex items-start justify-between gap-2 bg-ink/[0.04] px-4 py-3">
                        <div>
                            <p class="text-[14px] font-bold text-ink">{{ $name !== '' ? $name : 'Nome do grupo' }}</p>
                            <p class="text-[12px] text-ink/55">{{ $mode === 'one' ? 'Escolha 1' : 'Escolha até '.$max_choices.' · 0/'.$max_choices }}</p>
                        </div>
                        <span class="rounded-full px-2.5 py-0.5 text-[10.5px] font-bold uppercase {{ $is_required ? 'bg-accent text-white' : 'bg-ink/10 text-ink/55' }}">{{ $is_required ? 'Obrigatório' : 'Opcional' }}</span>
                    </div>
                    <ul class="divide-y divide-ink/[0.06]">
                        @forelse (collect($options)->filter(fn ($option) => trim($option['name']) !== '') as $option)
                            <li class="flex items-center justify-between gap-3 px-4 py-2.5 {{ ($option['is_available'] ?? true) ? '' : 'opacity-40' }}">
                                <div>
                                    <p class="text-[13.5px] font-semibold text-ink">{{ $option['name'] }}</p>
                                    @if (trim((string) $option['price']) !== '' && \App\Support\Money::parse((string) $option['price']) !== null)
                                        <p class="text-[12px] text-ink/50">+ {{ \App\Support\Money::brl(\App\Support\Money::parse((string) $option['price'])) }}</p>
                                    @endif
                                </div>
                                <span class="flex size-8 items-center justify-center rounded-full border border-ink/20 text-[16px] text-ink/60" aria-hidden="true">+</span>
                            </li>
                        @empty
                            <li class="px-4 py-4 text-[13px] text-ink/45">Adicione opções para ver a prévia.</li>
                        @endforelse
                    </ul>
                </div>
            </aside>
        </form>
    @endif
</div>
