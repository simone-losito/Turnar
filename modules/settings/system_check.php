<?php
// modules/settings/system_check.php

require_once __DIR__ . '/../../core/helpers.php';

require_login();
require_permission('settings.view');

$pageTitle = 'Controllo sistema';
$pageSubtitle = 'Checklist tecnica per verificare che Turnar sia pronto all’uso';
$activeModule = 'settings';

$db = db_connect();

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function check_table_exists(mysqli $db, string $table): bool
{
    $safe = $db->real_escape_string($table);
    $res = $db->query("SHOW TABLES LIKE '{$safe}'");
    if ($res instanceof mysqli_result) {
        $ok = $res->num_rows > 0;
        $res->free();
        return $ok;
    }
    return false;
}

function check_column_exists(mysqli $db, string $table, string $column): bool
{
    $stmt = $db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    if (!$stmt) return false;
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res instanceof mysqli_result && $res->num_rows > 0;
    $stmt->close();
    return $ok;
}

function check_file_exists_rel(string $path): bool
{
    return is_file(dirname(__DIR__, 2) . '/' . ltrim($path, '/'));
}

function check_dir_writable_rel(string $path): bool
{
    $full = dirname(__DIR__, 2) . '/' . trim($path, '/');
    return is_dir($full) && is_writable($full);
}

$checks = [];

$requiredTables = [
    'users' => 'Utenti e login',
    'dipendenti' => 'Anagrafica personale',
    'cantieri' => 'Destinazioni / cantieri',
    'eventi_turni' => 'Turni pianificati',
    'settings' => 'Impostazioni dinamiche',
];

foreach ($requiredTables as $table => $label) {
    $checks[] = [
        'area' => 'Database',
        'label' => $label,
        'ok' => check_table_exists($db, $table),
        'hint' => "Tabella richiesta: {$table}",
    ];
}

$specialColumns = ['is_special', 'special_type', 'counts_as_work', 'counts_as_absence'];
foreach ($specialColumns as $column) {
    $checks[] = [
        'area' => 'Destinazioni speciali',
        'label' => 'Colonna cantieri.' . $column,
        'ok' => check_column_exists($db, 'cantieri', $column),
        'hint' => 'Se manca, esegui database/migrations/2026_04_27_special_destinations.sql',
    ];
}

$requiredFiles = [
    'core/special_destinations.php' => 'Motore destinazioni speciali',
    'modules/reports/report_special_destinations.php' => 'Report HR speciale',
    'modules/reports/export_special_destinations_csv.php' => 'Export CSV HR',
    'modules/reports/report_special_destinations_print.php' => 'Stampa/PDF HR',
    'modules/dashboard/special_overview.php' => 'Dashboard HR',
    'app/index.php' => 'App mobile / PWA',
    'assets/css/turnar.css' => 'CSS principale',
    'assets/css/turnar-polish.css' => 'CSS polish finale',
];

foreach ($requiredFiles as $path => $label) {
    $checks[] = [
        'area' => 'File applicativi',
        'label' => $label,
        'ok' => check_file_exists_rel($path),
        'hint' => $path,
    ];
}

$optionalWritableDirs = [
    'uploads' => 'Upload generali',
    'uploads/destinations' => 'Foto destinazioni',
    'uploads/operators' => 'Foto personale',
];

foreach ($optionalWritableDirs as $path => $label) {
    $checks[] = [
        'area' => 'Cartelle upload',
        'label' => $label,
        'ok' => check_dir_writable_rel($path),
        'hint' => 'Cartella consigliata scrivibile: ' . $path,
    ];
}

$total = count($checks);
$okCount = 0;
foreach ($checks as $check) {
    if (!empty($check['ok'])) $okCount++;
}
$koCount = $total - $okCount;
$percent = $total > 0 ? round(($okCount / $total) * 100) : 0;

$byArea = [];
foreach ($checks as $check) {
    $area = (string)$check['area'];
    if (!isset($byArea[$area])) $byArea[$area] = [];
    $byArea[$area][] = $check;
}

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
.check-shell{display:grid;gap:18px}.check-hero,.check-card{background:var(--content-card-bg);border:1px solid var(--line);border-radius:24px;box-shadow:var(--shadow);padding:20px}.check-hero{display:grid;grid-template-columns:1.2fr .8fr;gap:18px;align-items:center}.check-title{margin:0;color:var(--text);font-size:28px;font-weight:950}.check-text{margin:8px 0 0;color:var(--muted);line-height:1.6}.check-score{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.check-kpi{border:1px solid var(--line);border-radius:18px;padding:14px;background:color-mix(in srgb,var(--bg-3) 84%,transparent)}.check-kpi-label{font-size:11px;color:var(--muted);font-weight:900;text-transform:uppercase;letter-spacing:.05em}.check-kpi-value{font-size:28px;color:var(--text);font-weight:950;margin-top:8px}.check-progress{height:14px;border-radius:999px;background:color-mix(in srgb,var(--bg-3) 84%,transparent);border:1px solid var(--line);overflow:hidden;margin-top:16px}.check-progress span{display:block;height:100%;width:<?= (int)$percent ?>%;background:linear-gradient(135deg,var(--primary),var(--primary-2))}.check-area{display:grid;gap:10px}.check-area-title{margin:0 0 12px;color:var(--text);font-size:18px}.check-row{display:grid;grid-template-columns:auto 1fr auto;gap:12px;align-items:center;padding:12px;border:1px solid var(--line);border-radius:16px;background:color-mix(in srgb,var(--bg-3) 84%,transparent)}.check-dot{width:14px;height:14px;border-radius:999px;background:#ef4444;box-shadow:0 0 0 4px rgba(239,68,68,.12)}.check-row.ok .check-dot{background:#22c55e;box-shadow:0 0 0 4px rgba(34,197,94,.12)}.check-name{color:var(--text);font-weight:900}.check-hint{color:var(--muted);font-size:12px;margin-top:3px}.check-status{font-size:12px;font-weight:950;border-radius:999px;padding:7px 10px;border:1px solid var(--line)}.check-status.ok{color:#14532d;background:rgba(34,197,94,.15);border-color:rgba(34,197,94,.28)}.check-status.ko{color:#7f1d1d;background:rgba(239,68,68,.15);border-color:rgba(239,68,68,.28)}.check-actions{display:flex;gap:10px;flex-wrap:wrap}@media(max-width:900px){.check-hero{grid-template-columns:1fr}.check-score{grid-template-columns:1fr}.check-row{grid-template-columns:auto 1fr}}
</style>

<div class="check-shell">
    <section class="check-hero">
        <div>
            <h2 class="check-title">Stato produzione Turnar</h2>
            <p class="check-text">
                Questa pagina controlla le parti essenziali del gestionale: database, destinazioni speciali,
                report HR, esportazioni, stampa/PDF, app mobile e cartelle upload. Serve per capire subito
                cosa manca prima di considerare l’installazione pronta.
            </p>
            <div class="check-progress"><span></span></div>
        </div>

        <div class="check-score">
            <div class="check-kpi"><div class="check-kpi-label">Pronto</div><div class="check-kpi-value"><?= (int)$percent ?>%</div></div>
            <div class="check-kpi"><div class="check-kpi-label">OK</div><div class="check-kpi-value"><?= (int)$okCount ?></div></div>
            <div class="check-kpi"><div class="check-kpi-label">Da sistemare</div><div class="check-kpi-value"><?= (int)$koCount ?></div></div>
        </div>
    </section>

    <section class="check-card">
        <div class="check-actions">
            <a class="btn btn-primary" href="<?= h(app_url('modules/dashboard/special_overview.php')) ?>">Dashboard HR</a>
            <a class="btn btn-secondary" href="<?= h(app_url('modules/reports/report_special_destinations.php')) ?>">Report HR</a>
            <a class="btn btn-ghost" href="<?= h(app_url('modules/reports/report_special_destinations_print.php')) ?>">Stampa PDF HR</a>
            <a class="btn btn-ghost" href="<?= h(app_url('app/')) ?>">App mobile</a>
        </div>
    </section>

    <?php foreach ($byArea as $area => $items): ?>
        <section class="check-card">
            <h3 class="check-area-title"><?= h($area) ?></h3>
            <div class="check-area">
                <?php foreach ($items as $item): ?>
                    <?php $ok = !empty($item['ok']); ?>
                    <div class="check-row <?= $ok ? 'ok' : 'ko' ?>">
                        <div class="check-dot"></div>
                        <div>
                            <div class="check-name"><?= h($item['label']) ?></div>
                            <div class="check-hint"><?= h($item['hint']) ?></div>
                        </div>
                        <span class="check-status <?= $ok ? 'ok' : 'ko' ?>"><?= $ok ? 'OK' : 'Verifica' ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>
