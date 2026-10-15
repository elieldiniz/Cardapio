<?php

namespace App\Support;

use App\Models\Restaurant;
use Illuminate\Support\Facades\Storage;

/**
 * A restaurant's QR code and table display as it should be drawn: the saved choices
 * merged with the defaults, limited to what the plan allows (the full customization
 * is a plan feature; every plan can still pick the QR color) and made safe to scan.
 */
class RestaurantQr
{
    /** @var array<string, string> */
    public const TEMPLATES = [
        'classico' => 'Clássico',
        'moldura' => 'Moldura colorida',
        'escuro' => 'Escuro',
    ];

    /** @var array<string, mixed> */
    public const DEFAULTS = [
        'color' => QrStyle::DEFAULT_COLOR,
        'style' => 'square',
        'logo' => false,
        'template' => 'classico',
        /** Main color of the display (band, background or detail, depending on the model); null = the restaurant color. */
        'display_color' => null,
        /** Background of the card; null = the model's own (white, the main color or dark). Text color follows it automatically. */
        'display_bg' => null,
        /** Color of the letters; null = automatic (readable on the background). */
        'display_text' => null,
        /** Font of the name and the headline (a key of config('feed.fonts')); null = the card's own. */
        'display_font' => null,
        'headline' => 'Aponte a câmera e veja o cardápio em vídeo',
        'subtitle' => 'Sem app, sem cadastro. Peça ao garçom.',
        'show_logo' => true,
        'show_name' => true,
    ];

    /** Larger logos would make the SVG (and the downloaded PNG) heavy for nothing. */
    private const MAX_LOGO_BYTES = 400_000;

    public static function canCustomize(Restaurant $restaurant): bool
    {
        return (bool) $restaurant->plan?->qr_customization;
    }

    /**
     * @param  array<string, mixed>|null  $override  unsaved choices (the live preview)
     * @return array<string, mixed>
     */
    public static function settings(Restaurant $restaurant, ?array $override = null): array
    {
        $saved = $override ?? ($restaurant->qr_settings ?? []);
        $settings = array_merge(self::DEFAULTS, array_intersect_key((array) $saved, self::DEFAULTS));

        // A color that would not scan is darkened, never drawn as is.
        $color = (string) $settings['color'];
        $settings['color'] = strtoupper(QrStyle::isReadable($color) ? $color : QrStyle::readable($color));

        if (! self::canCustomize($restaurant)) {
            return array_merge(self::DEFAULTS, ['color' => $settings['color']]);
        }

        if (! isset(QrStyle::STYLES[$settings['style']])) {
            $settings['style'] = 'square';
        }

        if (! isset(self::TEMPLATES[$settings['template']])) {
            $settings['template'] = 'classico';
        }

        $displayColor = (string) $settings['display_color'];
        $settings['display_color'] = QrStyle::isHex($displayColor) ? strtoupper($displayColor) : null;
        $displayText = (string) $settings['display_text'];
        $settings['display_text'] = QrStyle::isHex($displayText) ? strtoupper($displayText) : null;
        $settings['display_font'] = array_key_exists((string) $settings['display_font'], (array) config('feed.fonts')) ? $settings['display_font'] : null;
        $displayBg = (string) $settings['display_bg'];
        $settings['display_bg'] = QrStyle::isHex($displayBg) ? strtoupper($displayBg) : null;

        $hasLogo = $restaurant->logo_path !== null;
        $settings['logo'] = (bool) $settings['logo'] && $hasLogo;
        $settings['show_logo'] = (bool) $settings['show_logo'] && $hasLogo;
        $settings['show_name'] = (bool) $settings['show_name'];

        return $settings;
    }

    /**
     * The card's main color: the dono's choice or the restaurant color.
     *
     * @param  array<string, mixed>  $settings
     */
    public static function accent(Restaurant $restaurant, array $settings): string
    {
        return $settings['display_color'] ?? $restaurant->accentColor();
    }

    /**
     * The card's background: the dono's choice, or the model's own.
     *
     * @param  array<string, mixed>  $settings
     */
    public static function background(Restaurant $restaurant, array $settings): string
    {
        return $settings['display_bg'] ?? match ($settings['template']) {
            'moldura' => self::accent($restaurant, $settings),
            'escuro' => '#161412',
            default => '#FFFFFF',
        };
    }

    /**
     * Color of the letters: the dono's choice when it reads well on the background (it may have
     * changed since it was picked), otherwise black or white, whichever reads better.
     *
     * @param  array<string, mixed>  $settings
     */
    public static function textColor(Restaurant $restaurant, array $settings): string
    {
        $background = self::background($restaurant, $settings);
        $chosen = (string) ($settings['display_text'] ?? '');

        return QrStyle::isHex($chosen) && QrStyle::contrast($chosen, $background) >= QrStyle::MIN_CONTRAST
            ? strtoupper($chosen)
            : QrStyle::onColor($background);
    }

    /**
     * @param  array<string, mixed>  $settings  already resolved by {@see settings()}
     */
    public static function style(array $settings): QrStyle
    {
        return new QrStyle((string) $settings['color'], (string) $settings['style'], (bool) $settings['logo']);
    }

    /**
     * The restaurant logo as a data URI, so it travels inside the SVG (and into the PNG download).
     */
    public static function logoDataUri(Restaurant $restaurant): ?string
    {
        $path = $restaurant->logo_path;
        $disk = Storage::disk('public');

        if ($path === null || ! $disk->exists($path) || $disk->size($path) > self::MAX_LOGO_BYTES) {
            return null;
        }

        $mime = $disk->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) $disk->get($path));
    }

    /**
     * @param  array<string, mixed>|null  $override  unsaved choices (the live preview)
     */
    public static function svg(Restaurant $restaurant, ?array $override = null): string
    {
        $settings = self::settings($restaurant, $override);

        return QrCode::svg(
            $restaurant->feedUrl(),
            self::style($settings),
            $settings['logo'] ? self::logoDataUri($restaurant) : null,
        );
    }
}
