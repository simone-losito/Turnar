<?php
// templates/layout_top.php
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/settings.php';

$pageTitle = $pageTitle ?? app_name();
$pageSubtitle = $pageSubtitle ?? '';
$activeModule = $activeModule ?? '';
$extraHead = $extraHead ?? '';

if (!function_exists('h')) {
    function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}

$isLogged = auth_check();
$currentUserName = $isLogged ? auth_display_name() : 'Ospite';
$currentUserRole = $isLogged ? role_label(auth_role()) : '';
$currentUserScope = $isLogged ? scope_label(auth_scope()) : '';
$themeMode = app_theme_mode();
$resolvedThemeMode = in_array($themeMode, ['dark','light'], true) ? $themeMode : 'dark';
$companyLogo = function_exists('app_company_logo') ? trim((string)app_company_logo()) : '';
$companyLogoUrl = $companyLogo !== '' ? app_url($companyLogo) : app_url('assets/img/turnar-logo.svg');

$navItems = [
    ['dashboard','Dashboard',app_url('modules/dashboard/index.php'),'dashboard.view'],
    ['operators','Personale',app_url('modules/operators/index.php'),'operators.view'],
    ['destinations','Destinazioni',app_url('modules/destinations/index.php'),'destinations.view'],
    ['assignments','Turni',app_url('modules/turni/planning.php'),'assignments.view'],
    ['calendar','Calendario',app_url('modules/turni/calendar.php'),'assignments.view'],
    ['communications','Comunicazioni',app_url('modules/communications/index.php'),'communications.view'],
    ['reports','Report',app_url('modules/reports/index.php'),'reports.view'],
    ['users','Utenti',app_url('modules/users/index.php'),'users.view'],
    ['settings','Impostazioni',app_url('modules/settings/index.php'),'settings.view'],
    ['mobile','Mobile',mobile_url('index.php'),null],
];
?>
<!DOCTYPE html>
<html lang="it" data-theme="<?php echo h($resolvedThemeMode); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo h($pageTitle); ?> · <?php echo h(app_name()); ?></title>
<link rel="stylesheet" href="<?php echo h(app_url('assets/css/turnar.css')); ?>">
<link rel="stylesheet" href="<?php echo h(app_url('assets/css/turnar-dark-tune.css')); ?>">
<link rel="stylesheet" href="<?php echo h(app_url('assets/css/turnar-compact.css')); ?>">
<link rel="stylesheet" href="<?php echo h(app_url('assets/css/operators-polish.css')); ?>">
<link rel="stylesheet" href="<?php echo h(app_url('assets/css/operators-edit-polish.css')); ?>">
<?php echo $extraHead; ?>
</head>
<body class="turnar-desktop-page">
<div class="app-shell">
<header class="topbar">
    <div class="topbar-main">
        <a class="brand-wrap" href="<?php echo h(app_url('index.php')); ?>">
            <span class="brand-mark"><img src="<?php echo h($companyLogoUrl); ?>" alt="Logo"></span>
            <span class="brand-copy"><strong><?php echo h(app_name()); ?></strong><small><?php echo h(app_tagline()); ?></small></span>
        </a>
        <nav class="topnav">
            <?php foreach ($navItems as $item): ?>
                <?php
                [$key, $label, $href, $permission] = $item;

                $moduleOk = function_exists('module_menu_visible') ? module_menu_visible($key) : true;
                $permissionOk = empty($permission) || (function_exists('can') && can($permission));

                if (!$moduleOk || !$permissionOk) {
                    continue;
                }

                $isActive = ($activeModule === $key) || ($activeModule === 'gantt' && $key === 'reports');
                ?>
                <a class="topnav-link <?php echo $isActive ? 'active' : ''; ?>" href="<?php echo h($href); ?>"><?php echo h($label); ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="topbar-actions">
            <form method="post" action="<?php echo h(app_url('modules/settings/toggle_theme.php')); ?>">
                <button class="theme-toggle" type="submit"><?php echo $resolvedThemeMode === 'dark' ? '☀️' : '🌙'; ?></button>
            </form>
            <div class="user-box">
                <span class="user-name"><?php echo h($currentUserName); ?></span>
                <?php if ($currentUserRole !== ''): ?>
                    <span class="user-meta"><?php echo h($currentUserRole); ?> · <?php echo h($currentUserScope); ?></span>
                <?php endif; ?>
            </div>
            <?php if ($isLogged): ?>
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
