<?php
// modules/settings/modules.php
require_once __DIR__ . '/../../core/helpers.php';
require_login();
require_permission('settings.view');
if(!isMaster()) die('Accesso negato');
$db=db_connect();
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}

$modules=[
 'dashboard'=>'Dashboard',
 'operators'=>'Personale',
 'destinations'=>'Destinazioni',
 'assignments'=>'Turni',
 'calendar'=>'Calendario',
 'communications'=>'Comunicazioni',
 'reports'=>'Report',
 'users'=>'Utenti',
 'settings'=>'Impostazioni',
 'mobile'=>'App mobile'
];

$msg=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 foreach($modules as $k=>$v){
   $val=isset($_POST['mod'][$k])?1:0;
   $stmt=$db->prepare("REPLACE INTO settings (k,v) VALUES (?,?)");
   if($stmt){$stmt->bind_param('ss',$k,$val);$stmt->execute();$stmt->close();}
 }
 $msg='Moduli aggiornati';
}

$pageTitle='Moduli software';
$pageSubtitle='Attiva o disattiva le sezioni del gestionale';
$activeModule='settings';
require_once __DIR__ . '/../../templates/layout_top.php';
?>
<div class="content-card">
<h3>Gestione moduli</h3>
<?php if($msg):?><div class="alert success"><?=h($msg)?></div><?php endif;?>
<form method="post">
<?php foreach($modules as $k=>$v): ?>
<label><input type="checkbox" name="mod[<?=$k?>]" checked> <?=$v?></label><br>
<?php endforeach; ?>
<br>
<button class="btn btn-primary">Salva</button>
</form>
</div>
<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>