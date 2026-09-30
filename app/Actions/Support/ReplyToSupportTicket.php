<?php

namespace App\Actions\Support;

use App\Models\AdminLog;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\SupportTicketAnswered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Adds a message to a ticket, and closes/reopens it. A staff reply marks it
 * "respondido" and tells the dono in the panel; a dono reply puts it back in
 * the support queue (reopening a closed ticket). Staff actions are audited.
 */
class ReplyToSupportTicket
{
    public const OWNER_LIMIT_PER_10_MIN = 20;

    public function handle(SupportTicket $ticket, User $author, string $body): SupportTicketMessage
    {
        Gate::forUser($author)->authorize('reply', $ticket);

        $body = trim(Validator::validate(['body' => $body], ['body' => ['required', 'string', 'max:5000']])['body']);
        $fromStaff = $author->isSuperAdmin();

        // Double submit: the same message from the same person seconds ago is the same message.
        $duplicate = $ticket->messages()
            ->where('user_id', $author->id)
            ->where('body', $body)
            ->where('created_at', '>=', now()->subSeconds(30))
            ->first();

        if ($duplicate) {
            return $duplicate;
        }

        if (! $fromStaff && ! RateLimiter::attempt("support-reply:{$author->id}", self::OWNER_LIMIT_PER_10_MIN, fn () => true, 600)) {
            throw ValidationException::withMessages(['body' => 'Muitas mensagens em pouco tempo. Aguarde alguns minutos.']);
        }

        $message = DB::transaction(function () use ($ticket, $author, $body, $fromStaff) {
            $message = $ticket->messages()->create([
                'user_id' => $author->id,
                'from_staff' => $fromStaff,
                'body' => $body,
            ]);

            $ticket->markMessageReceived($fromStaff);

            if ($fromStaff) {
                AdminLog::record($author, 'chamado_respondido', "support_ticket:{$ticket->id}", ['restaurant_id' => $ticket->restaurant_id]);
            }

            return $message;
        });

        if ($fromStaff) {
            $ticket->user->notify(new SupportTicketAnswered($ticket));
        }

        return $message;
    }

    public function close(SupportTicket $ticket, User $actor): void
    {
        Gate::forUser($actor)->authorize('close', $ticket);

        DB::transaction(function () use ($ticket, $actor) {
            $ticket->close();

            if ($actor->isSuperAdmin()) {
                AdminLog::record($actor, 'chamado_fechado', "support_ticket:{$ticket->id}", ['restaurant_id' => $ticket->restaurant_id]);
            }
        });
    }

    public function reopen(SupportTicket $ticket, User $actor): void
    {
        Gate::forUser($actor)->authorize('reopen', $ticket);

        DB::transaction(function () use ($ticket, $actor) {
            $ticket->reopen();

            AdminLog::record($actor, 'chamado_reaberto', "support_ticket:{$ticket->id}", ['restaurant_id' => $ticket->restaurant_id]);
        });
    }
}
