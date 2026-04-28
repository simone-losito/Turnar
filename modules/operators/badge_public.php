<?php
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/settings.php';

$token = trim((string)($_GET['t'] ?? ''));
if($token==='') die('Badge non valido');

$db = db_connect();
$stmt = $db->prepare("SELECT * FROM dipendenti WHERE badge_token=? AND badge_enabled=1");
$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();
$dip = $res->fetch_assoc();

if(!$dip) die('Badge non valido o disattivato');

if(!empty($dip['badge_expires_at']) && $dip['badge_expires_at'] < date('Y-m-d')){
    die('Badge scaduto');
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$logo = app_company_logo();
$azienda = app_name();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Badge valido</title>
<style>body{font-family:Arial;text-align:center;padding:20px}</style>
</head>
<body>
<?php if($logo): ?><img src="<?=h(app_url($logo))?>" style="max-height:50px;"><?php endif; ?>
<h2><?=h($azienda)?></h2>
<?php if(!empty($dip['foto'])): ?><img src="<?=h(app_url($dip['foto']))?>" style="width:100px;border-radius:10px;"><?php endif; ?>
<h3><?=h($dip['nome'].' '.$dip['cognome'])?></h3>
<p>Matricola: <?=h($dip['matricola'])?></p>
<p style="color:green;font-weight:bold">Badge valido</p>
</body>
</html>