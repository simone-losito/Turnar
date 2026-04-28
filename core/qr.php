<?php
// core/qr.php
// Helper QR semplice per badge digitali.
// Usa QRServer come generatore immagini. Il contenuto resta un URL Turnar.

require_once __DIR__ . '/helpers.php';

if (!function_exists('turnar_qr_url')) {
    function turnar_qr_url(string $data, int $size = 120): string
    {
        $size = max(80, min(512, $size));
        return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . rawurlencode($data);
    }
}
