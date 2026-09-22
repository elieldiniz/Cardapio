<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Explains the move back to Grátis in the panel and by e-mail (US-5.5).
 */
class PlanDowngraded extends Notification
{
    use Queueable;

    /**
     * @param  array<int, string>  $hiddenDishes
     */
    public function __construct(
        public readonly bool $forNonPayment,
        public readonly array $hiddenDishes,
        public readonly ?int $dishLimit,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function message(): string
    {
        $reason = $this->forNonPayment
            ? 'Não conseguimos cobrar sua assinatura depois das novas tentativas, então seu restaurante voltou ao plano Grátis. Seu cardápio continua no ar.'
            : 'Sua assinatura foi encerrada e seu restaurante voltou ao plano Grátis. Seu cardápio continua no ar.';

        if ($this->hiddenDishes !== []) {
            $reason .= ' O plano Grátis permite '.$this->dishLimit.' pratos, então ocultamos: '.implode(', ', $this->hiddenDishes).'. Assine de novo para voltar a exibi-los, ou reduza a quantidade de pratos.';
        }

        return $reason;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'billing',
            'level' => 'warning',
            'title' => $this->forNonPayment ? 'Pagamento não aprovado — plano alterado para Grátis' : 'Plano alterado para Grátis',
            'message' => $this->message(),
            'action_route' => 'panel.subscription',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->toArray($notifiable)['title'])
            ->line($this->message())
            ->action('Ver assinatura', route('panel.subscription'));
    }
}
