<?php
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/communications.php';
require_login();
require_permission('communications.create');
$db=db_connect();
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$dip=[];$res=$db->query("SELECT id,nome,cognome FROM dipendenti ORDER BY cognome ASC");while($r=$res->fetch_assoc())$dip[]=$r;
$cant=[];$res2=$db->query("SELECT id,nome FROM cantieri ORDER BY nome ASC");while($r=$res2->fetch_assoc())$cant[]=$r;
$roles=['user'=>'User','manager'=>'Manager','master'=>'Master'];
$msg=null;$err=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 $sub=trim($_POST['subject']??'');$body=trim($_POST['body']??'');$mode=$_POST['mode']??'selected';
 $app=isset($_POST['send_app']);$mail=isset($_POST['send_email']);$rec=[];
 if($mode==='all')$rec=comm_get_all_recipients();
 if($mode==='selected')$rec=comm_get_selected_recipients($_POST['dipendenti']??[]);
 if($mode==='role')$rec=comm_get_role_recipients($_POST['ruolo']??'');
 if($mode==='destination')$rec=comm_get_destination_recipients((int)($_POST['cantiere']??0),today_date(),today_date());
 $r=comm_send($sub,$body,$rec,$app,$mail);
 if($r['ok'])$msg='Inviata';else $err=$r['message'];
}
$pageTitle='Nuova comunicazione';$activeModule='communications';
require_once __DIR__ . '/../../templates/layout_top.php';?>
<div class="content-card">
<h3>Nuova comunicazione</h3>
<?php if($msg):?><div class="alert success"><?=h($msg)?></div><?php endif;?>
<?php if($err):?><div class="alert error"><?=h($err)?></div><?php endif;?>
<form method="post">
<input name="subject" placeholder="Oggetto" required>
<textarea name="body" placeholder="Messaggio" required></textarea>
<select name="mode" id="mode">
<option value="selected">Selezionati</option>
<option value="all">Tutti</option>
<option value="role">Ruolo</option>
<option value="destination">Cantiere</option>
</select>
<div id="box-selected"><?php foreach($dip as $d):?><label><input type="checkbox" name="dipendenti[]" value="<?=$d['id']?>"><?=h($d['cognome'].' '.$d['nome'])?></label><br><?php endforeach;?></div>
<div id="box-role" style="display:none;"><select name="ruolo"><?php foreach($roles as $k=>$v):?><option value="<?=$k?>"><?=$v?></option><?php endforeach;?></select></div>
<div id="box-destination" style="display:none;"><select name="cantiere"><?php foreach($cant as $c):?><option value="<?=$c['id']?>"><?=h($c['nome'])?></option><?php endforeach;?></select></div>
<label><input type="checkbox" name="send_app" checked>App</label>
<label><input type="checkbox" name="send_email">Email</label>
<button class="btn btn-primary">Invia</button>
</form>
</div>
<script>
document.getElementById('mode').addEventListener('change',function(){
document.getElementById('box-selected').style.display='none';
document.getElementById('box-role').style.display='none';
document.getElementById('box-destination').style.display='none';
if(this.value==='selected')document.getElementById('box-selected').style.display='block';
if(this.value==='role')document.getElementById('box-role').style.display='block';
if(this.value==='destination')document.getElementById('box-destination').style.display='block';
});
</script>
<?php require_once __DIR__ . '/../../templates/layout_bottom.php';?>