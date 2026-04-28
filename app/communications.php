<?php
// app/communications.php
require_once __DIR__ . '/config.php';
require_mobile_login();

$db = db_connect();
$dipendenteId = (int)(auth_dipendente_id() ?? 0);
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function table_exists_mobile(mysqli $db,string $t):bool{$s=$db->real_escape_string($t);$r=$db->query("SHOW TABLES LIKE '{$s}'");if($r instanceof mysqli_result){$ok=$r->num_rows>0;$r->free();return $ok;}return false;}

$items=[];$missing=!table_exists_mobile($db,'communications')||!table_exists_mobile($db,'communication_recipients');
if(!$missing && $dipendenteId>0){
 $stmt=$db->prepare("SELECT c.id,c.subject,c.body,c.sender_label,c.sent_at,c.created_at,cr.read_at FROM communication_recipients cr INNER JOIN communications c ON c.id=cr.communication_id WHERE cr.dipendente_id=? ORDER BY c.sent_at DESC,c.created_at DESC,c.id DESC LIMIT 100");
 if($stmt){$stmt->bind_param('i',$dipendenteId);$stmt->execute();$res=$stmt->get_result();while($r=$res->fetch_assoc())$items[]=$r;$stmt->close();}
}
?>
<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><title>Comunicazioni · Turnar</title><link rel="manifest" href="manifest.php"><meta name="theme-color" content="#6ea8ff"><style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;padding:16px;padding-bottom:calc(20px + env(safe-area-inset-bottom));font-family:system-ui,-apple-system,Segoe UI,sans-serif;color:#eef4ff;background:radial-gradient(circle at top left,rgba(110,168,255,.18),transparent 30%),linear-gradient(180deg,#0b1226,#050816)}a{color:inherit;text-decoration:none}.shell{width:min(760px,100%);margin:0 auto;display:grid;gap:14px}.card{border:1px solid rgba(255,255,255,.12);border-radius:24px;background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.025));box-shadow:0 18px 42px rgba(0,0,0,.32);padding:16px}.top{display:flex;align-items:center;justify-content:space-between;gap:12px}.title{margin:0;font-size:25px;font-weight:950}.sub{color:#aab8d3;font-size:13px;margin-top:5px}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:40px;border-radius:999px;border:1px solid rgba(255,255,255,.14);padding:9px 13px;font-weight:900;font-size:13px;background:rgba(255,255,255,.07)}.msg{display:block;padding:14px;border:1px solid rgba(255,255,255,.12);border-radius:18px;background:rgba(255,255,255,.045)}.msg.unread{border-color:rgba(110,168,255,.45);background:rgba(110,168,255,.12)}.msg-title{font-weight:950;font-size:15px}.msg-meta{color:#aab8d3;font-size:12px;margin-top:4px}.msg-preview{color:#dbeafe;font-size:13px;line-height:1.45;margin-top:8px;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}.pill{display:inline-flex;margin-top:8px;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:900;background:rgba(52,211,153,.16);color:#bbf7d0}.pill.unread{background:rgba(251,191,36,.16);color:#fde68a}.empty{padding:18px;border:1px dashed rgba(255,255,255,.18);border-radius:18px;color:#aab8d3;text-align:center}
</style></head><body><div class="shell">
<section class="card top"><div><h1 class="title">Comunicazioni</h1><div class="sub">Messaggi aziendali ricevuti</div></div><a class="btn" href="index.php">Home</a></section>
<?php if($missing): ?><section class="card"><div class="empty">Modulo comunicazioni non ancora installato. Esegui la migration comunicazioni.</div></section><?php elseif(empty($items)): ?><section class="card"><div class="empty">Nessuna comunicazione ricevuta.</div></section><?php else: ?>
<section class="card" style="display:grid;gap:10px;">
<?php foreach($items as $it): $read=!empty($it['read_at']); $preview=mb_substr(trim(strip_tags((string)$it['body'])),0,180); ?>
<a class="msg <?= $read?'':'unread' ?>" href="communication_view.php?id=<?= (int)$it['id'] ?>">
 <div class="msg-title"><?= h($it['subject']) ?></div>
 <div class="msg-meta">Da <?= h($it['sender_label'] ?: 'Turnar') ?> · <?= h(format_datetime_it($it['sent_at'] ?: $it['created_at'])) ?></div>
 <div class="msg-preview"><?= nl2br(h($preview)) ?></div>
 <span class="pill <?= $read?'':'unread' ?>"><?= $read?'Letta':'Nuova' ?></span>
</a>
<?php endforeach; ?>
</section><?php endif; ?>
</div></body></html>