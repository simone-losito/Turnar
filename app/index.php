<?php
// app/index.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../core/settings.php';
require_once __DIR__ . '/../core/app_notifications.php';
require_once __DIR__ . '/../core/push.php';

require_mobile_login();

$user = auth_user();
$dipendenteId = (int)(auth_dipendente_id() ?? 0);
$notifications = $dipendenteId ? app_notification_list_for_dipendente($dipendenteId, 30) : [];
$unreadCount = $dipendenteId ? app_notification_unread_count($dipendenteId) : 0;
$pushPublicKey = app_push_vapid_public_key();

function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

function mobile_turn_date_from_notification(array $item): ?string
{
    $link = trim((string)($item['link'] ?? ''));
    $message = trim((string)($item['messaggio'] ?? ''));
    if ($link !== '' && preg_match('/(?:\?|&)date=(\d{4}-\d{2}-\d{2})/', $link, $m)) return $m[1];
    if ($link !== '' && preg_match('/(?:\?|&)data=(\d{4}-\d{2}-\d{2})/', $link, $m)) return $m[1];
    if ($message !== '' && preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $message, $m)) return $m[3] . '-' . $m[2] . '-' . $m[1];
    if ($message !== '' && preg_match('/(\d{4}-\d{2}-\d{2})/', $message, $m)) return $m[1];
    return null;
}

function mobile_open_link_for_notification(array $item): string
{
    $link = trim((string)($item['link'] ?? ''));
    $tipo = trim((string)($item['tipo'] ?? ''));
    if ($tipo === 'comunicazione' && $link !== '') return $link;
    if (strpos($link, 'communication_view.php') !== false) return $link;
    $date = mobile_turn_date_from_notification($item);
    if ($date) {
        $ts = strtotime($date);
        if ($ts !== false) return 'calendar.php?m=' . (int)date('n', $ts) . '&y=' . (int)date('Y', $ts) . '&date=' . urlencode($date);
    }
    if ($link !== '' && strpos($link, 'calendar.php') !== false) return $link;
    return $tipo === 'comunicazione' ? 'communications.php' : 'calendar.php';
}

function mobile_nice_turn_date(array $item): string
{
    $date = mobile_turn_date_from_notification($item);
    if (!$date) return 'Giorno modificato';
    $ts = strtotime($date);
    if ($ts === false) return $date;
    $days = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
    return $days[(int)date('w', $ts)] . ' ' . date('d/m/Y', $ts);
}

$displayName = trim((string)($user['nome'] ?? '')) ?: trim((string)($user['username'] ?? 'Utente'));

$turnAlerts = [];
foreach ($notifications as $item) {
    if (trim((string)($item['tipo'] ?? '')) === 'turno' && empty($item['is_read'])) {
        $turnAlerts[] = $item;
    }
    if (count($turnAlerts) >= 5) break;
}

$recentTurns = [];
foreach ($notifications as $item) {
    if (trim((string)($item['tipo'] ?? '')) === 'turno') $recentTurns[] = $item;
    if (count($recentTurns) >= 5) break;
}

$themeMode = function_exists('app_theme_mode') ? app_theme_mode() : (string)setting('theme_mode', 'dark');
$themePrimary = function_exists('app_theme_primary') ? app_theme_primary() : (string)setting('theme_primary_color', '#6ea8ff');
$themeSecondary = function_exists('app_theme_secondary') ? app_theme_secondary() : (string)setting('theme_secondary_color', '#8b5cf6');
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $themePrimary)) $themePrimary = '#6ea8ff';
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $themeSecondary)) $themeSecondary = '#8b5cf6';
$isLight = $themeMode === 'light';
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Turnar App</title>
<link rel="manifest" href="manifest.php">
<meta name="theme-color" content="<?= h($themePrimary) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Turnar">
<link rel="apple-touch-icon" href="icon.php?size=180">
<style>
:root{--primary:<?=h($themePrimary)?>;--primary-2:<?=h($themeSecondary)?>;--bg:<?=$isLight?'#f4f7fb':'#050816'?>;--card:<?=$isLight?'rgba(255,255,255,.94)':'rgba(255,255,255,.055)'?>;--soft:<?=$isLight?'rgba(15,23,42,.045)':'rgba(255,255,255,.06)'?>;--line:<?=$isLight?'rgba(15,23,42,.11)':'rgba(255,255,255,.13)'?>;--text:<?=$isLight?'#122033':'#eef4ff'?>;--muted:<?=$isLight?'#5f6f86':'#aab8d3'?>;--danger:#f87171;--warning:#fbbf24;--success:#34d399}*{box-sizing:border-box}body{margin:0;min-height:100vh;padding:16px;padding-bottom:calc(22px + env(safe-area-inset-bottom));font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:var(--text);background:radial-gradient(circle at top left,color-mix(in srgb,var(--primary) 18%,transparent),transparent 30%),radial-gradient(circle at top right,color-mix(in srgb,var(--primary-2) 14%,transparent),transparent 28%),var(--bg)}a{color:inherit;text-decoration:none}.shell{width:min(760px,100%);margin:0 auto;display:grid;gap:14px}.card{border:1px solid var(--line);border-radius:24px;background:var(--card);box-shadow:0 18px 42px rgba(0,0,0,.24);padding:16px}.hero{background:linear-gradient(135deg,color-mix(in srgb,var(--primary) 22%,var(--card)),color-mix(in srgb,var(--primary-2) 16%,var(--card)));}.top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.title{margin:0;font-size:26px;line-height:1.05;font-weight:950}.sub{margin-top:6px;color:var(--muted);font-size:14px;line-height:1.45}.badge{display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--line);border-radius:999px;padding:8px 11px;font-size:12px;font-weight:950;background:var(--soft);white-space:nowrap}.badge.hot{background:rgba(251,191,36,.18);border-color:rgba(251,191,36,.32);color:<?=$isLight?'#78350f':'#fde68a'?>}.nav{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.quick{display:block;border:1px solid var(--line);border-radius:20px;padding:14px;background:var(--soft)}.quick strong{display:block;font-size:15px}.quick span{display:block;margin-top:5px;color:var(--muted);font-size:12px;line-height:1.4}.alerts{display:grid;gap:10px}.alert-card{position:relative;border:1px solid rgba(251,191,36,.36);border-radius:20px;padding:14px 48px 14px 14px;background:linear-gradient(180deg,rgba(251,191,36,.18),rgba(251,191,36,.08))}.alert-card.read{border-color:var(--line);background:var(--soft)}.alert-kicker{font-size:11px;font-weight:950;text-transform:uppercase;letter-spacing:.06em;color:<?=$isLight?'#92400e':'#fde68a'?>}.alert-day{margin-top:5px;font-size:19px;font-weight:950}.alert-body{margin-top:7px;color:var(--text);font-size:13px;line-height:1.45;white-space:pre-line}.alert-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.btn{border:1px solid var(--line);border-radius:999px;padding:9px 12px;background:var(--soft);font-size:12px;font-weight:950;display:inline-flex;align-items:center;justify-content:center}.btn.primary{border-color:color-mix(in srgb,var(--primary) 50%,transparent);background:linear-gradient(135deg,var(--primary),var(--primary-2));color:white}.close-x{position:absolute;right:10px;top:10px;width:32px;height:32px;border-radius:999px;border:1px solid var(--line);background:var(--soft);color:var(--text);font-weight:950;font-size:16px}.section-title{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}.section-title h2{margin:0;font-size:18px}.empty{padding:16px;border:1px dashed var(--line);border-radius:18px;color:var(--muted);text-align:center}.notif{border:1px solid var(--line);border-radius:18px;padding:12px;background:var(--soft)}.notif-title{font-weight:900}.notif-meta{margin-top:4px;color:var(--muted);font-size:12px}.notif-body{margin-top:7px;font-size:13px;line-height:1.45;white-space:pre-line}.footer{display:flex;justify-content:center;padding:10px}.logout{color:var(--muted);font-size:13px}@media(max-width:560px){.nav{grid-template-columns:1fr}.title{font-size:23px}.top{flex-direction:column}}
</style>
</head>
<body>
<div class="shell">
<section class="card hero">
 <div class="top">
  <div><h1 class="title">Ciao <?=h($displayName)?></h1><div class="sub">Apri subito le modifiche turni senza cercare i giorni a mano.</div></div>
  <span class="badge <?=count($turnAlerts)>0?'hot':''?>"><?=count($turnAlerts)?> modific<?=count($turnAlerts)===1?'a':'he'?> turni</span>
 </div>
</section>

<?php if(!empty($turnAlerts)): ?>
<section class="card">
 <div class="section-title"><h2>⚠️ Modifiche turni da vedere</h2><span class="badge hot">Nuove</span></div>
 <div class="alerts">
 <?php foreach($turnAlerts as $item): $id=(int)($item['id']??0); $open=mobile_open_link_for_notification($item); ?>
  <article class="alert-card" id="turn-alert-<?=$id?>">
   <button class="close-x mark-read-btn" data-id="<?=$id?>" aria-label="Chiudi notifica">×</button>
   <div class="alert-kicker">Turno assegnato / modificato</div>
   <div class="alert-day"><?=h(mobile_nice_turn_date($item))?></div>
   <div class="alert-body"><?=h((string)($item['messaggio']??''))?></div>
   <div class="alert-actions">
    <a class="btn primary" href="<?=h($open)?>">Apri giorno modificato</a>
    <button class="btn mark-read-btn" data-id="<?=$id?>">Segna vista</button>
   </div>
  </article>
 <?php endforeach; ?>
 </div>
</section>
<?php endif; ?>

<section class="nav">
 <a class="quick" href="calendar.php"><strong>📅 Calendario turni</strong><span>Vai al calendario personale.</span></a>
 <a class="quick" href="communications.php"><strong>💬 Comunicazioni</strong><span>Leggi messaggi, allegati e avvisi aziendali.</span></a>
 <a class="quick" href="notifications_setup.php"><strong>🔔 Notifiche telefono</strong><span>Attiva o controlla le push su Apple/Android.</span></a>
 <a class="quick" href="#storico"><strong>🗂️ Storico notifiche</strong><span>Consulta le notifiche salvate.</span></a>
</section>

<section class="card">
 <div class="section-title"><h2>Ultime modifiche turni</h2><span class="badge"><?=count($recentTurns)?> recenti</span></div>
 <?php if(empty($recentTurns)): ?><div class="empty">Nessuna modifica turno recente.</div><?php else: ?>
 <div class="alerts">
 <?php foreach($recentTurns as $item): $read=!empty($item['is_read']); $open=mobile_open_link_for_notification($item); ?>
  <article class="alert-card <?=$read?'read':''?>">
   <div class="alert-kicker"><?=$read?'Già vista':'Da vedere'?></div>
   <div class="alert-day"><?=h(mobile_nice_turn_date($item))?></div>
   <div class="alert-body"><?=h((string)($item['messaggio']??''))?></div>
   <div class="alert-actions"><a class="btn primary" href="<?=h($open)?>">Apri giorno</a></div>
  </article>
 <?php endforeach; ?>
 </div>
 <?php endif; ?>
</section>

<section class="card" id="storico">
 <div class="section-title"><h2>Storico notifiche</h2><?php if($unreadCount>0): ?><button class="btn" id="markAllReadBtn">Segna tutte lette</button><?php endif; ?></div>
 <?php if(empty($notifications)): ?><div class="empty">Nessuna notifica disponibile.</div><?php else: ?>
 <div class="alerts">
 <?php foreach($notifications as $item): $id=(int)($item['id']??0); $read=!empty($item['is_read']); $open=mobile_open_link_for_notification($item); ?>
  <article class="notif">
   <div class="notif-title"><?=h((string)($item['titolo']??'Notifica'))?></div>
   <div class="notif-meta"><?=h(format_datetime_it((string)($item['created_at']??'')))?> · <?=$read?'Letta':'Nuova'?></div>
   <div class="notif-body"><?=h((string)($item['messaggio']??''))?></div>
   <div class="alert-actions"><a class="btn" href="<?=h($open)?>">Apri</a><?php if(!$read): ?><button class="btn mark-read-btn" data-id="<?=$id?>">Chiudi</button><?php endif; ?></div>
  </article>
 <?php endforeach; ?>
 </div>
 <?php endif; ?>
</section>

<div class="footer"><a class="logout" href="logout.php">Esci</a></div>
</div>

<script>
const TURNAR_PUSH_PUBLIC_KEY = <?=json_encode($pushPublicKey, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
if('serviceWorker' in navigator){window.addEventListener('load',()=>navigator.serviceWorker.register('./service-worker.js').catch(()=>{}));}
async function postAction(url, bodyData){const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:new URLSearchParams(bodyData).toString()});return await r.json();}
async function markRead(id){if(!id)return;try{const data=await postAction('api/notifications.php',{action:'mark_read',id:id});if(data&&data.success){const el=document.getElementById('turn-alert-'+id);if(el){el.remove();}else{window.location.reload();}}}catch(e){}}
document.querySelectorAll('.mark-read-btn').forEach(btn=>btn.addEventListener('click',()=>markRead(btn.getAttribute('data-id')||'')));
const markAll=document.getElementById('markAllReadBtn');if(markAll){markAll.addEventListener('click',async()=>{try{const data=await postAction('api/notifications.php',{action:'mark_all_read'});if(data&&data.success)window.location.reload();}catch(e){}});}
if('setAppBadge' in navigator){try{const c=<?= (int)$unreadCount ?>; if(c>0) navigator.setAppBadge(c); else navigator.clearAppBadge();}catch(e){}}
</script>
</body>
</html>