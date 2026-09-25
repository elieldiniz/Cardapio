<?php

namespace App\Notifications;

use App\Models\Dish;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the dono an AI generation finished and its variations await approval
 * (US-3.3). Sent once per generation: always in the panel, by e-mail only when
 * the dono opted in under Minha conta.
 */
class VideoReadyForReview extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Dish $dish,
        public readonly int $variations,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable->notify_video_ready ? ['database', 'mail'] : ['database'];
    }

    public function title(): string
    {
        return ($this->variations === 1 ? 'Vídeo pronto' : 'Vídeos prontos')." para aprovar: {$this->dish->name}";
    }

    public function message(): string
    {
        if ($this->variations === 1) {
            return "O vídeo de \"{$this->dish->name}\" terminou de processar. Ele só aparece no cardápio depois que você aprovar.";
        }

        return "As {$this->variations} variações de \"{$this->dish->name}\" terminaram de processar. Escolha a melhor para ela aparecer no cardápio.";
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'video',
            'level' => 'info',
            'title' => $this->title(),
            'message' => $this->message(),
            'action_url' => route('panel.dishes.video', $this->dish),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting('Olá!')
            ->line($this->message())
            ->action('Revisar vídeos', route('panel.dishes.video', $this->dish))
            ->line('Você recebe este e-mail porque ativou o aviso em Minha conta. Dá para desligar por lá quando quiser.')
            ->salutation('Equipe '.config('app.name'));
    }
}
