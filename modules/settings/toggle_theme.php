<?php
// modules/settings/toggle_theme.php
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/settings.php';

require_login();

$current = app_theme_mode();
if (!in_array($current, ['dark', 'light'], true)) {
    $current = 'dark';
}

$next = $current === 'dark' ? 'light' : 'dark';
setting_set('theme_mode', $next);

$back = app_url();
if (!empty($_SERVER['HTTP_REFERER'])) {
    $ref = (string)$_SERVER['HTTP_REFERER'];
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host !== '' && strpos($ref, $host) !== false) {
        $back = $ref;
    }
}

header('Location: ' . $back);
exit;
