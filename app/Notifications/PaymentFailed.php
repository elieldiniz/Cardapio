<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A subscription charge failed; Stripe will retry for a few days before any
 * plan change (US-5.5).
 */
class PaymentFailed extends Notification
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'billing',
            'level' => 'error',
            'title' => 'O pagamento da sua assinatura falhou',
            'message' => 'Vamos tentar cobrar de novo nos próximos dias. Atualize o cartão para não voltar ao plano Grátis.',
            'action_route' => 'panel.subscription',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject($data['title'])
            ->line($data['message'])
            ->action('Atualizar cartão', route('panel.subscription'));
    }
}
