<?php

use App\Actions\Account\DeleteOwnAccount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::panel')] #[Title('Minha conta')] class extends Component
{
    // Dados pessoais
    public string $name = '';

    public string $email = '';

    public string $email_password = '';

    // Senha
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    // Restaurante
    public string $restaurant_name = '';

    // Notificações
    public bool $notify_video_ready = false;

    // Sessões
    public string $sessions_password = '';

    // Excluir conta
    public string $delete_password = '';

    public string $delete_confirmation = '';

    public function mount(): void
    {
        $user = auth()->user();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->restaurant_name = $user->restaurant->name;
        $this->notify_video_ready = $user->notify_video_ready;
    }

    public function updatedNotifyVideoReady(bool $value): void
    {
        auth()->user()->update(['notify_video_ready' => $value]);

        $this->dispatch('toast', message: $value ? 'Você vai receber um e-mail quando os vídeos ficarem prontos.' : 'Aviso por e-mail desligado.');
    }

    public function updateProfile()
    {
        $user = auth()->user();
        $emailChanged = strcasecmp(trim($this->email), $user->email) !== 0;

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'email_password' => $emailChanged ? ['required', 'current_password'] : [],
        ], [
            'email.unique' => 'Este e-mail já está em uso.',
            'email_password.required' => 'Confirme sua senha para trocar o e-mail.',
            'email_password.current_password' => 'Senha incorreta.',
        ]);

        $user->name = trim($this->name);

        if ($emailChanged) {
            $user->email = trim($this->email);
            $user->email_verified_at = null;
        }

        $user->save();
        $this->reset('email_password');

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();

            return $this->redirectRoute('verification.notice');
        }

        $this->dispatch('toast', message: 'Dados atualizados.');

        return null;
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'current_password.current_password' => 'Senha atual incorreta.',
            'password.min' => 'A nova senha precisa ter pelo menos 8 caracteres.',
            'password.confirmed' => 'A confirmação não confere com a nova senha.',
        ]);

        auth()->user()->forceFill(['password' => $this->password])->save();

        // Other devices are signed out; this one stays signed in.
        $this->deleteOtherSessions();

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->dispatch('toast', message: 'Senha alterada. Os outros aparelhos foram desconectados.');
    }

    public function updateRestaurant(): void
    {
        $this->validate(['restaurant_name' => ['required', 'string', 'max:255']], [
            'restaurant_name.required' => 'Informe o nome do restaurante.',
        ]);

        // The slug (link and QR code) never changes, so printed QR codes keep working.
        auth()->user()->restaurant->update(['name' => trim($this->restaurant_name)]);

        $this->dispatch('toast', message: 'Nome do restaurante atualizado.');
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    #[Computed]
    public function sessions()
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', auth()->id())
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn (object $session) => (object) [
                'device' => $this->describeAgent((string) $session->user_agent),
                'ip' => $session->ip_address,
                'last_active' => \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                'current' => $session->id === session()->getId(),
            ]);
    }

    public function logoutOtherSessions(): void
    {
        $this->validate(['sessions_password' => ['required', 'current_password']], [
            'sessions_password.required' => 'Confirme sua senha.',
            'sessions_password.current_password' => 'Senha incorreta.',
        ]);

        $this->deleteOtherSessions();
        $this->reset('sessions_password');
        unset($this->sessions);

        $this->dispatch('toast', message: 'Os outros aparelhos foram desconectados.');
    }

    public function deleteAccount(DeleteOwnAccount $deleteOwnAccount)
    {
        $restaurantName = auth()->user()->restaurant->name;

        $this->validate([
            'delete_password' => ['required', 'current_password'],
            'delete_confirmation' => ['required', Rule::in([$restaurantName])],
        ], [
            'delete_password.required' => 'Confirme sua senha.',
            'delete_password.current_password' => 'Senha incorreta.',
            'delete_confirmation.in' => 'Digite o nome do restaurante exatamente como aparece acima.',
            'delete_confirmation.required' => 'Digite o nome do restaurante para confirmar.',
        ]);

        try {
            $deleteOwnAccount->handle(auth()->user());
        } catch (\Throwable $exception) {
            report($exception);
            $this->addError('delete_password', 'Não foi possível cancelar sua assinatura agora. Tente de novo em instantes; nada foi excluído.');

            return null;
        }

        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();
        session()->flash('status', 'Sua conta foi excluída.');

        return $this->redirect(url('/'));
    }

    private function deleteOtherSessions(): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', auth()->id())
            ->where('id', '!=', session()->getId())
            ->delete();
    }

    private function describeAgent(string $agent): string
    {
        $platform = match (true) {
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iPhone/iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'Mac',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Aparelho desconhecido',
        };

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => null,
        };

        return $browser ? "{$browser} · {$platform}" : $platform;
    }
};
?>

<div class="flex flex-col gap-[18px]">
    <x-ui.page-header title="Minha conta" subtitle="Seus dados de acesso e do restaurante." />

    <x-ui.card title="Dados pessoais">
        <form wire:submit="updateProfile" class="grid gap-4 sm:grid-cols-2">
            <x-ui.field label="Seu nome" for="name" error="name">
                <x-ui.input id="name" wire:model="name" autocomplete="name" />
            </x-ui.field>
            <x-ui.field label="E-mail" for="email" error="email" hint="Ao trocar, enviamos um link de confirmação para o novo e-mail.">
                <x-ui.input id="email" type="email" wire:model.live.blur="email" autocomplete="email" />
            </x-ui.field>
            @if (strcasecmp(trim($email), auth()->user()->email) !== 0)
                <x-ui.field label="Senha atual (para trocar o e-mail)" for="email_password" error="email_password">
                    <x-ui.input id="email_password" type="password" wire:model="email_password" autocomplete="current-password" />
                </x-ui.field>
            @endif
            <div class="sm:col-span-2">
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="updateProfile">Salvar dados</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card title="Trocar senha">
        <form wire:submit="updatePassword" class="grid gap-4 sm:grid-cols-3">
            <x-ui.field label="Senha atual" for="current_password" error="current_password">
                <x-ui.input id="current_password" type="password" wire:model="current_password" autocomplete="current-password" />
            </x-ui.field>
            <x-ui.field label="Nova senha" for="password" error="password" hint="Mínimo de 8 caracteres.">
                <x-ui.input id="password" type="password" wire:model="password" autocomplete="new-password" minlength="8" />
            </x-ui.field>
            <x-ui.field label="Confirme a nova senha" for="password_confirmation">
                <x-ui.input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" />
            </x-ui.field>
            <div class="sm:col-span-3">
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="updatePassword">Trocar senha</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card title="Restaurante">
        <form wire:submit="updateRestaurant" class="flex flex-col gap-4">
            <x-ui.field label="Nome do restaurante" for="restaurant_name" error="restaurant_name">
                <x-ui.input id="restaurant_name" wire:model="restaurant_name" />
            </x-ui.field>
            <p class="text-[12.5px] text-ink/50">O link do cardápio (<span class="font-semibold text-ink/70">{{ auth()->user()->restaurant->feedUrl() }}</span>) e o QR Code não mudam ao renomear.</p>
            <div>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="updateRestaurant">Salvar nome</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card title="Notificações">
        <label class="flex cursor-pointer items-start gap-3">
            <input type="checkbox" wire:model.live="notify_video_ready" class="mt-0.5 size-4 shrink-0 accent-[var(--color-accent)]" data-notify-video-ready>
            <span class="flex flex-col gap-0.5">
                <span class="text-[14px] font-bold text-ink">Me avisar por e-mail quando um vídeo gerado por IA ficar pronto</span>
                <span class="text-[12.5px] leading-relaxed text-ink/55">Um e-mail por geração, com todas as variações. O aviso aparece no painel de qualquer forma. E-mails de conta e cobrança são sempre enviados.</span>
            </span>
        </label>
    </x-ui.card>

    <x-ui.card title="Aparelhos conectados">
        @if ($this->sessions->isEmpty())
            <p class="text-[13.5px] text-ink/55">Nenhum outro aparelho conectado.</p>
        @else
            <ul class="mb-4 flex flex-col">
                @foreach ($this->sessions as $session)
                    <li class="flex items-center justify-between gap-3 border-b border-ink/[0.06] py-2.5 text-[13.5px] last:border-b-0">
                        <span class="font-semibold text-ink">{{ $session->device }}</span>
                        <span class="text-right text-[12.5px] text-ink/50">
                            {{ $session->ip }} ·
                            @if ($session->current)
                                <span class="font-bold text-success">este aparelho</span>
                            @else
                                {{ $session->last_active }}
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif

        <form wire:submit="logoutOtherSessions" class="flex flex-wrap items-start gap-2">
            <x-ui.field error="sessions_password" class="min-w-[200px] flex-1">
                <x-ui.input type="password" wire:model="sessions_password" placeholder="Sua senha" aria-label="Sua senha" autocomplete="current-password" />
            </x-ui.field>
            <x-ui.button type="submit" variant="secondary" wire:loading.attr="disabled" wire:target="logoutOtherSessions">Sair de todos os outros aparelhos</x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.card title="Excluir conta" class="border border-danger/20">
        <div x-data="{ open: false }" class="flex flex-col gap-4">
            <p class="text-[13.5px] leading-relaxed text-ink/65">
                O cardápio sai do ar na hora, a assinatura é cancelada e você perde o acesso ao painel.
                Seus dados ficam guardados por um tempo e depois são apagados de vez.
            </p>

            <div x-show="! open">
                <x-ui.button variant="danger" x-on:click="open = true" class="border border-danger/30">Quero excluir minha conta</x-ui.button>
            </div>

            <form x-show="open" x-cloak wire:submit="deleteAccount" class="flex flex-col gap-4" data-delete-account>
                <x-ui.field :label="'Digite o nome do restaurante: '.auth()->user()->restaurant->name" for="delete_confirmation" error="delete_confirmation">
                    <x-ui.input id="delete_confirmation" wire:model="delete_confirmation" autocomplete="off" />
                </x-ui.field>
                <x-ui.field label="Sua senha" for="delete_password" error="delete_password">
                    <x-ui.input id="delete_password" type="password" wire:model="delete_password" autocomplete="current-password" />
                </x-ui.field>
                <div class="flex gap-2.5">
                    <x-ui.button type="submit" class="!bg-danger !text-white" wire:loading.attr="disabled" wire:target="deleteAccount">Excluir conta definitivamente</x-ui.button>
                    <x-ui.button variant="secondary" x-on:click="open = false">Cancelar</x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.card>
</div>
