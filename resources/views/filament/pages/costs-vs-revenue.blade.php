@php
    $summary = $this->getSummary();
    $brl = fn (?int $cents) => $cents === null ? '—' : \App\Support\Money::brlFromCents($cents);
    $usd = fn (float $value) => 'US$ '.number_format($value, 2, ',', '.');
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Mês</x-slot>
        <div class="flex flex-wrap items-end gap-4">
            <x-filament::input.wrapper>
                <x-filament::input type="month" wire:model.live="month" />
            </x-filament::input.wrapper>
        </div>
    </x-filament::section>

    <div class="grid gap-6 md:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">Custos</x-slot>
            <dl class="grid gap-3 text-sm">
                <div class="flex justify-between"><dt>IA (soma do custo das gerações)</dt><dd class="font-semibold" data-ai-cost>{{ $usd($summary['ai_cost_usd']) }}</dd></div>
                <div class="flex justify-between"><dt>Hospedagem de vídeo (Mux)</dt><dd class="font-semibold" data-mux-cost>{{ $usd($summary['mux_cost_usd']) }}</dd></div>
                <div class="flex justify-between border-t pt-3 dark:border-white/10"><dt class="font-semibold">Total</dt><dd class="font-bold">{{ $usd($summary['total_cost_usd']) }}</dd></div>
                <div class="flex justify-between"><dt>Total em reais</dt><dd class="font-semibold">{{ $brl($summary['total_cost_brl_cents']) }}</dd></div>
            </dl>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Receita</x-slot>
            <dl class="grid gap-3 text-sm">
                <div class="flex justify-between"><dt>MRR (assinaturas ativas)</dt><dd class="font-semibold" data-mrr>{{ $brl($summary['mrr_cents']) }}</dd></div>
                <div class="flex justify-between"><dt>Pacotes avulsos no mês</dt><dd class="font-semibold" data-addon-revenue>{{ $brl($summary['addon_revenue_cents']) }}</dd></div>
                <div class="flex justify-between border-t pt-3 dark:border-white/10"><dt class="font-semibold">Total</dt><dd class="font-bold">{{ $brl($summary['revenue_cents']) }}</dd></div>
                <div class="flex justify-between">
                    <dt class="font-semibold">Margem</dt>
                    <dd @class(['font-bold', 'text-danger-600' => ($summary['margin_cents'] ?? 0) < 0, 'text-success-600' => ($summary['margin_cents'] ?? 0) >= 0]) data-margin>
                        {{ $summary['margin_cents'] === null ? 'Informe a cotação do dólar' : $brl($summary['margin_cents']) }}
                    </dd>
                </div>
            </dl>
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Custos informados manualmente</x-slot>
        <x-slot name="description">A fatura do Mux e a cotação do dólar do mês selecionado.</x-slot>

        <form wire:submit="save" class="grid gap-4 md:grid-cols-3">
            <label class="grid gap-1 text-sm font-medium">
                Custo do Mux no mês (US$)
                <x-filament::input.wrapper :valid="! $errors->has('muxCostUsd')">
                    <x-filament::input type="number" step="0.01" min="0" wire:model="muxCostUsd" />
                </x-filament::input.wrapper>
                @error('muxCostUsd') <span class="text-danger-600 text-xs">{{ $message }}</span> @enderror
            </label>
            <label class="grid gap-1 text-sm font-medium">
                Cotação US$ → R$
                <x-filament::input.wrapper :valid="! $errors->has('usdBrlRate')">
                    <x-filament::input type="number" step="0.0001" min="0" wire:model="usdBrlRate" />
                </x-filament::input.wrapper>
                @error('usdBrlRate') <span class="text-danger-600 text-xs">{{ $message }}</span> @enderror
            </label>
            <div class="flex items-end">
                <x-filament::button type="submit">Salvar</x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
