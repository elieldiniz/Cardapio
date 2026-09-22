<?php

use App\Support\HomeRedirect;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::auth')] #[Title('Entrar')] class extends Component
{
    /**
     * The single message for any failed attempt, so it never reveals whether
     * the e-mail exists (US-8.2).
     */
    public const INVALID_CREDENTIALS = 'E-mail ou senha inválidos.';

    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login()
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->addError('email', 'Muitas tentativas. Tente novamente em '.RateLimiter::availableIn($throttleKey).' segundos.');

            return;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey);
            $this->reset('password');
            $this->addError('email', self::INVALID_CREDENTIALS);

            return;
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        return $this->redirectIntended(HomeRedirect::for(Auth::user()));
    }
};
?>

<form wire:submit="login" class="flex flex-col gap-4" novalidate>
    <div>
        <h1 class="font-serif text-[26px] text-ink">Entrar</h1>
        <p class="mt-1 text-[13.5px] text-ink/55">Acesse o painel do seu restaurante.</p>
    </div>

    <x-ui.field label="E-mail" for="email" error="email">
        <x-ui.input id="email" type="email" wire:model="email" autocomplete="email" required autofocus />
    </x-ui.field>

    <x-ui.field label="Senha" for="password" error="password">
        <x-ui.input id="password" type="password" wire:model="password" autocomplete="current-password" required />
    </x-ui.field>

    <div class="flex items-center justify-between gap-3 text-[13px]">
        <label class="flex items-center gap-2 text-ink/65">
            <input type="checkbox" wire:model="remember" class="size-4 rounded accent-[var(--accent,#ff6b3d)]">
            Manter conectado
        </label>
        <a href="{{ route('password.request') }}" class="font-semibold text-accent hover:underline" wire:navigate>Esqueci minha senha</a>
    </div>

    <x-ui.button type="submit" size="lg" class="w-full" wire:loading.attr="disabled">Entrar</x-ui.button>

    <p class="text-center text-[13px] text-ink/55">
        Ainda não tem conta? <a href="{{ route('register') }}" class="font-semibold text-accent hover:underline" wire:navigate>Criar conta grátis</a>
    </p>
</form>
