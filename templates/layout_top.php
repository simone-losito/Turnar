<?php
// templates/layout_top.php
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/settings.php';

if (!isset($pageTitle)) { $pageTitle = app_name(); }
if (!isset($pageSubtitle)) { $pageSubtitle = ''; }
if (!isset($activeModule)) { $activeModule = ''; }
if (!isset($extraHead)) { $extraHead = ''; }

if (!function_exists('h')) {
    function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}

$currentUserName  = auth_check() ? auth_display_name() : 'Ospite';
$currentUserRole  = auth_check() ? role_label(auth_role()) : '';
$currentUserScope = auth_check() ? scope_label(auth_scope()) : '';

$themeMode = app_theme_mode();
$resolvedThemeMode = in_array($themeMode, ['dark','light'], true) ? $themeMode : 'dark';
?>
<!DOCTYPE html>
<html lang="it" data-theme="<?php echo h($resolvedThemeMode); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo h($pageTitle); ?> · <?php echo h(app_name()); ?></title>
<link rel="stylesheet" href="<?php echo h(app_url('assets/css/turnar.css')); ?>">
</head>
<body>

<header class="topbar">
    <div class="topbar-inner">
        <div class="brand-wrap">
            <strong><?php echo h(app_name()); ?></strong>
        </div>

        <div class="topbar-right" style="display:flex;gap:10px;align-items:center;">
            <form method="post" action="<?php echo h(app_url('modules/settings/toggle_theme.php')); ?>">
                <button class="btn btn-ghost" type="submit">🌙 / ☀️</button>
            </form>

            <div class="user-box">
                <span><?php echo h($currentUserName); ?></span>
            </div>
        </div>
    </div>
</header>

<main class="page-wrap">
