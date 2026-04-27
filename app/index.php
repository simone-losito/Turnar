<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../core/settings.php';
require_once __DIR__ . '/../core/app_notifications.php';
require_once __DIR__ . '/../core/push.php';

require_mobile_login();

$user = auth_user();
$dipendenteId = auth_dipendente_id();

$notifications = $dipendenteId ? app_notification_list_for_dipendente($dipendenteId, 20) : [];
$unreadCount = $dipendenteId ? app_notification_unread_count($dipendenteId) : 0;
$pushPublicKey = app_push_vapid_public_key();

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function build_calendar_link_from_notification(?string $link, ?string $message = ''): string
{
    $link = trim((string)$link);
    $message = trim((string)$message);

    if ($link !== '') {
        if (preg_match('/(?:\?|&)date=(\d{4}-\d{2}-\d{2})/', $link, $m)) {
            $date = $m[1];
            $ts = strtotime($date);
            if ($ts !== false) {
                return 'calendar.php?m=' . (int)date('n', $ts) . '&y=' . (int)date('Y', $ts) . '&date=' . urlencode($date);
            }
        }

        if (strpos($link, 'calendar.php') !== false) {
            return $link;
        }
    }

    if ($message !== '' && preg_match('/(\d{2}\/\d{2}\/\d{4})/', $message, $m)) {
        $parts = explode('/', $m[1]);
        if (count($parts) === 3) {
            $date = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
            $ts = strtotime($date);
            if ($ts !== false) {
                return 'calendar.php?m=' . (int)date('n', $ts) . '&y=' . (int)date('Y', $ts) . '&date=' . urlencode($date);
            }
        }
    }

    return 'calendar.php';
}

$displayName = trim((string)($user['nome'] ?? ''));
if ($displayName === '') {
    $displayName = trim((string)($user['username'] ?? 'Utente'));
}

$latestTurnNotifications = [];
foreach ($notifications as $item) {
    if (trim((string)($item['tipo'] ?? '')) === 'turno') {
        $latestTurnNotifications[] = $item;
    }
    if (count($latestTurnNotifications) >= 3) {
        break;
    }
}

$latestTurnUnread = 0;
foreach ($latestTurnNotifications as $item) {
    if (empty($item['is_read'])) {
        $latestTurnUnread++;
    }
}

$themeMode = function_exists('app_theme_mode') ? app_theme_mode() : (string)setting('theme_mode', 'dark');
$themePrimary = function_exists('app_theme_primary') ? app_theme_primary() : (string)setting('theme_primary_color', '#6ea8ff');
$themeSecondary = function_exists('app_theme_secondary') ? app_theme_secondary() : (string)setting('theme_secondary_color', '#8b5cf6');

if (!preg_match('/^#[0-9a-fA-F]{6}$/', $themePrimary)) {
    $themePrimary = '#6ea8ff';
}
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $themeSecondary)) {
    $themeSecondary = '#8b5cf6';
}

$resolvedThemeMode = in_array($themeMode, ['dark', 'light', 'auto'], true) ? $themeMode : 'dark';
if ($resolvedThemeMode === 'auto') {
    $resolvedThemeMode = 'dark';
}

$isLightTheme = ($resolvedThemeMode === 'light');

if ($isLightTheme) {
    $bg1 = '#eef3fb';
    $bg2 = '#f7f9fc';
    $bg3 = '#ffffff';
    $line = 'rgba(15,23,42,.10)';
    $text = '#122033';
    $muted = '#5f6f86';
    $shadow = '0 18px 40px rgba(15,23,42,.10)';
    $bodyBackground = 'radial-gradient(circle at top left, rgba(110,168,255,.10), transparent 28%), radial-gradient(circle at top right, rgba(139,92,246,.08), transparent 24%), linear-gradient(180deg, #f8fbff, #edf3fb)';
    $cardBackground = 'linear-gradient(180deg, rgba(255,255,255,.96), rgba(255,255,255,.90))';
    $softBackground = 'rgba(15,23,42,.04)';
    $softBackground2 = 'rgba(15,23,42,.03)';
    $softBackground3 = 'rgba(15,23,42,.05)';
    $dangerSoft = 'rgba(248,113,113,.10)';
    $dangerBorder = 'rgba(248,113,113,.24)';
} else {
    $bg1 = '#050816';
    $bg2 = '#0b1226';
    $bg3 = '#121a31';
    $line = 'rgba(255,255,255,.10)';
    $text = '#eef4ff';
    $muted = '#aab8d3';
    $shadow = '0 18px 40px rgba(0,0,0,.35)';
    $bodyBackground = 'radial-gradient(circle at top left, rgba(110,168,255,.18), transparent 28%), radial-gradient(circle at top right, rgba(139,92,246,.14), transparent 24%), linear-gradient(180deg, #0b1226, #050816)';
    $cardBackground = 'linear-gradient(180deg, rgba(255,255,255,.045), rgba(255,255,255,.02))';
    $softBackground = 'rgba(255,255,255,.05)';
    $softBackground2 = 'rgba(255,255,255,.04)';
    $softBackground3 = 'rgba(255,255,255,.06)';
    $dangerSoft = 'rgba(248,113,113,.10)';
    $dangerBorder = 'rgba(248,113,113,.28)';
}

$appThemeColor = $themePrimary;
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Turnar App</title>

<link rel="manifest" href="manifest.php">
<meta name="theme-color" content="<?php echo h($appThemeColor); ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Turnar">
<link rel="apple-touch-icon" href="icon.php?size=180">

<style>
:root{
    --bg-1:<?php echo h($bg1); ?>;
    --bg-2:<?php echo h($bg2); ?>;
    --bg-3:<?php echo h($bg3); ?>;
    --line:<?php echo h($line); ?>;
    --text:<?php echo h($text); ?>;
    --muted:<?php echo h($muted); ?>;
    --primary:<?php echo h($themePrimary); ?>;
    --primary-2:<?php echo h($themeSecondary); ?>;
    --success:#34d399;
    --warning:#fbbf24;
    --danger:#f87171;
    --shadow:<?php echo h($shadow); ?>;
    --body-bg:<?php echo $bodyBackground; ?>;
    --mobile-card-bg:<?php echo $cardBackground; ?>;
    --mobile-soft-bg:<?php echo h($softBackground); ?>;
    --mobile-soft-bg-2:<?php echo h($softBackground2); ?>;
    --mobile-soft-bg-3:<?php echo h($softBackground3); ?>;
    --mobile-danger-bg:<?php echo h($dangerSoft); ?>;
    --mobile-danger-border:<?php echo h($dangerBorder); ?>;
}
</style>

<link rel="stylesheet" href="<?php echo h(app_url('assets/css/turnar.css')); ?>?v=<?php echo urlencode((string)app_version()); ?>">
</head>

<body class="turnar-mobile-page">
<div class="app-shell mobile-app-shell">

    <section class="hero-card">
        <div class="hero-top">
            <div>
                <h1 class="hero-title">Ciao <?php echo h($displayName); ?></h1>
                <div class="hero-sub">Benvenuto nell’app Turnar</div>
            </div>

            <span class="badge primary">
                <?php echo (int)$unreadCount; ?> notifich<?php echo $unreadCount === 1 ? 'a' : 'e'; ?> da leggere
            </span>
        </div>
    </section>

    <section class="install-card hidden" id="installCard">
        <div class="install-head">
            <div>
                <h2 class="install-title">Installa Turnar sul telefono</h2>
                <div class="install-sub">Aprila come una vera app dalla schermata Home, più comoda e veloce.</div>
            </div>
            <span class="badge">PWA pronta</span>
        </div>

        <div class="install-actions">
            <button type="button" class="install-btn hidden" id="installAppBtn">Installa app</button>
            <button type="button" class="install-secondary-btn" id="closeInstallCardBtn">Nascondi</button>
        </div>

        <div class="install-help hidden" id="iosInstallHelp">
            Su iPhone: apri questa pagina in Safari, poi usa <strong>Condividi → Aggiungi alla schermata Home</strong>.
        </div>
    </section>

    <section class="push-card" id="pushCard">
        <div class="push-head">
            <div>
                <h2 class="push-title">Notifiche push browser</h2>
                <div class="push-sub">Attiva gli avvisi istantanei quando ricevi un nuovo turno.</div>
            </div>
            <span class="badge">Realtime</span>
        </div>

        <div class="push-status-row">
            <span class="push-pill" id="pushSupportPill">Controllo browser…</span>
            <span class="push-pill" id="pushPermissionPill">Permesso…</span>
        </div>

        <div class="push-actions">
            <button type="button" class="push-btn" id="enablePushBtn">Attiva push</button>
            <button type="button" class="push-secondary-btn hidden" id="disablePushBtn">Disattiva push</button>
        </div>

        <div class="push-help" id="pushHelpBox">
            Le notifiche push mostrano un avviso anche fuori dall’app, se il browser e il telefono le supportano.
        </div>
    </section>

    <section class="nav-row">
        <a class="quick-link" href="calendar.php">
            <div class="quick-link-title">📅 Calendario turni</div>
            <div class="quick-link-text">Apri il calendario personale mensile con dettaglio dei turni assegnati.</div>
        </a>

        <a class="quick-link" href="#notifiche">
            <div class="quick-link-title">🔔 Notifiche</div>
            <div class="quick-link-text">Turni assegnati, modifiche e avvisi importanti.</div>
        </a>
    </section>

    <section class="turn-banner-card">
        <div class="turn-banner-head">
            <div>
                <h2 class="turn-banner-title">Ultime modifiche turni</h2>
                <div class="turn-banner-sub">Qui vedi subito le ultime notifiche legate ai turni</div>
            </div>

            <div class="turn-banner-pills">
                <span class="turn-banner-pill"><?php echo count($latestTurnNotifications); ?> recenti</span>
                <?php if ($latestTurnUnread > 0): ?>
                    <span class="turn-banner-pill unread"><?php echo $latestTurnUnread; ?> nuove</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($latestTurnNotifications)): ?>
            <div class="turn-banner-list">
                <?php foreach ($latestTurnNotifications as $item): ?>
                    <?php
                    $notificationId = (int)($item['id'] ?? 0);
                    $titolo = (string)($item['titolo'] ?? '');
                    $messaggio = (string)($item['messaggio'] ?? '');
                    $isRead = !empty($item['is_read']);
                    $createdAt = (string)($item['created_at'] ?? '');
                    $smartLink = build_calendar_link_from_notification(
                        (string)($item['link'] ?? ''),
                        (string)($item['messaggio'] ?? '')
                    );
                    ?>
                    <article class="turn-banner-item <?php echo $isRead ? '' : 'unread'; ?>">
                        <div class="turn-banner-item-top">
                            <div class="turn-banner-item-title"><?php echo h($titolo); ?></div>
                            <div class="turn-banner-item-date"><?php echo h(format_datetime_it($createdAt)); ?></div>
                        </div>

                        <div class="turn-banner-item-body"><?php echo nl2br(h($messaggio)); ?></div>

                        <div class="turn-banner-item-actions">
                            <a href="<?php echo h($smartLink); ?>" class="action-link">Apri turno</a>

                            <?php if (!$isRead): ?>
                                <button type="button" class="action-btn mark-read-btn" data-id="<?php echo $notificationId; ?>">
                                    Segna letta
                                </button>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                Nessuna modifica recente ai turni.
            </div>
        <?php endif; ?>
    </section>

    <section class="block-card" id="notifiche">
        <div class="block-head">
            <div>
                <h2 class="block-title">Notifiche</h2>
                <div class="block-sub">Storico notifiche salvate per questo dipendente</div>
            </div>

            <?php if ($unreadCount > 0): ?>
                <button type="button" class="action-btn" id="markAllReadBtn">Segna tutte come lette</button>
            <?php endif; ?>
        </div>

        <div class="notifications-list" id="notificationsList">
            <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $item): ?>
                    <?php
                    $notificationId = (int)($item['id'] ?? 0);
                    $titolo = (string)($item['titolo'] ?? '');
                    $messaggio = (string)($item['messaggio'] ?? '');
                    $tipo = trim((string)($item['tipo'] ?? 'info'));
                    if ($tipo === '') {
                        $tipo = 'info';
                    }
                    $isRead = !empty($item['is_read']);
                    $createdAt = (string)($item['created_at'] ?? '');
                    $smartLink = build_calendar_link_from_notification(
                        (string)($item['link'] ?? ''),
                        (string)($item['messaggio'] ?? '')
                    );
                    ?>
                    <article class="notification-card <?php echo h($tipo); ?> <?php echo $isRead ? '' : 'unread'; ?>">
                        <div class="notification-top">
                            <div class="notification-title"><?php echo h($titolo); ?></div>
                            <div class="notification-date"><?php echo h(format_datetime_it($createdAt)); ?></div>
                        </div>

                        <div class="notification-body"><?php echo nl2br(h($messaggio)); ?></div>

                        <div class="notification-foot">
                            <span class="notification-pill <?php echo $isRead ? 'read' : 'unread'; ?>">
                                <?php echo $isRead ? 'Letta' : 'Nuova'; ?>
                            </span>

                            <div class="notification-actions">
                                <a href="<?php echo h($smartLink); ?>" class="action-link">Apri</a>

                                <?php if (!$isRead): ?>
                                    <button type="button" class="action-btn mark-read-btn" data-id="<?php echo $notificationId; ?>">
                                        Segna letta
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    Nessuna notifica disponibile al momento.
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="footer-actions">
        <a class="logout-link" href="logout.php">Esci</a>
    </div>
</div>

<script>
const TURNAR_PUSH_PUBLIC_KEY = <?php echo json_encode($pushPublicKey, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('./service-worker.js').catch(function () {});
    });
}

(function () {
    const markAllBtn = document.getElementById('markAllReadBtn');
    const markReadButtons = document.querySelectorAll('.mark-read-btn');
    const installCard = document.getElementById('installCard');
    const installAppBtn = document.getElementById('installAppBtn');
    const closeInstallCardBtn = document.getElementById('closeInstallCardBtn');
    const iosInstallHelp = document.getElementById('iosInstallHelp');

    const pushSupportPill = document.getElementById('pushSupportPill');
    const pushPermissionPill = document.getElementById('pushPermissionPill');
    const pushHelpBox = document.getElementById('pushHelpBox');
    const enablePushBtn = document.getElementById('enablePushBtn');
    const disablePushBtn = document.getElementById('disablePushBtn');

    let deferredPrompt = null;

    function isIos() {
        return /iphone|ipad|ipod/i.test(window.navigator.userAgent);
    }

    function isInStandaloneMode() {
        return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    }

    function shouldHideInstallCardForever() {
        try {
            return localStorage.getItem('turnar_install_card_closed') === '1';
        } catch (e) {
            return false;
        }
    }

    function hideInstallCardForever() {
        try {
            localStorage.setItem('turnar_install_card_closed', '1');
        } catch (e) {}
    }

    function showInstallCard() {
        if (!installCard || isInStandaloneMode() || shouldHideInstallCardForever()) {
            return;
        }

        installCard.classList.remove('hidden');

        if (isIos() && iosInstallHelp) {
            iosInstallHelp.classList.remove('hidden');
        }
    }

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }

        return outputArray;
    }

    async function postAction(url, bodyData) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: new URLSearchParams(bodyData).toString()
        });

        return await response.json();
    }

    async function postJson(url, data) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json; charset=UTF-8' },
            body: JSON.stringify(data)
        });

        return await response.json();
    }

    async function markOne(id) {
        if (!id) return;

        try {
            const data = await postAction('api/notifications.php', { action: 'mark_read', id: id });
            if (data && data.success) {
                window.location.reload();
            }
        } catch (e) {}
    }

    if (markAllBtn) {
        markAllBtn.addEventListener('click', async function () {
            try {
                const data = await postAction('api/notifications.php', { action: 'mark_all_read' });
                if (data && data.success) {
                    window.location.reload();
                }
            } catch (e) {}
        });
    }

    markReadButtons.forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const id = this.getAttribute('data-id') || '';
            await markOne(id);
        });
    });

    if (closeInstallCardBtn) {
        closeInstallCardBtn.addEventListener('click', function () {
            if (installCard) {
                installCard.classList.add('hidden');
            }
            hideInstallCardForever();
        });
    }

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;

        showInstallCard();

        if (installAppBtn) {
            installAppBtn.classList.remove('hidden');
        }
    });

    if (installAppBtn) {
        installAppBtn.addEventListener('click', async function () {
            if (!deferredPrompt) return;

            deferredPrompt.prompt();

            try {
                await deferredPrompt.userChoice;
            } catch (e) {}

            deferredPrompt = null;

            if (installCard) {
                installCard.classList.add('hidden');
            }
        });
    }

    window.addEventListener('appinstalled', function () {
        if (installCard) {
            installCard.classList.add('hidden');
        }
        hideInstallCardForever();
    });

    showInstallCard();

    function setPushSupportState(text, type) {
        if (!pushSupportPill) return;
        pushSupportPill.textContent = text;
        pushSupportPill.className = 'push-pill ' + (type || '');
    }

    function setPushPermissionState(text, type) {
        if (!pushPermissionPill) return;
        pushPermissionPill.textContent = text;
        pushPermissionPill.className = 'push-pill ' + (type || '');
    }

    async function updatePushUi() {
        const supported = ('serviceWorker' in navigator) && ('PushManager' in window) && ('Notification' in window);

        if (!supported) {
            setPushSupportState('Browser non supportato', 'danger');
            setPushPermissionState('Push non disponibili', 'danger');
            if (enablePushBtn) enablePushBtn.disabled = true;
            if (disablePushBtn) disablePushBtn.classList.add('hidden');
            if (pushHelpBox) {
                pushHelpBox.innerHTML = 'Questo browser o dispositivo non supporta le notifiche push web.';
            }
            return;
        }

        setPushSupportState('Browser compatibile', 'success');

        const permission = Notification.permission;
        if (permission === 'granted') {
            setPushPermissionState('Permesso concesso', 'success');
        } else if (permission === 'denied') {
            setPushPermissionState('Permesso negato', 'danger');
        } else {
            setPushPermissionState('Permesso da chiedere', 'warning');
        }

        if (!TURNAR_PUSH_PUBLIC_KEY) {
            if (pushHelpBox) {
                pushHelpBox.innerHTML = 'Push browser non attive: manca la configurazione VAPID lato server.';
            }
            if (enablePushBtn) enablePushBtn.disabled = true;
            return;
        }

        try {
            const registration = await navigator.serviceWorker.ready;
            const sub = await registration.pushManager.getSubscription();

            if (sub) {
                if (enablePushBtn) enablePushBtn.classList.add('hidden');
                if (disablePushBtn) disablePushBtn.classList.remove('hidden');
                if (pushHelpBox) {
                    pushHelpBox.innerHTML = 'Le notifiche push browser risultano attive su questo dispositivo.';
                }
            } else {
                if (enablePushBtn) enablePushBtn.classList.remove('hidden');
                if (disablePushBtn) disablePushBtn.classList.add('hidden');
                if (pushHelpBox) {
                    pushHelpBox.innerHTML = 'Attiva le push per ricevere subito l’avviso quando ti assegnano un turno.';
                }
            }
        } catch (e) {
            if (pushHelpBox) {
                pushHelpBox.innerHTML = 'Non sono riuscito a leggere lo stato delle push su questo dispositivo.';
            }
        }
    }

    async function enablePush() {
        try {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                await updatePushUi();
                return;
            }

            const registration = await navigator.serviceWorker.ready;
            let subscription = await registration.pushManager.getSubscription();

            if (!subscription) {
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(TURNAR_PUSH_PUBLIC_KEY)
                });
            }

            const payload = subscription.toJSON();
            payload.contentEncoding = (PushManager.supportedContentEncodings && PushManager.supportedContentEncodings[0])
                ? PushManager.supportedContentEncodings[0]
                : 'aesgcm';

            await postJson('api/push_subscribe.php', {
                action: 'subscribe',
                subscription: payload
            });

            await updatePushUi();
        } catch (e) {
            await updatePushUi();
        }
    }

    async function disablePush() {
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();

            if (!subscription) {
                await updatePushUi();
                return;
            }

            const endpoint = subscription.endpoint || '';

            await postJson('api/push_subscribe.php', {
                action: 'unsubscribe',
                endpoint: endpoint
            });

            await subscription.unsubscribe();
            await updatePushUi();
        } catch (e) {
            await updatePushUi();
        }
    }

    if (enablePushBtn) {
        enablePushBtn.addEventListener('click', enablePush);
    }

    if (disablePushBtn) {
        disablePushBtn.addEventListener('click', disablePush);
    }

    updatePushUi();
})();
</script>

</body>
</html>