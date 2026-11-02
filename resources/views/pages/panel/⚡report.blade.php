<?php

use App\Services\Cash\ReportBuilder;
use App\Support\Money;
use App\Support\StorePeriod;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * Relatório financeiro (RF-12, UI-01): o que vendeu, o que entrou de verdade, o que saiu e o
 * resultado do período, nos dias do fuso da loja. A conta fica no ReportBuilder.
 */
new #[Layout('layouts::panel')] #[Title('Relatório')] class extends Component
{
    #[Url(as: 'periodo', except: 'hoje')]
    public string $period = 'hoje';

    #[Computed]
    public function restaurant()
    {
        return auth()->user()->restaurant;
    }

    #[Computed]
    public function report(): array
    {
        $period = in_array($this->period, StorePeriod::KEYS, true) && $this->period !== 'ontem_hoje' ? $this->period : 'hoje';

        return app(ReportBuilder::class)->build($this->restaurant, $period);
    }
};
?>

<div class="flex flex-col gap-[18px]">
    <x-ui.page-header title="Relatório" subtitle="Vendas, o que entrou de verdade, despesas e o resultado do período." />

    <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="Período">
        @foreach (['hoje', 'ontem', '7dias', 'mes', 'mes_passado'] as $key)
            <button type="button" role="tab" wire:click="$set('period', '{{ $key }}')" data-period="{{ $key }}" aria-selected="{{ $period === $key ? 'true' : 'false' }}" class="rounded-full px-3.5 py-1.5 text-[12.5px] font-bold transition {{ $period === $key ? 'bg-ink text-white' : 'bg-ink/[0.06] text-ink/60 hover:bg-ink/10' }}">{{ StorePeriod::label($key) }}</button>
        @endforeach
    </div>

    @php
        $report = $this->report;
        $origins = ['direto' => 'Direto', 'anuncio' => 'Anúncio', 'instagram' => 'Instagram', 'whatsapp' => 'WhatsApp', 'facebook' => 'Facebook', 'qrcode' => 'QR Code', 'balcao' => 'Balcão', 'outro' => 'Outros'];
    @endphp

    <div class="flex flex-wrap gap-3.5" data-report>
        <x-ui.stat-card label="Vendas" :value="Money::brl($report['sales_total'])" :hint="$report['sales_count'].' '.($report['sales_count'] === 1 ? 'venda' : 'vendas').' · ticket '.Money::brl($report['ticket'])" data-sales />
        <x-ui.stat-card label="Recebido" :value="Money::brl($report['received'])" hint="dinheiro que entrou" data-received />
        <x-ui.stat-card label="Despesas" :value="Money::brl($report['expenses'])" data-expenses />
        <x-ui.stat-card label="Resultado" :value="Money::brl($report['result'])" hint="recebido − despesas" data-result />
    </div>

    <x-ui.card title="Como os clientes pagaram">
        <dl class="grid gap-3 text-[14px] sm:grid-cols-3" data-by-method>
            <div class="rounded-xl bg-ink/[0.04] p-3.5"><dt class="text-ink/55">⚡ Pix</dt><dd class="mt-0.5 text-[18px] font-extrabold text-ink">{{ Money::brl($report['by_method']['pix']) }}</dd></div>
            <div class="rounded-xl bg-ink/[0.04] p-3.5"><dt class="text-ink/55">💵 Dinheiro</dt><dd class="mt-0.5 text-[18px] font-extrabold text-ink">{{ Money::brl($report['by_method']['dinheiro']) }}</dd></div>
            <div class="rounded-xl bg-ink/[0.04] p-3.5"><dt class="text-ink/55">💳 Cartão</dt><dd class="mt-0.5 text-[18px] font-extrabold text-ink">{{ Money::brl($report['by_method']['cartao']) }}</dd></div>
        </dl>
    </x-ui.card>

    <x-ui.card title="De onde vieram as vendas">
        @if ($report['by_origin'] === [])
            <p class="text-[13.5px] text-ink/50" data-no-origin>Nenhuma venda no período.</p>
        @else
            <table class="w-full text-[13.5px]" data-by-origin>
                <thead class="text-left text-[11.5px] font-bold uppercase tracking-wide text-ink/45">
                    <tr><th class="pb-2">Origem</th><th class="pb-2 text-right">Pedidos</th><th class="pb-2 text-right">Vendas</th></tr>
                </thead>
                <tbody>
                    @foreach ($report['by_origin'] as $row)
                        <tr class="border-t border-ink/[0.06]" data-origin="{{ $row['origin'] }}">
                            <td class="py-2 font-semibold text-ink">{{ $origins[$row['origin']] ?? ucfirst($row['origin']) }}</td>
                            <td class="py-2 text-right">{{ $row['orders'] }}</td>
                            <td class="py-2 text-right font-bold">{{ Money::brl($row['total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>
</div>
