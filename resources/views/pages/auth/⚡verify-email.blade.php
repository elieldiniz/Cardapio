<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::auth')] #[Title('Confirme seu e-mail')] class extends Component
{
    public bool $sent = false;

    public function mount()
    {
        if (auth()->user()->hasVerifiedEmail()) {
            return $this->redirectRoute('panel.home');
        }
    }

    public function resend(): void
    {
        $key = 'verify-email:'.auth()->id();

        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError('resend', 'Muitos envios. Tente de novo em alguns minutos.');

            return;
        }

        \Illuminate\Support\Facades\RateLimiter::hit($key, 600);
        auth()->user()->sendEmailVerificationNotification();
        $this->sent = true;
    }
};
?>

<div class="flex flex-col gap-4">
    <div>
        <h1 class="font-serif text-[26px] text-ink">Confirme seu e-mail</h1>
        <p class="mt-1 text-[13.5px] leading-relaxed text-ink/60">
            Enviamos um link de confirmação para <strong class="text-ink">{{ auth()->user()->email }}</strong>.
            Clique nele para liberar o painel do seu restaurante.
        </p>
    </div>

    @if ($sent)
        <div class="rounded-xl bg-success/10 p-3.5 text-[13.5px] font-medium text-success" role="status">Enviamos um novo link. Confira também a caixa de spam.</div>
    @endif
    @error('resend')
        <div class="rounded-xl bg-danger/8 p-3.5 text-[13.5px] font-medium text-danger" role="alert">{{ $message }}</div>
    @enderror

    <x-ui.button size="lg" class="w-full" wire:click="resend" wire:loading.attr="disabled">Reenviar link</x-ui.button>

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <button type="submit" class="text-[13px] font-semibold text-ink/55 hover:text-ink">Sair e entrar com outra conta</button>
    </form>
</div>
