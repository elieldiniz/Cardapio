<?php

use App\Filament\Resources\Plans\Schemas\PlanForm;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Shown instead of a feature the plan does not include once the 14-day trial is over.
 * Nothing the owner created is lost: it comes back when the plan includes the feature again.
 */
new #[Layout('layouts::panel')] #[Title('Recurso do plano')] class extends Component
{
    public string $feature = '';

    public function mount(string $feature): void
    {
        abort_unless(array_key_exists($feature, PlanForm::FEATURES), 404);

        $this->feature = $feature;
    }

    #[Computed]
    public function restaurant()
    {
        return auth()->user()->restaurant;
    }

    #[Computed]
    public function label(): string
    {
        return PlanForm::FEATURES[$this->feature][0];
    }

    #[Computed]
    public function neededPlan()
    {
        return $this->restaurant->planFor($this->feature);
    }
};
?>

<div class="flex flex-col gap-[18px]">
    <x-ui.page-header title="{{ $this->label }}" subtitle="Este recurso não está no seu plano atual." />

    <x-ui.card data-locked="{{ $feature }}">
        <div class="flex flex-col items-start gap-3">
            <p class="text-[15px] font-bold text-ink">🔒 {{ $this->label }}</p>
            <p class="text-[13.5px] text-ink/65">
                @if ($this->restaurant->trialEnded())
                    Seus 14 dias de teste com tudo liberado terminaram. Tudo o que você criou está guardado e volta assim que o plano incluir este recurso.
                @else
                    Tudo o que você criou aqui fica guardado e volta assim que o plano incluir este recurso.
                @endif
            </p>
            @if ($this->neededPlan)
                <p class="text-[13.5px] text-ink/65">Disponível a partir do plano <strong class="text-ink">{{ $this->neededPlan->name }}</strong>.</p>
            @endif
            <x-ui.button :href="route('panel.subscription')" wire:navigate>Ver planos</x-ui.button>
        </div>
    </x-ui.card>
</div>
