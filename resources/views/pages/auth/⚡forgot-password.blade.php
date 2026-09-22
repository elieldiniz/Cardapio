<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::auth')] #[Title('Esqueci minha senha')] class extends Component
{
    /**
     * Shown whether or not the e-mail exists, so the form never reveals which
     * accounts are registered (US-8.3).
     */
    public const CONFIRMATION = 'Se este e-mail estiver cadastrado, você receberá um link para redefinir sua senha. O link vale por 60 minutos.';

    public string $email = '';

    public ?string $status = null;

    public function sendResetLink(): void
    {
        $this->validate(['email' => ['required', 'string', 'email']]);

        Password::sendResetLink(['email' => $this->email]);

        $this->status = self::CONFIRMATION;
    }
};
?>

<form wire:submit="sendResetLink" class="flex flex-col gap-4" novalidate>
    <div>
        <h1 class="font-serif text-[26px] text-ink">Esqueci minha senha</h1>
        <p class="mt-1 text-[13.5px] text-ink/55">Informe o e-mail da sua conta e enviaremos um link para criar uma nova senha.</p>
    </div>

    @if ($status)
        <div class="rounded-xl bg-success/10 p-3.5 text-[13.5px] font-medium text-success" role="status">{{ $status }}</div>
    @endif

    <x-ui.field label="E-mail" for="email" error="email">
        <x-ui.input id="email" type="email" wire:model="email" autocomplete="email" required />
    </x-ui.field>

    <x-ui.button type="submit" size="lg" class="w-full" wire:loading.attr="disabled">Enviar link</x-ui.button>

    <p class="text-center text-[13px] text-ink/55">
        <a href="{{ route('login') }}" class="font-semibold text-accent hover:underline" wire:navigate>Voltar para o login</a>
    </p>
</form>
