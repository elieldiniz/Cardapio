<?php

namespace App\Notifications;

use App\Models\Dish;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Panel notice sent a few days before an unreviewed AI round is cleaned up
 * by videos:prune, so the dono can still approve a variation.
 */
class UnreviewedVideosExpiring extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Dish $dish,
        public readonly int $days,
    ) {}

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
            'kind' => 'video',
            'level' => 'warning',
            'title' => "Vídeos de \"{$this->dish->name}\" serão removidos em {$this->days} dias",
            'message' => 'As variações geradas para este prato ainda não foram aprovadas. Aprove a que quiser manter; as outras serão apagadas.',
            'action_url' => route('panel.dishes.video', $this->dish),
        ];
    }
}
