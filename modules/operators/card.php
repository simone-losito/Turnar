<?php
// modules/operators/card.php
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/settings.php';

require_login();

$id = (int)($_GET['id'] ?? 0);
$db = db_connect();

$stmt = $db->prepare("SELECT * FROM dipendenti WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$dip = $res->fetch_assoc();

if(!$dip) die("Dipendente non trovato");

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$logo = app_company_logo();
$azienda = app_name();

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<div class="content-card" style="max-width:420px;margin:auto;text-align:center">

    <div style="margin-bottom:15px;">
        <?php if($logo): ?>
            <img src="<?=h(app_url($logo))?>" style="max-height:60px;">
        <?php endif; ?>
        <div style="font-weight:900;margin-top:5px;"><?=h($azienda)?></div>
    </div>

    <div style="margin:20px 0;">
        <?php if(!empty($dip['foto'])): ?>
            <img src="<?=h(app_url($dip['foto']))?>" 
                 style="width:140px;height:140px;border-radius:20px;object-fit:cover;">
        <?php else: ?>
            <div style="width:140px;height:140px;border-radius:20px;background:#ccc;margin:auto;"></div>
        <?php endif; ?>
    </div>

    <h2 style="margin:0;"><?=h($dip['nome'].' '.$dip['cognome'])?></h2>

    <div style="margin-top:10px;font-size:14px;color:var(--muted)">
        Matricola: <strong><?=h($dip['matricola'] ?? '---')?></strong>
    </div>

</div>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>
