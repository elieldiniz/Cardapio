<?php

namespace App\Models;

use App\Support\FailureReason;
use Database\Factories\VideoGenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['dish_id', 'preset_id', 'custom_prompt', 'provider_id', 'status_id', 'variations_requested', 'provider_operations', 'cost_usd', 'failure_reason', 'expiry_warned_at'])]
class VideoGeneration extends Model
{
    /** @use HasFactory<VideoGenerationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'cost_usd' => 'decimal:4',
            'provider_operations' => 'array',
            'expiry_warned_at' => 'datetime',
        ];
    }

    public const TURN_LIMITED = 'limited';

    public const TURN_WIDE = 'wide';

    /**
     * 1–2 photos never get a full turn (the AI would invent the back of the
     * dish); 3–4 angles allow a wider one (US-3.1, US-3.2).
     */
    public static function turnFor(int $photoCount): string
    {
        return $photoCount >= 3 ? self::TURN_WIDE : self::TURN_LIMITED;
    }

    /**
     * Short code the owner can quote to support: GER-<letter of the kind>-<id>. The letter only hints at the
     * kind (F foto, S segurança, T tempo, X serviço); the real reason is read by support in the admin.
     */
    public function errorCode(): string
    {
        $letter = ['foto' => 'F', 'seguranca' => 'S', 'tempo' => 'T', 'servico' => 'X'][FailureReason::category($this->failure_reason)];

        return 'GER-'.$letter.'-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * What the owner is told when a generation failed: plain Portuguese and what to try, never the technical text.
     */
    public function ownerErrorMessage(): string
    {
        return [
            'foto' => 'Não conseguimos usar esta foto. Envie outra, com boa luz e o prato em destaque, e gere de novo.',
            'seguranca' => 'A análise de segurança da IA não aceitou esta foto. Tente outra, sem pessoas, marcas ou textos, e gere de novo.',
            'tempo' => 'A criação demorou mais do que o esperado. Tente gerar de novo.',
            'servico' => 'Tivemos um problema no nosso serviço de vídeo. Tente de novo em alguns minutos.',
        ][FailureReason::category($this->failure_reason)];
    }

    public function isFinished(): bool
    {
        return in_array($this->status?->slug, ['pronto', 'erro'], true);
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    public function preset(): BelongsTo
    {
        return $this->belongsTo(AiPreset::class, 'preset_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(GenerationStatus::class, 'status_id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class, 'generation_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(VideoGenerationPhoto::class);
    }
}
