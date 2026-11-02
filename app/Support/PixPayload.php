<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The static Pix "copia e cola" (BR Code, EMV QRCPS) for a key and an amount (RF-06c).
 * The amount always comes from the server's total; the key, holder and city come from
 * the store settings. Ends with the CRC16-CCITT checksum the banks verify.
 */
class PixPayload
{
    public static function build(string $key, string $holder, string $city, ?string $amount = null, ?string $txid = null): string
    {
        $account = self::field('00', 'br.gov.bcb.pix').self::field('01', trim($key));

        $payload = self::field('00', '01')
            .self::field('26', $account)
            .self::field('52', '0000')
            .self::field('53', '986')
            .($amount !== null && (float) $amount > 0 ? self::field('54', number_format((float) $amount, 2, '.', '')) : '')
            .self::field('58', 'BR')
            .self::field('59', self::clean($holder, 25, 'RECEBEDOR'))
            .self::field('60', self::clean($city, 15, 'BRASIL'))
            .self::field('62', self::field('05', self::txid($txid)));

        $payload .= '6304';

        return $payload.self::crc16($payload);
    }

    public static function crc16(string $data): string
    {
        $crc = 0xFFFF;

        for ($i = 0, $length = strlen($data); $i < $length; $i++) {
            $crc ^= ord($data[$i]) << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    private static function field(string $id, string $value): string
    {
        return $id.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    private static function clean(string $text, int $max, string $fallback): string
    {
        $text = strtoupper(preg_replace('/[^A-Za-z0-9 ]/', '', Str::ascii($text)) ?? '');
        $text = trim(substr($text, 0, $max));

        return $text !== '' ? $text : $fallback;
    }

    private static function txid(?string $txid): string
    {
        $clean = preg_replace('/[^A-Za-z0-9]/', '', (string) $txid) ?? '';

        return $clean !== '' ? substr($clean, 0, 25) : '***';
    }
}
