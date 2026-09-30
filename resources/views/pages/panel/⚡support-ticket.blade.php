<?php

use App\Actions\Support\ReplyToSupportTicket;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts::panel')] class extends Component
{
    #[Locked]
    public int $ticketId;

    public string $body = '';

    public function mount(SupportTicket $ticket): void
    {
        abort_unless(Gate::allows('view', $ticket), 404);

        $this->ticketId = $ticket->id;

        // Reading the thread clears the "support answered" notice for it.
        auth()->user()->unreadNotifications()
            ->where('data->action_url', route('panel.support.show', $ticket))
            ->update(['read_at' => now()]);
    }

    public function render()
    {
        return $this->view()->title($this->ticket->subject);
    }

    #[Computed]
    public function ticket(): SupportTicket
    {
        return SupportTicket::query()
            ->where('restaurant_id', auth()->user()->restaurant_id)
            ->with('messages.user')
            ->findOrFail($this->ticketId);
    }

    public function reply(): void
    {
        $this->validate(['body' => ['required', 'string', 'max:5000']], [
            'body.required' => 'Escreva a sua mensagem.',
        ]);

        app(ReplyToSupportTicket::class)->handle($this->ticket, auth()->user(), $this->body);

        $this->reset('body');
        unset($this->ticket);
        $this->dispatch('toast', message: 'Mensagem enviada.');
    }

    public function close(): void
    {
        app(ReplyToSupportTicket::class)->close($this->ticket, auth()->user());

        unset($this->ticket);
        $this->dispatch('toast', message: 'Chamado fechado.');
    }
};
?>

<div class="flex flex-col gap-[18px]">
    <a href="{{ route('panel.support') }}" wire:navigate class="w-fit text-[13px] font-bold text-accent hover:underline">← Todos os chamados</a>

    <x-ui.page-header :title="$this->ticket->subject" :subtitle="$this->ticket->category->label().' · '.$this->ticket->status->label()">
        @if (! $this->ticket->isClosed() && auth()->user()->can('close', $this->ticket))
            <x-ui.button variant="secondary" wire:click="close" wire:confirm="Fechar este chamado?" data-ticket-close>Marcar como resolvido</x-ui.button>
        @endif
    </x-ui.page-header>

    <div class="flex flex-col gap-3" data-thread>
        @foreach ($this->ticket->messages as $message)
            <div wire:key="message-{{ $message->id }}" class="flex {{ $message->from_staff ? 'justify-start' : 'justify-end' }}">
                <div class="flex max-w-[85%] flex-col gap-1 rounded-2xl px-4 py-3 {{ $message->from_staff ? 'bg-white shadow-[0_1px_3px_rgba(0,0,0,0.06)]' : 'bg-accent/10' }}">
                    <span class="text-[11.5px] font-bold text-ink/50">{{ $message->from_staff ? 'Suporte' : 'Você' }} · {{ $message->created_at->format('d/m H:i') }}</span>
                    <p class="whitespace-pre-line text-[14px] leading-relaxed text-ink">{{ $message->body }}</p>
                </div>
            </div>
        @endforeach
    </div>

    @can('reply', $this->ticket)
    <x-ui.card title="{{ $this->ticket->isClosed() ? 'Reabrir conversa' : 'Responder' }}">
        <form wire:submit="reply" class="flex flex-col gap-3">
            <x-ui.field label="Mensagem" for="body" error="body">
                <x-ui.textarea id="body" wire:model="body" rows="4" maxlength="5000" placeholder="Escreva a sua mensagem…" />
            </x-ui.field>
            <div>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="reply">Enviar</x-ui.button>
            </div>
        </form>
    </x-ui.card>
    @else
        <p class="text-[13px] text-ink/50">Modo suporte: para não falar em nome do dono, respostas ficam desativadas.</p>
    @endcan
</div>
