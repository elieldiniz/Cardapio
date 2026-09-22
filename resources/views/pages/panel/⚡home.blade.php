<?php

use App\Models\DishView;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::panel')] #[Title('Início')] class extends Component
{
    #[Computed]
    public function restaurant()
    {
        return auth()->user()->restaurant;
    }

    /**
     * The summary cards from the mockup's home screen.
     *
     * @return array<int, array{label: string, value: int}>
     */
    #[Computed]
    public function stats(): array
    {
        $dishes = $this->restaurant->dishes()->with('status')->get();

        return [
            ['label' => 'Pratos ativos', 'value' => $dishes->where('status.slug', 'ativo')->count()],
            ['label' => 'Esgotados', 'value' => $dishes->where('status.slug', 'esgotado')->count()],
            ['label' => 'Categorias', 'value' => $this->restaurant->categories()->count()],
            ['label' => 'Visualizações hoje', 'value' => DishView::query()
                ->where('restaurant_id', $this->restaurant->id)
                ->whereDate('viewed_on', today())
                ->count()],
        ];
    }
};
?>

<div class="flex flex-col gap-[22px]">
    <x-ui.page-header :title="'Olá, '.$this->restaurant->name" subtitle="Aqui está um resumo do seu cardápio hoje." />

    <div class="flex flex-wrap gap-3.5">
        @foreach ($this->stats as $stat)
            <x-ui.stat-card :label="$stat['label']" :value="$stat['value']" />
        @endforeach
    </div>

    <x-ui.card title="Ações rápidas">
        <div class="flex flex-wrap gap-2.5">
            @if (Route::has('panel.dishes.create'))
                <x-ui.button :href="route('panel.dishes.create')" wire:navigate>+ Novo prato</x-ui.button>
            @endif
            @if (Route::has('panel.categories'))
                <x-ui.button variant="secondary" :href="route('panel.categories')" wire:navigate>Gerenciar categorias</x-ui.button>
            @endif
            @if (Route::has('panel.qr-code'))
                <x-ui.button variant="secondary" :href="route('panel.qr-code')" wire:navigate>Ver QR Code</x-ui.button>
            @endif
        </div>
    </x-ui.card>
</div>
