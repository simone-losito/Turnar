<?php
// modules/operators/badges.php
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/settings.php';
require_once __DIR__ . '/../../core/qr.php';

require_login();
require_permission('operators.view');

$db = db_connect();
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function badge_table_has_column(mysqli $db, string $column): bool{
    $safe = $db->real_escape_string($column);
    $res = $db->query("SHOW COLUMNS FROM dipendenti LIKE '{$safe}'");
    if($res instanceof mysqli_result){ $ok = $res->num_rows > 0; $res->free(); return $ok; }
    return false;
}

$hasBadgeFields = badge_table_has_column($db, 'badge_token') && badge_table_has_column($db, 'badge_enabled') && badge_table_has_column($db, 'badge_expires_at');
$canEdit = function_exists('can') ? can('operators.edit') : true;
$msg = '';
$err = '';

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$hasBadgeFields){
        $err = 'Migration badge pro non eseguita.';
    } elseif(!$canEdit){
        $err = 'Non hai i permessi per modificare i badge.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $action = trim((string)($_POST['action'] ?? ''));
        if($id <= 0){
            $err = 'Dipendente non valido.';
        } else {
            if($action === 'save'){
                $enabled = isset($_POST['badge_enabled']) ? 1 : 0;
                $unlimited = isset($_POST['unlimited']) ? 1 : 0;
                $expires = $unlimited ? null : trim((string)($_POST['badge_expires_at'] ?? ''));
                if($expires === '') $expires = null;
                if($expires !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires)){
                    $err = 'Data scadenza non valida.';
                } else {
                    $stmt = $db->prepare("UPDATE dipendenti SET badge_enabled=?, badge_expires_at=? WHERE id=?");
                    if($stmt){
                        $stmt->bind_param('isi', $enabled, $expires, $id);
                        $stmt->execute();
                        $stmt->close();
                        $msg = 'Badge aggiornato.';
                    }
                }
            } elseif($action === 'regenerate'){
                $token = bin2hex(random_bytes(16));
                $stmt = $db->prepare("UPDATE dipendenti SET badge_token=? WHERE id=?");
                if($stmt){
                    $stmt->bind_param('si', $token, $id);
                    $stmt->execute();
                    $stmt->close();
                    $msg = 'Token QR rigenerato.';
                }
            }
        }
    }
}

if($hasBadgeFields){
    $res = $db->query("SELECT id,nome,cognome,matricola,foto,badge_token,badge_enabled,badge_expires_at FROM dipendenti ORDER BY cognome ASC,nome ASC");
} else {
    $res = $db->query("SELECT id,nome,cognome,matricola,foto FROM dipendenti ORDER BY cognome ASC,nome ASC");
}

$pageTitle = 'Gestione badge';
$pageSubtitle = 'Tesserini digitali, QR sicuro, stato e scadenza';
$activeModule = 'operators';
require_once __DIR__ . '/../../templates/layout_top.php';
?>
<style>
.badge-panel{display:grid;gap:14px}.badge-row{display:grid;grid-template-columns:70px minmax(0,1fr) 160px 220px;gap:14px;align-items:center;border:1px solid var(--line);border-radius:22px;padding:14px;background:color-mix(in srgb,var(--bg-3) 88%,transparent)}.badge-photo{width:58px;height:58px;border-radius:16px;object-fit:cover;background:var(--bg-3);border:1px solid var(--line)}.badge-name{font-weight:950;font-size:16px}.badge-meta{color:var(--muted);font-size:12px;margin-top:4px}.state{display:inline-flex;padding:7px 10px;border-radius:999px;font-size:12px;font-weight:900;border:1px solid var(--line)}.state.ok{background:rgba(34,197,94,.14);color:#bbf7d0;border-color:rgba(34,197,94,.28)}.state.warn{background:rgba(251,191,36,.14);color:#fde68a;border-color:rgba(251,191,36,.28)}.state.ko{background:rgba(248,113,113,.14);color:#fecdd3;border-color:rgba(248,113,113,.28)}.badge-actions{display:grid;gap:8px}.mini-form{display:grid;gap:8px}.mini-line{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.mini-line input[type="checkbox"]{width:auto}.mini-line input[type="date"]{max-width:150px}.qr-small{width:58px;height:58px;border-radius:10px;background:white;padding:4px}.top-actions{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:14px}.alert-ok,.alert-ko{padding:12px 14px;border-radius:16px;margin-bottom:12px;border:1px solid var(--line)}.alert-ok{background:rgba(34,197,94,.12)}.alert-ko{background:rgba(248,113,113,.12)}@media(max-width:980px){.badge-row{grid-template-columns:60px minmax(0,1fr)}.badge-actions{grid-column:1/-1}.qr-cell{grid-column:1/-1}}
</style>
<div class="content-card">
<div class="top-actions">
 <div><h3 style="margin:0">Badge dipendenti</h3><div class="text-muted">Gestisci tesserini digitali con QR verificabile.</div></div>
 <a class="btn btn-ghost" href="<?=h(app_url('modules/operators/index.php'))?>">Torna al personale</a>
</div>
<?php if($msg):?><div class="alert-ok"><?=h($msg)?></div><?php endif;?>
<?php if($err):?><div class="alert-ko"><?=h($err)?></div><?php endif;?>
<?php if(!$hasBadgeFields): ?>
<div class="alert-ko">Esegui prima la migration <strong>database/migrations/2026_04_28_operator_badge_pro.sql</strong>.</div>
<?php endif; ?>
<div class="badge-panel">
<?php if($res && $res->num_rows>0): while($d=$res->fetch_assoc()): ?>
<?php
$enabled = $hasBadgeFields ? (int)($d['badge_enabled'] ?? 1) === 1 : false;
$expires = $hasBadgeFields ? trim((string)($d['badge_expires_at'] ?? '')) : '';
$token = $hasBadgeFields ? trim((string)($d['badge_token'] ?? '')) : '';
$expired = $expires !== '' && $expires < date('Y-m-d');
$statusText = !$hasBadgeFields ? 'Non configurato' : (!$enabled ? 'Disattivo' : ($expired ? 'Scaduto' : 'Valido'));
$statusClass = !$hasBadgeFields || !$enabled || $expired ? 'ko' : ($expires === '' ? 'ok' : 'warn');
$qr = $token !== '' ? turnar_qr_url(app_url('modules/operators/badge_public.php?t=' . $token), 90) : '';
?>
<div class="badge-row">
 <div><?php if(!empty($d['foto'])): ?><img class="badge-photo" src="<?=h(app_url($d['foto']))?>"><?php else: ?><div class="badge-photo"></div><?php endif; ?></div>
 <div><div class="badge-name"><?=h(($d['cognome']??'').' '.($d['nome']??''))?></div><div class="badge-meta">Matricola: <?=h($d['matricola'] ?? '---')?> · <span class="state <?=$statusClass?>"><?=$statusText?></span><?php if($expires!==''): ?> · Scadenza <?=h(date('d/m/Y', strtotime($expires)))?><?php else: ?> · Illimitato<?php endif; ?></div></div>
 <div class="qr-cell"><?php if($qr): ?><img class="qr-small" src="<?=h($qr)?>"><?php else: ?><span class="text-muted">QR da generare</span><?php endif; ?></div>
 <div class="badge-actions">
  <a class="btn btn-secondary" href="<?=h(app_url('modules/operators/card.php?id='.(int)$d['id']))?>">Apri badge</a>
  <?php if($hasBadgeFields && $canEdit): ?>
  <form class="mini-form" method="post">
   <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
   <input type="hidden" name="action" value="save">
   <label class="mini-line"><input type="checkbox" name="badge_enabled" value="1" <?=$enabled?'checked':''?>> Attivo</label>
   <label class="mini-line"><input type="checkbox" name="unlimited" value="1" <?=$expires===''?'checked':''?>> Scadenza illimitata</label>
   <div class="mini-line"><input type="date" name="badge_expires_at" value="<?=h($expires)?>"><button class="btn btn-primary" type="submit">Salva</button></div>
  </form>
  <form method="post" onsubmit="return confirm('Rigenerare il QR? Il vecchio QR non sarà più valido.');">
   <input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="action" value="regenerate"><button class="btn btn-ghost" type="submit">Rigenera QR</button>
  </form>
  <?php endif; ?>
 </div>
</div>
<?php endwhile; else: ?><div class="text-muted">Nessun dipendente.</div><?php endif; ?>
</div>
</div>
<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>
