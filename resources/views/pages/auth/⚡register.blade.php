<?php

use App\Actions\RegisterRestaurantOwner;
use App\Support\HomeRedirect;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::auth')] #[Title('Criar conta')] class extends Component
{
    public string $name = '';

    public string $restaurant_name = '';

    public string $email = '';

    public string $password = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'restaurant_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
        ];
    }

    protected function messages(): array
    {
        return [
            'email.unique' => 'Este e-mail já está cadastrado.',
            'password.min' => 'A senha precisa ter pelo menos 8 caracteres.',
        ];
    }

    public function register(RegisterRestaurantOwner $registerOwner)
    {
        $data = $this->validate();

        $user = $registerOwner->handle($data);

        Auth::login($user);
        session()->regenerate();

        return $this->redirect(HomeRedirect::for($user));
    }
};
?>

<form wire:submit="register" class="flex flex-col gap-4" novalidate>
    <div>
        <h1 class="font-serif text-[26px] text-ink">Criar conta</h1>
        <p class="mt-1 text-[13.5px] text-ink/55">Comece grátis, com 5 vídeos por IA para experimentar.</p>
    </div>

    <x-ui.field label="Seu nome" for="name" error="name">
        <x-ui.input id="name" wire:model="name" autocomplete="name" required />
    </x-ui.field>

    <x-ui.field label="Nome do restaurante" for="restaurant_name" error="restaurant_name">
        <x-ui.input id="restaurant_name" wire:model="restaurant_name" autocomplete="organization" required />
    </x-ui.field>

    <x-ui.field label="E-mail" for="email" error="email">
        <x-ui.input id="email" type="email" wire:model="email" autocomplete="email" required />
    </x-ui.field>

    <x-ui.field label="Senha" for="password" error="password" hint="Mínimo de 8 caracteres.">
        <x-ui.input id="password" type="password" wire:model="password" autocomplete="new-password" minlength="8" required />
    </x-ui.field>

    <x-ui.button type="submit" size="lg" class="mt-1 w-full" wire:loading.attr="disabled">Criar conta grátis</x-ui.button>

    <p class="text-center text-[13px] text-ink/55">
        Já tem conta? <a href="{{ route('login') }}" class="font-semibold text-accent hover:underline" wire:navigate>Entrar</a>
    </p>
</form>
