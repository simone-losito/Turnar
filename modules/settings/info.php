<?php
// modules/settings/info.php
require_once __DIR__ . '/../../core/helpers.php';
require_login();
require_permission('settings.view');

$db=db_connect();
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function info_table_exists(mysqli $db,string $t):bool{$s=$db->real_escape_string($t);$r=$db->query("SHOW TABLES LIKE '{$s}'");if($r instanceof mysqli_result){$ok=$r->num_rows>0;$r->free();return $ok;}return false;}
function info_file_exists_rel(string $p):bool{return is_file(dirname(__DIR__,2).'/'.ltrim($p,'/'));}

$checks=[
 ['Database utenti',info_table_exists($db,'users')],
 ['Database dipendenti',info_table_exists($db,'dipendenti')],
 ['Database turni',info_table_exists($db,'eventi_turni')],
 ['Comunicazioni',info_table_exists($db,'communications')],
 ['Destinatari comunicazioni',info_table_exists($db,'communication_recipients')],
 ['Allegati comunicazioni',info_table_exists($db,'communication_attachments')],
 ['Push subscriptions',info_table_exists($db,'app_push_subscriptions')],
 ['Audit log',info_table_exists($db,'audit_log')],
 ['Service worker app',info_file_exists_rel('app/service-worker.js')],
 ['CSS principale',info_file_exists_rel('assets/css/turnar.css')],
];

$pageTitle='Info programma';
$pageSubtitle='Versione, stato moduli e controlli installazione';
$activeModule='settings';
require_once __DIR__ . '/../../templates/layout_top.php';
?>
<style>.info-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.info-card{background:var(--content-card-bg);border:1px solid var(--line);border-radius:24px;box-shadow:var(--shadow);padding:18px}.info-label{font-size:12px;color:var(--muted);font-weight:900;text-transform:uppercase;letter-spacing:.05em}.info-value{font-size:24px;font-weight:950;color:var(--text);margin-top:8px}.info-list{display:grid;gap:10px}.info-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px;border:1px solid var(--line);border-radius:16px;background:color-mix(in srgb,var(--bg-3) 84%,transparent)}.ok{color:#14532d;background:rgba(34,197,94,.16);border-color:rgba(34,197,94,.3)}.ko{color:#7f1d1d;background:rgba(239,68,68,.16);border-color:rgba(239,68,68,.3)}.pill{display:inline-flex;padding:6px 10px;border-radius:999px;border:1px solid var(--line);font-size:12px;font-weight:900}@media(max-width:900px){.info-grid{grid-template-columns:1fr}}</style>
<div class="info-grid">
 <div class="info-card"><div class="info-label">Software</div><div class="info-value"><?=h(app_name())?></div><div class="text-muted">Gestionale Turnar</div></div>
 <div class="info-card"><div class="info-label">Versione</div><div class="info-value"><?=h(app_version())?></div><div class="text-muted">Versione applicazione</div></div>
 <div class="info-card"><div class="info-label">Ambiente</div><div class="info-value"><?=h($_SERVER['HTTP_HOST']??'locale')?></div><div class="text-muted">Host corrente</div></div>
</div>
<div class="info-card" style="margin-top:16px;"><h3>Controlli installazione</h3><div class="info-list">
<?php foreach($checks as $c): ?><div class="info-row"><strong><?=h($c[0])?></strong><span class="pill <?=$c[1]?'ok':'ko'?>"><?=$c[1]?'OK':'Verifica'?></span></div><?php endforeach; ?>
</div></div>
<div class="info-card" style="margin-top:16px;"><h3>Link rapidi</h3><a class="btn btn-primary" href="<?=h(app_url('modules/settings/system_check.php'))?>">Controllo sistema</a> <a class="btn btn-secondary" href="<?=h(app_url('modules/settings/audit.php'))?>">Audit</a> <a class="btn btn-ghost" href="<?=h(app_url('app/'))?>">App mobile</a></div>
<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>