<?php

use App\Models\DishView;
use App\Services\Panel\OnboardingProgress;
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

    /** The 9-step setup guide (RF-24). */
    #[Computed]
    public function guide(): array
    {
        return app(OnboardingProgress::class)->for($this->restaurant);
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
            ['label' => 'Produtos ativos', 'value' => $dishes->where('status.slug', 'ativo')->count()],
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

    @php($guide = $this->guide)
    @if ($guide['percent'] < 100)
        <x-ui.card data-guide>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-[15px] font-bold text-ink">Primeiros passos</h2>
                <span class="text-[13px] font-bold text-ink/60" data-guide-percent>{{ $guide['percent'] }}% · {{ $guide['done'] }} de {{ $guide['total'] }}</span>
            </div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-ink/10"><div class="h-full rounded-full bg-accent" style="width: {{ $guide['percent'] }}%"></div></div>
            <ol class="mt-3 flex flex-col gap-1.5">
                @foreach ($guide['steps'] as $step)
                    <li data-guide-step="{{ $step['key'] }}" data-done="{{ $step['done'] ? '1' : '0' }}">
                        <a href="{{ route($step['route']) }}" wire:navigate class="flex items-start gap-2.5 rounded-lg px-2 py-1.5 hover:bg-ink/[0.04]">
                            <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full text-[11px] font-bold {{ $step['done'] ? 'bg-emerald-600 text-white' : 'border border-ink/25 text-transparent' }}">✓</span>
                            <span>
                                <span class="block text-[13.5px] font-semibold {{ $step['done'] ? 'text-ink/45 line-through' : 'text-ink' }}">{{ $step['label'] }}</span>
                                @unless ($step['done']) <span class="block text-[12px] text-ink/50">{{ $step['hint'] }}</span> @endunless
                            </span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </x-ui.card>
    @endif

    <div class="flex flex-wrap gap-3.5">
        @foreach ($this->stats as $stat)
            <x-ui.stat-card :label="$stat['label']" :value="$stat['value']" />
        @endforeach
    </div>

    <x-ui.card title="Ações rápidas">
        <div class="flex flex-wrap gap-2.5">
            @if (Route::has('panel.dishes.create'))
                <x-ui.button :href="route('panel.dishes.create')" wire:navigate>+ Novo produto</x-ui.button>
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
