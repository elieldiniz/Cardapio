<?php

use App\Support\QrCode;
use App\Support\QrStyle;
use chillerlan\QRCode\QRCode as Reader;

const QR_TEST_URL = 'https://www.degustou.com.br/r/restaurante-exemplo-do-bairro';

/**
 * Redraws the geometry of a styled code with GD (what the SVG describes) and reads it back,
 * so every style is proven to still encode the same address.
 */
function readBackStyledQr(QrStyle $style, bool $withLogo, int $scale = 12): string
{
    $shapes = QrCode::shapes(QR_TEST_URL, $style, $withLogo);
    $px = $shapes['size'] * $scale;
    $im = imagecreatetruecolor($px, $px);
    imageantialias($im, true);

    $white = imagecolorallocate($im, 255, 255, 255);
    [$r, $g, $b] = sscanf(ltrim($style->color, '#'), '%02x%02x%02x');
    $dark = imagecolorallocate($im, $r, $g, $b);
    imagefilledrectangle($im, 0, 0, $px, $px, $white);

    foreach ($shapes['modules'] as [$x, $y]) {
        if ($style->style === 'dots') {
            imagefilledellipse($im, (int) (($x + 0.5) * $scale), (int) (($y + 0.5) * $scale), (int) (0.92 * $scale), (int) (0.92 * $scale), $dark);
        } else {
            imagefilledrectangle($im, $x * $scale, $y * $scale, ($x + 1) * $scale - 1, ($y + 1) * $scale - 1, $dark);
        }
    }

    foreach ($shapes['eyes'] as [$x, $y]) {
        $cx = (int) (($x + 3.5) * $scale);
        $cy = (int) (($y + 3.5) * $scale);

        if ($style->style === 'dots') {
            imagefilledellipse($im, $cx, $cy, 7 * $scale, 7 * $scale, $dark);
            imagefilledellipse($im, $cx, $cy, 5 * $scale, 5 * $scale, $white);
            imagefilledellipse($im, $cx, $cy, 3 * $scale, 3 * $scale, $dark);
        } else {
            imagefilledrectangle($im, $x * $scale, $y * $scale, ($x + 7) * $scale - 1, ($y + 7) * $scale - 1, $dark);
            imagefilledrectangle($im, ($x + 1) * $scale, ($y + 1) * $scale, ($x + 6) * $scale - 1, ($y + 6) * $scale - 1, $white);
            imagefilledrectangle($im, ($x + 2) * $scale, ($y + 2) * $scale, ($x + 5) * $scale - 1, ($y + 5) * $scale - 1, $dark);
        }
    }

    if ($shapes['logo'] !== null) {
        // The logo: a white disc with a colored picture in it.
        $center = (int) ($shapes['size'] / 2 * $scale);
        $diameter = (int) ($shapes['logo'] * $scale);
        imagefilledellipse($im, $center, $center, $diameter, $diameter, $white);
        imagefilledellipse($im, $center, $center, (int) ($diameter * 0.85), (int) ($diameter * 0.85), imagecolorallocate($im, 255, 107, 61));
    }

    ob_start();
    imagepng($im);

    return (string) (new Reader)->readFromBlob(ob_get_clean());
}

it('still encodes the same address in every style, color and with or without a logo', function (string $shape, bool $withLogo, string $color) {
    expect(readBackStyledQr(new QrStyle($color, $shape, $withLogo), $withLogo))->toBe(QR_TEST_URL);
})->with(function () {
    foreach (array_keys(QrStyle::STYLES) as $shape) {
        foreach ([false, true] as $withLogo) {
            foreach (['#1A1815', '#0B5D3B', '#7A1F2B', '#1D4ED8'] as $color) {
                yield "{$shape}".($withLogo ? ' + logo' : '')." {$color}" => [$shape, $withLogo, $color];
            }
        }
    }
});

it('draws the same geometry the svg describes, with the quiet zone and the logo cleared in the middle', function () {
    $logo = 'data:image/png;base64,'.base64_encode('x');
    $svg = QrCode::svg(QR_TEST_URL, new QrStyle('#0B5D3B', 'rounded', true), $logo);
    $shapes = QrCode::shapes(QR_TEST_URL, new QrStyle('#0B5D3B', 'rounded', true), true);

    expect($svg)->toStartWith('<svg')
        ->and($svg)->toContain('fill="#0B5D3B"')
        ->and($svg)->toContain('rx="0.3"')
        ->and($svg)->toContain($logo)
        ->and(substr_count($svg, '<rect x='))->toBeGreaterThan(count($shapes['modules']) - 1)
        ->and($shapes['eyes'])->toHaveCount(3)
        ->and($shapes['logo'] % 2)->toBe(1);

    // Nothing dark inside the cleared logo area.
    $size = $shapes['size'];
    $half = intdiv($shapes['logo'], 2);
    $mid = intdiv($size, 2);
    foreach ($shapes['modules'] as [$x, $y]) {
        expect(abs($x - $mid) <= $half && abs($y - $mid) <= $half)->toBeFalse();
    }
});

it('keeps the plain square style identical in meaning to a normal code (no eyes drawn separately)', function () {
    $shapes = QrCode::shapes(QR_TEST_URL, new QrStyle);

    expect($shapes['eyes'])->toBe([])->and($shapes['logo'])->toBeNull();
});

it('does not draw a logo when none is given, even if the style asks for one', function () {
    $svg = QrCode::svg(QR_TEST_URL, new QrStyle('#1A1815', 'square', true), null);

    expect($svg)->not->toContain('<image');
});

it('computes contrast and darkens a color until it reads well on white', function (string $hex, bool $readable) {
    expect(QrStyle::isReadable($hex))->toBe($readable)
        ->and(QrStyle::isReadable(QrStyle::readable($hex)))->toBeTrue();
})->with([
    'near black' => ['#1A1815', true],
    'dark green' => ['#0B5D3B', true],
    'restaurant orange' => ['#FF6B3D', false],
    'light yellow' => ['#FFE066', false],
    'white' => ['#FFFFFF', false],
]);

it('picks readable text color for a background', function () {
    expect(QrStyle::onColor('#161412'))->toBe('#FFFFFF')
        ->and(QrStyle::onColor('#FFE066'))->toBe('#1A1815')
        ->and(QrStyle::onColor('#FF6B3D'))->toBeIn(['#FFFFFF', '#1A1815']);
});

it('rejects values that are not hex colors', function () {
    expect(QrStyle::isHex('#FF6B3D'))->toBeTrue()
        ->and(QrStyle::isHex('red'))->toBeFalse()
        ->and(QrStyle::isHex('#FFF'))->toBeFalse()
        ->and(QrStyle::readable('javascript:alert(1)'))->toBe(QrStyle::DEFAULT_COLOR);
});
