<?php

namespace App\Actions\Support;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A dono opens a support ticket from the panel.
 */
class OpenSupportTicket
{
    public function handle(User $owner, string $subject, string $category, string $body): SupportTicket
    {
        return DB::transaction(function () use ($owner, $subject, $category, $body) {
            $ticket = SupportTicket::create([
                'restaurant_id' => $owner->restaurant_id,
                'user_id' => $owner->id,
                'subject' => trim($subject),
                'category' => $category,
                'status' => SupportTicket::STATUS_OPEN,
                'last_message_at' => now(),
            ]);

            $ticket->messages()->create([
                'user_id' => $owner->id,
                'from_staff' => false,
                'body' => trim($body),
            ]);

            return $ticket;
        });
    }
}
