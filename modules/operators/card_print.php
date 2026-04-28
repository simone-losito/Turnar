<?php
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/settings.php';
require_once __DIR__ . '/../../core/qr.php';

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
$cardUrl = app_url('modules/operators/card.php?id=' . $id);
$qr = turnar_qr_url($cardUrl, 120);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Badge</title>
<style>
body{font-family:Arial;text-align:center}
.card{border:1px solid #ccc;padding:20px;border-radius:12px;width:300px;margin:auto}
</style>
</head>
<body>
<div class="card">
<?php if($logo): ?><img src="<?=h(app_url($logo))?>" style="max-height:50px;"><?php endif; ?>
<h3><?=h($azienda)?></h3>
<?php if(!empty($dip['foto'])): ?><img src="<?=h(app_url($dip['foto']))?>" style="width:100px;height:100px;border-radius:10px;"><?php endif; ?>
<h2><?=h($dip['nome'].' '.$dip['cognome'])?></h2>
<div>Matricola: <strong><?=h($dip['matricola'])?></strong></div>
<img src="<?=h($qr)?>" style="width:80px;margin-top:10px;">
</div>
</body>
</html>