<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;

/**
 * Ships the Degusta symbol and wordmark inside every e-mail that references
 * them (`cid:symbol`, `cid:wordmark-light`, `cid:wordmark-dark`). E-mail clients such as Outlook
 * and Gmail ignore web fonts, and embedding avoids remote-image blocking.
 */
class EmbedBrandImages
{
    public const IMAGES = ['symbol', 'wordmark-light', 'wordmark-dark'];

    public function handle(MessageSending $event): void
    {
        $html = (string) $event->message->getHtmlBody();

        foreach (self::IMAGES as $name) {
            if (str_contains($html, "cid:{$name}")) {
                $event->message->embedFromPath(public_path("images/brand/{$name}.png"), $name, 'image/png');
            }
        }
    }
}
