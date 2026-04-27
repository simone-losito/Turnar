<?php
// app/icon.php
// Genera un'icona PNG dinamica per la PWA

$size = isset($_GET['size']) ? (int)$_GET['size'] : 192;
if (!in_array($size, [180, 192, 512], true)) {
    $size = 192;
}

if (!function_exists('imagecreatetruecolor')) {
    header('Content-Type: image/svg+xml; charset=UTF-8');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 512 512">
        <defs>
            <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#6ea8ff"/>
                <stop offset="100%" stop-color="#8b5cf6"/>
            </linearGradient>
        </defs>
        <rect width="512" height="512" rx="110" fill="url(#g)"/>
        <text x="256" y="300" text-anchor="middle" font-family="Arial, sans-serif" font-size="220" font-weight="700" fill="#ffffff">T</text>
    </svg>';
    exit;
}

$img = imagecreatetruecolor($size, $size);
imagealphablending($img, true);
imagesavealpha($img, true);

$bg = imagecolorallocate($img, 11, 18, 38);
imagefilledrectangle($img, 0, 0, $size, $size, $bg);

// gradiente semplice
for ($y = 0; $y < $size; $y++) {
    $ratio = $size > 1 ? $y / ($size - 1) : 0;
    $r = (int)round((110 * (1 - $ratio)) + (139 * $ratio));
    $g = (int)round((168 * (1 - $ratio)) + (92 * $ratio));
    $b = (int)round((255 * (1 - $ratio)) + (246 * $ratio));
    $color = imagecolorallocate($img, $r, $g, $b);

    imagefilledrectangle(
        $img,
        (int)round($size * 0.09),
        (int)round($size * 0.09) + $y,
        (int)round($size * 0.91),
        (int)round($size * 0.09) + $y,
        $color
    );
}

// maschera visiva bordi
$overlay = imagecolorallocatealpha($img, 255, 255, 255, 100);
imagefilledellipse(
    $img,
    (int)round($size * 0.30),
    (int)round($size * 0.25),
    (int)round($size * 0.50),
    (int)round($size * 0.28),
    $overlay
);

// testo
$textColor = imagecolorallocate($img, 255, 255, 255);
$font = 5;
$text = 'T';
$textWidth = imagefontwidth($font) * strlen($text);
$textHeight = imagefontheight($font);

$scale = max(1, (int)floor($size / 80));
for ($dx = 0; $dx < $scale; $dx++) {
    for ($dy = 0; $dy < $scale; $dy++) {
        imagestring(
            $img,
            $font,
            (int)round(($size - $textWidth * $scale) / 2) + $dx,
            (int)round(($size - $textHeight * $scale) / 2) + $dy,
            $text,
            $textColor
        );
    }
}

header('Content-Type: image/png');
imagepng($img);
imagedestroy($img);
exit;