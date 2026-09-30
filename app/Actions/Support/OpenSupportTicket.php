<?php

namespace App\Actions\Support;

use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A dono opens a support ticket from the panel.
 *
 * Guarded against abuse: a per-restaurant hourly limit and a cap on tickets
 * waiting to be resolved. A double submit returns the ticket just created.
 */
class OpenSupportTicket
{
    public const HOURLY_LIMIT = 5;

    public const MAX_UNRESOLVED = 10;

    public function handle(User $owner, string $subject, string $category, string $body): SupportTicket
    {
        Gate::forUser($owner)->authorize('create', SupportTicket::class);

        $data = Validator::validate(
            ['subject' => trim($subject), 'category' => $category, 'body' => trim($body)],
            [
                'subject' => ['required', 'string', 'max:120'],
                'category' => ['required', Rule::enum(TicketCategory::class)],
                'body' => ['required', 'string', 'min:10', 'max:5000'],
            ],
        );

        $duplicate = SupportTicket::query()
            ->where('restaurant_id', $owner->restaurant_id)
            ->where('user_id', $owner->id)
            ->where('subject', $data['subject'])
            ->where('created_at', '>=', now()->subMinute())
            ->first();

        if ($duplicate) {
            return $duplicate;
        }

        $unresolved = SupportTicket::query()
            ->where('restaurant_id', $owner->restaurant_id)
            ->where('status', '!=', TicketStatus::Closed)
            ->count();

        if ($unresolved >= self::MAX_UNRESOLVED) {
            throw ValidationException::withMessages([
                'body' => 'Você já tem muitos chamados em aberto. Feche os resolvidos ou aguarde a nossa resposta.',
            ]);
        }

        if (! RateLimiter::attempt("support-open:{$owner->restaurant_id}", self::HOURLY_LIMIT, fn () => true, 3600)) {
            throw ValidationException::withMessages([
                'body' => 'Muitos chamados em pouco tempo. Tente novamente em alguns minutos.',
            ]);
        }

        return DB::transaction(function () use ($owner, $data) {
            $ticket = SupportTicket::create([
                'restaurant_id' => $owner->restaurant_id,
                'user_id' => $owner->id,
                'subject' => $data['subject'],
                'category' => $data['category'],
            ]);

            $ticket->markMessageReceived(fromStaff: false);

            $ticket->messages()->create([
                'user_id' => $owner->id,
                'from_staff' => false,
                'body' => $data['body'],
            ]);

            return $ticket;
        });
    }
}
