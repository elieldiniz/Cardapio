<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A support request opened by a dono from the panel and answered by the
 * super admin from /admin.
 */
#[Fillable(['restaurant_id', 'user_id', 'subject', 'category', 'status', 'last_message_at', 'closed_at'])]
class SupportTicket extends Model
{
    public const STATUS_OPEN = 'aberto';

    public const STATUS_ANSWERED = 'respondido';

    public const STATUS_CLOSED = 'fechado';

    /** @var array<string, string> */
    public const CATEGORIES = [
        'duvida' => 'Dúvida',
        'problema' => 'Problema técnico',
        'cobranca' => 'Cobrança / assinatura',
        'video' => 'Vídeos e IA',
        'sugestao' => 'Sugestão',
        'outro' => 'Outro',
    ];

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_OPEN => 'Aguardando suporte',
        self::STATUS_ANSWERED => 'Respondido',
        self::STATUS_CLOSED => 'Fechado',
    ];

    protected function casts(): array
    {
        return [
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
        return $this->hasMany(SupportTicketMessage::class)->oldest();
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_CLOSED);
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Outro';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
