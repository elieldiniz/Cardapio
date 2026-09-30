<?php

namespace App\Models;

use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A support request opened by a dono from the panel and answered by the
 * super admin from /admin.
 *
 * Status and timestamps are not mass assignable: they only change through the
 * transition methods below (driven by the support actions).
 */
#[Fillable(['restaurant_id', 'user_id', 'subject', 'category'])]
class SupportTicket extends Model
{
    protected function casts(): array
    {
        return [
            'category' => TicketCategory::class,
            'status' => TicketStatus::class,
            'last_message_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->oldest()->oldest('id');
    }

    public function isClosed(): bool
    {
        return $this->status === TicketStatus::Closed;
    }

    /**
     * A new message arrived: staff replies wait on the dono, dono replies
     * wait on support (and reopen a closed ticket).
     */
    public function markMessageReceived(bool $fromStaff): void
    {
        $this->forceFill([
            'status' => $fromStaff ? TicketStatus::Answered : TicketStatus::Open,
            'last_message_at' => now(),
            'closed_at' => null,
        ])->save();
    }

    public function close(): void
    {
        $this->forceFill(['status' => TicketStatus::Closed, 'closed_at' => now()])->save();
    }

    public function reopen(): void
    {
        $this->forceFill(['status' => TicketStatus::Open, 'closed_at' => null])->save();
    }
}
