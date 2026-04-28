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
$companyLogo = function_exists('app_company_logo') ? trim((string)app_company_logo()) : '';
$companyLogoUrl = $companyLogo !== '' ? app_url($companyLogo) : '';

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => app_url('modules/dashboard/index.php'), 'permission' => 'dashboard.view'],
    ['key' => 'operators', 'label' => 'Personale', 'href' => app_url('modules/operators/index.php'), 'permission' => 'operators.view'],
    ['key' => 'destinations', 'label' => 'Destinazioni', 'href' => app_url('modules/destinations/index.php'), 'permission' => 'destinations.view'],
    ['key' => 'assignments', 'label' => 'Turni', 'href' => app_url('modules/turni/planning.php'), 'permission' => 'assignments.view'],
    ['key' => 'calendar', 'label' => 'Calendario', 'href' => app_url('modules/turni/calendar.php'), 'permission' => 'assignments.view'],
    ['key' => 'communications', 'label' => 'Comunicazioni', 'href' => app_url('modules/communications/index.php'), 'permission' => 'communications.view'],
    ['key' => 'reports', 'label' => 'Report', 'href' => app_url('modules/reports/index.php'), 'permission' => 'reports.view'],
    ['key' => 'users', 'label' => 'Utenti', 'href' => app_url('modules/users/index.php'), 'permission' => 'users.view'],
    ['key' => 'settings', 'label' => 'Impostazioni', 'href' => app_url('modules/settings/index.php'), 'permission' => 'settings.view'],
    ['key' => 'mobile', 'label' => 'Mobile', 'href' => mobile_url('index.php'), 'permission' => null],
];
?>
<!DOCTYPE html>
<html lang="it" data-theme="<?php echo h($resolvedThemeMode); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo h($pageTitle); ?> · <?php echo h(app_name()); ?></title>
<link rel="stylesheet" href="<?php echo h(app_url('assets/css/turnar.css')); ?>">
<link rel="stylesheet" href="<?php echo h(app_url('assets/css/turnar-compact.css')); ?>">
<?php echo $extraHead; ?>
</head>
<body class="turnar-desktop-page">
<div class="app-shell">
    <header class="topbar">
        <div class="topbar-main">
            <a class="brand-wrap" href="<?php echo h(app_url('index.php')); ?>">
                <span class="brand-mark">
                    <?php if ($companyLogoUrl !== ''): ?>
                        <img src="<?php echo h($companyLogoUrl); ?>" alt="Logo">
                    <?php else: ?>
                        T
                    <?php endif; ?>
                </span>
                <span class="brand-copy">
                    <strong><?php echo h(app_name()); ?></strong>
                    <small><?php echo h(app_tagline()); ?></small>
                </span>
            </a>

            <nav class="topnav">
                <?php foreach ($navItems as $item): ?>
                    <?php
                    $moduleOk = function_exists('module_enabled') ? module_enabled($item['key']) : true;
                    $permissionOk = empty($item['permission']) || (function_exists('can') && can($item['permission']));
                    if (!$moduleOk || !$permissionOk) { continue; }
                    $isActive = $activeModule === $item['key'];
                    if ($activeModule === 'gantt' && $item['key'] === 'reports') { $isActive = true; }
                    ?>
                    <a class="topnav-link <?php echo $isActive ? 'active' : ''; ?>" href="<?php echo h($item['href']); ?>">
                        <?php echo h($item['label']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="topbar-actions">
                <form method="post" action="<?php echo h(app_url('modules/settings/toggle_theme.php')); ?>">
                    <button class="theme-toggle" type="submit">
                        <?php echo $resolvedThemeMode === 'dark' ? '☀️' : '🌙'; ?>
                    </button>
                </form>

                <div class="user-box">
                    <span class="user-name"><?php echo h($currentUserName); ?></span>
                    <?php if ($currentUserRole !== ''): ?>
                        <span class="user-meta"><?php echo h($currentUserRole); ?> · <?php echo h($currentUserScope); ?></span>
                    <?php endif; ?>
                </div>

                <?php if (auth_check()): ?>
                    <a class="logout-link compact" href="<?php echo h(app_url('modules/auth/logout.php')); ?>">Esci</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="page-wrap">
        <div class="page-head compact-page-head">
            <div>
                <h1><?php echo h($pageTitle); ?></h1>
                <?php if (trim((string)$pageSubtitle) !== ''): ?>
                    <p><?php echo h($pageSubtitle); ?></p>
                <?php endif; ?>
            </div>
        </div>
