<?php

namespace App\Actions\Support;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\SupportTicketAnswered;
use Illuminate\Support\Facades\DB;

/**
 * Adds a message to a ticket. A staff reply marks it "respondido" and tells
 * the dono in the panel; a dono reply puts it back in the support queue.
 * Replying to a closed ticket reopens it.
 */
class ReplyToSupportTicket
{
    public function handle(SupportTicket $ticket, User $author, string $body): SupportTicketMessage
    {
        $fromStaff = $author->isSuperAdmin();

        $message = DB::transaction(function () use ($ticket, $author, $body, $fromStaff) {
            $message = $ticket->messages()->create([
                'user_id' => $author->id,
                'from_staff' => $fromStaff,
                'body' => trim($body),
            ]);

            $ticket->update([
                'status' => $fromStaff ? SupportTicket::STATUS_ANSWERED : SupportTicket::STATUS_OPEN,
                'last_message_at' => now(),
                'closed_at' => null,
            ]);

            return $message;
        });

        if ($fromStaff) {
            $ticket->user->notify(new SupportTicketAnswered($ticket));
        }

        return $message;
    }

    public function close(SupportTicket $ticket): void
    {
        $ticket->update(['status' => SupportTicket::STATUS_CLOSED, 'closed_at' => now()]);
    }

    public function reopen(SupportTicket $ticket): void
    {
        $ticket->update(['status' => SupportTicket::STATUS_OPEN, 'closed_at' => null]);
    }
}
