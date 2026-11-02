<?php

use App\Models\DishView;
use App\Services\Orders\OrderStats;
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
        return $this->restaurant->metricsLevelSlug();
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

    /**
     * Counters of orders and sales: open to every plan (RF-21).
     */
    #[Computed]
    public function orderCounters(): array
    {
        return app(OrderStats::class)->counters($this->restaurant, $this->days);
    }

    /**
     * Funnel, rankings and peak hour: only for plans with per-dish metrics (RF-21).
     */
    #[Computed]
    public function advanced(): ?array
    {
        return $this->level === 'cardapio_total' || ! $this->restaurant->hasFeature('orders')
            ? null
            : app(OrderStats::class)->advanced($this->restaurant, $this->days);
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
    <x-ui.page-header title="Visualizações" subtitle="Quais produtos chamam mais atenção no seu cardápio.">
        <x-ui.select wire:model.live="days" class="w-auto" aria-label="Período">
            @foreach ($this::PERIODS as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </x-ui.select>
    </x-ui.page-header>

    <div class="flex flex-wrap gap-3.5">
        <x-ui.stat-card label="Visualizações de produtos" :value="number_format($this->totals['views'], 0, ',', '.')" data-total-views />
        <x-ui.stat-card label="Visitas ao cardápio" :value="number_format($this->totals['sessions'], 0, ',', '.')" />
    </div>

    @if ($this->restaurant->hasFeature('orders'))
        @php($counters = $this->orderCounters)
        <div class="flex flex-wrap gap-3.5" data-order-counters>
            <x-ui.stat-card label="Pedidos pelo cardápio" :value="number_format($counters['orders'], 0, ',', '.')" data-total-orders />
            <x-ui.stat-card label="Vendas finalizadas" :value="\App\Support\Money::brl($counters['sales'])" />
            <x-ui.stat-card label="Origem dos pedidos" :value="$counters['by_origin'] === [] ? '—' : ucfirst(array_key_first($counters['by_origin']))" hint="canal que mais trouxe pedidos" />
        </div>

        @if ($this->advanced)
            @php($advanced = $this->advanced)
            <div class="grid gap-[18px] lg:grid-cols-2" data-advanced-stats>
                <x-ui.card title="Funil do pedido">
                    <ol class="flex flex-col gap-2" data-funnel>
                        @foreach ($advanced['funnel'] as $step)
                            <li class="flex items-center justify-between text-[13.5px]" data-step="{{ $step['key'] }}">
                                <span class="text-ink/70">{{ $step['label'] }}</span>
                                <span class="font-bold text-ink">{{ number_format($step['count'], 0, ',', '.') }}@if ($step['rate'] !== null) <span class="ml-1 text-[11.5px] font-medium text-ink/45">{{ $step['rate'] }}%</span>@endif</span>
                            </li>
                        @endforeach
                    </ol>
                </x-ui.card>
                <x-ui.card title="Rankings e horário de pico">
                    @foreach (['most_added' => 'Mais adicionados ao pedido', 'most_ordered' => 'Mais pedidos', 'top_options' => 'Complementos mais escolhidos'] as $key => $title)
                        <p class="mt-2 text-[12px] font-bold uppercase tracking-wide text-ink/45 first:mt-0">{{ $title }}</p>
                        <ul class="mt-1 text-[13.5px]" data-ranking="{{ $key }}">
                            @forelse ($advanced[$key] as $row)
                                <li class="flex justify-between py-0.5"><span class="text-ink/75">{{ $row['name'] }}</span><b>{{ $row['count'] }}</b></li>
                            @empty
                                <li class="py-0.5 text-ink/40">Sem dados no período.</li>
                            @endforelse
                        </ul>
                    @endforeach
                    <p class="mt-3 text-[12px] font-bold uppercase tracking-wide text-ink/45">Horário de pico</p>
                    <p class="mt-1 text-[13.5px] text-ink/75" data-peak>
                        {{ $advanced['peak_hours'] === [] ? 'Sem pedidos no período.' : collect($advanced['peak_hours'])->map(fn ($row) => sprintf('%02dh (%d)', $row['hour'], $row['orders']))->implode(' · ') }}
                    </p>
                </x-ui.card>
            </div>
        @endif
    @endif

    @if ($this->level === 'cardapio_total')
        <x-ui.card>
            <x-ui.empty-state icon="views" title="Veja os números de cada produto" description="No seu plano você vê o total do cardápio. Planos pagos mostram visualizações por produto e o tempo médio assistido.">
                <x-ui.button :href="route('panel.subscription')" wire:navigate>Conhecer os planos</x-ui.button>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.card title="Por produto" :padding="false">
            @if ($this->perDish->isEmpty())
                <x-ui.empty-state icon="views" title="Sem visualizações no período" description="Assim que clientes abrirem o cardápio pelo QR Code, os produtos aparecem aqui." />
            @else
                <table class="w-full text-left text-[13.5px]" data-per-dish>
                    <thead class="text-[11.5px] font-bold tracking-wide text-ink/45 uppercase">
                        <tr>
                            <th class="px-5 py-2.5 sm:px-6">Produto</th>
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
            <p class="text-[12.5px] text-ink/50">O tempo médio assistido por produto está disponível no plano Pro.</p>
        @endunless
    @endif
</div>
