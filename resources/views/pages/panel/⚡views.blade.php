<?php

use App\Models\DishView;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::panel')] #[Title('Visualizações')] class extends Component
{
    public const PERIODS = [7 => 'Últimos 7 dias', 30 => 'Últimos 30 dias', 90 => 'Últimos 90 dias'];

    #[Url]
    public int $days = 30;

    #[Computed]
    public function restaurant()
    {
        return auth()->user()->restaurant->load('plan.metricsLevel');
    }

    /**
     * cardapio_total → only the menu total; por_prato → per-dish views;
     * por_prato_com_tempo_assistido → per-dish views + average watch time (US-6.3).
     */
    #[Computed]
    public function level(): string
    {
        return $this->restaurant->plan?->metricsLevel?->slug ?? 'cardapio_total';
    }

    private function since()
    {
        return today()->subDays(max(1, array_key_exists($this->days, self::PERIODS) ? $this->days : 30) - 1);
    }

    /**
     * @return array{views: int, sessions: int}
     */
    #[Computed]
    public function totals(): array
    {
        $row = DishView::query()
            ->where('restaurant_id', $this->restaurant->id)
            ->whereDate('viewed_on', '>=', $this->since())
            ->selectRaw('count(*) as views, count(distinct session_token) as sessions')
            ->first();

        return ['views' => (int) $row->views, 'sessions' => (int) $row->sessions];
    }

    #[Computed]
    public function perDish()
    {
        if ($this->level === 'cardapio_total') {
            return collect();
        }

        return DishView::query()
            ->where('dish_views.restaurant_id', $this->restaurant->id)
            ->whereDate('viewed_on', '>=', $this->since())
            ->join('dishes', 'dishes.id', '=', 'dish_views.dish_id')
            ->groupBy('dish_views.dish_id', 'dishes.name')
            ->select('dishes.name', DB::raw('count(*) as views'), DB::raw('avg(dish_views.seconds_watched) as avg_seconds'))
            ->orderByDesc('views')
            ->get();
    }
};
?>

@php
    $withTime = $this->level === 'por_prato_com_tempo_assistido';
    $maxViews = max(1, (int) $this->perDish->max('views'));
@endphp

<div class="flex flex-col gap-[18px]" data-metrics-level="{{ $this->level }}">
    <x-ui.page-header title="Visualizações" subtitle="Quais pratos chamam mais atenção no seu cardápio.">
        <x-ui.select wire:model.live="days" class="w-auto" aria-label="Período">
            @foreach ($this::PERIODS as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </x-ui.select>
    </x-ui.page-header>

    <div class="flex flex-wrap gap-3.5">
        <x-ui.stat-card label="Visualizações de pratos" :value="number_format($this->totals['views'], 0, ',', '.')" data-total-views />
        <x-ui.stat-card label="Visitas ao cardápio" :value="number_format($this->totals['sessions'], 0, ',', '.')" />
    </div>

    @if ($this->level === 'cardapio_total')
        <x-ui.card>
            <x-ui.empty-state icon="views" title="Veja os números de cada prato" description="No seu plano você vê o total do cardápio. Planos pagos mostram visualizações por prato e o tempo médio assistido.">
                <x-ui.button :href="route('panel.subscription')" wire:navigate>Conhecer os planos</x-ui.button>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.card title="Por prato" :padding="false">
            @if ($this->perDish->isEmpty())
                <x-ui.empty-state icon="views" title="Sem visualizações no período" description="Assim que clientes abrirem o cardápio pelo QR Code, os pratos aparecem aqui." />
            @else
                <table class="w-full text-left text-[13.5px]" data-per-dish>
                    <thead class="text-[11.5px] font-bold tracking-wide text-ink/45 uppercase">
                        <tr>
                            <th class="px-5 py-2.5 sm:px-6">Prato</th>
                            <th class="px-3 py-2.5 text-right">Visualizações</th>
                            @if ($withTime)
                                <th class="px-5 py-2.5 text-right sm:px-6">Tempo médio</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->perDish as $row)
                            <tr class="border-t border-ink/[0.06]">
                                <td class="px-5 py-3 sm:px-6">
                                    <span class="font-semibold text-ink">{{ $row->name }}</span>
                                    <span class="mt-1.5 block h-1.5 rounded-full bg-accent/80" style="width: {{ round($row->views / $maxViews * 100) }}%"></span>
                                </td>
                                <td class="px-3 py-3 text-right font-bold text-ink">{{ number_format($row->views, 0, ',', '.') }}</td>
                                @if ($withTime)
                                    <td class="px-5 py-3 text-right text-ink/70 sm:px-6">{{ number_format((float) $row->avg_seconds, 1, ',', '.') }}s</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-ui.card>

        @unless ($withTime)
            <p class="text-[12.5px] text-ink/50">O tempo médio assistido por prato está disponível no plano Pro.</p>
        @endunless
    @endif
</div>
