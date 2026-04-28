<?php
// app/profile.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../core/settings.php';

require_mobile_login();

$db = db_connect();
$id = (int)(auth_dipendente_id() ?? 0);

$stmt = $db->prepare("SELECT * FROM dipendenti WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$dip = $res->fetch_assoc();

if(!$dip) die("Profilo non trovato");

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$logo = app_company_logo();
$azienda = app_name();
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profilo</title>
<link rel="stylesheet" href="../assets/css/turnar.css">
</head>
<body>

<div class="content-card" style="max-width:420px;margin:auto;text-align:center;margin-top:20px;">

    <?php if($logo): ?>
        <img src="<?=h(app_url($logo))?>" style="max-height:50px;">
    <?php endif; ?>

    <h3><?=h($azienda)?></h3>

    <div style="margin:20px 0;">
        <?php if(!empty($dip['foto'])): ?>
            <img src="<?=h(app_url($dip['foto']))?>" style="width:120px;height:120px;border-radius:20px;object-fit:cover;">
        <?php endif; ?>
    </div>

    <h2><?=h($dip['nome'].' '.$dip['cognome'])?></h2>
    <div>Matricola: <strong><?=h($dip['matricola'] ?? '---')?></strong></div>

</div>

</body>
</html>
