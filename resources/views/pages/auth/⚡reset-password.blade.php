<?php

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::auth')] #[Title('Redefinir senha')] class extends Component
{
    public const INVALID_TOKEN = 'Este link de redefinição é inválido ou já expirou. Peça um novo link.';

    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function resetPassword()
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', PasswordRule::min(8)],
        ], [
            'password.min' => 'A senha precisa ter pelo menos 8 caracteres.',
        ]);

        $status = Password::reset(
            ['email' => $this->email, 'password' => $this->password, 'token' => $this->token],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                // End every existing session of this user (US-8.3).
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->addError('email', self::INVALID_TOKEN);

            return;
        }

        session()->flash('toast', ['message' => 'Senha redefinida. Entre com a nova senha.', 'type' => 'success']);

        return $this->redirectRoute('login', navigate: true);
    }
};
?>

<form wire:submit="resetPassword" class="flex flex-col gap-4" novalidate>
    <div>
        <h1 class="font-serif text-[26px] text-ink">Redefinir senha</h1>
        <p class="mt-1 text-[13.5px] text-ink/55">Escolha uma nova senha para sua conta.</p>
    </div>

    <x-ui.field label="E-mail" for="email" error="email">
        <x-ui.input id="email" type="email" wire:model="email" autocomplete="email" required />
    </x-ui.field>

    <x-ui.field label="Nova senha" for="password" error="password" hint="Mínimo de 8 caracteres.">
        <x-ui.input id="password" type="password" wire:model="password" autocomplete="new-password" minlength="8" required />
    </x-ui.field>

    <x-ui.button type="submit" size="lg" class="w-full" wire:loading.attr="disabled">Salvar nova senha</x-ui.button>
</form>
