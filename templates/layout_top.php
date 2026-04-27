<?php
// templates/layout_top.php
// Layout superiore condiviso di Turnar

require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/settings.php';

if (!isset($pageTitle)) {
    $pageTitle = app_name();
}

if (!isset($pageSubtitle)) {
    $pageSubtitle = '';
}

if (!isset($activeModule)) {
    $activeModule = '';
}

if (!isset($extraHead)) {
    $extraHead = '';
}

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$currentUserName  = auth_check() ? auth_display_name() : 'Ospite';
$currentUserRole  = auth_check() ? role_label(auth_role()) : '';
$currentUserScope = auth_check() ? scope_label(auth_scope()) : '';

// --------------------------------------------------
// RICONOSCIMENTO PAGINA ATTIVA MENU
// --------------------------------------------------
$currentScriptPath = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$currentRequestUri = str_replace('\\', '/', (string)($_SERVER['REQUEST_URI'] ?? ''));
$currentFullPath   = $currentRequestUri !== '' ? $currentRequestUri : $currentScriptPath;

$effectiveActiveModule = $activeModule;

$ganttNeedles = [
    '/modules/reports/report_gantt_destination.php',
    '/modules/reports/report_gantt_destination_print.php',
    '/modules/reports/export_gantt_destination_csv.php',
];

foreach ($ganttNeedles as $needle) {
    if (strpos($currentFullPath, $needle) !== false) {
        $effectiveActiveModule = 'reports_gantt';
        break;
    }
}

$specialReportNeedles = [
    '/modules/reports/report_special_destinations.php',
    '/modules/reports/export_special_destinations_csv.php',
];
foreach ($specialReportNeedles as $needle) {
    if (strpos($currentFullPath, $needle) !== false) {
        $effectiveActiveModule = 'reports_special';
        break;
    }
}

if (strpos($currentFullPath, '/modules/dashboard/special_overview.php') !== false) {
    $effectiveActiveModule = 'dashboard_special';
}

$navItems = [
    [
        'key'        => 'dashboard',
        'label'      => 'Dashboard',
        'url'        => app_url(),
        'enabled'    => module_enabled('dashboard'),
        'permission' => 'dashboard.view',
    ],
    [
        'key'        => 'dashboard_special',
        'label'      => 'Dashboard HR',
        'url'        => app_url('modules/dashboard/special_overview.php'),
        'enabled'    => module_enabled('dashboard'),
        'permission' => 'dashboard.view',
    ],
    [
        'key'        => 'operators',
        'label'      => 'Personale',
        'url'        => app_url('modules/operators/index.php'),
        'enabled'    => module_enabled('operators'),
        'permission' => 'operators.view',
    ],
    [
        'key'        => 'destinations',
        'label'      => 'Destinazioni',
        'url'        => app_url('modules/destinations/index.php'),
        'enabled'    => module_enabled('destinations'),
        'permission' => 'destinations.view',
    ],
    [
        'key'        => 'assignments',
        'label'      => 'Turni',
        'url'        => app_url('modules/turni/index.php'),
        'enabled'    => module_enabled('assignments'),
        'permission' => 'assignments.view',
    ],
    [
        'key'        => 'calendar',
        'label'      => 'Calendario',
        'url'        => app_url('modules/turni/calendar.php'),
        'enabled'    => module_enabled('calendar'),
        'permission' => 'calendar.view',
    ],
    [
        'key'        => 'communications',
        'label'      => 'Comunicazioni',
        'url'        => app_url('modules/communications/index.php'),
        'enabled'    => module_enabled('communications'),
        'permission' => 'communications.view',
    ],
    [
        'key'        => 'reports',
        'label'      => 'Report',
        'url'        => app_url('modules/reports/index.php'),
        'enabled'    => module_enabled('reports'),
        'permission' => 'reports.view',
    ],
    [
        'key'        => 'reports_special',
        'label'      => 'Report HR',
        'url'        => app_url('modules/reports/report_special_destinations.php'),
        'enabled'    => module_enabled('reports'),
        'permission' => 'reports.view',
    ],
    [
        'key'        => 'reports_gantt',
        'label'      => 'Gantt',
        'url'        => app_url('modules/reports/report_gantt_destination.php'),
        'enabled'    => module_enabled('reports'),
        'permission' => 'reports.view',
    ],
    [
        'key'        => 'users',
        'label'      => 'Gestione Utenti',
        'url'        => app_url('modules/users/index.php'),
        'enabled'    => module_enabled('users'),
        'permission' => 'users.view',
    ],
    [
        'key'        => 'settings',
        'label'      => 'Impostazioni',
        'url'        => app_url('modules/settings/index.php'),
        'enabled'    => module_enabled('settings'),
        'permission' => 'settings.view',
    ],
    [
        'key'        => 'mobile',
        'label'      => 'Mobile',
        'url'        => mobile_url(),
        'enabled'    => module_enabled('mobile'),
        'permission' => 'mobile.view',
    ],
];

$companyLogoPath = function_exists('app_company_logo') ? trim((string)app_company_logo()) : '';
$companyLogoUrl  = $companyLogoPath !== '' ? app_url($companyLogoPath) : '';

$topbarLogoMode = function_exists('app_topbar_logo_mode') ? trim((string)app_topbar_logo_mode()) : 'logo_and_name';
if (!in_array($topbarLogoMode, ['icon', 'logo', 'logo_and_name'], true)) {
    $topbarLogoMode = 'logo_and_name';
}

$appTitle   = app_name();
$appTagline = function_exists('app_tagline') ? app_tagline() : (string)setting('app_tagline', 'Organizzazione turni e operatività');

$favicon    = function_exists('app_favicon') ? trim((string)app_favicon()) : '';
$faviconUrl = $favicon !== '' ? app_url($favicon) : '';

$themeMode      = function_exists('app_theme_mode') ? app_theme_mode() : (string)setting('theme_mode', 'dark');
$themePrimary   = function_exists('app_theme_primary') ? app_theme_primary() : (string)setting('theme_primary_color', '#6ea8ff');
$themeSecondary = function_exists('app_theme_secondary') ? app_theme_secondary() : (string)setting('theme_secondary_color', '#8b5cf6');

if (!preg_match('/^#[0-9a-fA-F]{6}$/', $themePrimary)) {
    $themePrimary = '#6ea8ff';
}

if (!preg_match('/^#[0-9a-fA-F]{6}$/', $themeSecondary)) {
    $themeSecondary = '#8b5cf6';
}

if (!in_array($themeMode, ['dark', 'light', 'auto'], true)) {
    $themeMode = 'dark';
}

$resolvedThemeMode = $themeMode === 'auto' ? 'dark' : $themeMode;
?>
<!DOCTYPE html>
<html lang="it" data-theme="<?php echo h($resolvedThemeMode); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?> · <?php echo h($appTitle); ?></title>

    <?php if ($faviconUrl !== ''): ?>
        <link rel="icon" type="image/png" href="<?php echo h($faviconUrl); ?>">
    <?php endif; ?>

    <style>
        :root{
            --primary:<?php echo h($themePrimary); ?>;
            --primary-2:<?php echo h($themeSecondary); ?>;
        }
    </style>

    <link rel="stylesheet" href="<?php echo h(app_url('assets/css/turnar.css')); ?>?v=<?php echo urlencode((string)app_version()); ?>">

    <?php echo $extraHead; ?>
</head>
<body class="turnar-desktop-page theme-<?php echo h($resolvedThemeMode); ?>">
<div class="app-shell">

    <header class="topbar">
        <div class="topbar-inner">
            <div class="brand-wrap">
                <div class="brand-logo <?php echo $companyLogoUrl !== '' ? 'has-image' : ''; ?> <?php echo $topbarLogoMode === 'logo' ? 'logo-only' : ''; ?>">
                    <?php if ($companyLogoUrl !== ''): ?>
                        <img src="<?php echo h($companyLogoUrl); ?>" alt="Logo aziendale">
                    <?php else: ?>
                        T
                    <?php endif; ?>
                </div>

                <?php if ($topbarLogoMode !== 'logo'): ?>
                    <div class="brand-text">
                        <h1 class="brand-title"><?php echo h($appTitle); ?></h1>
                        <div class="brand-subtitle">
                            <?php echo h($appTagline); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="topbar-right">
                <div class="user-box">
                    <div class="user-box-label">Utente corrente</div>
                    <div class="user-box-main">
                        <span><?php echo h($currentUserName); ?></span>

                        <?php if ($currentUserRole !== ''): ?>
                            <span class="role-pill"><?php echo h($currentUserRole); ?></span>
                        <?php endif; ?>

                        <?php if ($currentUserScope !== ''): ?>
                            <span class="scope-pill"><?php echo h($currentUserScope); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <nav class="nav-bar">
            <?php foreach ($navItems as $item): ?>
                <?php
                if (empty($item['enabled'])) {
                    continue;
                }

                $permission = trim((string)($item['permission'] ?? ''));

                if ($permission !== '' && auth_check() && !can($permission)) {
                    continue;
                }

                if ($permission !== '' && !auth_check() && $item['key'] !== 'mobile') {
                    continue;
                }

                $isActive = ($effectiveActiveModule === $item['key']);
                ?>
                <a
                    class="nav-link <?php echo $isActive ? 'active' : ''; ?>"
                    href="<?php echo h($item['url']); ?>"
                >
                    <?php echo h($item['label']); ?>
                </a>
            <?php endforeach; ?>

            <?php if (auth_check()): ?>
                <a class="nav-link logout" href="<?php echo h(app_url('modules/auth/logout.php')); ?>">
                    Esci
                </a>
            <?php endif; ?>
        </nav>
    </header>

    <main class="page-wrap">
        <div class="page-head">
            <div class="page-head-left">
                <h1><?php echo h($pageTitle); ?></h1>
                <?php if ($pageSubtitle !== ''): ?>
                    <p><?php echo h($pageSubtitle); ?></p>
                <?php endif; ?>
            </div>

            <div class="page-head-right">
                <span class="soft-pill">Versione <?php echo h(app_version()); ?></span>
                <span class="soft-pill"><?php echo h(format_datetime_it(now_datetime())); ?></span>
            </div>
        </div>