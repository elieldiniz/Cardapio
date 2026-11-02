{{--
    The free plan's 14-day trial (everything unlocked), then a notice that it ended.
    Renders nothing for paid plans.
--}}
@php
    $restaurant = auth()->user()?->restaurant;
@endphp

@if ($restaurant?->onTrial())
    <div data-trial-banner role="status" {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-3 gap-y-1 rounded-[12px] bg-accent/10 px-4 py-3 text-[13px] text-ink']) }}>
        <span class="font-bold">🎁 Teste completo: {{ $restaurant->trialDaysLeft() === 1 ? 'último dia' : 'faltam '.$restaurant->trialDaysLeft().' dias' }}.</span>
        <span class="text-ink/65">Todos os recursos estão liberados. Depois, o plano Grátis mantém só pedidos e cardápio.</span>
        <a href="{{ route('panel.subscription') }}" wire:navigate class="font-bold underline">Ver planos</a>
    </div>
@elseif ($restaurant?->trialEnded())
    <div data-trial-ended-banner role="status" {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-3 gap-y-1 rounded-[12px] bg-ink/[0.06] px-4 py-3 text-[13px] text-ink']) }}>
        <span class="font-bold">Seu teste de 14 dias terminou.</span>
        <span class="text-ink/65">Seus dados estão guardados. Assine para voltar a usar caixa, PDV, cupons e mais.</span>
        <a href="{{ route('panel.subscription') }}" wire:navigate class="font-bold underline">Ver planos</a>
    </div>
@endif
