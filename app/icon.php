<?php
// app/icon.php
// Genera icona PNG dinamica PWA: usa logo azienda se disponibile, fallback Turnar.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../core/settings.php';

$size = isset($_GET['size']) ? (int)$_GET['size'] : 192;
if (!in_array($size, [180, 192, 512], true)) {
    $size = 192;
}

function icon_hex_to_rgb(string $hex, array $fallback): array
{
    $hex = trim($hex);
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
        return $fallback;
    }
    return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
}

function icon_load_source(string $path)
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!is_file($path)) {
        return null;
    }
    if ($ext === 'png' && function_exists('imagecreatefrompng')) return @imagecreatefrompng($path);
    if (($ext === 'jpg' || $ext === 'jpeg') && function_exists('imagecreatefromjpeg')) return @imagecreatefromjpeg($path);
    if ($ext === 'webp' && function_exists('imagecreatefromwebp')) return @imagecreatefromwebp($path);
    return null;
}

$companyLogoRel = function_exists('app_company_logo') ? trim((string)app_company_logo()) : '';
$companyLogoFs = $companyLogoRel !== '' ? realpath(__DIR__ . '/../' . ltrim($companyLogoRel, '/')) : false;
$rootFs = realpath(__DIR__ . '/..');

if (function_exists('imagecreatetruecolor') && $companyLogoFs && $rootFs && strpos($companyLogoFs, $rootFs) === 0) {
    $src = icon_load_source($companyLogoFs);
    if ($src) {
        $img = imagecreatetruecolor($size, $size);
        imagealphablending($img, true);
        imagesavealpha($img, true);

        [$r1, $g1, $b1] = icon_hex_to_rgb(function_exists('app_theme_primary') ? app_theme_primary() : '#6ea8ff', [110,168,255]);
        [$r2, $g2, $b2] = icon_hex_to_rgb(function_exists('app_theme_secondary') ? app_theme_secondary() : '#8b5cf6', [139,92,246]);

        for ($y = 0; $y < $size; $y++) {
            $ratio = $size > 1 ? $y / ($size - 1) : 0;
            $r = (int)round(($r1 * (1 - $ratio)) + ($r2 * $ratio));
            $g = (int)round(($g1 * (1 - $ratio)) + ($g2 * $ratio));
            $b = (int)round(($b1 * (1 - $ratio)) + ($b2 * $ratio));
            $color = imagecolorallocate($img, $r, $g, $b);
            imagefilledrectangle($img, 0, $y, $size, $y, $color);
        }

        $white = imagecolorallocate($img, 255, 255, 255);
        $pad = (int)round($size * 0.16);
        imagefilledellipse($img, (int)round($size / 2), (int)round($size / 2), $size - ($pad * 2), $size - ($pad * 2), $white);

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $box = (int)round($size * 0.58);
        $scale = min($box / max(1, $srcW), $box / max(1, $srcH));
        $dstW = max(1, (int)round($srcW * $scale));
        $dstH = max(1, (int)round($srcH * $scale));
        $dstX = (int)round(($size - $dstW) / 2);
        $dstY = (int)round(($size - $dstH) / 2);

        imagecopyresampled($img, $src, $dstX, $dstY, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($src);

        header('Content-Type: image/png');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        imagepng($img);
        imagedestroy($img);
        exit;
    }
}

if (!function_exists('imagecreatetruecolor')) {
    header('Content-Type: image/svg+xml; charset=UTF-8');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 512 512"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#6ea8ff"/><stop offset="100%" stop-color="#8b5cf6"/></linearGradient></defs><rect width="512" height="512" rx="110" fill="url(#g)"/><text x="256" y="300" text-anchor="middle" font-family="Arial, sans-serif" font-size="220" font-weight="700" fill="#ffffff">T</text></svg>';
    exit;
}

$img = imagecreatetruecolor($size, $size);
imagealphablending($img, true);
imagesavealpha($img, true);

$bg = imagecolorallocate($img, 11, 18, 38);
imagefilledrectangle($img, 0, 0, $size, $size, $bg);
for ($y = 0; $y < $size; $y++) {
    $ratio = $size > 1 ? $y / ($size - 1) : 0;
    $r = (int)round((110 * (1 - $ratio)) + (139 * $ratio));
    $g = (int)round((168 * (1 - $ratio)) + (92 * $ratio));
    $b = (int)round((255 * (1 - $ratio)) + (246 * $ratio));
    $color = imagecolorallocate($img, $r, $g, $b);
    imagefilledrectangle($img, (int)round($size * 0.09), (int)round($size * 0.09) + $y, (int)round($size * 0.91), (int)round($size * 0.09) + $y, $color);
}
$overlay = imagecolorallocatealpha($img, 255, 255, 255, 100);
imagefilledellipse($img, (int)round($size * 0.30), (int)round($size * 0.25), (int)round($size * 0.50), (int)round($size * 0.28), $overlay);
$textColor = imagecolorallocate($img, 255, 255, 255);
$font = 5; $text = 'T';
$textWidth = imagefontwidth($font) * strlen($text);
$textHeight = imagefontheight($font);
$scale = max(1, (int)floor($size / 80));
for ($dx = 0; $dx < $scale; $dx++) {
    for ($dy = 0; $dy < $scale; $dy++) {
        imagestring($img, $font, (int)round(($size - $textWidth * $scale) / 2) + $dx, (int)round(($size - $textHeight * $scale) / 2) + $dy, $text, $textColor);
    }
}
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
imagepng($img);
imagedestroy($img);
exit;
