<?php

namespace App\Notifications;

use App\Models\Video;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the dono a video finished processing and awaits their approval (US-3.3).
 */
class VideoReadyForReview extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Video $video) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dish = $this->video->dish;

        return (new MailMessage)
            ->subject("Vídeo pronto para aprovar: {$dish->name}")
            ->greeting('Olá!')
            ->line("Um novo vídeo de \"{$dish->name}\" terminou de processar.")
            ->line('Ele só aparece no cardápio depois que você aprovar.')
            ->action('Revisar vídeo', route('panel.dishes.video', $dish))
            ->salutation('Equipe '.config('app.name'));
    }
}
