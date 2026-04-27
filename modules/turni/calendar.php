<?php
// modules/turni/calendar.php

require_once __DIR__ . '/TurniRepository.php';

require_login();
require_module('assignments');

$pageTitle    = 'Calendario turni';
$pageSubtitle = 'Vista mensile e settimanale del calendario Turnar';
$activeModule = 'calendar';

$canManage   = can_manage_assignments();
$canViewAll  = can_view_all_assignments();
$canViewTeam = can_view_team_assignments();
$canViewOwn  = can_view_own_assignments();

$repo = new TurniRepository(db_connect());

// --------------------------------------------------
// DATA / VISTA
// --------------------------------------------------
$today = new DateTimeImmutable('today');
$todayIso = $today->format('Y-m-d');

$view = trim((string)get('view', 'month'));
if (!in_array($view, ['month', 'week'], true)) {
    $view = 'month';
}

$month = (int)get('month', (int)$today->format('n'));
$year  = (int)get('year', (int)$today->format('Y'));

if ($month < 1 || $month > 12) {
    $month = (int)$today->format('n');
}
if ($year < 2020 || $year > 2100) {
    $year = (int)$today->format('Y');
}

$selectedDateIso = normalize_date_iso((string)get('date', $todayIso)) ?: $todayIso;
$selectedDateObj = new DateTimeImmutable($selectedDateIso);

$pageError = '';
$pageNotice = '';
$turniByDate = [];
$rangeStart = null;
$rangeEnd   = null;
$prevLink   = '';
$nextLink   = '';
$todayLink  = '';
$headerTitle = '';
$headerSub   = '';
$monthStartForView = null;

// --------------------------------------------------
// FLASH MESSAGGI
// --------------------------------------------------
if (!empty($_SESSION['turnar_calendar_notice'])) {
    $pageNotice = (string)$_SESSION['turnar_calendar_notice'];
    unset($_SESSION['turnar_calendar_notice']);
}

if (!empty($_SESSION['turnar_calendar_error'])) {
    $pageError = (string)$_SESSION['turnar_calendar_error'];
    unset($_SESSION['turnar_calendar_error']);
}

// --------------------------------------------------
// HELPERS
// --------------------------------------------------
function calendar_time_to_minutes(?string $time): int
{
    $time = trim((string)$time);
    if ($time === '') {
        return 0;
    }

    $parts = explode(':', $time);
    $h = isset($parts[0]) ? (int)$parts[0] : 0;
    $m = isset($parts[1]) ? (int)$parts[1] : 0;

    return ($h * 60) + $m;
}

function calendar_group_turni_by_cantiere(array $turni): array
{
    $grouped = [];

    foreach ($turni as $turno) {
        $destId = (int)($turno['id_cantiere'] ?? 0);
        $destName = trim((string)($turno['destinazione_nome'] ?? 'Senza destinazione'));

        $key = $destId > 0 ? (string)$destId : 'x_' . md5($destName);

        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'id_cantiere'            => $destId,
                'destinazione_nome'      => $destName !== '' ? $destName : 'Senza destinazione',
                'destinazione_comune'    => (string)($turno['destinazione_comune'] ?? ''),
                'destinazione_tipologia' => (string)($turno['destinazione_tipologia'] ?? ''),
                'turni'                  => [],
            ];
        }

        $grouped[$key]['turni'][] = $turno;
    }

    foreach ($grouped as &$group) {
        usort($group['turni'], function(array $a, array $b): int {
            $aStart = calendar_time_to_minutes((string)($a['ora_inizio'] ?? ''));
            $bStart = calendar_time_to_minutes((string)($b['ora_inizio'] ?? ''));
            if ($aStart !== $bStart) {
                return $aStart <=> $bStart;
            }

            $aEnd = calendar_time_to_minutes((string)($a['ora_fine'] ?? ''));
            $bEnd = calendar_time_to_minutes((string)($b['ora_fine'] ?? ''));
            if ($aEnd !== $bEnd) {
                return $aEnd <=> $bEnd;
            }

            $aName = mb_strtolower(trim((string)($a['operatore_nome'] ?? '')), 'UTF-8');
            $bName = mb_strtolower(trim((string)($b['operatore_nome'] ?? '')), 'UTF-8');

            return $aName <=> $bName;
        });
    }
    unset($group);

    usort($grouped, function(array $a, array $b): int {
        $aName = mb_strtolower(trim((string)($a['destinazione_nome'] ?? '')), 'UTF-8');
        $bName = mb_strtolower(trim((string)($b['destinazione_nome'] ?? '')), 'UTF-8');
        return $aName <=> $bName;
    });

    return $grouped;
}

function calendar_build_url(string $view, DateTimeImmutable $date): string
{
    if ($view === 'week') {
        return app_url('modules/turni/calendar.php?view=week&date=' . urlencode($date->format('Y-m-d')));
    }

    return app_url(
        'modules/turni/calendar.php?view=month&month=' . $date->format('n') . '&year=' . $date->format('Y')
    );
}

function calendar_build_self_url(string $view, ?DateTimeImmutable $monthStart, ?DateTimeImmutable $selectedDate): string
{
    if ($view === 'week' && $selectedDate instanceof DateTimeImmutable) {
        return app_url('modules/turni/calendar.php?view=week&date=' . urlencode($selectedDate->format('Y-m-d')));
    }

    if ($monthStart instanceof DateTimeImmutable) {
        return app_url('modules/turni/calendar.php?view=month&month=' . $monthStart->format('n') . '&year=' . $monthStart->format('Y'));
    }

    return app_url('modules/turni/calendar.php');
}

$monthLabelMonths = [
    1  => 'Gennaio',
    2  => 'Febbraio',
    3  => 'Marzo',
    4  => 'Aprile',
    5  => 'Maggio',
    6  => 'Giugno',
    7  => 'Luglio',
    8  => 'Agosto',
    9  => 'Settembre',
    10 => 'Ottobre',
    11 => 'Novembre',
    12 => 'Dicembre',
];

$weekdayLabels = ['Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato', 'Domenica'];
$weekdayShort  = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];

// --------------------------------------------------
// RANGE MENSILE / SETTIMANALE
// --------------------------------------------------
if ($view === 'month') {
    $currentMonthDate = DateTimeImmutable::createFromFormat('Y-n-j', $year . '-' . $month . '-1');
    if (!$currentMonthDate) {
        $currentMonthDate = new DateTimeImmutable($today->format('Y-m-01'));
    }

    $monthStartForView = $currentMonthDate->modify('first day of this month');
    $monthEnd = $currentMonthDate->modify('last day of this month');

    $gridStart = $monthStartForView;
    $dayOfWeek = (int)$gridStart->format('N');
    if ($dayOfWeek > 1) {
        $gridStart = $gridStart->modify('-' . ($dayOfWeek - 1) . ' days');
    }

    $gridEnd = $monthEnd;
    $dayOfWeekEnd = (int)$gridEnd->format('N');
    if ($dayOfWeekEnd < 7) {
        $gridEnd = $gridEnd->modify('+' . (7 - $dayOfWeekEnd) . ' days');
    }

    $rangeStart = $gridStart;
    $rangeEnd   = $gridEnd;

    $prevLink  = calendar_build_url('month', $monthStartForView->modify('-1 month'));
    $nextLink  = calendar_build_url('month', $monthStartForView->modify('+1 month'));
    $todayLink = calendar_build_url('month', $today);

    $headerTitle = $monthLabelMonths[(int)$monthStartForView->format('n')] . ' ' . $monthStartForView->format('Y');
    $headerSub   = 'Vista mensile • dal ' . $gridStart->format('d/m/Y') . ' al ' . $gridEnd->format('d/m/Y');
} else {
    $weekStart = $selectedDateObj;
    $weekDay = (int)$weekStart->format('N');
    if ($weekDay > 1) {
        $weekStart = $weekStart->modify('-' . ($weekDay - 1) . ' days');
    }
    $weekEnd = $weekStart->modify('+6 days');

    $rangeStart = $weekStart;
    $rangeEnd   = $weekEnd;

    $prevLink  = calendar_build_url('week', $weekStart->modify('-7 days'));
    $nextLink  = calendar_build_url('week', $weekStart->modify('+7 days'));
    $todayLink = calendar_build_url('week', $today);

    $headerTitle = 'Settimana ' . $weekStart->format('d/m') . ' → ' . $weekEnd->format('d/m/Y');
    $headerSub   = 'Vista settimanale • dal ' . $weekStart->format('d/m/Y') . ' al ' . $weekEnd->format('d/m/Y');
}

// --------------------------------------------------
// DATI
// --------------------------------------------------
try {
    $turniByDate = $repo->getTurniGroupedByDateBetween(
        $rangeStart->format('Y-m-d'),
        $rangeEnd->format('Y-m-d')
    );
} catch (Throwable $e) {
    $pageError = 'Errore caricamento calendario: ' . $e->getMessage();
}

$currentCalendarUrl = calendar_build_self_url($view, $monthStartForView, $selectedDateObj);

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
.calendar-header-card{
    margin-bottom:18px;
}

.calendar-top{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
}

.calendar-top-left h2{
    margin:0 0 8px;
    font-size:24px;
    font-weight:900;
    color:var(--text);
}

.calendar-top-left p{
    margin:0;
    color:var(--muted);
    line-height:1.6;
}

.calendar-nav{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
}

.calendar-switch{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    margin-bottom:18px;
}

.calendar-switch-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:10px 14px;
    border-radius:999px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 88%, transparent);
    color:var(--text);
    text-decoration:none;
    font-size:13px;
    font-weight:700;
    transition:.16s ease;
}

.calendar-switch-btn:hover{
    background:color-mix(in srgb, var(--bg-3) 96%, transparent);
    transform:translateY(-1px);
}

.calendar-switch-btn.active{
    background:linear-gradient(135deg, color-mix(in srgb, var(--primary) 22%, transparent), color-mix(in srgb, var(--primary-2) 18%, transparent));
    border-color:color-mix(in srgb, var(--primary) 48%, transparent);
    color:var(--text);
    box-shadow:0 10px 24px rgba(0,0,0,.12);
}

.calendar-month-banner{
    margin-bottom:18px;
    padding:16px 18px;
    border-radius:22px;
    border:1px solid var(--line);
    background:var(--content-card-bg);
    box-shadow:var(--shadow);
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
}

.calendar-month-title{
    font-size:24px;
    font-weight:900;
    line-height:1.1;
    color:var(--text);
}

.calendar-month-sub{
    color:var(--muted);
    font-size:13px;
}

.calendar-legend{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}

.calendar-legend-badge{
    display:inline-flex;
    align-items:center;
    padding:6px 10px;
    border-radius:999px;
    font-size:11px;
    font-weight:700;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 86%, transparent);
    color:var(--text);
    line-height:1;
}

.calendar-legend-badge.today{
    border-color:rgba(251,191,36,.35);
    color:#b45309;
    background:rgba(251,191,36,.10);
}

.calendar-legend-badge.outside{
    border-color:rgba(148,163,184,.25);
    color:#64748b;
    background:rgba(148,163,184,.08);
}

.calendar-legend-badge.conflict{
    border-color:rgba(239,68,68,.35);
    color:#b91c1c;
    background:rgba(239,68,68,.12);
}

.calendar-flash{
    margin-bottom:18px;
    padding:14px 16px;
    border-radius:18px;
    line-height:1.6;
    font-size:14px;
    border:1px solid var(--line);
}

.calendar-flash.notice{
    border-color:rgba(52,211,153,.24);
    background:rgba(52,211,153,.10);
    color:#166534;
}

.calendar-flash.error{
    border-color:rgba(248,113,113,.24);
    background:rgba(248,113,113,.10);
    color:#b91c1c;
}

.calendar-grid-wrap{
    overflow-x:auto;
}

.calendar-grid{
    min-width:1200px;
    display:grid;
    grid-template-columns:repeat(7, minmax(170px, 1fr));
    gap:12px;
}

.calendar-grid.week{
    min-width:1320px;
    grid-template-columns:repeat(7, minmax(185px, 1fr));
}

.calendar-weekday{
    padding:12px 14px;
    border-radius:16px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 82%, transparent);
    color:var(--text);
    font-size:13px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
}

.calendar-day{
    min-height:170px;
    border-radius:20px;
    border:1px solid var(--line);
    background:var(--content-card-bg);
    box-shadow:var(--shadow);
    padding:10px;
    display:flex;
    flex-direction:column;
    gap:8px;
    transition:.16s ease;
}

.calendar-day.week-mode{
    min-height:440px;
}

.calendar-day.outside-month{
    opacity:.62;
    background:linear-gradient(180deg, rgba(148,163,184,.06), color-mix(in srgb, var(--bg-3) 76%, transparent));
}

.calendar-day.today{
    border-color:rgba(251,191,36,.38);
    box-shadow:0 0 0 1px rgba(251,191,36,.14), 0 12px 28px rgba(0,0,0,.12);
}

.calendar-day.drop-target{
    border-color:color-mix(in srgb, var(--primary) 55%, transparent);
    background:linear-gradient(180deg, color-mix(in srgb, var(--primary) 14%, transparent), var(--content-card-bg));
    box-shadow:0 0 0 1px color-mix(in srgb, var(--primary) 12%, transparent), 0 16px 30px rgba(0,0,0,.16);
    transform:translateY(-1px);
}

.calendar-day.conflict-hint{
    border-color:rgba(239,68,68,.50);
    background:linear-gradient(180deg, rgba(239,68,68,.12), var(--content-card-bg));
    box-shadow:0 0 0 1px rgba(239,68,68,.16), 0 16px 30px rgba(0,0,0,.16);
}

.calendar-day.conflict-hint.drop-target{
    border-color:rgba(239,68,68,.65);
    background:linear-gradient(180deg, rgba(239,68,68,.16), var(--content-card-bg));
    box-shadow:0 0 0 1px rgba(239,68,68,.24), 0 16px 30px rgba(0,0,0,.18);
}

.calendar-day-header{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:10px;
}

.calendar-day-num{
    font-size:21px;
    font-weight:900;
    line-height:1;
    color:var(--text);
}

.calendar-day-meta{
    font-size:10px;
    color:var(--muted);
    margin-top:3px;
}

.calendar-day-tools{
    display:flex;
    gap:6px;
    flex-wrap:wrap;
    justify-content:flex-end;
}

.calendar-mini-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:5px 9px;
    border-radius:11px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 88%, transparent);
    color:var(--text);
    text-decoration:none;
    font-size:10px;
    font-weight:700;
    transition:.16s ease;
}

.calendar-mini-btn:hover{
    transform:translateY(-1px);
    background:color-mix(in srgb, var(--bg-3) 96%, transparent);
}

.calendar-mini-btn-primary{
    background:linear-gradient(135deg, color-mix(in srgb, var(--primary) 85%, #ffffff 15%), color-mix(in srgb, var(--primary-2) 85%, #ffffff 15%));
    border-color:color-mix(in srgb, var(--primary) 35%, transparent);
    color:#fff;
}

.calendar-day-empty{
    margin-top:2px;
    padding:10px;
    border-radius:12px;
    border:1px dashed var(--line);
    color:var(--muted);
    font-size:11px;
    background:color-mix(in srgb, var(--bg-3) 76%, transparent);
}

.calendar-day-content{
    display:flex;
    flex-direction:column;
    gap:6px;
}

.calendar-cantiere{
    border-radius:14px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 82%, transparent);
    overflow:hidden;
}

.calendar-cantiere-toggle{
    width:100%;
    border:0;
    outline:none;
    cursor:pointer;
    text-align:left;
    padding:8px 10px;
    border-bottom:1px solid color-mix(in srgb, var(--line) 82%, transparent);
    background:color-mix(in srgb, var(--primary) 8%, transparent);
    color:var(--text);
    transition:.16s ease;
}

.calendar-cantiere-toggle:hover{
    background:color-mix(in srgb, var(--primary) 12%, transparent);
}

.calendar-cantiere-toggle-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
}

.calendar-cantiere-title{
    font-size:12px;
    font-weight:900;
    line-height:1.25;
    color:var(--text);
}

.calendar-cantiere-right{
    display:flex;
    align-items:center;
    gap:6px;
    flex-shrink:0;
}

.calendar-cantiere-count{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:22px;
    height:22px;
    padding:0 7px;
    border-radius:999px;
    border:1px solid color-mix(in srgb, var(--primary) 24%, transparent);
    background:color-mix(in srgb, var(--primary) 14%, transparent);
    color:color-mix(in srgb, var(--primary) 72%, var(--text));
    font-size:10px;
    font-weight:900;
}

.calendar-cantiere-chevron{
    font-size:11px;
    color:var(--muted);
    transition:transform .18s ease;
}

.calendar-cantiere.open .calendar-cantiere-chevron{
    transform:rotate(180deg);
}

.calendar-cantiere-sub{
    margin-top:3px;
    font-size:10px;
    color:var(--muted);
    line-height:1.35;
}

.calendar-turn-list{
    padding:8px;
    display:none;
    flex-direction:column;
    gap:6px;
}

.calendar-cantiere.open .calendar-turn-list{
    display:flex;
}

.calendar-turn{
    display:block;
    padding:8px;
    border-radius:12px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 88%, transparent);
    text-decoration:none;
    color:inherit;
    transition:.16s ease;
}

.calendar-turn:hover{
    transform:translateY(-1px);
    border-color:color-mix(in srgb, var(--primary) 30%, transparent);
    background:color-mix(in srgb, var(--bg-3) 96%, transparent);
    box-shadow:0 8px 18px rgba(0,0,0,.12);
}

.calendar-turn.draggable{
    cursor:grab;
}

.calendar-turn.dragging{
    opacity:.50;
    transform:scale(.985);
    border-color:color-mix(in srgb, var(--primary) 55%, transparent);
    box-shadow:0 10px 22px rgba(0,0,0,.18);
}

.calendar-turn-name{
    font-size:11px;
    font-weight:800;
    color:var(--text);
    margin-bottom:3px;
}

.calendar-turn-meta{
    font-size:10px;
    color:var(--text);
    line-height:1.35;
}

.calendar-turn-badges{
    display:flex;
    gap:5px;
    flex-wrap:wrap;
    margin-top:5px;
}

.calendar-turn-badge{
    display:inline-flex;
    align-items:center;
    padding:3px 7px;
    border-radius:999px;
    font-size:9px;
    font-weight:800;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 88%, transparent);
    color:var(--text);
}

.calendar-turn-badge.responsabile{
    background:rgba(244,114,182,.12);
    color:#be185d;
    border-color:rgba(244,114,182,.24);
}

.calendar-help{
    margin-top:18px;
    padding:14px 16px;
    border-radius:18px;
    border:1px solid color-mix(in srgb, var(--primary) 18%, transparent);
    background:color-mix(in srgb, var(--primary) 8%, transparent);
    color:var(--text);
    font-size:13px;
    line-height:1.7;
}

.calendar-modal-overlay{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.52);
    display:flex;
    align-items:center;
    justify-content:center;
    z-index:9999;
    padding:18px;
    backdrop-filter:blur(3px);
}

.calendar-modal{
    width:min(430px, 100%);
    border-radius:22px;
    padding:20px;
    background:var(--content-card-bg);
    border:1px solid var(--line);
    box-shadow:0 24px 50px rgba(0,0,0,.28);
    color:var(--text);
}

.calendar-modal.conflict{
    border-color:rgba(239,68,68,.55);
    box-shadow:0 0 0 1px rgba(239,68,68,.16), 0 24px 50px rgba(0,0,0,.28);
}

.calendar-modal-title{
    font-size:20px;
    font-weight:900;
    margin-bottom:14px;
    line-height:1.2;
    color:var(--text);
}

.calendar-modal-body{
    font-size:14px;
    line-height:1.7;
    color:var(--text);
}

.calendar-modal-grid{
    display:grid;
    grid-template-columns:110px 1fr;
    gap:8px 12px;
    margin-top:10px;
}

.calendar-modal-label{
    color:var(--muted);
    font-weight:700;
}

.calendar-modal-value{
    color:var(--text);
    font-weight:700;
}

.calendar-modal-warning{
    margin-top:14px;
    padding:12px 14px;
    border-radius:14px;
    background:rgba(239,68,68,.14);
    border:1px solid rgba(239,68,68,.26);
    color:#b91c1c;
    font-size:13px;
    line-height:1.6;
}

.calendar-modal-actions{
    display:flex;
    gap:10px;
    margin-top:18px;
    flex-wrap:wrap;
}

.calendar-modal-actions button{
    flex:1;
    min-width:120px;
    padding:11px 14px;
    border-radius:14px;
    border:0;
    font-weight:800;
    cursor:pointer;
    transition:.16s ease;
}

.calendar-modal-actions button:hover{
    transform:translateY(-1px);
}

.btn-cancel{
    background:#475569;
    color:#fff;
}

.btn-confirm{
    background:linear-gradient(135deg, #22c55e, #16a34a);
    color:#fff;
}

.btn-force{
    background:linear-gradient(135deg, #ef4444, #dc2626);
    color:#fff;
}

@media (max-width: 860px){
    .calendar-month-title{
        font-size:20px;
    }

    .calendar-modal-grid{
        grid-template-columns:1fr;
        gap:4px;
    }
}
</style>

<div class="card calendar-header-card">
    <div class="calendar-top">
        <div class="calendar-top-left">
            <h2>Calendario turni</h2>
            <p>
                Vista mensile e settimanale del calendario Turnar con cantieri compatti e apribili.
            </p>
        </div>

        <div class="calendar-nav">
            <a class="btn btn-ghost" href="<?php echo h($prevLink); ?>">
                ← <?php echo $view === 'month' ? 'Mese prima' : 'Settimana prima'; ?>
            </a>
            <a class="btn btn-ghost" href="<?php echo h($todayLink); ?>">
                Oggi
            </a>
            <a class="btn btn-ghost" href="<?php echo h($nextLink); ?>">
                <?php echo $view === 'month' ? 'Mese dopo' : 'Settimana dopo'; ?> →
            </a>
            <a class="btn btn-primary" href="<?php echo h(app_url('modules/turni/planning.php?data=' . urlencode($todayIso))); ?>">
                Vai al planning
            </a>
        </div>
    </div>
</div>

<div class="calendar-switch">
    <a
        class="calendar-switch-btn <?php echo $view === 'month' ? 'active' : ''; ?>"
        href="<?php echo h(app_url('modules/turni/calendar.php?view=month&month=' . $today->format('n') . '&year=' . $today->format('Y'))); ?>"
    >
        Mese
    </a>
    <a
        class="calendar-switch-btn <?php echo $view === 'week' ? 'active' : ''; ?>"
        href="<?php echo h(app_url('modules/turni/calendar.php?view=week&date=' . urlencode($selectedDateIso))); ?>"
    >
        Settimana
    </a>
</div>

<?php if ($pageNotice !== ''): ?>
    <div class="calendar-flash notice">
        <?php echo h($pageNotice); ?>
    </div>
<?php endif; ?>

<?php if ($pageError !== ''): ?>
    <div class="calendar-flash error">
        <?php echo h($pageError); ?>
    </div>
<?php endif; ?>

<div class="calendar-month-banner">
    <div>
        <div class="calendar-month-title">
            <?php echo h($headerTitle); ?>
        </div>
        <div class="calendar-month-sub">
            <?php echo h($headerSub); ?>
        </div>
    </div>

    <div class="calendar-legend">
        <span class="calendar-legend-badge today">Oggi</span>
        <?php if ($view === 'month'): ?>
            <span class="calendar-legend-badge outside">Fuori mese</span>
        <?php endif; ?>
        <span class="calendar-legend-badge">Cantieri compatti</span>
        <span class="calendar-legend-badge">Trascina turno</span>
        <span class="calendar-legend-badge conflict">Conflitto live</span>
    </div>
</div>

<div class="calendar-grid-wrap">
    <div class="calendar-grid <?php echo $view === 'week' ? 'week' : ''; ?>" style="margin-bottom:12px;">
        <?php foreach ($weekdayShort as $index => $label): ?>
            <div class="calendar-weekday" title="<?php echo h($weekdayLabels[$index]); ?>">
                <?php echo h($label); ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="calendar-grid <?php echo $view === 'week' ? 'week' : ''; ?>">
        <?php
        $cursor = $rangeStart;
        while ($cursor <= $rangeEnd):
            $dateIso = $cursor->format('Y-m-d');
            $isToday = $dateIso === $todayIso;
            $isOutsideMonth = $view === 'month' && ($monthStartForView instanceof DateTimeImmutable) && ($cursor->format('m') !== $monthStartForView->format('m'));
            $dayTurni = $turniByDate[$dateIso] ?? [];
            $groupedCantieri = calendar_group_turni_by_cantiere($dayTurni);
            $newAssignmentUrl = app_url('modules/turni/new_assignment.php?data=' . urlencode($dateIso));
        ?>
            <div
                class="calendar-day <?php echo $isToday ? 'today' : ''; ?> <?php echo $isOutsideMonth ? 'outside-month' : ''; ?> <?php echo $view === 'week' ? 'week-mode' : ''; ?>"
                data-drop-date="<?php echo h($dateIso); ?>"
                ondragover="handleCalendarDayDragOver(event, this)"
                ondragenter="handleCalendarDayDragEnter(event, this)"
                ondragleave="handleCalendarDayDragLeave(event, this)"
                ondrop="handleCalendarDayDrop(event, this)"
            >
                <div class="calendar-day-header">
                    <div>
                        <div class="calendar-day-num"><?php echo h($cursor->format('j')); ?></div>
                        <div class="calendar-day-meta">
                            <?php echo h($weekdayLabels[(int)$cursor->format('N') - 1]); ?>
                            <?php if ($view === 'week'): ?>
                                • <?php echo h($cursor->format('d/m/Y')); ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="calendar-day-tools">
                        <?php if ($canManage): ?>
                            <a class="calendar-mini-btn calendar-mini-btn-primary" href="<?php echo h($newAssignmentUrl); ?>">
                                + Turno
                            </a>
                        <?php endif; ?>
                        <a class="calendar-mini-btn" href="<?php echo h(app_url('modules/turni/planning.php?data=' . urlencode($dateIso))); ?>">
                            Planning
                        </a>
                    </div>
                </div>

                <div class="calendar-day-content">
                    <?php if (empty($groupedCantieri)): ?>
                        <div class="calendar-day-empty">
                            Nessun turno in questa giornata.
                        </div>
                    <?php else: ?>
                        <?php foreach ($groupedCantieri as $groupIndex => $group): ?>
                            <?php
                            $accordionId = 'day-' . str_replace('-', '', $dateIso) . '-cant-' . $groupIndex . '-' . $view;
                            $turnCount = count($group['turni']);
                            ?>
                            <div class="calendar-cantiere" id="<?php echo h($accordionId); ?>">
                                <button
                                    type="button"
                                    class="calendar-cantiere-toggle"
                                    onclick="toggleCalendarCantiere('<?php echo h($accordionId); ?>')"
                                >
                                    <div class="calendar-cantiere-toggle-top">
                                        <div class="calendar-cantiere-title">
                                            <?php echo h($group['destinazione_nome'] ?? 'Senza destinazione'); ?>
                                        </div>

                                        <div class="calendar-cantiere-right">
                                            <span class="calendar-cantiere-count"><?php echo (int)$turnCount; ?></span>
                                            <span class="calendar-cantiere-chevron">▼</span>
                                        </div>
                                    </div>

                                    <?php
                                    $subParts = [];
                                    if (!empty($group['destinazione_comune'])) {
                                        $subParts[] = $group['destinazione_comune'];
                                    }
                                    if (!empty($group['destinazione_tipologia'])) {
                                        $subParts[] = $group['destinazione_tipologia'];
                                    }
                                    ?>
                                    <?php if (!empty($subParts)): ?>
                                        <div class="calendar-cantiere-sub">
                                            <?php echo h(implode(' • ', $subParts)); ?>
                                        </div>
                                    <?php endif; ?>
                                </button>

                                <div class="calendar-turn-list">
                                    <?php foreach ($group['turni'] as $turno): ?>
                                        <?php
                                        $editUrl = app_url('modules/turni/edit_assignment.php?id=' . (int)$turno['id']);
                                        ?>
                                        <a
                                            class="calendar-turn <?php echo $canManage ? 'draggable' : ''; ?>"
                                            href="<?php echo h($editUrl); ?>"
                                            <?php if ($canManage): ?>
                                                draggable="true"
                                                data-turn-id="<?php echo (int)$turno['id']; ?>"
                                                data-turn-date="<?php echo h((string)($turno['data'] ?? $dateIso)); ?>"
                                                data-turn-name="<?php echo h((string)($turno['operatore_nome'] ?? '')); ?>"
                                                data-turn-destination="<?php echo h((string)($group['destinazione_nome'] ?? '')); ?>"
                                                ondragstart="handleCalendarTurnDragStart(event, this)"
                                                ondragend="handleCalendarTurnDragEnd(event, this)"
                                                onclick="if(window.__calendarDragActive){ event.preventDefault(); }"
                                            <?php endif; ?>
                                        >
                                            <div class="calendar-turn-name">
                                                <?php echo h($turno['operatore_nome'] ?? ''); ?>
                                            </div>
                                            <div class="calendar-turn-meta">
                                                <?php echo h(format_time_it($turno['ora_inizio'] ?? '')); ?>
                                                →
                                                <?php echo h(format_time_it($turno['ora_fine'] ?? '')); ?>
                                            </div>

                                            <?php if (!empty($turno['is_capocantiere'])): ?>
                                                <div class="calendar-turn-badges">
                                                    <span class="calendar-turn-badge responsabile">Responsabile</span>
                                                </div>
                                            <?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php
            $cursor = $cursor->modify('+1 day');
        endwhile;
        ?>
    </div>
</div>

<div class="calendar-help">
    <?php if ($canManage): ?>
        Adesso puoi trascinare un turno da un giorno a un altro. I giorni in conflitto si evidenziano in rosso mentre trascini, e al rilascio compare un modal pulito con scelta normale o forzata.
    <?php elseif ($canViewAll || $canViewTeam || $canViewOwn): ?>
        Hai accesso in visualizzazione al calendario turni.
    <?php else: ?>
        Il tuo profilo ha visibilità limitata su questo modulo.
    <?php endif; ?>
</div>

<script>
let calendarDraggedTurnId = '';
let calendarDraggedTurnDate = '';
let calendarDraggedTurnName = '';
let calendarDraggedTurnDestination = '';
let calendarConflictDates = new Set();
let calendarCurrentTargetDate = '';
window.__calendarDragActive = false;

function resetCalendarDragData() {
    calendarDraggedTurnId = '';
    calendarDraggedTurnDate = '';
    calendarDraggedTurnName = '';
    calendarDraggedTurnDestination = '';
    calendarCurrentTargetDate = '';
}

function toggleCalendarCantiere(id) {
    const box = document.getElementById(id);
    if (!box) return;
    box.classList.toggle('open');
}

function clearCalendarConflictHints() {
    document.querySelectorAll('.calendar-day.conflict-hint').forEach(function(day) {
        day.classList.remove('conflict-hint');
    });
    document.querySelectorAll('.calendar-day.drop-target').forEach(function(day) {
        day.classList.remove('drop-target');
    });
    calendarConflictDates = new Set();
}

function applyCalendarConflictHints(dates) {
    clearCalendarConflictHints();

    if (!Array.isArray(dates)) return;

    dates.forEach(function(dateIso) {
        calendarConflictDates.add(String(dateIso));
        const day = document.querySelector('.calendar-day[data-drop-date="' + String(dateIso) + '"]');
        if (day) {
            day.classList.add('conflict-hint');
        }
    });
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function openCalendarMoveModal(options) {
    const overlay = document.createElement('div');
    overlay.className = 'calendar-modal-overlay';

    const conflictClass = options.conflict ? ' conflict' : '';
    const warningHtml = options.conflict
        ? `
            <div class="calendar-modal-warning">
                In questa data esiste già un turno in conflitto per questo dipendente.
                Puoi annullare oppure forzare lo spostamento.
            </div>
        `
        : '';

    const actionHtml = options.conflict
        ? `
            <button type="button" class="btn-cancel">Annulla</button>
            <button type="button" class="btn-force">Forza spostamento</button>
        `
        : `
            <button type="button" class="btn-cancel">Annulla</button>
            <button type="button" class="btn-confirm">Conferma spostamento</button>
        `;

    overlay.innerHTML = `
        <div class="calendar-modal${conflictClass}">
            <div class="calendar-modal-title">
                ${options.conflict ? '⚠️ Conflitto rilevato' : 'Sposta turno'}
            </div>

            <div class="calendar-modal-body">
                <div class="calendar-modal-grid">
                    <div class="calendar-modal-label">Operatore</div>
                    <div class="calendar-modal-value">${escapeHtml(options.name || '-')}</div>

                    <div class="calendar-modal-label">Cantiere</div>
                    <div class="calendar-modal-value">${escapeHtml(options.destination || '-')}</div>

                    <div class="calendar-modal-label">Da</div>
                    <div class="calendar-modal-value">${escapeHtml(options.from || '-')}</div>

                    <div class="calendar-modal-label">A</div>
                    <div class="calendar-modal-value">${escapeHtml(options.to || '-')}</div>
                </div>

                ${warningHtml}
            </div>

            <div class="calendar-modal-actions">
                ${actionHtml}
            </div>
        </div>
    `;

    document.body.appendChild(overlay);

    const closeModal = function(resetData = true) {
        overlay.remove();
        clearCalendarConflictHints();
        if (resetData) {
            resetCalendarDragData();
        }
    };

    const cancelBtn = overlay.querySelector('.btn-cancel');
    if (cancelBtn) {
        cancelBtn.onclick = function() {
            closeModal(true);
        };
    }

    const confirmBtn = overlay.querySelector('.btn-confirm');
    if (confirmBtn) {
        confirmBtn.onclick = function() {
            overlay.remove();
            submitCalendarMove(false);
        };
    }

    const forceBtn = overlay.querySelector('.btn-force');
    if (forceBtn) {
        forceBtn.onclick = function() {
            overlay.remove();
            submitCalendarMove(true);
        };
    }

    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            closeModal(true);
        }
    });
}

function submitCalendarMove(forceMode) {
    if (!calendarDraggedTurnId || !calendarCurrentTargetDate) {
        alert('Dati spostamento non validi.');
        clearCalendarConflictHints();
        resetCalendarDragData();
        return;
    }

    const form = document.createElement('form');
    form.method = 'post';
    form.action = '<?php echo h(app_url('modules/turni/move_assignment_date.php')); ?>';

    const fields = {
        id: calendarDraggedTurnId,
        new_date: calendarCurrentTargetDate,
        return_url: '<?php echo h($currentCalendarUrl); ?>',
        force: forceMode ? '1' : '0'
    };

    Object.keys(fields).forEach(function(key) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = fields[key];
        form.appendChild(input);
    });

    document.body.appendChild(form);

    clearCalendarConflictHints();
    form.submit();
}

async function loadCalendarConflictHints() {
    if (!calendarDraggedTurnId) {
        clearCalendarConflictHints();
        return;
    }

    const url =
        '<?php echo h(app_url('modules/turni/check_assignment_move_conflicts.php')); ?>'
        + '?id=' + encodeURIComponent(calendarDraggedTurnId)
        + '&range_start=' + encodeURIComponent('<?php echo h($rangeStart->format('Y-m-d')); ?>')
        + '&range_end=' + encodeURIComponent('<?php echo h($rangeEnd->format('Y-m-d')); ?>');

    try {
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });

        const data = await response.json();
        if (data && data.success && Array.isArray(data.conflict_dates)) {
            applyCalendarConflictHints(data.conflict_dates);
        } else {
            clearCalendarConflictHints();
        }
    } catch (err) {
        clearCalendarConflictHints();
    }
}

async function checkCalendarTargetConflict(targetDate) {
    const url =
        '<?php echo h(app_url('modules/turni/check_assignment_move_conflicts.php')); ?>'
        + '?id=' + encodeURIComponent(calendarDraggedTurnId)
        + '&target_date=' + encodeURIComponent(targetDate);

    try {
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });

        const data = await response.json();
        if (data && data.success) {
            return !!data.has_conflict;
        }
    } catch (err) {
    }

    return false;
}

function handleCalendarTurnDragStart(event, el) {
    calendarDraggedTurnId = el.getAttribute('data-turn-id') || '';
    calendarDraggedTurnDate = el.getAttribute('data-turn-date') || '';
    calendarDraggedTurnName = el.getAttribute('data-turn-name') || '';
    calendarDraggedTurnDestination = el.getAttribute('data-turn-destination') || '';
    calendarCurrentTargetDate = '';
    window.__calendarDragActive = true;

    if (!calendarDraggedTurnId) {
        event.preventDefault();
        window.__calendarDragActive = false;
        return;
    }

    el.classList.add('dragging');

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', calendarDraggedTurnId);
    }

    loadCalendarConflictHints();
}

function handleCalendarTurnDragEnd(event, el) {
    el.classList.remove('dragging');

    document.querySelectorAll('.calendar-day.drop-target').forEach(function(day) {
        day.classList.remove('drop-target');
    });

    setTimeout(function() {
        window.__calendarDragActive = false;
    }, 0);
}

function handleCalendarDayDragOver(event, el) {
    if (!calendarDraggedTurnId) return;
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
}

function handleCalendarDayDragEnter(event, el) {
    if (!calendarDraggedTurnId) return;
    event.preventDefault();
    el.classList.add('drop-target');
}

function handleCalendarDayDragLeave(event, el) {
    const related = event.relatedTarget;
    if (related && el.contains(related)) {
        return;
    }
    el.classList.remove('drop-target');
}

async function handleCalendarDayDrop(event, el) {
    event.preventDefault();
    el.classList.remove('drop-target');

    if (!calendarDraggedTurnId) return;

    const targetDate = el.getAttribute('data-drop-date') || '';
    if (!targetDate) return;

    if (calendarDraggedTurnDate === targetDate) {
        clearCalendarConflictHints();
        resetCalendarDragData();
        return;
    }

    calendarCurrentTargetDate = targetDate;

    let hasConflict = false;

    if (calendarConflictDates.has(String(targetDate))) {
        hasConflict = true;
    } else {
        hasConflict = await checkCalendarTargetConflict(targetDate);
    }

    openCalendarMoveModal({
        name: calendarDraggedTurnName,
        destination: calendarDraggedTurnDestination,
        from: calendarDraggedTurnDate,
        to: targetDate,
        conflict: hasConflict
    });
}
</script>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>