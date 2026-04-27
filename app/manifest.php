<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/manifest+json; charset=UTF-8');

$base = app_mobile_url();
$startUrl = app_mobile_url('login.php');
$scope = app_mobile_url('');

echo json_encode([
    'name' => 'Turnar App',
    'short_name' => 'Turnar',
    'description' => 'App mobile Turnar per turni e notifiche.',
    'start_url' => $startUrl,
    'scope' => $scope,
    'display' => 'standalone',
    'background_color' => '#0b1226',
    'theme_color' => '#6ea8ff',
    'orientation' => 'portrait',
    'icons' => [
        [
            'src' => app_mobile_url('icon.php?size=192'),
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
        [
            'src' => app_mobile_url('icon.php?size=512'),
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);