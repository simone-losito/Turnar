<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../core/settings.php';
require_once __DIR__ . '/../core/app_notifications.php';

require_mobile_login();

$user = auth_user();
$dipendenteId = auth_dipendente_id();

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (!$dipendenteId) {
    http_response_code(403);
    exit('Dipendente non associato all’utente.');
}

$displayName = trim((string)($user['nome'] ?? ''));
if ($displayName === '') {
    $displayName = trim((string)($user['username'] ?? 'Utente'));
}

$selectedDate = trim((string)($_GET['date'] ?? ''));
$month = isset($_GET['m']) ? (int)$_GET['m'] : 0;
$year  = isset($_GET['y']) ? (int)$_GET['y'] : 0;

if ($selectedDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedTs = strtotime($selectedDate);
    if ($selectedTs !== false) {
        if ($month <= 0 || $month > 12) {
            $month = (int)date('n', $selectedTs);
        }
        if ($year <= 0) {
            $year = (int)date('Y', $selectedTs);
        }
    }
}

if ($month < 1 || $month > 12) {
    $month = (int)date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = (int)date('Y');
}

$firstDayTs = strtotime(sprintf('%04d-%02d-01', $year, $month));
if ($firstDayTs === false) {
    $firstDayTs = strtotime(date('Y-m-01'));
}

$year = (int)date('Y', $firstDayTs);
$month = (int)date('n', $firstDayTs);

$daysInMonth = (int)date('t', $firstDayTs);
$startWeekDay = (int)date('N', $firstDayTs);

$prevMonthTs = strtotime('-1 month', $firstDayTs);
$nextMonthTs = strtotime('+1 month', $firstDayTs);

$prevMonth = (int)date('n', $prevMonthTs);
$prevYear  = (int)date('Y', $prevMonthTs);
$nextMonth = (int)date('n', $nextMonthTs);
$nextYear  = (int)date('Y', $nextMonthTs);

$monthStart = date('Y-m-01', $firstDayTs);
$monthEnd   = date('Y-m-t', $firstDayTs);

$turniByDate = [];
$selectedDayTurns = [];
$todayIso = date('Y-m-d');

$notifiedDates = [];
$unreadNotifiedDates = [];
$notificationsByDate = [];

try {
    $db = db_connect();

    $stmt = $db->prepare("
        SELECT
            et.id,
            et.data,
            et.ora_inizio,
            et.ora_fine,
            et.id_cantiere,
            c.commessa AS cantiere_nome
        FROM eventi_turni et
        LEFT JOIN cantieri c ON c.id = et.id_cantiere
        WHERE et.id_dipendente = ?
          AND et.data BETWEEN ? AND ?
        ORDER BY et.data ASC, et.ora_inizio ASC, et.id ASC
    ");

    if ($stmt) {
        $stmt->bind_param('iss', $dipendenteId, $monthStart, $monthEnd);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            $dateKey = (string)($row['data'] ?? '');
            if ($dateKey === '') {
                continue;
            }

            if (!isset($turniByDate[$dateKey])) {
                $turniByDate[$dateKey] = [];
            }

            $turniByDate[$dateKey][] = $row;
        }

        $stmt->close();
    }

    $stmtNotif = $db->prepare("
        SELECT id, titolo, messaggio, link, is_read, created_at, tipo
        FROM app_notifications
        WHERE dipendente_id = ?
          AND tipo = 'turno'
        ORDER BY created_at DESC, id DESC
        LIMIT 200
    ");

    if ($stmtNotif) {
        $stmtNotif->bind_param('i', $dipendenteId);
        $stmtNotif->execute();
        $resNotif = $stmtNotif->get_result();

        while ($row = $resNotif->fetch_assoc()) {
            $link = trim((string)($row['link'] ?? ''));
            $isRead = !empty($row['is_read']);

            if ($link !== '' && preg_match('/(?:\?|&)date=(\d{4}-\d{2}-\d{2})/', $link, $matches)) {
                $dateFromLink = $matches[1];

                if ($dateFromLink >= $monthStart && $dateFromLink <= $monthEnd) {
                    $notifiedDates[$dateFromLink] = true;

                    if (!isset($notificationsByDate[$dateFromLink])) {
                        $notificationsByDate[$dateFromLink] = [];
                    }

                    $notificationsByDate[$dateFromLink][] = $row;

                    if (!$isRead) {
                        $unreadNotifiedDates[$dateFromLink] = true;
                    }
                }
            }
        }

        $stmtNotif->close();
    }
} catch (Throwable $e) {
    $turniByDate = [];
    $notifiedDates = [];
    $unreadNotifiedDates = [];
    $notificationsByDate = [];
}

if ($selectedDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    if (isset($turniByDate[$todayIso])) {
        $selectedDate = $todayIso;
    } else {
        $selectedDate = sprintf('%04d-%02d-01', $year, $month);
    }
}

$selectedDayTurns = $turniByDate[$selectedDate] ?? [];
$selectedDayNotifications = $notificationsByDate[$selectedDate] ?? [];

$monthNames = [
    1 => 'Gennaio',
    2 => 'Febbraio',
    3 => 'Marzo',
    4 => 'Aprile',
    5 => 'Maggio',
    6 => 'Giugno',
    7 => 'Luglio',
    8 => 'Agosto',
    9 => 'Settembre',
    10 => 'Ottobre',
    11 => 'Novembre',
    12 => 'Dicembre',
];

$weekLabels = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];

$unreadCount = app_notification_unread_count($dipendenteId);
$monthNotifiedCount = count($notifiedDates);
$monthUnreadNotifiedCount = count($unreadNotifiedDates);

$selectedHasNotice = !empty($notifiedDates[$selectedDate]);
$selectedHasUnreadNotice = !empty($unreadNotifiedDates[$selectedDate]);

$themeMode = function_exists('app_theme_mode') ? app_theme_mode() : (string)setting('theme_mode', 'dark');
$themePrimary = function_exists('app_theme_primary') ? app_theme_primary() : (string)setting('theme_primary_color', '#6ea8ff');
$themeSecondary = function_exists('app_theme_secondary') ? app_theme_secondary() : (string)setting('theme_secondary_color', '#8b5cf6');

if (!preg_match('/^#[0-9a-fA-F]{6}$/', $themePrimary)) {
    $themePrimary = '#6ea8ff';
}
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $themeSecondary)) {
    $themeSecondary = '#8b5cf6';
}

$resolvedThemeMode = $themeMode;
if (!in_array($resolvedThemeMode, ['dark', 'light', 'auto'], true)) {
    $resolvedThemeMode = 'dark';
}
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
    $bodyBackground = "
        radial-gradient(circle at top left, rgba(110,168,255,.10), transparent 28%),
        radial-gradient(circle at top right, rgba(139,92,246,.08), transparent 24%),
        linear-gradient(180deg, #f8fbff, #edf3fb)
    ";
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
    $bodyBackground = "
        radial-gradient(circle at top left, rgba(110,168,255,.18), transparent 28%),
        radial-gradient(circle at top right, rgba(139,92,246,.14), transparent 24%),
        linear-gradient(180deg, #0b1226, #050816)
    ";
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
<title>Calendario Turni - Turnar App</title>

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
    --mobile-card-bg:<?php echo h($cardBackground); ?>;
    --mobile-soft-bg:<?php echo h($softBackground); ?>;
    --mobile-soft-bg-2:<?php echo h($softBackground2); ?>;
    --mobile-soft-bg-3:<?php echo h($softBackground3); ?>;
    --mobile-danger-bg:<?php echo h($dangerSoft); ?>;
    --mobile-danger-border:<?php echo h($dangerBorder); ?>;
    --badge-radius:999px;
}
</style>

<link rel="stylesheet" href="<?php echo h('../assets/css/turnar.css'); ?>?v=<?php echo urlencode((string)app_version()); ?>">
</head>

<body class="turnar-mobile-page">

<div class="mobile-app-shell">

    <section class="hero-card">
        <div class="hero-top">
            <div>
                <h1 class="hero-title">Calendario turni</h1>
                <div class="hero-sub">Ciao <?php echo h($displayName); ?>, qui trovi i tuoi turni mensili</div>
            </div>

            <span class="badge primary">
                <?php echo (int)$unreadCount; ?> notifich<?php echo $unreadCount === 1 ? 'a' : 'e'; ?>
            </span>
        </div>

        <div class="hero-actions">
            <a class="nav-btn" href="index.php">← Home</a>
            <a class="nav-btn" href="index.php#notifiche">🔔 Notifiche</a>
            <a class="nav-btn logout" href="logout.php">Esci</a>
        </div>
    </section>

    <section class="legend-card">
        <div class="legend-head">
            <div>
                <h2 class="legend-title">Legenda calendario</h2>
                <div class="legend-sub">I giorni con avvisi turno vengono evidenziati direttamente nel mese</div>
            </div>

            <div class="legend-pills">
                <span class="legend-pill"><?php echo $monthNotifiedCount; ?> giorni con notifiche</span>
                <?php if ($monthUnreadNotifiedCount > 0): ?>
                    <span class="legend-pill notice-new">
                        <?php echo $monthUnreadNotifiedCount; ?> nuovi
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="legend-pills">
            <span class="legend-pill"><span class="legend-dot turn"></span> Giorno con turno</span>
            <span class="legend-pill"><span class="legend-dot notice"></span> Giorno segnalato da notifica</span>
            <span class="legend-pill"><span class="legend-dot notice-new"></span> Giorno con notifica non letta</span>
        </div>
    </section>

    <section class="calendar-card">
        <div class="calendar-head">
            <div>
                <h2 class="calendar-title"><?php echo h(($monthNames[$month] ?? '') . ' ' . $year); ?></h2>
                <div class="calendar-sub">Tocca un giorno per vedere i dettagli del turno</div>
            </div>

            <div class="calendar-nav">
                <a class="calendar-nav-link" href="?m=<?php echo $prevMonth; ?>&y=<?php echo $prevYear; ?>&date=<?php echo h(date('Y-m-d', strtotime(sprintf('%04d-%02d-01', $prevYear, $prevMonth)))); ?>">←</a>
                <a class="calendar-nav-link" href="?m=<?php echo (int)date('n'); ?>&y=<?php echo (int)date('Y'); ?>&date=<?php echo h($todayIso); ?>">Oggi</a>
                <a class="calendar-nav-link" href="?m=<?php echo $nextMonth; ?>&y=<?php echo $nextYear; ?>&date=<?php echo h(date('Y-m-d', strtotime(sprintf('%04d-%02d-01', $nextYear, $nextMonth)))); ?>">→</a>
            </div>
        </div>

        <div class="week-grid">
            <?php foreach ($weekLabels as $weekLabel): ?>
                <div class="week-label"><?php echo h($weekLabel); ?></div>
            <?php endforeach; ?>
        </div>

        <div class="calendar-grid">
            <?php for ($empty = 1; $empty < $startWeekDay; $empty++): ?>
                <div class="day-cell empty"></div>
            <?php endfor; ?>

            <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                <?php
                $dateIso = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $hasTurns = !empty($turniByDate[$dateIso]);
                $hasNotice = !empty($notifiedDates[$dateIso]);
                $hasUnreadNotice = !empty($unreadNotifiedDates[$dateIso]);
                $isToday = ($dateIso === $todayIso);
                $isSelected = ($dateIso === $selectedDate);
                $turnCount = $hasTurns ? count($turniByDate[$dateIso]) : 0;

                $classes = ['day-cell'];
                if ($hasTurns) $classes[] = 'has-turns';
                if ($hasNotice) $classes[] = 'has-notice';
                if ($hasUnreadNotice) $classes[] = 'has-unread-notice';
                if ($isToday) $classes[] = 'today';
                if ($isSelected) $classes[] = 'selected';
                ?>
                <div class="<?php echo h(implode(' ', $classes)); ?>">
                    <a class="day-link" href="?m=<?php echo $month; ?>&y=<?php echo $year; ?>&date=<?php echo h($dateIso); ?>">
                        <div class="day-top">
                            <span class="day-number"><?php echo $day; ?></span>
                            <?php if ($isToday): ?>
                                <span class="day-pill today-pill">Oggi</span>
                            <?php endif; ?>
                        </div>

                        <div class="day-meta">
                            <?php if ($hasTurns): ?>
                                <span class="day-pill turns"><?php echo $turnCount; ?> turn<?php echo $turnCount === 1 ? 'o' : 'i'; ?></span>
                            <?php endif; ?>

                            <?php if ($hasUnreadNotice): ?>
                                <span class="day-pill notice-new">Nuovo avviso</span>
                            <?php elseif ($hasNotice): ?>
                                <span class="day-pill notice">Avviso</span>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
            <?php endfor; ?>
        </div>
    </section>

    <section class="day-detail-card">
        <div class="day-detail-head">
            <div>
                <h2 class="day-detail-title">Dettaglio giorno</h2>
                <div class="day-detail-sub"><?php echo h(format_date_it($selectedDate)); ?></div>

                <div class="day-status-row">
                    <?php if ($selectedHasUnreadNotice): ?>
                        <span class="day-status-pill notice-new">Giorno con avviso non letto</span>
                    <?php elseif ($selectedHasNotice): ?>
                        <span class="day-status-pill notice">Giorno segnalato da notifica</span>
                    <?php endif; ?>
                </div>
            </div>

            <span class="badge">
                <?php echo count($selectedDayTurns); ?> turn<?php echo count($selectedDayTurns) === 1 ? 'o' : 'i'; ?>
            </span>
        </div>

        <?php if (!empty($selectedDayTurns)): ?>
            <div class="turns-list">
                <?php foreach ($selectedDayTurns as $turno): ?>
                    <?php
                    $oraInizio = trim((string)($turno['ora_inizio'] ?? ''));
                    $oraFine = trim((string)($turno['ora_fine'] ?? ''));
                    $cantiereNome = trim((string)($turno['cantiere_nome'] ?? ''));
                    $idCantiere = (int)($turno['id_cantiere'] ?? 0);

                    if ($cantiereNome === '') {
                        $cantiereNome = 'Destinazione #' . $idCantiere;
                    }
                    ?>
                    <article class="turn-card">
                        <div class="turn-time">
                            <?php echo h(format_time_it($oraInizio)); ?> - <?php echo h(format_time_it($oraFine)); ?>
                        </div>
                        <div class="turn-destination"><?php echo h($cantiereNome); ?></div>
                        <div class="turn-meta">Turno assegnato nel calendario Turnar</div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                Nessun turno presente per questo giorno.
            </div>
        <?php endif; ?>
    </section>

    <section class="day-alerts-card">
        <div class="day-alerts-head">
            <div>
                <h2 class="day-alerts-title">Avvisi del giorno</h2>
                <div class="day-alerts-sub">Notifiche turno collegate alla data selezionata</div>
            </div>

            <span class="badge">
                <?php echo count($selectedDayNotifications); ?> avvis<?php echo count($selectedDayNotifications) === 1 ? 'o' : 'i'; ?>
            </span>
        </div>

        <?php if (!empty($selectedDayNotifications)): ?>
            <div class="alerts-list">
                <?php foreach ($selectedDayNotifications as $alert): ?>
                    <?php
                    $alertTitle = trim((string)($alert['titolo'] ?? 'Notifica turno'));
                    $alertBody = trim((string)($alert['messaggio'] ?? ''));
                    $alertDate = trim((string)($alert['created_at'] ?? ''));
                    $alertRead = !empty($alert['is_read']);
                    ?>
                    <article class="alert-card <?php echo $alertRead ? '' : 'unread'; ?>">
                        <div class="alert-title"><?php echo h($alertTitle); ?></div>
                        <div class="alert-body"><?php echo nl2br(h($alertBody)); ?></div>
                        <div class="alert-meta">
                            <?php echo $alertRead ? 'Letta' : 'Nuova'; ?> • <?php echo h(format_datetime_it($alertDate)); ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                Nessun avviso collegato a questo giorno.
            </div>
        <?php endif; ?>
    </section>

</div>

<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('./service-worker.js').catch(function () {});
    });
}
</script>

</body>
</html>