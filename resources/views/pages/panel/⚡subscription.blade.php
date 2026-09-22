<?php

use App\Models\GenerationLedger;
use App\Models\Plan;
use App\Models\VideoAddonPackage;
use App\Support\Money;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::panel')] #[Title('Assinatura')] class extends Component
{
    #[Computed]
    public function restaurant()
    {
        return auth()->user()->restaurant->load(['plan.metricsLevel', 'generationBalance']);
    }

    /**
     * Paid plans on sale, from the super admin's catalog (US-7.3).
     */
    #[Computed]
    public function plans()
    {
        return Plan::query()
            ->where('is_active', true)
            ->whereNotNull('stripe_price_id')
            ->where('price_cents', '>', 0)
            ->orderBy('price_cents')
            ->get();
    }

    #[Computed]
    public function packages()
    {
        return VideoAddonPackage::query()
            ->where('is_active', true)
            ->whereNotNull('stripe_price_id')
            ->orderBy('generations_count')
            ->get();
    }

    #[Computed]
    public function subscription()
    {
        return $this->restaurant->subscription('default');
    }

    /**
     * Generations used since the start of the month (US-5.4).
     */
    #[Computed]
    public function usedThisMonth(): int
    {
        return (int) abs(GenerationLedger::query()
            ->where('restaurant_id', $this->restaurant->id)
            ->whereHas('type', fn ($type) => $type->where('slug', 'uso'))
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('quantity'));
    }

    /**
     * Upgrade (US-5.2): Stripe Checkout in subscription mode, BRL, card only.
     */
    public function subscribe(int $planId)
    {
        $plan = $this->plans->firstWhere('id', $planId) ?? abort(404);

        if ($this->subscription?->valid()) {
            $this->subscription->swap($plan->stripe_price_id);
            $this->dispatch('toast', message: "Plano alterado para {$plan->name}. A mudança é confirmada pela Stripe em instantes.");

            return null;
        }

        $checkout = $this->restaurant
            ->newSubscription('default', $plan->stripe_price_id)
            ->checkout([
                'success_url' => route('panel.subscription', ['checkout' => 'sucesso']),
                'cancel_url' => route('panel.subscription'),
                'payment_method_types' => ['card'],
                'locale' => 'pt-BR',
            ]);

        return redirect()->away($checkout->asStripeCheckoutSession()->url);
    }

    /**
     * Addon package (US-5.3): Stripe Checkout in one-time payment mode.
     */
    public function buyPackage(int $packageId)
    {
        $package = $this->packages->firstWhere('id', $packageId) ?? abort(404);

        $checkout = $this->restaurant->checkout([$package->stripe_price_id => 1], [
            'mode' => 'payment',
            'success_url' => route('panel.subscription', ['checkout' => 'pacote']),
            'cancel_url' => route('panel.subscription'),
            'payment_method_types' => ['card'],
            'locale' => 'pt-BR',
            'metadata' => ['addon_package_id' => $package->id],
        ]);

        return redirect()->away($checkout->asStripeCheckoutSession()->url);
    }

    /**
     * Stripe Customer Portal: card, invoices, cancellation (US-5.4).
     */
    public function openPortal()
    {
        $this->restaurant->createOrGetStripeCustomer();

        return redirect()->away($this->restaurant->billingPortalUrl(route('panel.subscription')));
    }
};
?>

@php
    $plan = $this->restaurant->plan;
    $balance = $this->restaurant->generationBalance;
    $isPaid = $this->subscription?->valid();
    $metricsLabels = ['cardapio_total' => 'Total do cardápio', 'por_prato' => 'Por prato', 'por_prato_com_tempo_assistido' => 'Por prato + tempo assistido'];
@endphp

<div class="flex flex-col gap-[18px]">
    <x-ui.page-header title="Assinatura" subtitle="Seu plano, suas gerações de vídeo e sua cobrança." />

    @if (request('checkout') === 'sucesso')
        <div class="rounded-xl bg-success/10 px-4 py-3 text-[13.5px] font-medium text-success" role="status">Pagamento recebido! Seu novo plano é ativado assim que a Stripe confirmar — normalmente em alguns segundos.</div>
    @elseif (request('checkout') === 'pacote')
        <div class="rounded-xl bg-success/10 px-4 py-3 text-[13.5px] font-medium text-success" role="status">Compra recebida! As gerações extras entram no seu saldo assim que a Stripe confirmar o pagamento.</div>
    @endif

    <div class="flex flex-wrap gap-3.5">
        <x-ui.stat-card label="Plano atual" :value="$plan->name" data-current-plan />
        <x-ui.stat-card label="Gerações usadas no mês" :value="$this->usedThisMonth" data-used-this-month />
        <x-ui.stat-card label="Saldo mensal" :value="$balance?->monthly_balance ?? 0" />
        <x-ui.stat-card label="Saldo avulso (não expira)" :value="$balance?->addon_balance ?? 0" />
    </div>

    <x-ui.card title="Seu plano">
        <ul class="grid gap-2 text-[13.5px] text-ink/70 sm:grid-cols-2">
            <li><strong class="text-ink">{{ $plan->price_cents ? Money::brlFromCents($plan->price_cents).'/mês' : 'Grátis' }}</strong></li>
            <li>Pratos: <strong class="text-ink">{{ $this->restaurant->dishes()->count() }} de {{ $plan->dish_limit ?? 'ilimitados' }}</strong></li>
            <li>Gerações por mês: <strong class="text-ink">{{ $plan->monthly_generations }}</strong>@if ($plan->initial_generations) · {{ $plan->initial_generations }} de degustação (uma vez)@endif</li>
            <li>Métricas: <strong class="text-ink">{{ $metricsLabels[$plan->metricsLevel?->slug] ?? '—' }}</strong></li>
            <li>Marca "feito com": <strong class="text-ink">{{ $plan->removes_branding ? 'removida' : 'exibida' }}</strong></li>
            @if ($balance?->renews_at)
                <li>Renova em: <strong class="text-ink">{{ $balance->renews_at->format('d/m/Y') }}</strong></li>
            @endif
        </ul>

        @if ($this->restaurant->hasStripeId())
            <div class="mt-4 border-t border-ink/[0.06] pt-4">
                <x-ui.button variant="secondary" wire:click="openPortal" data-portal>Gerenciar cartão, faturas e cancelamento</x-ui.button>
            </div>
        @endif
    </x-ui.card>

    <x-ui.card title="Planos">
        @if ($this->plans->isEmpty())
            <p class="text-[13.5px] text-ink/55">Nenhum plano pago disponível no momento.</p>
        @else
            <div class="grid gap-3.5 sm:grid-cols-2">
                @foreach ($this->plans as $option)
                    @php $isCurrent = $option->id === $plan->id; @endphp
                    <div wire:key="plan-{{ $option->id }}" class="flex flex-col gap-3 rounded-xl border {{ $isCurrent ? 'border-accent' : 'border-ink/10' }} p-4" data-plan="{{ $option->name }}">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="font-serif text-[20px] text-ink">{{ $option->name }}</span>
                            <span class="text-[15px] font-bold text-ink">{{ Money::brlFromCents($option->price_cents) }}<span class="text-[12px] font-medium text-ink/50">/mês</span></span>
                        </div>
                        <ul class="flex flex-col gap-1 text-[13px] text-ink/65">
                            <li>{{ $option->dish_limit ? 'Até '.$option->dish_limit.' pratos' : 'Pratos ilimitados' }}</li>
                            <li>{{ $option->monthly_generations }} gerações de vídeo por mês</li>
                            <li>Métricas: {{ $metricsLabels[$option->metricsLevel?->slug] ?? '—' }}</li>
                            @if ($option->removes_branding)
                                <li>Sem a marca "feito com"</li>
                            @endif
                        </ul>
                        @if ($isCurrent)
                            <span class="mt-auto text-[12.5px] font-bold text-accent">Seu plano atual</span>
                        @else
                            <x-ui.button class="mt-auto" wire:click="subscribe({{ $option->id }})" wire:loading.attr="disabled">{{ $isPaid ? 'Trocar para '.$option->name : 'Assinar '.$option->name }}</x-ui.button>
                        @endif
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-[12px] text-ink/45">Pagamento em reais, somente cartão de crédito, processado pela Stripe.</p>
        @endif
    </x-ui.card>

    <x-ui.card title="Pacotes de gerações avulsas" id="pacotes">
        <p class="mb-3 text-[13px] text-ink/55">Gerações avulsas não expiram e são usadas só depois que o saldo mensal acaba.</p>
        @if ($this->packages->isEmpty())
            <p class="text-[13.5px] text-ink/55">Nenhum pacote disponível no momento.</p>
        @else
            <div class="flex flex-wrap gap-3">
                @foreach ($this->packages as $package)
                    <div wire:key="package-{{ $package->id }}" class="flex min-w-[180px] flex-1 flex-col gap-2 rounded-xl border border-ink/10 p-4" data-package="{{ $package->name }}">
                        <span class="text-[14px] font-bold text-ink">{{ $package->name }}</span>
                        <span class="text-[13px] text-ink/60">{{ $package->generations_count }} gerações · {{ Money::brlFromCents($package->price_cents) }}</span>
                        <x-ui.button size="sm" variant="secondary" wire:click="buyPackage({{ $package->id }})" wire:loading.attr="disabled">Comprar</x-ui.button>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.card>
</div>
