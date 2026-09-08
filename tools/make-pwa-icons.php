<?php

declare(strict_types=1);

ini_set('memory_limit', '512M');

/**
 * Generate Courtly PWA icons from the official logo mark.
 *
 * Pure PHP (no GD/Imagick): decodes the PNG logo, resamples it with a
 * Catmull-Rom cubic filter and composites it onto a background square.
 *
 * Usage:  php tools/make-pwa-icons.php
 */

if (! function_exists('gzuncompress')) {
    fwrite(STDERR, "The zlib extension is required but not loaded.\n");
    exit(1);
}

$assetDir = __DIR__ . '/../public/assets';
$outDir = $assetDir . '/icons/pwa';

$lightMarkPath = $assetDir . '/courtly-mark.png';      // light mark (white "C" + dot) on transparent
$darkMarkPath  = $assetDir . '/courtly-mark-dark.png'; // dark mark (navy "C" + dot) on transparent

if (! is_dir($outDir) && ! mkdir($outDir, 0777, true) && ! is_dir($outDir)) {
    fwrite(STDERR, "Cannot create output directory: {$outDir}\n");
    exit(1);
}

// ── PNG decode ──────────────────────────────────────────────────────────

function pngCrc(string $data): string
{
    return hash('crc32b', $data, true);
}

function pngChunk(string $type, string $data): string
{
    return pack('N', strlen($data)) . $type . $data . pngCrc($type . $data);
}

function paeth(int $a, int $b, int $c): int
{
    $p = $a + $b - $c;
    $pa = abs($p - $a);
    $pb = abs($p - $b);
    $pc = abs($p - $c);
    if ($pa <= $pb && $pa <= $pc) {
        return $a;
    }
    return $pb <= $pc ? $b : $c;
}

/**
 * Decode a PNG into [width, height, pixels] where pixels is a flat array of
 * [r,g,b,a] ints. Returns null on failure.
 */
function decodePng(string $path): ?array
{
    $data = @file_get_contents($path);
    if ($data === false || substr($data, 0, 8) !== "\x89PNG\r\n\x1a\n") {
        return null;
    }

    $pos = 8;
    $len = strlen($data);
    $ihdr = null;
    $plte = null;
    $trns = null;
    $idat = '';

    while ($pos + 8 <= $len) {
        $clen = unpack('N', substr($data, $pos, 4))[1];
        $type = substr($data, $pos + 4, 4);
        $chunk = substr($data, $pos + 8, $clen);
        $pos += 12 + $clen;

        if ($type === 'IHDR') {
            $ihdr = unpack('Nwidth/Nheight/CbitDepth/CcolorType/Ccompression/Cfilter/Cinterlace', $chunk);
        } elseif ($type === 'PLTE') {
            $plte = $chunk;
        } elseif ($type === 'tRNS') {
            $trns = $chunk;
        } elseif ($type === 'IDAT') {
            $idat .= $chunk;
        } elseif ($type === 'IEND') {
            break;
        }
    }

    if ($ihdr === null || $idat === '' || $ihdr['interlace'] !== 0 || $ihdr['compression'] !== 0 || $ihdr['filter'] !== 0) {
        return null;
    }

    $raw = @gzuncompress($idat);
    if ($raw === false) {
        return null;
    }

    $w = $ihdr['width'];
    $h = $ihdr['height'];
    $pixels = unfilterPng($raw, $w, $h, $ihdr['bitDepth'], $ihdr['colorType'], $plte, $trns);

    return [$w, $h, $pixels];
}

function unfilterPng(string $raw, int $w, int $h, int $bitDepth, int $colorType, ?string $plte, ?string $trns): array
{
    $channels = match ($colorType) {
        0 => 1, // grayscale
        2 => 3, // RGB
        3 => 1, // palette index
        4 => 2, // grayscale + alpha
        6 => 4, // RGBA
        default => 0,
    };
    if ($channels === 0) {
        return [];
    }

    $bpp = (int) max(1, ceil($channels * $bitDepth / 8));
    $stride = (int) ceil($w * $channels * $bitDepth / 8);
    $rawLen = strlen($raw);
    $pos = 0;

    $prev = array_fill(0, $stride, 0);
    $rows = [];

    for ($y = 0; $y < $h; $y++) {
        if ($pos >= $rawLen) {
            break;
        }
        $filter = ord($raw[$pos]);
        $pos++;
        $line = [];
        for ($i = 0; $i < $stride; $i++) {
            if ($pos >= $rawLen) {
                break;
            }
            $x = ord($raw[$pos]);
            $pos++;
            $a = $i >= $bpp ? $line[$i - $bpp] : 0;
            $b = $prev[$i] ?? 0;
            $c = $i >= $bpp ? ($prev[$i - $bpp] ?? 0) : 0;
            $val = match ($filter) {
                1 => ($x + $a) & 0xff,
                2 => ($x + $b) & 0xff,
                3 => ($x + intdiv($a + $b, 2)) & 0xff,
                4 => ($x + paeth($a, $b, $c)) & 0xff,
                default => $x,
            };
            $line[] = $val;
        }
        $rows[] = $line;
        $prev = $line;
    }

    return unpackPixels($rows, $w, $h, $bitDepth, $colorType, $plte, $trns);
}

function unpackPixels(array $rows, int $w, int $h, int $bitDepth, int $colorType, ?string $plte, ?string $trns): array
{
    $pixels = [];
    $channels = match ($colorType) {
        0 => 1, 2 => 3, 3 => 1, 4 => 2, 6 => 4,
        default => 0,
    };

    $palette = [];
    $palAlpha = [];
    if ($colorType === 3 && $plte !== null) {
        for ($i = 0; $i + 2 < strlen($plte); $i += 3) {
            $palette[] = [ord($plte[$i]), ord($plte[$i + 1]), ord($plte[$i + 2])];
            $palAlpha[] = 255;
        }
        if ($trns !== null) {
            for ($i = 0; $i < strlen($trns); $i++) {
                if (isset($palAlpha[$i])) {
                    $palAlpha[$i] = ord($trns[$i]);
                }
            }
        }
    }

    $trGray = null;
    if ($colorType === 0 && $trns !== null && strlen($trns) >= 2) {
        $trGray = unpack('n', substr($trns, 0, 2))[1];
    }
    $trRgb = null;
    if ($colorType === 2 && $trns !== null && strlen($trns) >= 6) {
        $v = unpack('n3', substr($trns, 0, 6));
        $trRgb = [$v[1], $v[2], $v[3]];
    }

    // Sub-byte depths (grayscale / palette).
    if ($bitDepth < 8) {
        $bitsPerPixel = $channels * $bitDepth; // channels is 1 here
        $perByte = intdiv(8, $bitsPerPixel);
        $mask = (1 << $bitDepth) - 1;
        foreach ($rows as $row) {
            foreach ($row as $byte) {
                for ($k = $perByte - 1; $k >= 0; $k--) {
                    $v = ($byte >> ($k * $bitDepth)) & $mask;
                    if ($colorType === 3) {
                        $c = $palette[$v] ?? [0, 0, 0];
                        $pixels[] = [$c[0], $c[1], $c[2], $palAlpha[$v] ?? 255];
                    } else {
                        $g = (int) round($v * 255 / $mask);
                        $pixels[] = [$g, $g, $g, 255];
                    }
                    if (count($pixels) >= $w * $h) {
                        break 2;
                    }
                }
            }
        }
        return $pixels;
    }

    $step = $bitDepth === 16 ? 2 : 1;

    foreach ($rows as $row) {
        $count = count($row);
        for ($i = 0; $i + $step * $channels - 1 < $count; $i += $step * $channels) {
            if ($colorType === 6) { // RGBA
                $r = $row[$i];
                $g = $row[$i + $step];
                $b = $row[$i + 2 * $step];
                $a = $row[$i + 3 * $step];
                $pixels[] = [$r, $g, $b, $a];
            } elseif ($colorType === 2) { // RGB
                $r = $row[$i];
                $g = $row[$i + $step];
                $b = $row[$i + 2 * $step];
                $a = 255;
                if ($trRgb !== null && $r === ($trRgb[0] >> 8) && $g === ($trRgb[1] >> 8) && $b === ($trRgb[2] >> 8)) {
                    $a = 0;
                }
                $pixels[] = [$r, $g, $b, $a];
            } elseif ($colorType === 4) { // grayscale + alpha
                $v = $row[$i];
                $a = $row[$i + $step];
                $pixels[] = [$v, $v, $v, $a];
            } elseif ($colorType === 0) { // grayscale
                $v = $row[$i];
                $a = 255;
                if ($trGray !== null && $v === ($trGray >> 8)) {
                    $a = 0;
                }
                $pixels[] = [$v, $v, $v, $a];
            } elseif ($colorType === 3) { // palette (8-bit)
                $c = $palette[$row[$i]] ?? [0, 0, 0];
                $pixels[] = [$c[0], $c[1], $c[2], $palAlpha[$row[$i]] ?? 255];
            }
        }
    }

    return $pixels;
}

// ── PNG encode ──────────────────────────────────────────────────────────

function encodePngFromRaw(int $w, int $h, string $raw): string
{
    $ihdr = pack('N', $w) . pack('N', $h) . "\x08\x06\x00\x00\x00";

    return "\x89PNG\r\n\x1a\n"
        . pngChunk('IHDR', $ihdr)
        . pngChunk('IDAT', gzcompress($raw, 9))
        . pngChunk('IEND', '');
}

// ── Resampling + compositing ────────────────────────────────────────────

function clampF(float $v, float $lo, float $hi): float
{
    return max($lo, min($hi, $v));
}

function cubicKernel(float $x): float
{
    $x = abs($x);
    if ($x <= 1.0) {
        return (1.5 * $x - 2.5) * $x * $x + 1.0;
    }
    if ($x < 2.0) {
        return ((-0.5 * $x + 2.5) * $x - 4.0) * $x + 2.0;
    }
    return 0.0;
}

function sampleCubic(array $src, int $w, int $h, float $x, float $y): array
{
    $xi = (int) floor($x);
    $yi = (int) floor($y);
    $fx = $x - $xi;
    $fy = $y - $yi;
    $r = $g = $b = $a = $wsum = 0.0;

    for ($m = -1; $m <= 2; $m++) {
        $sy = $yi + $m;
        if ($sy < 0 || $sy >= $h) {
            continue;
        }
        $wy = cubicKernel($fy - $m);
        if ($wy == 0.0) {
            continue;
        }
        for ($n = -1; $n <= 2; $n++) {
            $sx = $xi + $n;
            if ($sx < 0 || $sx >= $w) {
                continue;
            }
            $wx = cubicKernel($fx - $n);
            $wgt = $wx * $wy;
            if ($wgt == 0.0) {
                continue;
            }
            $p = $src[$sy * $w + $sx];
            $r += $p[0] * $wgt;
            $g += $p[1] * $wgt;
            $b += $p[2] * $wgt;
            $a += $p[3] * $wgt;
            $wsum += $wgt;
        }
    }

    if ($wsum == 0.0) {
        return [0, 0, 0, 0];
    }

    return [
        (int) round(clampF($r / $wsum, 0, 255)),
        (int) round(clampF($g / $wsum, 0, 255)),
        (int) round(clampF($b / $wsum, 0, 255)),
        (int) round(clampF($a / $wsum, 0, 255)),
    ];
}

function roundedRectCov(float $x, float $y, float $w, float $h, float $r): float
{
    $qx = abs($x - $w / 2) - ($w / 2 - $r);
    $qy = abs($y - $h / 2) - ($h / 2 - $r);
    $d = hypot(max($qx, 0.0), max($qy, 0.0)) + min(max($qx, $qy), 0.0) - $r;
    return clampF(0.5 - $d, 0.0, 1.0);
}

/**
 * Render a square icon to raw PNG scanlines: background + centered mark.
 * $fullBleed: true → opaque square (maskable / apple touch), false → rounded.
 * $markScale: mark width as a fraction of the icon size.
 */
function renderIconRaw(int $size, array $mark, int $mw, int $mh, array $bg, bool $fullBleed, float $markScale): string
{
    $corner = 0.22 * $size;
    $scale = ($size * $markScale) / max($mw, $mh);
    $drawW = $mw * $scale;
    $drawH = $mh * $scale;
    $ox = ($size - $drawW) / 2;
    $oy = ($size - $drawH) / 2;

    $raw = '';
    for ($y = 0; $y < $size; $y++) {
        $raw .= "\x00";
        for ($x = 0; $x < $size; $x++) {
            $cov = $fullBleed ? 1.0 : roundedRectCov((float) $x, (float) $y, (float) $size, (float) $size, $corner);
            $r = (float) $bg[0];
            $g = (float) $bg[1];
            $b = (float) $bg[2];
            $a = $cov;

            if ($cov > 0.0) {
                $sx = ($x - $ox) / $scale;
                $sy = ($y - $oy) / $scale;
                if ($sx >= -0.5 && $sx <= $mw - 0.5 && $sy >= -0.5 && $sy <= $mh - 0.5) {
                    $m = sampleCubic($mark, $mw, $mh, $sx, $sy);
                    $ma = clampF($m[3] / 255.0, 0.0, 1.0);
                    if ($ma > 0.0) {
                        $r = $m[0] * $ma + $r * (1.0 - $ma);
                        $g = $m[1] * $ma + $g * (1.0 - $ma);
                        $b = $m[2] * $ma + $b * (1.0 - $ma);
                    }
                }
            }

            $raw .= pack('C4',
                (int) round(clampF($r, 0, 255)),
                (int) round(clampF($g, 0, 255)),
                (int) round(clampF($b, 0, 255)),
                (int) round(clampF($a * 255.0, 0, 255))
            );
        }
    }

    return $raw;
}

// ── Main ────────────────────────────────────────────────────────────────

$lightMark = decodePng($lightMarkPath);
$darkMark = decodePng($darkMarkPath);

if ($lightMark === null) {
    fwrite(STDERR, "Failed to decode light mark: {$lightMarkPath}\n");
    exit(1);
}
if ($darkMark === null) {
    fwrite(STDERR, "Failed to decode dark mark: {$darkMarkPath}\n");
    exit(1);
}

echo sprintf("light mark: %s (%dx%d, %d px)\n", basename($lightMarkPath), $lightMark[0], $lightMark[1], count($lightMark[2]));
echo sprintf("dark mark:  %s (%dx%d, %d px)\n", basename($darkMarkPath), $darkMark[0], $darkMark[1], count($darkMark[2]));

$navy  = [11, 14, 42];    // #0b0e2a
$paper = [245, 245, 250]; // #f5f5fa

// Same logo at every required size — only the dimensions change, never the
// image or its quality (each size is rendered once, straight from the source
// mark with the Catmull-Rom resampler).
$anySizes  = [72, 96, 128, 144, 152, 192, 384, 512];
$maskSizes = [192, 512];

$targets = [];
foreach ($anySizes as $s) {
    $targets["icon-{$s}.png"]       = [$s, $lightMark, $navy, false, 0.78];
    $targets["icon-light-{$s}.png"] = [$s, $darkMark, $paper, false, 0.78];
}
foreach ($maskSizes as $s) {
    $targets["icon-maskable-{$s}.png"]       = [$s, $lightMark, $navy, true, 0.58];
    $targets["icon-light-maskable-{$s}.png"] = [$s, $darkMark, $paper, true, 0.58];
}
$targets['apple-touch-icon.png'] = [180, $lightMark, $navy, true, 0.68];

foreach ($targets as $file => [$size, $mark, $bg, $fullBleed, $markScale]) {
    $raw = renderIconRaw($size, $mark[2], $mark[0], $mark[1], $bg, $fullBleed, $markScale);
    $path = $outDir . '/' . $file;
    file_put_contents($path, encodePngFromRaw($size, $size, $raw));
    echo "wrote {$path} ({$size}x{$size})\n";
}

echo "Done.\n";
