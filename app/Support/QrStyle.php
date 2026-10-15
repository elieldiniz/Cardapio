<?php

namespace App\Support;

/**
 * How a QR code is drawn (color, module shape, logo in the middle) plus the
 * color math that keeps it scannable: dark modules on white need enough
 * contrast, so a color that is too light is refused or darkened.
 */
final class QrStyle
{
    /** @var array<string, string> */
    public const STYLES = [
        'square' => 'Quadrado',
        'rounded' => 'Arredondado',
        'dots' => 'Pontos',
    ];

    public const DEFAULT_COLOR = '#1A1815';

    /** WCAG-like ratio of the modules against the white background; below this the code gets hard to read. */
    public const MIN_CONTRAST = 4.5;

    public function __construct(
        public readonly string $color = self::DEFAULT_COLOR,
        public readonly string $style = 'square',
        public readonly bool $logo = false,
    ) {}

    public static function isHex(string $value): bool
    {
        return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $value);
    }

    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, sscanf(ltrim($hex, '#'), '%02x%02x%02x'));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    public static function contrast(string $a, string $b = '#FFFFFF'): float
    {
        [$light, $dark] = [max(self::luminance($a), self::luminance($b)), min(self::luminance($a), self::luminance($b))];

        return ($light + 0.05) / ($dark + 0.05);
    }

    public static function isReadable(string $hex): bool
    {
        return self::isHex($hex) && self::contrast($hex) >= self::MIN_CONTRAST;
    }

    /**
     * The given color darkened step by step until it reads well on white.
     */
    public static function readable(string $hex): string
    {
        if (! self::isHex($hex)) {
            return self::DEFAULT_COLOR;
        }

        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');

        for ($i = 0; $i < 40 && ! self::isReadable(sprintf('#%02X%02X%02X', $r, $g, $b)); $i++) {
            [$r, $g, $b] = [(int) round($r * 0.92), (int) round($g * 0.92), (int) round($b * 0.92)];
        }

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    /**
     * Text color that reads on a background of this color: white on dark, near-black on light.
     */
    public static function onColor(string $hex): string
    {
        return self::contrast('#FFFFFF', $hex) >= self::contrast(self::DEFAULT_COLOR, $hex) ? '#FFFFFF' : self::DEFAULT_COLOR;
    }
}
