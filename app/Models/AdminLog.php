<?php

namespace App\Models;

use Database\Factories\AdminLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'action_id', 'target', 'data'])]
class AdminLog extends Model
{
    /** @use HasFactory<AdminLogFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    /**
     * Append an audit entry for a sensitive super admin action (US-7.2, US-7.5).
     *
     * @param  array<string, mixed>|null  $data
     */
    public static function record(User $admin, string $actionSlug, ?string $target = null, ?array $data = null): self
    {
        return static::create([
            'user_id' => $admin->id,
            'action_id' => AdminAction::idFor($actionSlug),
            'target' => $target,
            'data' => $data,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(AdminAction::class, 'action_id');
    }
}
