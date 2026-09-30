<?php

use App\Actions\Support\OpenSupportTicket;
use App\Models\SupportTicket;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::panel')] #[Title('Suporte')] class extends Component
{
    public bool $showForm = false;

    public string $category = 'duvida';

    public string $subject = '';

    public string $body = '';

    #[Computed]
    public function tickets()
    {
        return SupportTicket::query()
            ->where('restaurant_id', auth()->user()->restaurant_id)
            ->orderByRaw('status = ? asc', [SupportTicket::STATUS_CLOSED])
            ->latest('last_message_at')
            ->get();
    }

    public function whatsappUrl(): ?string
    {
        $number = config('landing.contact.whatsapp');

        return $number ? 'https://wa.me/'.$number.'?text='.rawurlencode('Olá! Preciso de ajuda com o meu cardápio ('.auth()->user()->restaurant->name.').') : null;
    }

    public function open()
    {
        $this->validate([
            'category' => ['required', Rule::in(array_keys(SupportTicket::CATEGORIES))],
            'subject' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
        ], [
            'subject.required' => 'Conte o assunto em poucas palavras.',
            'body.required' => 'Descreva o que aconteceu ou o que você precisa.',
            'body.min' => 'Escreva um pouco mais para conseguirmos ajudar.',
        ]);

        $ticket = app(OpenSupportTicket::class)->handle(auth()->user(), $this->subject, $this->category, $this->body);

        session()->flash('toast', ['message' => 'Chamado enviado! Respondemos por aqui.', 'type' => 'success']);

        return $this->redirectRoute('panel.support.show', $ticket, navigate: true);
    }
};
?>

<div class="flex flex-col gap-[18px]">
    <x-ui.page-header title="Suporte" subtitle="Abra um chamado e acompanhe a resposta aqui no painel.">
        <div class="flex flex-wrap gap-2">
            @if ($url = $this->whatsappUrl())
                <x-ui.button variant="secondary" :href="$url" target="_blank" rel="noopener" data-support-whatsapp>Falar no WhatsApp</x-ui.button>
            @endif
            <x-ui.button wire:click="$toggle('showForm')" data-support-new>{{ $showForm ? 'Cancelar' : 'Novo chamado' }}</x-ui.button>
        </div>
    </x-ui.page-header>

    @if ($showForm)
        <x-ui.card title="Novo chamado" data-support-form>
            <form wire:submit="open" class="flex flex-col gap-3.5">
                <x-ui.field label="Sobre o quê?" for="category">
                    <x-ui.select id="category" wire:model="category">
                        @foreach (\App\Models\SupportTicket::CATEGORIES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Assunto" for="subject" error="subject">
                    <x-ui.input id="subject" wire:model="subject" maxlength="120" placeholder="Ex.: O vídeo do prato não aparece" />
                </x-ui.field>

                <x-ui.field label="Mensagem" for="body" error="body">
                    <x-ui.textarea id="body" wire:model="body" rows="5" maxlength="5000" placeholder="Explique com detalhes. Se puder, diga o nome do prato ou a tela onde aconteceu." />
                </x-ui.field>

                <div>
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="open">Enviar chamado</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card title="Seus chamados" :padding="false">
        @forelse ($this->tickets as $ticket)
            <a
                href="{{ route('panel.support.show', $ticket) }}"
                wire:navigate
                wire:key="ticket-{{ $ticket->id }}"
                class="flex items-center justify-between gap-3 border-t border-ink/8 px-5 py-4 transition hover:bg-ink/[0.02] sm:px-6"
                data-ticket
            >
                <div class="flex min-w-0 flex-col gap-0.5">
                    <span class="truncate text-[14px] font-bold text-ink">{{ $ticket->subject }}</span>
                    <span class="text-[12px] text-ink/50">{{ $ticket->categoryLabel() }} · {{ $ticket->last_message_at?->diffForHumans() }}</span>
                </div>
                <span class="shrink-0 rounded-full px-2.5 py-1 text-[11.5px] font-bold {{ match ($ticket->status) {
                    \App\Models\SupportTicket::STATUS_ANSWERED => 'bg-success/10 text-success',
                    \App\Models\SupportTicket::STATUS_CLOSED => 'bg-ink/8 text-ink/50',
                    default => 'bg-accent/10 text-accent',
                } }}">{{ $ticket->statusLabel() }}</span>
            </a>
        @empty
            <x-ui.empty-state
                title="Nenhum chamado ainda"
                description="Ficou com dúvida ou algo não funcionou? Abra um chamado e a equipe responde por aqui."
            />
        @endforelse
    </x-ui.card>
</div>
