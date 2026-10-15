<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\QRCode as Generator;
use chillerlan\QRCode\QROptions;

/**
 * QR codes as inline SVG — no image extension needed on the server.
 *
 * The matrix comes from the library; the drawing is ours, so the modules can be
 * squares, rounded squares or dots, in any readable color, with the restaurant
 * logo in the middle. Styling never changes what is encoded: the URL is the same.
 */
class QrCode
{
    /** Quiet zone (modules of white around the code) the matrix is built with. */
    public const QUIET_ZONE = 4;

    /** The logo covers about this share of the code's width, which error correction level H absorbs easily. */
    private const LOGO_SHARE = 0.25;

    public static function svg(string $data, ?QrStyle $style = null, ?string $logoDataUri = null): string
    {
        $style ??= new QrStyle;
        $withLogo = $style->logo && $logoDataUri !== null;
        $shapes = self::shapes($data, $style, $withLogo);
        $size = $shapes['size'];
        $color = $style->color;

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 '.$size.' '.$size.'" shape-rendering="'.($style->style === 'square' ? 'crispEdges' : 'geometricPrecision').'" role="img" aria-label="QR Code do cardápio">';
        $svg .= '<rect width="'.$size.'" height="'.$size.'" fill="#FFFFFF"/>';
        $svg .= '<g fill="'.$color.'">';

        if ($style->style === 'square') {
            $svg .= '<path d="'.implode('', array_map(fn (array $m) => "M{$m[0]} {$m[1]}h1v1h-1z", $shapes['modules'])).'"/>';
        } else {
            foreach ($shapes['modules'] as [$x, $y]) {
                $svg .= $style->style === 'rounded'
                    ? '<rect x="'.$x.'" y="'.$y.'" width="1" height="1" rx="0.3"/>'
                    : '<circle cx="'.($x + 0.5).'" cy="'.($y + 0.5).'" r="0.46"/>';
            }

            $svg .= self::eyes($shapes['eyes'], $style->style, $color);
        }

        $svg .= '</g>';

        if ($shapes['logo'] !== null) {
            $svg .= self::logo($size, $shapes['logo'], $logoDataUri, $color);
        }

        return $svg.'</svg>';
    }

    /**
     * The geometry of a styled code, independent of how it is drawn: dark modules, the three
     * "eyes" (only for non-square styles, which draw them as one shape) and the logo size.
     * Having it separate lets the tests redraw it and read it back.
     *
     * @return array{size: int, modules: array<int, array{0: int, 1: int}>, eyes: array<int, array{0: int, 1: int}>, logo: int|null}
     */
    public static function shapes(string $data, QrStyle $style, bool $withLogo = false): array
    {
        $matrix = self::matrix($data, $style, $withLogo);
        $size = $matrix->getSize();
        $customEyes = $style->style !== 'square';
        $finders = self::finders($size);

        $modules = [];

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($matrix->check($x, $y) && ! ($customEyes && self::insideFinder($x, $y, $finders))) {
                    $modules[] = [$x, $y];
                }
            }
        }

        return [
            'size' => $size,
            'modules' => $modules,
            'eyes' => $customEyes ? $finders : [],
            'logo' => $withLogo ? self::logoModules($size) : null,
        ];
    }

    /**
     * The module matrix (quiet zone included). With a logo the middle is cleared and the
     * error correction is raised; stylized shapes get a bit more correction as well.
     */
    public static function matrix(string $data, QrStyle $style, bool $withLogo = false): QRMatrix
    {
        $ecc = match (true) {
            $withLogo => EccLevel::H,
            $style->style !== 'square' => EccLevel::Q,
            default => EccLevel::M,
        };

        $options = [
            'eccLevel' => $ecc,
            'addQuietzone' => true,
            'quietzoneSize' => self::QUIET_ZONE,
        ];

        if ($withLogo) {
            // The version (and so the size) depends on the data and level: build once, then clear the middle.
            $size = (new Generator(new QROptions($options)))->addByteSegment($data)->getQRMatrix()->getSize();
            $logo = self::logoModules($size);
            $options += ['addLogoSpace' => true, 'logoSpaceWidth' => $logo, 'logoSpaceHeight' => $logo];
        }

        return (new Generator(new QROptions($options)))->addByteSegment($data)->getQRMatrix();
    }

    /**
     * Odd number of modules the logo spans, so it sits exactly in the middle of the (odd-sized) matrix.
     */
    public static function logoModules(int $matrixSize): int
    {
        $dataArea = $matrixSize - 2 * self::QUIET_ZONE;

        return ((int) round($dataArea * self::LOGO_SHARE)) | 1;
    }

    /**
     * @return array<int, array{0: int, 1: int}> top-left module of the three 7×7 finder patterns ("eyes")
     */
    private static function finders(int $size): array
    {
        $q = self::QUIET_ZONE;

        return [[$q, $q], [$size - $q - 7, $q], [$q, $size - $q - 7]];
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $finders
     */
    private static function insideFinder(int $x, int $y, array $finders): bool
    {
        foreach ($finders as [$fx, $fy]) {
            if ($x >= $fx && $x < $fx + 7 && $y >= $fy && $y < $fy + 7) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $finders
     */
    private static function eyes(array $finders, string $style, string $color): string
    {
        $out = '';

        foreach ($finders as [$x, $y]) {
            $out .= $style === 'dots'
                ? '<circle cx="'.($x + 3.5).'" cy="'.($y + 3.5).'" r="3" fill="none" stroke="'.$color.'" stroke-width="1"/><circle cx="'.($x + 3.5).'" cy="'.($y + 3.5).'" r="1.5"/>'
                : '<rect x="'.($x + 0.5).'" y="'.($y + 0.5).'" width="6" height="6" rx="1.6" fill="none" stroke="'.$color.'" stroke-width="1"/><rect x="'.($x + 2).'" y="'.($y + 2).'" width="3" height="3" rx="0.9"/>';
        }

        return $out;
    }

    private static function logo(int $size, int $modules, string $dataUri, string $color): string
    {
        $center = $size / 2;
        $radius = $modules / 2 - 0.3;
        $box = $radius * 2;

        return '<defs><clipPath id="qr-logo-clip"><circle cx="'.$center.'" cy="'.$center.'" r="'.($radius - 0.25).'"/></clipPath></defs>'
            .'<circle cx="'.$center.'" cy="'.$center.'" r="'.$radius.'" fill="#FFFFFF" stroke="'.$color.'" stroke-width="0.25"/>'
            .'<image xlink:href="'.$dataUri.'" x="'.($center - $radius).'" y="'.($center - $radius).'" width="'.$box.'" height="'.$box.'" preserveAspectRatio="xMidYMid slice" clip-path="url(#qr-logo-clip)"/>';
    }
}
