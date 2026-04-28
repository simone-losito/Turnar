<?php
// app/communication_view.php
require_once __DIR__ . '/config.php';
require_mobile_login();

$db = db_connect();
$dipendenteId = (int)(auth_dipendente_id() ?? 0);
$id = (int)($_GET['id'] ?? 0);
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}

$item=null;$attachments=[];
if($id>0 && $dipendenteId>0){
 $stmt=$db->prepare("SELECT c.id,c.subject,c.body,c.sender_label,c.sent_at,c.created_at FROM communication_recipients cr INNER JOIN communications c ON c.id=cr.communication_id WHERE cr.communication_id=? AND cr.dipendente_id=? LIMIT 1");
 if($stmt){$stmt->bind_param('ii',$id,$dipendenteId);$stmt->execute();$res=$stmt->get_result();$item=$res->fetch_assoc();$stmt->close();}
 $stmt2=$db->prepare("UPDATE communication_recipients SET read_at=NOW() WHERE communication_id=? AND dipendente_id=? AND read_at IS NULL");
 if($stmt2){$stmt2->bind_param('ii',$id,$dipendenteId);$stmt2->execute();$stmt2->close();}
 if($item){
   $st=$db->prepare("SELECT id,original_name,file_path FROM communication_attachments WHERE communication_id=?");
   if($st){$st->bind_param('i',$id);$st->execute();$res2=$st->get_result();while($r=$res2->fetch_assoc())$attachments[]=$r;$st->close();}
 }
}
?>
<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><title>Messaggio · Turnar</title><style>
body{margin:0;padding:16px;font-family:system-ui;background:#050816;color:#eef4ff}.card{border:1px solid rgba(255,255,255,.12);border-radius:24px;padding:16px;background:rgba(255,255,255,.04)}.title{font-size:20px;font-weight:900}.meta{color:#aab8d3;font-size:12px;margin-top:6px}.body{margin-top:12px;line-height:1.6;font-size:14px}.btn{display:inline-block;margin-top:16px;padding:10px 14px;border-radius:999px;background:rgba(255,255,255,.1)}.att{margin-top:14px;padding:10px;border:1px solid rgba(255,255,255,.15);border-radius:12px}.att a{color:#93c5fd;font-weight:700}
</style></head><body>
<div class="card">
<?php if(!$item): ?>
<div>Messaggio non trovato</div>
<?php else: ?>
<div class="title"><?= h($item['subject']) ?></div>
<div class="meta">Da <?= h($item['sender_label'] ?: 'Turnar') ?> · <?= h(format_datetime_it($item['sent_at'] ?: $item['created_at'])) ?></div>
<div class="body"><?= nl2br(h($item['body'])) ?></div>
<?php if(!empty($attachments)): ?>
<div class="att"><strong>Allegati:</strong><br>
<?php foreach($attachments as $a): ?>
<a href="communication_attachment.php?id=<?= (int)$a['id'] ?>" target="_blank">📎 <?= h($a['original_name']) ?></a><br>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>
<a class="btn" href="communications.php">← Torna</a>
</div>
</body></html>