<?php

namespace App\Support;

use Throwable;

/**
 * The text kept on a failed generation: the exception message without anything that looks like a
 * secret (API keys, tokens, signed query strings), cut to a size that is safe to store and show.
 */
class FailureReason
{
    /**
     * The kind of failure, for the message the owner sees (the technical text stays in the admin):
     * foto, seguranca, tempo or servico (anything else, including no recorded reason).
     */
    public static function category(?string $reason): string
    {
        $text = mb_strtolower((string) $reason);

        return match (true) {
            str_contains($text, 'could not read photo'), str_contains($text, 'needs at least one photo') => 'foto',
            str_contains($text, 'produced no video'), (bool) preg_match('/responsible ai|safety|filtered|sensitive|blocked/', $text) => 'seguranca',
            str_contains($text, 'did not finish in time'), str_contains($text, 'timed out'), str_contains($text, 'curl error 28') => 'tempo',
            default => 'servico',
        };
    }

    public static function from(?Throwable $exception): ?string
    {
        if ($exception === null) {
            return null;
        }

        $message = trim($exception->getMessage());
        $message = preg_replace('/\b(key|api_key|apikey|token|access_token|signature|x-goog-signature|secret|password)=[^&\s"\']+/i', '$1=[oculto]', $message);
        $message = preg_replace('/(authorization|x-goog-api-key|api[-_ ]?key)(["\']?\s*[:=]\s*["\']?)[A-Za-z0-9._\-]{8,}/i', '$1$2[oculto]', $message);
        $message = preg_replace('/\bAIza[0-9A-Za-z_\-]{20,}\b/', '[oculto]', $message);

        return mb_substr(get_class($exception) === \RuntimeException::class ? $message : class_basename($exception).': '.$message, 0, 1000) ?: null;
    }
}
