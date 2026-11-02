<?php

namespace App\Services\Address;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Looks a CEP up on ViaCEP so the owner can register a delivery neighborhood by typing
 * a CEP instead of the neighborhood name (RF-06b). Returns null when the CEP does not
 * exist and throws CepLookupFailed when the service is unreachable, so the caller can
 * tell "CEP not found" from "try again later".
 */
class ViaCepLookup
{
    /** @return array{cep: string, street: string, neighborhood: string, city: string, state: string}|null */
    public function find(string $cep): ?array
    {
        $digits = preg_replace('/\D/', '', $cep) ?? '';

        if (strlen($digits) !== 8) {
            return null;
        }

        try {
            $response = Http::timeout(8)->acceptJson()->get("https://viacep.com.br/ws/{$digits}/json/");
        } catch (Throwable $exception) {
            throw new CepLookupFailed(previous: $exception);
        }

        if ($response->failed()) {
            // ViaCEP answers 400 for a malformed CEP; anything else is a service problem.
            if ($response->status() === 400) {
                return null;
            }

            throw new CepLookupFailed;
        }

        $data = $response->json();

        if (! is_array($data) || ($data['erro'] ?? false)) {
            return null;
        }

        return [
            'cep' => substr($digits, 0, 5).'-'.substr($digits, 5),
            'street' => trim((string) ($data['logradouro'] ?? '')),
            'neighborhood' => trim((string) ($data['bairro'] ?? '')),
            'city' => trim((string) ($data['localidade'] ?? '')),
            'state' => trim((string) ($data['uf'] ?? '')),
        ];
    }
}
