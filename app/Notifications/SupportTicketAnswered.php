<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Panel notice for the dono when support replies to their ticket.
 */
class SupportTicketAnswered extends Notification
{
    use Queueable;

    public function __construct(public readonly SupportTicket $ticket) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'support',
            'level' => 'info',
            'title' => "Resposta do suporte: {$this->ticket->subject}",
            'message' => 'A equipe respondeu ao seu chamado. Abra para ler e continuar a conversa.',
            'action_url' => route('panel.support.show', $this->ticket),
        ];
    }
}
