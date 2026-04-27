<?php
// modules/turni/planning.php

require_once __DIR__ . '/TurniRepository.php';

require_login();
require_module('assignments');

$pageTitle    = 'Planning turni';
$pageSubtitle = 'Pianificazione operativa giornaliera di Turnar';
$activeModule = 'assignments';

$canManage   = can_manage_assignments();
$canViewAll  = can_view_all_assignments();
$canViewTeam = can_view_team_assignments();
$canViewOwn  = can_view_own_assignments();

$repo = new TurniRepository(db_connect());
$db   = db_connect();

// --------------------------------------------------
// DATA
// --------------------------------------------------
$todayIso = today_date();
$selectedDate = trim((string)get('data', $todayIso));
$selectedDate = normalize_date_iso($selectedDate) ?: $todayIso;

$prevDate = date('Y-m-d', strtotime($selectedDate . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($selectedDate . ' +1 day'));

// --------------------------------------------------
// FILTRI
// --------------------------------------------------
$operatorSearch    = trim((string)get('operator_search', ''));
$destinationSearch = trim((string)get('destination_search', ''));
$onlyUnassigned    = (string)get('only_unassigned', '0') === '1';
$onlyFavorites     = (string)get('only_favorites', '0') === '1';

// --------------------------------------------------
// DATI
// --------------------------------------------------
$stats        = ['tot_operatori' => 0, 'tot_destinazioni' => 0, 'tot_turni' => 0, 'tot_assegnati' => 0, 'tot_non_assegnati' => 0];
$operatori    = [];
$destinazioni = [];
$turniGiorno  = [];
$pageError    = '';

// --------------------------------------------------
// PREFERITI DESTINAZIONI UTENTE
// --------------------------------------------------
$currentUserId = (int)(auth_id() ?? 0);
$userFavoriteDestinationIds = [];
$favoritesTable = 'user_favorite_destinations';
$favoriteColumn = null;

try {
    if ($currentUserId > 0) {
        $resCols = $db->query("SHOW COLUMNS FROM `{$favoritesTable}`");
        if ($resCols) {
            $columns = [];
            while ($col = $resCols->fetch_assoc()) {
                $columns[] = (string)($col['Field'] ?? '');
            }

            if (in_array('cantiere_id', $columns, true)) {
                $favoriteColumn = 'cantiere_id';
            } elseif (in_array('destination_id', $columns, true)) {
                $favoriteColumn = 'destination_id';
            }
        }

        if ($favoriteColumn !== null) {
            $stmtFav = $db->prepare("
                SELECT `{$favoriteColumn}` AS destination_ref
                FROM `{$favoritesTable}`
                WHERE user_id = ?
            ");

            if ($stmtFav) {
                $stmtFav->bind_param('i', $currentUserId);
                $stmtFav->execute();
                $resFav = $stmtFav->get_result();

                while ($favRow = $resFav ? $resFav->fetch_assoc() : null) {
                    if (!$favRow) {
                        break;
                    }
                    $userFavoriteDestinationIds[] = (int)$favRow['destination_ref'];
                }

                $stmtFav->close();
            }
        }
    }
} catch (Throwable $e) {
    // Silenzioso: i preferiti non devono rompere il planning
    $userFavoriteDestinationIds = [];
}

// --------------------------------------------------
// DATI REPOSITORY
// --------------------------------------------------
try {
    $stats        = $repo->getDashboardStats($selectedDate);
    $operatori    = $repo->getOperatori(['search' => '']);
    $destinazioni = $repo->getDestinazioni(['search' => '']);
    $turniGiorno  = $repo->getTurniByData($selectedDate);

    foreach ($destinazioni as &$dest) {
        $destId = (int)($dest['id'] ?? 0);
        $dest['is_favorite'] = in_array($destId, $userFavoriteDestinationIds, true);
    }
    unset($dest);

    usort($destinazioni, static function(array $a, array $b): int {
        $favA = !empty($a['is_favorite']) ? 1 : 0;
        $favB = !empty($b['is_favorite']) ? 1 : 0;

        if ($favA !== $favB) {
            return $favB <=> $favA;
        }

        $nameA = mb_strtolower(trim((string)($a['commessa'] ?? '')), 'UTF-8');
        $nameB = mb_strtolower(trim((string)($b['commessa'] ?? '')), 'UTF-8');

        return $nameA <=> $nameB;
    });
} catch (Throwable $e) {
    $pageError = 'Errore caricamento planning: ' . $e->getMessage();
}

// --------------------------------------------------
// HELPERS
// --------------------------------------------------
function planning_time_to_minutes(?string $time): int
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

// --------------------------------------------------
// CONFLITTI UI
// --------------------------------------------------
$conflictingTurnIds = [];
$conflictByOperatorId = [];
$destinationConflictIds = [];

$turniByDipendente = [];
foreach ($turniGiorno as $turno) {
    $dipId = (int)($turno['id_dipendente'] ?? 0);
    if ($dipId <= 0) {
        continue;
    }
    if (!isset($turniByDipendente[$dipId])) {
        $turniByDipendente[$dipId] = [];
    }
    $turniByDipendente[$dipId][] = $turno;
}

foreach ($turniByDipendente as $dipId => $items) {
    $countItems = count($items);
    if ($countItems < 2) {
        continue;
    }

    for ($i = 0; $i < $countItems; $i++) {
        $aStart = planning_time_to_minutes((string)($items[$i]['ora_inizio'] ?? ''));
        $aEnd   = planning_time_to_minutes((string)($items[$i]['ora_fine'] ?? ''));
        $aId    = (int)($items[$i]['id'] ?? 0);
        $aDest  = (int)($items[$i]['id_cantiere'] ?? 0);

        for ($j = $i + 1; $j < $countItems; $j++) {
            $bStart = planning_time_to_minutes((string)($items[$j]['ora_inizio'] ?? ''));
            $bEnd   = planning_time_to_minutes((string)($items[$j]['ora_fine'] ?? ''));
            $bId    = (int)($items[$j]['id'] ?? 0);
            $bDest  = (int)($items[$j]['id_cantiere'] ?? 0);

            if (!($aEnd <= $bStart || $aStart >= $bEnd)) {
                if ($aId > 0) {
                    $conflictingTurnIds[$aId] = true;
                }
                if ($bId > 0) {
                    $conflictingTurnIds[$bId] = true;
                }
                if ($aDest > 0) {
                    $destinationConflictIds[$aDest] = true;
                }
                if ($bDest > 0) {
                    $destinationConflictIds[$bDest] = true;
                }
                $conflictByOperatorId[$dipId] = true;
            }
        }
    }
}

$totalConflictingTurns = count($conflictingTurnIds);
$totalConflictOperators = count($conflictByOperatorId);
$totalConflictDestinations = count($destinationConflictIds);

// --------------------------------------------------
// MAPPE
// --------------------------------------------------
$turniPerDestinazione = [];
foreach ($turniGiorno as $turno) {
    $destId = (int)($turno['id_cantiere'] ?? 0);
    if (!isset($turniPerDestinazione[$destId])) {
        $turniPerDestinazione[$destId] = [];
    }
    $turniPerDestinazione[$destId][] = $turno;
}

// ORDINAMENTO TURNI PER DESTINAZIONE
foreach ($turniPerDestinazione as $destId => $turniDest) {
    usort($turniDest, function(array $a, array $b): int {
        $aStart = planning_time_to_minutes((string)($a['ora_inizio'] ?? ''));
        $bStart = planning_time_to_minutes((string)($b['ora_inizio'] ?? ''));
        if ($aStart !== $bStart) {
            return $aStart <=> $bStart;
        }

        $aEnd = planning_time_to_minutes((string)($a['ora_fine'] ?? ''));
        $bEnd = planning_time_to_minutes((string)($b['ora_fine'] ?? ''));
        if ($aEnd !== $bEnd) {
            return $aEnd <=> $bEnd;
        }

        $aName = mb_strtolower(trim((string)($a['operatore_nome'] ?? '')), 'UTF-8');
        $bName = mb_strtolower(trim((string)($b['operatore_nome'] ?? '')), 'UTF-8');

        return $aName <=> $bName;
    });

    $turniPerDestinazione[$destId] = $turniDest;
}

// Mappa operatori assegnati
$operatoriAssegnatiIds = [];
foreach ($turniGiorno as $turno) {
    $operatoriAssegnatiIds[(int)($turno['id_dipendente'] ?? 0)] = true;
}

$defaultStart = (string)setting('default_shift_start', '07:00');
$defaultEnd   = (string)setting('default_shift_end', '16:00');

$quickConflict = null;
if (!empty($_SESSION['turnar_quick_assign_conflict']) && is_array($_SESSION['turnar_quick_assign_conflict'])) {
    $quickConflict = $_SESSION['turnar_quick_assign_conflict'];
    unset($_SESSION['turnar_quick_assign_conflict']);
}

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
.planning-header-card{
    margin-bottom:18px;
}

.planning-top-actions{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
}

.planning-top-title{
    margin:0;
    font-size:24px;
    font-weight:900;
    color:var(--text);
}

.planning-top-text{
    margin:8px 0 0;
    color:var(--muted);
    line-height:1.6;
}

.planning-action-row{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
}

.planning-toolbar-box{
    margin-bottom:18px;
}

.planning-toolbar{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:end;
}

.planning-field{
    display:flex;
    flex-direction:column;
    gap:6px;
    min-width:180px;
}

.planning-field label{
    font-size:12px;
    color:var(--muted);
    font-weight:700;
}

.planning-inline-check{
    display:flex;
    align-items:center;
    gap:10px;
    min-height:44px;
    padding:10px 14px;
    border-radius:14px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 88%, transparent);
    color:var(--text);
    font-size:13px;
    font-weight:600;
}

.planning-inline-check input{
    width:auto;
    margin:0;
}

.planning-stats{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:14px;
    margin-bottom:18px;
}

.planning-stat{
    padding:16px;
    border-radius:18px;
    border:1px solid var(--line);
    background:var(--content-card-bg);
    box-shadow:var(--shadow);
}

.planning-stat-label{
    font-size:12px;
    color:var(--muted);
    text-transform:uppercase;
    letter-spacing:.05em;
    font-weight:700;
}

.planning-stat-value{
    margin-top:8px;
    font-size:28px;
    font-weight:900;
    line-height:1;
    color:var(--text);
}

.planning-layout{
    display:grid;
    grid-template-columns:minmax(280px, 0.95fr) minmax(420px, 1.5fr) minmax(280px, 1fr);
    gap:18px;
    align-items:start;
}

.planning-panel{
    background:var(--content-card-bg);
    border:1px solid var(--line);
    border-radius:24px;
    box-shadow:var(--shadow);
    padding:18px;
}

.planning-panel h3{
    margin:0 0 8px;
    font-size:18px;
    color:var(--text);
}

.planning-panel-sub{
    margin:0 0 14px;
    color:var(--muted);
    font-size:13px;
    line-height:1.5;
}

.planning-scroll{
    max-height:640px;
    overflow:auto;
    display:flex;
    flex-direction:column;
    gap:10px;
    padding-right:4px;
}

.operator-list-empty,
.destination-list-empty{
    display:none;
    padding:18px;
    border-radius:16px;
    border:1px dashed var(--line);
    color:var(--muted);
    background:color-mix(in srgb, var(--bg-3) 78%, transparent);
}

.operator-card{
    padding:14px;
    border-radius:16px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 82%, transparent);
    cursor:pointer;
    transition:.16s ease;
    user-select:none;
}

.operator-card:hover{
    transform:translateY(-1px);
    border-color:color-mix(in srgb, var(--primary) 32%, transparent);
    background:color-mix(in srgb, var(--bg-3) 92%, transparent);
}

.operator-card.selected{
    border-color:color-mix(in srgb, var(--primary) 48%, transparent);
    background:linear-gradient(180deg, color-mix(in srgb, var(--primary) 14%, transparent), color-mix(in srgb, var(--bg-3) 88%, transparent));
    box-shadow:0 8px 18px rgba(0,0,0,.12);
}

.operator-card.multi-selected{
    border-color:color-mix(in srgb, var(--primary-2) 52%, transparent);
    background:linear-gradient(180deg, color-mix(in srgb, var(--primary-2) 14%, transparent), color-mix(in srgb, var(--bg-3) 88%, transparent));
    box-shadow:0 8px 18px rgba(0,0,0,.12);
}

.operator-card.assigned{
    border-color:rgba(52,211,153,.26);
    background:linear-gradient(180deg, rgba(52,211,153,.08), color-mix(in srgb, var(--bg-3) 88%, transparent));
}

.operator-card.selected.assigned{
    border-color:color-mix(in srgb, var(--primary) 56%, transparent);
    background:linear-gradient(180deg, color-mix(in srgb, var(--primary) 16%, transparent), rgba(52,211,153,.06));
}

.operator-card.multi-selected.assigned{
    border-color:color-mix(in srgb, var(--primary-2) 56%, transparent);
    background:linear-gradient(180deg, color-mix(in srgb, var(--primary-2) 16%, transparent), rgba(52,211,153,.06));
}

.operator-card.dragging{
    opacity:.55;
    transform:scale(.985);
    border-color:color-mix(in srgb, var(--primary) 55%, transparent);
    box-shadow:0 10px 24px rgba(0,0,0,.22);
}

.operator-card.drag-source-selected{
    border-color:color-mix(in srgb, var(--primary) 58%, transparent);
    background:linear-gradient(180deg, color-mix(in srgb, var(--primary) 20%, transparent), color-mix(in srgb, var(--bg-3) 90%, transparent));
    box-shadow:0 10px 24px rgba(0,0,0,.18);
}

.operator-card.multi-selected.drag-source-selected{
    border-color:color-mix(in srgb, var(--primary-2) 58%, transparent);
    background:linear-gradient(180deg, color-mix(in srgb, var(--primary-2) 20%, transparent), color-mix(in srgb, var(--bg-3) 90%, transparent));
}

.operator-name{
    font-size:15px;
    font-weight:800;
    margin-bottom:6px;
    color:var(--text);
}

.operator-meta{
    font-size:13px;
    color:var(--text);
    line-height:1.55;
}

.badge-row{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    margin-top:10px;
}

.mini-badge{
    display:inline-flex;
    align-items:center;
    padding:4px 10px;
    border-radius:999px;
    font-size:11px;
    font-weight:700;
    line-height:1;
    background:color-mix(in srgb, var(--primary) 14%, transparent);
    color:color-mix(in srgb, var(--primary) 72%, var(--text));
    border:1px solid color-mix(in srgb, var(--primary) 24%, transparent);
}

.mini-badge-success{
    background:rgba(52,211,153,.14);
    color:#059669;
    border-color:rgba(52,211,153,.24);
}

.mini-badge-warning{
    background:rgba(251,191,36,.14);
    color:#b45309;
    border-color:rgba(251,191,36,.24);
}

.mini-badge-responsabile{
    background:rgba(244,114,182,.14);
    color:#be185d;
    border-color:rgba(244,114,182,.26);
}

.mini-badge-multi{
    background:color-mix(in srgb, var(--primary-2) 14%, transparent);
    color:color-mix(in srgb, var(--primary-2) 72%, var(--text));
    border-color:color-mix(in srgb, var(--primary-2) 24%, transparent);
}

.mini-badge-danger{
    background:rgba(248,113,113,.14);
    color:#dc2626;
    border-color:rgba(248,113,113,.26);
}

.mini-badge-favorite{
    background:rgba(251,191,36,.15);
    color:#b45309;
    border-color:rgba(251,191,36,.26);
}

.destination-card{
    padding:14px;
    border-radius:18px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 82%, transparent);
    transition:.16s ease;
}

.destination-card.favorite{
    border-color:rgba(251,191,36,.30);
    box-shadow:
        0 10px 22px rgba(0,0,0,.08),
        0 0 0 1px rgba(251,191,36,.08) inset;
}

.destination-card.quick-target{
    cursor:pointer;
}

.destination-card.quick-target:hover{
    transform:translateY(-1px);
    border-color:color-mix(in srgb, var(--primary) 32%, transparent);
    background:color-mix(in srgb, var(--bg-3) 92%, transparent);
}

.destination-card.quick-target-active{
    border-color:color-mix(in srgb, var(--primary) 42%, transparent);
    box-shadow:0 8px 18px rgba(0,0,0,.12);
}

.destination-card.has-conflict{
    border-color:rgba(248,113,113,.34);
    background:linear-gradient(180deg, rgba(248,113,113,.08), color-mix(in srgb, var(--bg-3) 84%, transparent));
    box-shadow:0 10px 22px rgba(0,0,0,.10);
}

.destination-card.favorite.has-conflict{
    box-shadow:
        0 10px 22px rgba(0,0,0,.10),
        0 0 0 1px rgba(251,191,36,.08) inset;
}

.destination-card.drag-over{
    border-color:color-mix(in srgb, var(--primary) 56%, transparent);
    background:linear-gradient(180deg, color-mix(in srgb, var(--primary) 12%, transparent), color-mix(in srgb, var(--bg-3) 88%, transparent));
    box-shadow:0 12px 28px rgba(0,0,0,.16);
    transform:translateY(-1px);
}

.destination-dropzone.drag-over{
    border-color:color-mix(in srgb, var(--primary) 52%, transparent);
    background:color-mix(in srgb, var(--primary) 8%, transparent);
    box-shadow:inset 0 0 0 1px color-mix(in srgb, var(--primary) 12%, transparent);
}

.destination-title{
    font-size:16px;
    font-weight:800;
    margin-bottom:6px;
    color:var(--text);
}

.destination-meta{
    font-size:13px;
    color:var(--text);
    line-height:1.55;
    margin-bottom:10px;
}

.destination-summary{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    margin:0 0 10px;
}

.destination-dropzone{
    min-height:64px;
    border-radius:14px;
    border:1px dashed var(--line);
    background:color-mix(in srgb, var(--bg-3) 76%, transparent);
    padding:10px;
    display:flex;
    flex-direction:column;
    gap:8px;
    transition:.16s ease;
}

.destination-empty{
    color:var(--muted);
    font-size:12px;
}

.turn-row{
    display:flex;
    gap:8px;
    align-items:stretch;
}

.turn-chip-link{
    display:block;
    text-decoration:none;
    color:inherit;
    flex:1;
}

.turn-chip{
    padding:10px 12px;
    border-radius:14px;
    border:1px solid color-mix(in srgb, var(--primary) 18%, var(--line));
    background:linear-gradient(180deg, color-mix(in srgb, var(--primary) 10%, transparent), color-mix(in srgb, var(--bg-3) 84%, transparent));
    transition:.16s ease;
}

.turn-chip.has-conflict{
    border-color:rgba(248,113,113,.34);
    background:linear-gradient(180deg, rgba(248,113,113,.14), color-mix(in srgb, var(--bg-3) 84%, transparent));
    box-shadow:0 6px 14px rgba(0,0,0,.10);
}

.turn-chip-link:hover .turn-chip{
    transform:translateY(-1px);
    border-color:color-mix(in srgb, var(--primary) 38%, transparent);
    box-shadow:0 8px 18px rgba(0,0,0,.12);
    background:linear-gradient(180deg, color-mix(in srgb, var(--primary) 14%, transparent), color-mix(in srgb, var(--bg-3) 88%, transparent));
}

.turn-chip-link:hover .turn-chip.has-conflict{
    border-color:rgba(248,113,113,.48);
    background:linear-gradient(180deg, rgba(248,113,113,.18), color-mix(in srgb, var(--bg-3) 88%, transparent));
}

.turn-chip.draggable-turn{
    cursor:grab;
}

.turn-chip.dragging{
    opacity:.55;
    transform:scale(.985);
    border-color:color-mix(in srgb, var(--primary) 55%, transparent);
    box-shadow:0 10px 24px rgba(0,0,0,.18);
}

.turn-chip-name{
    font-size:13px;
    font-weight:800;
    margin-bottom:4px;
    color:var(--text);
}

.turn-chip-meta{
    font-size:12px;
    color:var(--text);
    line-height:1.45;
}

.turn-chip-badges{
    display:flex;
    gap:6px;
    flex-wrap:wrap;
    margin-top:6px;
}

.turn-delete-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:42px;
    padding:0 12px;
    border-radius:14px;
    border:1px solid rgba(248,113,113,.24);
    background:rgba(248,113,113,.12);
    color:#dc2626;
    text-decoration:none;
    font-size:18px;
    font-weight:800;
    transition:.16s ease;
}

.turn-delete-btn:hover{
    transform:translateY(-1px);
    background:rgba(248,113,113,.18);
    border-color:rgba(248,113,113,.36);
}

.right-turn-row{
    display:flex;
    gap:8px;
    align-items:stretch;
    margin-bottom:10px;
}

.right-turn-row:last-child{
    margin-bottom:0;
}

.right-turn-link{
    display:block;
    text-decoration:none;
    color:inherit;
    flex:1;
}

.right-turn-card{
    padding:14px;
    border-radius:16px;
    border:1px solid var(--line);
    background:linear-gradient(180deg, color-mix(in srgb, var(--primary) 6%, transparent), color-mix(in srgb, var(--bg-3) 82%, transparent));
    transition:.16s ease;
}

.right-turn-card.has-conflict{
    border-color:rgba(248,113,113,.34);
    background:linear-gradient(180deg, rgba(248,113,113,.12), color-mix(in srgb, var(--bg-3) 84%, transparent));
    box-shadow:0 8px 18px rgba(0,0,0,.10);
}

.right-turn-link:hover .right-turn-card{
    transform:translateY(-1px);
    border-color:color-mix(in srgb, var(--primary) 34%, transparent);
    box-shadow:0 8px 18px rgba(0,0,0,.12);
    background:linear-gradient(180deg, color-mix(in srgb, var(--primary) 10%, transparent), color-mix(in srgb, var(--bg-3) 88%, transparent));
}

.right-turn-link:hover .right-turn-card.has-conflict{
    border-color:rgba(248,113,113,.48);
    background:linear-gradient(180deg, rgba(248,113,113,.16), color-mix(in srgb, var(--bg-3) 88%, transparent));
}

.right-turn-name{
    font-size:15px;
    font-weight:800;
    margin-bottom:6px;
    color:var(--text);
}

.right-turn-meta{
    font-size:13px;
    color:var(--text);
    line-height:1.55;
}

.right-turn-badges{
    display:flex;
    gap:6px;
    flex-wrap:wrap;
    margin-top:8px;
}

.helper-note{
    margin-top:18px;
    padding:14px 16px;
    border-radius:18px;
    border:1px solid rgba(251,191,36,.20);
    background:rgba(251,191,36,.08);
    color:#b45309;
    line-height:1.6;
    font-size:13px;
}

.quick-help{
    margin-bottom:14px;
    padding:12px 14px;
    border-radius:16px;
    border:1px solid color-mix(in srgb, var(--primary) 18%, transparent);
    background:color-mix(in srgb, var(--primary) 8%, transparent);
    color:var(--text);
    font-size:13px;
    line-height:1.6;
}

.quick-help strong{
    color:var(--text);
}

.conflict-banner{
    margin-bottom:18px;
    padding:14px 16px;
    border-radius:18px;
    border:1px solid rgba(248,113,113,.24);
    background:rgba(248,113,113,.10);
    color:#b91c1c;
    line-height:1.7;
}

.error-box{
    margin-bottom:18px;
    padding:14px 16px;
    border-radius:18px;
    border:1px solid rgba(248,113,113,.24);
    background:rgba(248,113,113,.10);
    color:#b91c1c;
    line-height:1.6;
    white-space:pre-line;
}

.quick-overlay{
    position:fixed;
    inset:0;
    background:rgba(3,7,20,.58);
    backdrop-filter:blur(4px);
    display:none;
    align-items:center;
    justify-content:center;
    z-index:999;
    padding:20px;
}

.quick-overlay.visible{
    display:flex;
}

.quick-modal{
    width:min(760px, 100%);
    background:var(--content-card-bg);
    border:1px solid var(--line);
    border-radius:24px;
    box-shadow:0 24px 50px rgba(0,0,0,.26);
    padding:20px;
    max-height:90vh;
    overflow:auto;
}

.quick-modal h3{
    margin:0 0 8px;
    font-size:20px;
    color:var(--text);
}

.quick-modal-sub{
    margin:0 0 16px;
    color:var(--muted);
    line-height:1.6;
    font-size:14px;
    white-space:pre-line;
}

.quick-form-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(140px,1fr));
    gap:14px;
}

.quick-field{
    display:flex;
    flex-direction:column;
    gap:6px;
}

.quick-field.full{
    grid-column:1 / -1;
}

.quick-field label{
    font-size:12px;
    color:var(--muted);
    font-weight:700;
}

.quick-check{
    grid-column:1 / -1;
    display:flex;
    align-items:center;
    gap:10px;
    padding:12px 14px;
    border-radius:14px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 82%, transparent);
    color:var(--text);
}

.quick-check input{
    width:auto;
}

.quick-actions{
    margin-top:16px;
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

.quick-multi-table-wrap{
    grid-column:1 / -1;
    border:1px solid var(--line);
    border-radius:18px;
    background:color-mix(in srgb, var(--bg-3) 82%, transparent);
    overflow:hidden;
}

.quick-multi-table{
    width:100%;
    border-collapse:collapse;
}

.quick-multi-table th,
.quick-multi-table td{
    padding:12px 10px;
    border-bottom:1px solid var(--line);
    text-align:left;
    vertical-align:middle;
    font-size:13px;
}

.quick-multi-table th{
    color:var(--muted);
    background:color-mix(in srgb, var(--bg-2) 55%, transparent);
    font-weight:800;
}

.quick-multi-table tr:last-child td{
    border-bottom:none;
}

.quick-multi-table input[type="time"]{
    width:100%;
    min-width:110px;
    padding:9px 10px;
    border-radius:12px;
}

.quick-multi-check{
    display:inline-flex;
    align-items:center;
    gap:8px;
    font-size:12px;
    color:var(--text);
}

.quick-multi-check input{
    width:auto;
}

.quick-multi-bulk{
    grid-column:1 / -1;
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    align-items:end;
    padding:14px;
    border-radius:16px;
    border:1px solid color-mix(in srgb, var(--primary) 16%, transparent);
    background:color-mix(in srgb, var(--primary) 7%, transparent);
}

.quick-multi-bulk-title{
    width:100%;
    font-size:13px;
    color:var(--text);
    font-weight:800;
    margin-bottom:2px;
}

.quick-multi-bulk .quick-field{
    min-width:140px;
    flex:1;
}

.quick-multi-apply-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:11px 14px;
    border-radius:14px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 88%, transparent);
    color:var(--text);
    font-size:13px;
    font-weight:700;
    cursor:pointer;
    min-height:42px;
    transition:transform .18s ease, border-color .18s ease, background .18s ease;
}

.quick-multi-apply-btn:hover{
    transform:translateY(-1px);
    border-color:color-mix(in srgb, var(--primary) 35%, transparent);
}

@media (max-width: 1280px){
    .planning-layout{
        grid-template-columns:1fr;
    }

    .planning-scroll{
        max-height:none;
    }
}

@media (max-width: 900px){
    .planning-top-actions{
        align-items:stretch;
    }

    .planning-action-row{
        width:100%;
    }

    .planning-toolbar{
        align-items:stretch;
    }

    .planning-field{
        width:100%;
        min-width:unset;
    }
}

@media (max-width: 640px){
    .quick-form-grid{
        grid-template-columns:1fr;
    }

    .quick-multi-table th,
    .quick-multi-table td{
        font-size:12px;
        padding:10px 8px;
    }
}
</style>

<div class="card planning-header-card">
    <div class="planning-top-actions">
        <div>
            <h2 class="planning-top-title">Planning operativo</h2>
            <p class="planning-top-text">
                Base operativa del tabellone Turnar.
            </p>
        </div>

        <div class="planning-action-row">
            <a class="btn btn-ghost" href="<?php echo h(app_url('modules/turni/planning.php?data=' . urlencode($prevDate))); ?>">← Giorno prima</a>
            <a class="btn btn-ghost" href="<?php echo h(app_url('modules/turni/planning.php?data=' . urlencode($todayIso))); ?>">Oggi</a>
            <a class="btn btn-ghost" href="<?php echo h(app_url('modules/turni/planning.php?data=' . urlencode($nextDate))); ?>">Giorno dopo →</a>

            <?php if ($canManage): ?>
                <a class="btn btn-primary" href="<?php echo h(app_url('modules/turni/new_assignment.php?data=' . urlencode($selectedDate))); ?>">
                    Nuova assegnazione
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($pageError !== ''): ?>
    <div class="alert-error mb-4">
        <?php echo h($pageError); ?>
    </div>
<?php endif; ?>

<?php if ($totalConflictingTurns > 0): ?>
    <div class="conflict-banner">
        <strong>Attenzione:</strong> nella data <strong><?php echo h(format_date_it($selectedDate)); ?></strong> sono presenti conflitti orari.<br>
        Turni in conflitto: <strong><?php echo (int)$totalConflictingTurns; ?></strong> ·
        Operatori coinvolti: <strong><?php echo (int)$totalConflictOperators; ?></strong> ·
        Destinazioni coinvolte: <strong><?php echo (int)$totalConflictDestinations; ?></strong>
    </div>
<?php endif; ?>

<div class="card planning-toolbar-box">
    <form method="get" class="planning-toolbar" id="planningFiltersForm">
        <div class="planning-field">
            <label for="data">Data planning</label>
            <input type="date" id="data" name="data" value="<?php echo h($selectedDate); ?>">
        </div>

        <div class="planning-field">
            <label for="operator_search">Filtro operatori (live)</label>
            <input type="text" id="operator_search" name="operator_search" value="<?php echo h($operatorSearch); ?>" placeholder="Nome, cognome, ruolo...">
        </div>

        <div class="planning-field" style="min-width:220px;">
            <label>&nbsp;</label>
            <label class="planning-inline-check">
                <input type="checkbox" id="only_unassigned" name="only_unassigned" value="1" <?php echo $onlyUnassigned ? 'checked' : ''; ?>>
                <span>Solo non assegnati</span>
            </label>
        </div>

        <div class="planning-field">
            <label for="destination_search">Filtro destinazioni (live)</label>
            <input type="text" id="destination_search" name="destination_search" value="<?php echo h($destinationSearch); ?>" placeholder="Commessa, comune, tipologia...">
        </div>

        <div class="planning-field" style="min-width:220px;">
            <label>&nbsp;</label>
            <label class="planning-inline-check">
                <input type="checkbox" id="only_favorites" name="only_favorites" value="1" <?php echo $onlyFavorites ? 'checked' : ''; ?>>
                <span>Solo preferite</span>
            </label>
        </div>

        <div class="planning-field" style="min-width:140px;">
            <label>&nbsp;</label>
            <button type="button" class="btn btn-ghost" onclick="clearPlanningFilters()">Pulisci filtri</button>
        </div>

        <div class="planning-field" style="min-width:140px;">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-primary">Aggiorna</button>
        </div>
    </form>
</div>

<div class="planning-stats">
    <div class="planning-stat">
        <div class="planning-stat-label">Operatori</div>
        <div class="planning-stat-value"><?php echo (int)($stats['tot_operatori'] ?? 0); ?></div>
    </div>
    <div class="planning-stat">
        <div class="planning-stat-label">Destinazioni attive</div>
        <div class="planning-stat-value"><?php echo (int)($stats['tot_destinazioni'] ?? 0); ?></div>
    </div>
    <div class="planning-stat">
        <div class="planning-stat-label">Turni in data</div>
        <div class="planning-stat-value"><?php echo (int)($stats['tot_turni'] ?? 0); ?></div>
    </div>
    <div class="planning-stat">
        <div class="planning-stat-label">Assegnati</div>
        <div class="planning-stat-value"><?php echo (int)($stats['tot_assegnati'] ?? 0); ?></div>
    </div>
    <div class="planning-stat">
        <div class="planning-stat-label">Non assegnati</div>
        <div class="planning-stat-value"><?php echo (int)($stats['tot_non_assegnati'] ?? 0); ?></div>
    </div>
</div>

<div class="planning-layout">

    <section class="planning-panel">
        <h3>Operatori</h3>
        <p class="planning-panel-sub">Elenco operatori.</p>

        <?php if ($canManage): ?>
            <div class="quick-help">
                <strong>Assegnazione rapida:</strong> click normale = selezione singola. <strong>CTRL + click</strong> = selezione multipla. Puoi anche trascinare gli operatori selezionati direttamente sulla destinazione.
            </div>
        <?php endif; ?>

        <div class="planning-scroll" id="operatoriList">
            <?php if (empty($operatori)): ?>
                <div class="empty-state">Nessun operatore trovato.</div>
            <?php else: ?>
                <?php foreach ($operatori as $op): ?>
                    <?php
                    $opId = (int)($op['id'] ?? 0);
                    $isAssigned = isset($operatoriAssegnatiIds[$opId]);
                    $hasOperatorConflict = isset($conflictByOperatorId[$opId]);

                    $searchParts = [
                        (string)($op['display_name'] ?? ''),
                        (string)($op['nome'] ?? ''),
                        (string)($op['cognome'] ?? ''),
                        (string)($op['email'] ?? ''),
                        (string)($op['telefono'] ?? ''),
                        (string)($op['livello'] ?? ''),
                        (string)($op['tipologia'] ?? ''),
                    ];
                    $operatorSearchText = mb_strtolower(trim(implode(' ', array_filter($searchParts))), 'UTF-8');
                    ?>
                    <div
                        class="operator-card <?php echo $isAssigned ? 'assigned' : ''; ?>"
                        data-search-text="<?php echo h($operatorSearchText); ?>"
                        data-is-assigned="<?php echo $isAssigned ? '1' : '0'; ?>"
                        <?php if ($canManage): ?>
                            data-operator-id="<?php echo (int)$opId; ?>"
                            data-operator-name="<?php echo h($op['display_name'] ?? ''); ?>"
                            draggable="true"
                            onclick="selectOperator(event, this)"
                            ondragstart="handleOperatorDragStart(event, this)"
                            ondragend="handleOperatorDragEnd(event, this)"
                        <?php endif; ?>
                    >
                        <div class="operator-name"><?php echo h($op['display_name'] ?? ''); ?></div>
                        <div class="operator-meta">
                            <?php if (!empty($op['email'])): ?>Email: <?php echo h($op['email']); ?><br><?php endif; ?>
                            <?php if (!empty($op['telefono'])): ?>Tel: <?php echo h($op['telefono']); ?><br><?php endif; ?>
                            <?php if (!empty($op['livello'])): ?>Livello: <?php echo h($op['livello']); ?><br><?php endif; ?>
                            <?php if (!empty($op['tipologia'])): ?>Tipologia: <?php echo h($op['tipologia']); ?><?php endif; ?>
                        </div>
                        <div class="badge-row">
                            <?php if (!empty($op['tipologia'])): ?><span class="mini-badge"><?php echo h($op['tipologia']); ?></span><?php endif; ?>
                            <?php if (!empty($op['preposto'])): ?><span class="mini-badge">Preposto</span><?php endif; ?>
                            <?php if (!empty($op['capo_cantiere'])): ?><span class="mini-badge mini-badge-responsabile">Responsabile</span><?php endif; ?>
                            <?php if ($isAssigned): ?>
                                <span class="mini-badge mini-badge-success">Assegnato</span>
                            <?php else: ?>
                                <span class="mini-badge mini-badge-warning">Libero</span>
                            <?php endif; ?>
                            <?php if ($hasOperatorConflict): ?>
                                <span class="mini-badge mini-badge-danger">Conflitto</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="operator-list-empty" id="operatoriListEmpty">
                    Nessun operatore corrisponde ai filtri impostati.
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="planning-panel">
        <h3>Destinazioni operative</h3>
        <p class="planning-panel-sub">Vista centrale del planning.</p>

        <div class="planning-scroll" id="destinazioniList">
            <?php if (empty($destinazioni)): ?>
                <div class="empty-state">Nessuna destinazione trovata.</div>
            <?php else: ?>
                <?php foreach ($destinazioni as $dest): ?>
                    <?php
                    $destId = (int)($dest['id'] ?? 0);
                    $turniDest = $turniPerDestinazione[$destId] ?? [];
                    $destHasConflict = isset($destinationConflictIds[$destId]);
                    $destIsFavorite = !empty($dest['is_favorite']);

                    $destinationSearchParts = [
                        (string)($dest['commessa'] ?? ''),
                        (string)($dest['comune'] ?? ''),
                        (string)($dest['tipologia'] ?? ''),
                        (string)($dest['data_inizio'] ?? ''),
                        (!isset($dest['attivo']) || (int)$dest['attivo'] === 1) ? 'attivo' : 'disattivo',
                        $destIsFavorite ? 'preferita preferito favorite' : '',
                    ];
                    $destinationSearchText = mb_strtolower(trim(implode(' ', array_filter($destinationSearchParts))), 'UTF-8');

                    $destAssignedCount = count($turniDest);
                    $destResponsabiliCount = 0;
                    $destConflictsCount = 0;

                    foreach ($turniDest as $turnoCountItem) {
                        if (!empty($turnoCountItem['is_capocantiere'])) {
                            $destResponsabiliCount++;
                        }
                        $countTurnoId = (int)($turnoCountItem['id'] ?? 0);
                        if ($countTurnoId > 0 && isset($conflictingTurnIds[$countTurnoId])) {
                            $destConflictsCount++;
                        }
                    }
                    ?>
                    <div
                        class="destination-card <?php echo $canManage ? 'quick-target' : ''; ?> <?php echo $destHasConflict ? 'has-conflict' : ''; ?> <?php echo $destIsFavorite ? 'favorite' : ''; ?>"
                        data-search-text="<?php echo h($destinationSearchText); ?>"
                        data-is-favorite="<?php echo $destIsFavorite ? '1' : '0'; ?>"
                        <?php if ($canManage): ?>
                            data-destination-id="<?php echo (int)$destId; ?>"
                            data-destination-name="<?php echo h((string)($dest['commessa'] ?? '')); ?>"
                            onclick="openQuickAssignModal(this)"
                            ondragover="handleDestinationDragOver(event, this)"
                            ondragenter="handleDestinationDragEnter(event, this)"
                            ondragleave="handleDestinationDragLeave(event, this)"
                            ondrop="handleDestinationDrop(event, this)"
                        <?php endif; ?>
                    >
                        <div class="destination-title"><?php echo h((string)($dest['commessa'] ?? '')); ?></div>
                        <div class="destination-meta">
                            <?php if (!empty($dest['comune'])): ?>Comune: <?php echo h($dest['comune']); ?><br><?php endif; ?>
                            <?php if (!empty($dest['tipologia'])): ?>Tipologia: <?php echo h($dest['tipologia']); ?><br><?php endif; ?>
                            <?php if (!empty($dest['data_inizio'])): ?>Inizio: <?php echo h(format_date_it($dest['data_inizio'])); ?><br><?php endif; ?>
                            Stato: <?php echo !isset($dest['attivo']) || (int)$dest['attivo'] === 1 ? 'Attivo' : 'Disattivo'; ?>
                        </div>

                        <div class="destination-summary">
                            <?php if ($destIsFavorite): ?>
                                <span class="mini-badge mini-badge-favorite">Preferita</span>
                            <?php endif; ?>

                            <span class="mini-badge"><?php echo (int)$destAssignedCount; ?> assegnati</span>

                            <?php if ($destResponsabiliCount > 0): ?>
                                <span class="mini-badge mini-badge-responsabile"><?php echo (int)$destResponsabiliCount; ?> responsabile<?php echo $destResponsabiliCount === 1 ? '' : 'i'; ?></span>
                            <?php endif; ?>

                            <?php if ($destConflictsCount > 0): ?>
                                <span class="mini-badge mini-badge-danger"><?php echo (int)$destConflictsCount; ?> conflitto<?php echo $destConflictsCount === 1 ? '' : 'i'; ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="destination-dropzone">
                            <?php if (empty($turniDest)): ?>
                                <div class="destination-empty">Nessun operatore assegnato in questa data.</div>
                            <?php else: ?>
                                <?php foreach ($turniDest as $turno): ?>
                                    <?php
                                    $editUrl = app_url('modules/turni/edit_assignment.php?id=' . (int)$turno['id']);
                                    $deleteUrl = app_url('modules/turni/delete_assignment.php?id=' . (int)$turno['id'] . '&data=' . urlencode($selectedDate));

                                    $turnoId = (int)($turno['id'] ?? 0);
                                    $turnoDipId = (int)($turno['id_dipendente'] ?? 0);
                                    $turnoDipNome = (string)($turno['operatore_nome'] ?? '');
                                    $turnoDestNome = (string)($turno['destinazione_nome'] ?? ($dest['commessa'] ?? ''));
                                    $turnoOraInizio = substr((string)($turno['ora_inizio'] ?? ''), 0, 5);
                                    $turnoOraFine   = substr((string)($turno['ora_fine'] ?? ''), 0, 5);
                                    $turnoResponsabile = !empty($turno['is_capocantiere']) ? 1 : 0;
                                    $turnoHasConflict = isset($conflictingTurnIds[$turnoId]);
                                    ?>
                                    <div class="turn-row">
                                        <a class="turn-chip-link" href="<?php echo h($editUrl); ?>" onclick="if(window.__turnDragActive){ event.preventDefault(); }">
                                            <div
                                                class="turn-chip <?php echo $canManage ? 'draggable-turn' : ''; ?> <?php echo $turnoHasConflict ? 'has-conflict' : ''; ?>"
                                                <?php if ($canManage): ?>
                                                    draggable="true"
                                                    data-turn-id="<?php echo $turnoId; ?>"
                                                    data-turn-operator-id="<?php echo $turnoDipId; ?>"
                                                    data-turn-operator-name="<?php echo h($turnoDipNome); ?>"
                                                    data-turn-destination-id="<?php echo $destId; ?>"
                                                    data-turn-destination-name="<?php echo h($turnoDestNome); ?>"
                                                    data-turn-start="<?php echo h($turnoOraInizio); ?>"
                                                    data-turn-end="<?php echo h($turnoOraFine); ?>"
                                                    data-turn-responsabile="<?php echo $turnoResponsabile; ?>"
                                                    ondragstart="handleAssignedTurnDragStart(event, this)"
                                                    ondragend="handleAssignedTurnDragEnd(event, this)"
                                                <?php endif; ?>
                                            >
                                                <div class="turn-chip-name"><?php echo h($turno['operatore_nome'] ?? ''); ?></div>
                                                <div class="turn-chip-meta">
                                                    Orario:
                                                    <?php echo h(format_time_it($turno['ora_inizio'] ?? '')); ?>
                                                    →
                                                    <?php echo h(format_time_it($turno['ora_fine'] ?? '')); ?>
                                                </div>
                                                <div class="turn-chip-badges">
                                                    <?php if (!empty($turno['is_capocantiere'])): ?>
                                                        <span class="mini-badge mini-badge-responsabile">Responsabile</span>
                                                    <?php endif; ?>
                                                    <?php if ($turnoHasConflict): ?>
                                                        <span class="mini-badge mini-badge-danger">Conflitto</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </a>

                                        <?php if ($canManage): ?>
                                            <a
                                                class="turn-delete-btn"
                                                href="<?php echo h($deleteUrl); ?>"
                                                onclick="event.stopPropagation(); return confirm('Vuoi davvero cancellare questo turno?');"
                                                title="Cancella turno"
                                            >
                                                ×
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="destination-list-empty" id="destinazioniListEmpty">
                    Nessuna destinazione corrisponde ai filtri impostati.
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="planning-panel">
        <h3>Riepilogo giornata</h3>
        <p class="planning-panel-sub">
            Data: <strong><?php echo h(format_date_it($selectedDate)); ?></strong>
        </p>

        <div>
            <?php if (empty($turniGiorno)): ?>
                <div class="empty-state">Nessun turno registrato in questa data.</div>
            <?php else: ?>
                <?php foreach ($turniGiorno as $turno): ?>
                    <?php
                    $editUrl = app_url('modules/turni/edit_assignment.php?id=' . (int)$turno['id']);
                    $deleteUrl = app_url('modules/turni/delete_assignment.php?id=' . (int)$turno['id'] . '&data=' . urlencode($selectedDate));
                    $turnoId = (int)($turno['id'] ?? 0);
                    $turnoHasConflict = isset($conflictingTurnIds[$turnoId]);
                    ?>
                    <div class="right-turn-row">
                        <a class="right-turn-link" href="<?php echo h($editUrl); ?>">
                            <div class="right-turn-card <?php echo $turnoHasConflict ? 'has-conflict' : ''; ?>">
                                <div class="right-turn-name"><?php echo h($turno['operatore_nome'] ?? ''); ?></div>
                                <div class="right-turn-meta">
                                    Destinazione: <strong><?php echo h((string)($turno['destinazione_nome'] ?? '—')); ?></strong><br>
                                    Orario:
                                    <?php echo h(format_time_it($turno['ora_inizio'] ?? '')); ?>
                                    →
                                    <?php echo h(format_time_it($turno['ora_fine'] ?? '')); ?>
                                </div>
                                <div class="right-turn-badges">
                                    <?php if (!empty($turno['is_capocantiere'])): ?>
                                        <span class="mini-badge mini-badge-responsabile">Responsabile</span>
                                    <?php endif; ?>
                                    <?php if ($turnoHasConflict): ?>
                                        <span class="mini-badge mini-badge-danger">Conflitto</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>

                        <?php if ($canManage): ?>
                            <a
                                class="turn-delete-btn"
                                href="<?php echo h($deleteUrl); ?>"
                                onclick="return confirm('Vuoi davvero cancellare questo turno?');"
                                title="Cancella turno"
                            >
                                ×
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="helper-note">
            <?php if ($canManage): ?>
                Hai i permessi per la gestione del planning.
            <?php elseif ($canViewAll || $canViewTeam || $canViewOwn): ?>
                Sei in sola visualizzazione in base ai tuoi permessi.
            <?php else: ?>
                Il tuo profilo ha visibilità limitata su questo modulo.
            <?php endif; ?>
        </div>
    </section>

</div>

<?php if ($canManage): ?>
<form id="quickAssignForm" method="post" action="<?php echo h(app_url('modules/turni/quick_assign.php')); ?>" style="display:none;">
    <input type="hidden" name="data" value="<?php echo h($selectedDate); ?>">
    <input type="hidden" name="id_dipendente" id="quickAssignDipendente" value="">
    <input type="hidden" name="id_cantiere" id="quickAssignCantiere" value="">
    <input type="hidden" name="id_turno" id="quickAssignTurnoId" value="">
    <input type="hidden" name="move_mode" id="quickAssignMoveMode" value="0">
    <input type="hidden" name="multi_mode" id="quickAssignMultiMode" value="0">
    <input type="hidden" name="partial_save" id="quickAssignPartialSave" value="0">
    <input type="hidden" name="id_dipendenti_json" id="quickAssignDipendentiJson" value="">
    <input type="hidden" name="assignments_json" id="quickAssignAssignmentsJson" value="">
    <input type="hidden" name="ora_inizio" id="quickAssignOraInizio" value="<?php echo h($defaultStart); ?>">
    <input type="hidden" name="ora_fine" id="quickAssignOraFine" value="<?php echo h($defaultEnd); ?>">
    <input type="hidden" name="is_responsabile" id="quickAssignResponsabile" value="0">
    <input type="hidden" name="force_save" id="quickAssignForceSave" value="0">
</form>

<div id="quickAssignOverlay" class="quick-overlay">
    <div class="quick-modal">
        <h3>Assegnazione rapida</h3>
        <p class="quick-modal-sub" id="quickModalText">
            Seleziona orario e conferma l’assegnazione.
        </p>

        <div class="quick-form-grid">
            <div class="quick-field full">
                <label>Operatore / Operatori selezionati</label>
                <input type="text" id="quickPreviewOperatore" value="" readonly>
            </div>

            <div class="quick-field full" id="quickMultiOperatorsWrap" style="display:none;">
                <label>Elenco operatori</label>
                <textarea id="quickPreviewOperatoriLista" rows="4" readonly></textarea>
            </div>

            <div class="quick-field full">
                <label>Destinazione selezionata</label>
                <input type="text" id="quickPreviewDestinazione" value="" readonly>
            </div>

            <div id="quickSingleFields" class="quick-form-grid" style="grid-column:1 / -1;">
                <div class="quick-field">
                    <label for="quickModalOraInizio">Ora inizio</label>
                    <input type="time" id="quickModalOraInizio" value="<?php echo h($defaultStart); ?>">
                </div>

                <div class="quick-field">
                    <label for="quickModalOraFine">Ora fine</label>
                    <input type="time" id="quickModalOraFine" value="<?php echo h($defaultEnd); ?>">
                </div>

                <label class="quick-check">
                    <input type="checkbox" id="quickModalResponsabile" value="1">
                    <span>Segna questo turno come <strong>Responsabile</strong></span>
                </label>
            </div>

            <div id="quickMultiFields" style="display:none; grid-column:1 / -1;">
                <div class="quick-multi-bulk">
                    <div class="quick-multi-bulk-title">Compila veloce tutti gli operatori</div>

                    <div class="quick-field">
                        <label for="bulkOraInizio">Ora inizio</label>
                        <input type="time" id="bulkOraInizio" value="<?php echo h($defaultStart); ?>">
                    </div>

                    <div class="quick-field">
                        <label for="bulkOraFine">Ora fine</label>
                        <input type="time" id="bulkOraFine" value="<?php echo h($defaultEnd); ?>">
                    </div>

                    <label class="quick-check" style="grid-column:auto; min-height:44px; margin:0;">
                        <input type="checkbox" id="bulkResponsabile" value="1">
                        <span>Responsabile</span>
                    </label>

                    <button type="button" class="quick-multi-apply-btn" onclick="applyBulkToMultiRows()">
                        Applica a tutti
                    </button>
                </div>

                <div class="quick-multi-table-wrap" style="margin-top:12px;">
                    <table class="quick-multi-table">
                        <thead>
                            <tr>
                                <th>Operatore</th>
                                <th>Ora inizio</th>
                                <th>Ora fine</th>
                                <th>Responsabile</th>
                            </tr>
                        </thead>
                        <tbody id="quickMultiRowsBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="quick-actions">
            <button type="button" class="btn btn-primary" onclick="submitQuickAssign()">Conferma assegnazione</button>
            <button type="button" class="btn btn-warning" id="quickForceBtn" style="display:none;" onclick="submitQuickAssign(true, false)">Conferma e salva comunque</button>
            <button type="button" class="btn btn-secondary" id="quickPartialBtn" style="display:none;" onclick="submitQuickAssign(false, true)">Conferma e salva solo quelli validi</button>
            <button type="button" class="btn btn-ghost" onclick="closeQuickAssignModal()">Annulla</button>
        </div>
    </div>
</div>

<script>
let selectedOperatorId = '';
let selectedOperatorName = '';
let selectedDestinationId = '';
let selectedDestinationName = '';
let draggedOperatorId = '';
let draggedOperatorName = '';

let draggedTurnId = '';
let draggedTurnOperatorId = '';
let draggedTurnOperatorName = '';
let draggedTurnSourceDestinationId = '';
let draggedTurnSourceDestinationName = '';
let draggedTurnStart = '';
let draggedTurnEnd = '';
let draggedTurnResponsabile = '0';

let quickMode = 'new';
let selectedOperatorIds = [];
let selectedOperatorNamesMap = {};
window.__turnDragActive = false;

function getOperatorCardById(operatorId) {
    return document.querySelector('.operator-card[data-operator-id="' + String(operatorId) + '"]');
}

function getSelectedOperatorsData() {
    return selectedOperatorIds.map(function(operatorId) {
        const card = getOperatorCardById(operatorId);
        return {
            id: String(operatorId),
            name: card ? (card.getAttribute('data-operator-name') || '') : (selectedOperatorNamesMap[String(operatorId)] || '')
        };
    });
}

function refreshOperatorSelectionUI() {
    document.querySelectorAll('.operator-card').forEach(function(card) {
        const operatorId = card.getAttribute('data-operator-id') || '';
        card.classList.remove('selected', 'multi-selected');

        if (selectedOperatorIds.includes(operatorId)) {
            if (selectedOperatorIds.length === 1) {
                card.classList.add('selected');
            } else {
                card.classList.add('multi-selected');
            }
        }
    });

    if (selectedOperatorIds.length === 1) {
        selectedOperatorId = selectedOperatorIds[0];
        selectedOperatorName = selectedOperatorNamesMap[selectedOperatorId] || '';
    } else {
        selectedOperatorId = '';
        selectedOperatorName = '';
    }

    if (selectedOperatorIds.length > 0) {
        document.querySelectorAll('.destination-card.quick-target').forEach(function(el) {
            el.classList.add('quick-target-active');
        });
    }
}

function clearOperatorSelection() {
    selectedOperatorIds = [];
    selectedOperatorNamesMap = {};
    selectedOperatorId = '';
    selectedOperatorName = '';
    refreshOperatorSelectionUI();
}

function setSingleOperatorSelection(card) {
    const operatorId = card.getAttribute('data-operator-id') || '';
    const operatorName = card.getAttribute('data-operator-name') || '';

    selectedOperatorIds = operatorId ? [operatorId] : [];
    selectedOperatorNamesMap = {};
    if (operatorId) {
        selectedOperatorNamesMap[operatorId] = operatorName;
    }

    refreshOperatorSelectionUI();
}

function toggleOperatorSelection(card) {
    const operatorId = card.getAttribute('data-operator-id') || '';
    const operatorName = card.getAttribute('data-operator-name') || '';

    if (!operatorId) {
        return;
    }

    if (selectedOperatorIds.includes(operatorId)) {
        selectedOperatorIds = selectedOperatorIds.filter(function(id) {
            return id !== operatorId;
        });
        delete selectedOperatorNamesMap[operatorId];
    } else {
        selectedOperatorIds.push(operatorId);
        selectedOperatorNamesMap[operatorId] = operatorName;
    }

    refreshOperatorSelectionUI();
}

function resetQuickMode() {
    quickMode = 'new';
    draggedTurnId = '';
    draggedTurnOperatorId = '';
    draggedTurnOperatorName = '';
    draggedTurnSourceDestinationId = '';
    draggedTurnSourceDestinationName = '';
    draggedTurnStart = '';
    draggedTurnEnd = '';
    draggedTurnResponsabile = '0';

    document.getElementById('quickAssignTurnoId').value = '';
    document.getElementById('quickAssignMoveMode').value = '0';
    document.getElementById('quickAssignMultiMode').value = '0';
    document.getElementById('quickAssignPartialSave').value = '0';
    document.getElementById('quickAssignDipendentiJson').value = '';
    document.getElementById('quickAssignAssignmentsJson').value = '';
    document.getElementById('quickAssignForceSave').value = '0';
    document.getElementById('quickForceBtn').style.display = 'none';
    document.getElementById('quickPartialBtn').style.display = 'none';
}

function selectOperator(event, card) {
    resetQuickMode();

    if (event && event.ctrlKey) {
        toggleOperatorSelection(card);
        return;
    }

    setSingleOperatorSelection(card);
}

function showSingleFields() {
    document.getElementById('quickSingleFields').style.display = 'grid';
    document.getElementById('quickMultiFields').style.display = 'none';
}

function showMultiFields() {
    document.getElementById('quickSingleFields').style.display = 'none';
    document.getElementById('quickMultiFields').style.display = 'block';
}

function updateQuickPreviewForOperators(operatorsData) {
    const wrap = document.getElementById('quickMultiOperatorsWrap');
    const listBox = document.getElementById('quickPreviewOperatoriLista');
    const preview = document.getElementById('quickPreviewOperatore');

    if (!Array.isArray(operatorsData) || operatorsData.length === 0) {
        preview.value = '';
        wrap.style.display = 'none';
        listBox.value = '';
        return;
    }

    if (operatorsData.length === 1) {
        preview.value = operatorsData[0].name || '';
        wrap.style.display = 'none';
        listBox.value = '';
        return;
    }

    preview.value = operatorsData.length + ' operatori selezionati';
    wrap.style.display = 'block';
    listBox.value = operatorsData.map(function(op) {
        return '- ' + (op.name || ('ID ' + op.id));
    }).join("\n");
}

function renderMultiRows(operatorsData, assignmentsByOperatorId) {
    const body = document.getElementById('quickMultiRowsBody');
    body.innerHTML = '';

    operatorsData.forEach(function(op) {
        const existing = assignmentsByOperatorId && assignmentsByOperatorId[String(op.id)]
            ? assignmentsByOperatorId[String(op.id)]
            : null;

        const tr = document.createElement('tr');
        tr.innerHTML = ''
            + '<td>' + escapeHtml(op.name || ('ID ' + op.id)) + '</td>'
            + '<td><input type="time" class="multi-row-start" data-operator-id="' + escapeAttr(op.id) + '" value="' + escapeAttr(existing && existing.ora_inizio ? existing.ora_inizio : '<?php echo h($defaultStart); ?>') + '"></td>'
            + '<td><input type="time" class="multi-row-end" data-operator-id="' + escapeAttr(op.id) + '" value="' + escapeAttr(existing && existing.ora_fine ? existing.ora_fine : '<?php echo h($defaultEnd); ?>') + '"></td>'
            + '<td><label class="quick-multi-check"><input type="checkbox" class="multi-row-responsabile" data-operator-id="' + escapeAttr(op.id) + '"' + ((existing && String(existing.is_responsabile) === '1') ? ' checked' : '') + '> <span>Sì</span></label></td>';
        body.appendChild(tr);
    });
}

function applyBulkToMultiRows() {
    const start = document.getElementById('bulkOraInizio').value.trim();
    const end = document.getElementById('bulkOraFine').value.trim();
    const responsabile = document.getElementById('bulkResponsabile').checked;

    document.querySelectorAll('.multi-row-start').forEach(function(input) {
        input.value = start;
    });

    document.querySelectorAll('.multi-row-end').forEach(function(input) {
        input.value = end;
    });

    document.querySelectorAll('.multi-row-responsabile').forEach(function(input) {
        input.checked = responsabile;
    });
}

function collectMultiAssignments() {
    const operatorsData = getSelectedOperatorsData();
    const assignments = [];

    operatorsData.forEach(function(op) {
        const startEl = document.querySelector('.multi-row-start[data-operator-id="' + op.id + '"]');
        const endEl = document.querySelector('.multi-row-end[data-operator-id="' + op.id + '"]');
        const respEl = document.querySelector('.multi-row-responsabile[data-operator-id="' + op.id + '"]');

        assignments.push({
            id_dipendente: String(op.id),
            nome: op.name || '',
            ora_inizio: startEl ? startEl.value.trim() : '',
            ora_fine: endEl ? endEl.value.trim() : '',
            is_responsabile: respEl && respEl.checked ? '1' : '0'
        });
    });

    return assignments;
}

function openQuickAssignModal(card) {
    const operatorsData = getSelectedOperatorsData();

    if (operatorsData.length === 0) {
        alert('Seleziona prima uno o più operatori.');
        return;
    }

    selectedDestinationId = card.getAttribute('data-destination-id') || '';
    selectedDestinationName = card.getAttribute('data-destination-name') || '';

    if (!selectedDestinationId) {
        return;
    }

    updateQuickPreviewForOperators(operatorsData);
    document.getElementById('quickPreviewDestinazione').value = selectedDestinationName;
    document.getElementById('quickAssignForceSave').value = '0';
    document.getElementById('quickAssignPartialSave').value = '0';
    document.getElementById('quickForceBtn').style.display = 'none';
    document.getElementById('quickPartialBtn').style.display = 'none';
    document.getElementById('quickAssignTurnoId').value = '';
    document.getElementById('quickAssignMoveMode').value = '0';
    document.getElementById('quickAssignAssignmentsJson').value = '';

    if (operatorsData.length > 1) {
        showMultiFields();
        document.getElementById('bulkOraInizio').value = '<?php echo h($defaultStart); ?>';
        document.getElementById('bulkOraFine').value = '<?php echo h($defaultEnd); ?>';
        document.getElementById('bulkResponsabile').checked = false;
        renderMultiRows(operatorsData, null);
        document.getElementById('quickAssignMultiMode').value = '1';
        document.getElementById('quickAssignDipendentiJson').value = JSON.stringify(operatorsData.map(function(op) {
            return op.id;
        }));
        document.getElementById('quickModalText').textContent = 'Stai assegnando più operatori insieme. Ogni operatore può avere un orario diverso.';
    } else {
        showSingleFields();
        document.getElementById('quickModalOraInizio').value = '<?php echo h($defaultStart); ?>';
        document.getElementById('quickModalOraFine').value = '<?php echo h($defaultEnd); ?>';
        document.getElementById('quickModalResponsabile').checked = false;
        document.getElementById('quickAssignMultiMode').value = '0';
        document.getElementById('quickAssignDipendentiJson').value = '';
        document.getElementById('quickModalText').textContent = 'Seleziona orario e conferma l’assegnazione.';
    }

    document.getElementById('quickAssignOverlay').classList.add('visible');
}

function openMoveTurnModal(targetCard) {
    selectedOperatorId = draggedTurnOperatorId;
    selectedOperatorName = draggedTurnOperatorName;
    selectedDestinationId = targetCard.getAttribute('data-destination-id') || '';
    selectedDestinationName = targetCard.getAttribute('data-destination-name') || '';

    if (!selectedDestinationId || !draggedTurnId) {
        return;
    }

    quickMode = 'move';

    showSingleFields();
    document.getElementById('quickPreviewOperatore').value = selectedOperatorName;
    document.getElementById('quickMultiOperatorsWrap').style.display = 'none';
    document.getElementById('quickPreviewOperatoriLista').value = '';
    document.getElementById('quickPreviewDestinazione').value = selectedDestinationName;
    document.getElementById('quickModalOraInizio').value = draggedTurnStart || '<?php echo h($defaultStart); ?>';
    document.getElementById('quickModalOraFine').value = draggedTurnEnd || '<?php echo h($defaultEnd); ?>';
    document.getElementById('quickModalResponsabile').checked = draggedTurnResponsabile === '1';
    document.getElementById('quickAssignForceSave').value = '0';
    document.getElementById('quickAssignPartialSave').value = '0';
    document.getElementById('quickForceBtn').style.display = 'none';
    document.getElementById('quickPartialBtn').style.display = 'none';
    document.getElementById('quickAssignTurnoId').value = draggedTurnId;
    document.getElementById('quickAssignMoveMode').value = '1';
    document.getElementById('quickAssignMultiMode').value = '0';
    document.getElementById('quickAssignDipendentiJson').value = '';
    document.getElementById('quickAssignAssignmentsJson').value = '';

    if (draggedTurnSourceDestinationId === selectedDestinationId) {
        document.getElementById('quickModalText').textContent = 'Stai modificando lo stesso turno sulla stessa destinazione. Puoi cambiare orario o ruolo e confermare.';
    } else {
        document.getElementById('quickModalText').textContent =
            'Stai spostando il turno dalla destinazione "' + draggedTurnSourceDestinationName +
            '" a "' + selectedDestinationName + '". Controlla orario e conferma.';
    }

    document.getElementById('quickAssignOverlay').classList.add('visible');
}

function closeQuickAssignModal() {
    document.getElementById('quickAssignOverlay').classList.remove('visible');
}

function submitQuickAssign(forceSave = false, partialSave = false) {
    const moveModeValue = quickMode === 'move' ? '1' : '0';
    const turnoId = document.getElementById('quickAssignTurnoId').value.trim();
    const operatorsData = getSelectedOperatorsData();
    const isMultiMode = moveModeValue === '0' && operatorsData.length > 1;

    document.getElementById('quickAssignPartialSave').value = partialSave ? '1' : '0';

    if (moveModeValue === '1') {
        const oraInizio = document.getElementById('quickModalOraInizio').value.trim();
        const oraFine = document.getElementById('quickModalOraFine').value.trim();
        const responsabile = document.getElementById('quickModalResponsabile').checked ? '1' : '0';

        if (!selectedOperatorId) {
            alert('Operatore turno non valido.');
            return;
        }

        if (!selectedDestinationId) {
            alert('Seleziona una destinazione.');
            return;
        }

        if (!oraInizio) {
            alert('Inserisci l’ora di inizio.');
            return;
        }

        if (!oraFine) {
            alert('Inserisci l’ora di fine.');
            return;
        }

        if (oraInizio === oraFine) {
            alert('Ora inizio e ora fine non possono coincidere.');
            return;
        }

        const msg =
            'Spostare il turno di ' + selectedOperatorName +
            ' su "' + selectedDestinationName +
            '" in data <?php echo h(format_date_it($selectedDate)); ?>?' +
            '\nOrario: ' + oraInizio + ' → ' + oraFine +
            (responsabile === '1' ? '\nRuolo turno: Responsabile' : '') +
            (forceSave ? '\n\nConferma forzata attiva.' : '');

        if (!confirm(msg)) {
            return;
        }

        document.getElementById('quickAssignDipendente').value = selectedOperatorId;
        document.getElementById('quickAssignMultiMode').value = '0';
        document.getElementById('quickAssignDipendentiJson').value = '';
        document.getElementById('quickAssignAssignmentsJson').value = '';
        document.getElementById('quickAssignCantiere').value = selectedDestinationId;
        document.getElementById('quickAssignTurnoId').value = turnoId;
        document.getElementById('quickAssignMoveMode').value = '1';
        document.getElementById('quickAssignOraInizio').value = oraInizio;
        document.getElementById('quickAssignOraFine').value = oraFine;
        document.getElementById('quickAssignResponsabile').value = responsabile;
        document.getElementById('quickAssignForceSave').value = forceSave ? '1' : '0';
        document.getElementById('quickAssignForm').submit();
        return;
    }

    if (operatorsData.length === 0) {
        alert('Seleziona prima uno o più operatori.');
        return;
    }

    if (!selectedDestinationId) {
        alert('Seleziona una destinazione.');
        return;
    }

    if (isMultiMode) {
        const assignments = collectMultiAssignments();

        for (let i = 0; i < assignments.length; i++) {
            if (!assignments[i].ora_inizio) {
                alert('Inserisci l’ora di inizio per ' + (assignments[i].nome || ('ID ' + assignments[i].id_dipendente)) + '.');
                return;
            }
            if (!assignments[i].ora_fine) {
                alert('Inserisci l’ora di fine per ' + (assignments[i].nome || ('ID ' + assignments[i].id_dipendente)) + '.');
                return;
            }
            if (assignments[i].ora_inizio === assignments[i].ora_fine) {
                alert('Ora inizio e ora fine non possono coincidere per ' + (assignments[i].nome || ('ID ' + assignments[i].id_dipendente)) + '.');
                return;
            }
        }

        let msg =
            'Assegnare ' + assignments.length + ' operatori a "' + selectedDestinationName +
            '" in data <?php echo h(format_date_it($selectedDate)); ?>?' +
            '\nOgni operatore userà l’orario impostato nella propria riga.';

        if (partialSave) {
            msg += '\n\nModalità attiva: salva solo quelli validi.';
        } else if (forceSave) {
            msg += '\n\nConferma forzata attiva.';
        }

        if (!confirm(msg)) {
            return;
        }

        document.getElementById('quickAssignDipendente').value = '';
        document.getElementById('quickAssignMultiMode').value = '1';
        document.getElementById('quickAssignDipendentiJson').value = JSON.stringify(assignments.map(function(item) {
            return item.id_dipendente;
        }));
        document.getElementById('quickAssignAssignmentsJson').value = JSON.stringify(assignments);
        document.getElementById('quickAssignCantiere').value = selectedDestinationId;
        document.getElementById('quickAssignTurnoId').value = '';
        document.getElementById('quickAssignMoveMode').value = '0';
        document.getElementById('quickAssignOraInizio').value = '';
        document.getElementById('quickAssignOraFine').value = '';
        document.getElementById('quickAssignResponsabile').value = '0';
        document.getElementById('quickAssignForceSave').value = forceSave ? '1' : '0';
        document.getElementById('quickAssignForm').submit();
        return;
    }

    const oraInizio = document.getElementById('quickModalOraInizio').value.trim();
    const oraFine = document.getElementById('quickModalOraFine').value.trim();
    const responsabile = document.getElementById('quickModalResponsabile').checked ? '1' : '0';

    if (!oraInizio) {
        alert('Inserisci l’ora di inizio.');
        return;
    }

    if (!oraFine) {
        alert('Inserisci l’ora di fine.');
        return;
    }

    if (oraInizio === oraFine) {
        alert('Ora inizio e ora fine non possono coincidere.');
        return;
    }

    const msg =
        'Assegnare ' + (operatorsData[0] ? operatorsData[0].name : selectedOperatorName) +
        ' a "' + selectedDestinationName +
        '" in data <?php echo h(format_date_it($selectedDate)); ?>?' +
        '\nOrario: ' + oraInizio + ' → ' + oraFine +
        (responsabile === '1' ? '\nRuolo turno: Responsabile' : '') +
        (forceSave ? '\n\nConferma forzata attiva.' : '');

    if (!confirm(msg)) {
        return;
    }

    document.getElementById('quickAssignDipendente').value = operatorsData[0] ? operatorsData[0].id : '';
    document.getElementById('quickAssignMultiMode').value = '0';
    document.getElementById('quickAssignDipendentiJson').value = '';
    document.getElementById('quickAssignAssignmentsJson').value = '';
    document.getElementById('quickAssignCantiere').value = selectedDestinationId;
    document.getElementById('quickAssignTurnoId').value = '';
    document.getElementById('quickAssignMoveMode').value = '0';
    document.getElementById('quickAssignOraInizio').value = oraInizio;
    document.getElementById('quickAssignOraFine').value = oraFine;
    document.getElementById('quickAssignResponsabile').value = responsabile;
    document.getElementById('quickAssignForceSave').value = forceSave ? '1' : '0';
    document.getElementById('quickAssignForm').submit();
}

function handleOperatorDragStart(event, card) {
    resetQuickMode();

    const operatorId = card.getAttribute('data-operator-id') || '';
    const operatorName = card.getAttribute('data-operator-name') || '';

    if (!operatorId) {
        event.preventDefault();
        return;
    }

    if (!selectedOperatorIds.includes(operatorId)) {
        setSingleOperatorSelection(card);
    } else {
        refreshOperatorSelectionUI();
    }

    const operatorsData = getSelectedOperatorsData();

    if (operatorsData.length === 0) {
        event.preventDefault();
        return;
    }

    draggedOperatorId = operatorId;
    draggedOperatorName = operatorName;

    document.querySelectorAll('.operator-card').forEach(function(el) {
        const elId = el.getAttribute('data-operator-id') || '';
        if (selectedOperatorIds.includes(elId)) {
            el.classList.add('drag-source-selected');
        }
    });

    card.classList.add('dragging');

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', operatorId);
    }
}

function handleOperatorDragEnd(event, card) {
    card.classList.remove('dragging');

    document.querySelectorAll('.operator-card.drag-source-selected').forEach(el => {
        el.classList.remove('drag-source-selected');
    });

    document.querySelectorAll('.destination-card.drag-over').forEach(el => {
        el.classList.remove('drag-over');
    });

    document.querySelectorAll('.destination-dropzone.drag-over').forEach(el => {
        el.classList.remove('drag-over');
    });

    draggedOperatorId = '';
    draggedOperatorName = '';
}

function handleAssignedTurnDragStart(event, chip) {
    quickMode = 'move';
    window.__turnDragActive = true;

    draggedTurnId = chip.getAttribute('data-turn-id') || '';
    draggedTurnOperatorId = chip.getAttribute('data-turn-operator-id') || '';
    draggedTurnOperatorName = chip.getAttribute('data-turn-operator-name') || '';
    draggedTurnSourceDestinationId = chip.getAttribute('data-turn-destination-id') || '';
    draggedTurnSourceDestinationName = chip.getAttribute('data-turn-destination-name') || '';
    draggedTurnStart = chip.getAttribute('data-turn-start') || '';
    draggedTurnEnd = chip.getAttribute('data-turn-end') || '';
    draggedTurnResponsabile = chip.getAttribute('data-turn-responsabile') || '0';

    if (!draggedTurnId || !draggedTurnOperatorId) {
        event.preventDefault();
        quickMode = 'new';
        window.__turnDragActive = false;
        return;
    }

    chip.classList.add('dragging');

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', draggedTurnId);
    }
}

function handleAssignedTurnDragEnd(event, chip) {
    chip.classList.remove('dragging');

    document.querySelectorAll('.destination-card.drag-over').forEach(el => {
        el.classList.remove('drag-over');
    });

    document.querySelectorAll('.destination-dropzone.drag-over').forEach(el => {
        el.classList.remove('drag-over');
    });

    setTimeout(function () {
        window.__turnDragActive = false;
    }, 0);
}

function handleDestinationDragOver(event, card) {
    if (!draggedOperatorId && !draggedTurnId) {
        return;
    }
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
}

function handleDestinationDragEnter(event, card) {
    if (!draggedOperatorId && !draggedTurnId) {
        return;
    }
    event.preventDefault();
    card.classList.add('drag-over');
    const dropzone = card.querySelector('.destination-dropzone');
    if (dropzone) {
        dropzone.classList.add('drag-over');
    }
}

function handleDestinationDragLeave(event, card) {
    const related = event.relatedTarget;
    if (related && card.contains(related)) {
        return;
    }

    card.classList.remove('drag-over');
    const dropzone = card.querySelector('.destination-dropzone');
    if (dropzone) {
        dropzone.classList.remove('drag-over');
    }
}

function handleDestinationDrop(event, card) {
    event.preventDefault();

    card.classList.remove('drag-over');
    const dropzone = card.querySelector('.destination-dropzone');
    if (dropzone) {
        dropzone.classList.remove('drag-over');
    }

    if (draggedTurnId) {
        openMoveTurnModal(card);
        return;
    }

    const operatorsData = getSelectedOperatorsData();

    if (!draggedOperatorId && operatorsData.length === 0) {
        return;
    }

    selectedDestinationId = card.getAttribute('data-destination-id') || '';
    selectedDestinationName = card.getAttribute('data-destination-name') || '';

    if (!selectedDestinationId) {
        return;
    }

    quickMode = 'new';
    document.getElementById('quickAssignTurnoId').value = '';
    document.getElementById('quickAssignMoveMode').value = '0';
    document.getElementById('quickAssignPartialSave').value = '0';
    document.getElementById('quickAssignAssignmentsJson').value = '';
    updateQuickPreviewForOperators(operatorsData);
    document.getElementById('quickPreviewDestinazione').value = selectedDestinationName;
    document.getElementById('quickAssignForceSave').value = '0';
    document.getElementById('quickForceBtn').style.display = 'none';
    document.getElementById('quickPartialBtn').style.display = 'none';

    if (operatorsData.length > 1) {
        showMultiFields();
        document.getElementById('bulkOraInizio').value = '<?php echo h($defaultStart); ?>';
        document.getElementById('bulkOraFine').value = '<?php echo h($defaultEnd); ?>';
        document.getElementById('bulkResponsabile').checked = false;
        renderMultiRows(operatorsData, null);
        document.getElementById('quickAssignMultiMode').value = '1';
        document.getElementById('quickAssignDipendentiJson').value = JSON.stringify(operatorsData.map(function(op) { return op.id; }));
        document.getElementById('quickModalText').textContent = 'Operatori trascinati sulla destinazione. Ogni operatore può avere un orario diverso.';
    } else {
        showSingleFields();
        document.getElementById('quickAssignMultiMode').value = '0';
        document.getElementById('quickAssignDipendentiJson').value = '';
        document.getElementById('quickModalOraInizio').value = '<?php echo h($defaultStart); ?>';
        document.getElementById('quickModalOraFine').value = '<?php echo h($defaultEnd); ?>';
        document.getElementById('quickModalResponsabile').checked = false;
        document.getElementById('quickModalText').textContent = 'Operatore trascinato sulla destinazione. Seleziona orario e conferma l’assegnazione.';
    }

    document.getElementById('quickAssignOverlay').classList.add('visible');
}

document.getElementById('quickAssignOverlay').addEventListener('click', function(e) {
    if (e.target === this) {
        closeQuickAssignModal();
    }
});

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function escapeAttr(value) {
    return escapeHtml(value);
}

function normalizeForSearch(value) {
    return String(value || '').toLocaleLowerCase('it-IT').trim();
}

function filterOperatorCardsLive() {
    const searchInput = document.getElementById('operator_search');
    const onlyUnassignedInput = document.getElementById('only_unassigned');
    const emptyBox = document.getElementById('operatoriListEmpty');

    if (!searchInput || !onlyUnassignedInput) {
        return;
    }

    const searchValue = normalizeForSearch(searchInput.value);
    const onlyUnassigned = !!onlyUnassignedInput.checked;

    let visibleCount = 0;

    document.querySelectorAll('.operator-card').forEach(function(card) {
        const searchText = normalizeForSearch(card.getAttribute('data-search-text') || '');
        const isAssigned = (card.getAttribute('data-is-assigned') || '0') === '1';

        let visible = true;

        if (searchValue !== '' && !searchText.includes(searchValue)) {
            visible = false;
        }

        if (onlyUnassigned && isAssigned) {
            visible = false;
        }

        card.style.display = visible ? '' : 'none';

        if (visible) {
            visibleCount++;
        }
    });

    if (emptyBox) {
        emptyBox.style.display = visibleCount === 0 ? 'block' : 'none';
    }
}

function filterDestinationCardsLive() {
    const searchInput = document.getElementById('destination_search');
    const onlyFavoritesInput = document.getElementById('only_favorites');
    const emptyBox = document.getElementById('destinazioniListEmpty');

    if (!searchInput || !onlyFavoritesInput) {
        return;
    }

    const searchValue = normalizeForSearch(searchInput.value);
    const onlyFavorites = !!onlyFavoritesInput.checked;
    let visibleCount = 0;

    document.querySelectorAll('.destination-card').forEach(function(card) {
        const searchText = normalizeForSearch(card.getAttribute('data-search-text') || '');
        const isFavorite = (card.getAttribute('data-is-favorite') || '0') === '1';

        let visible = true;

        if (searchValue !== '' && !searchText.includes(searchValue)) {
            visible = false;
        }

        if (onlyFavorites && !isFavorite) {
            visible = false;
        }

        card.style.display = visible ? '' : 'none';

        if (visible) {
            visibleCount++;
        }
    });

    if (emptyBox) {
        emptyBox.style.display = visibleCount === 0 ? 'block' : 'none';
    }
}

function clearPlanningFilters() {
    const operatorSearchInput = document.getElementById('operator_search');
    const onlyUnassignedInput = document.getElementById('only_unassigned');
    const destinationSearchInput = document.getElementById('destination_search');
    const onlyFavoritesInput = document.getElementById('only_favorites');

    if (operatorSearchInput) {
        operatorSearchInput.value = '';
    }

    if (onlyUnassignedInput) {
        onlyUnassignedInput.checked = false;
    }

    if (destinationSearchInput) {
        destinationSearchInput.value = '';
    }

    if (onlyFavoritesInput) {
        onlyFavoritesInput.checked = false;
    }

    filterOperatorCardsLive();
    filterDestinationCardsLive();
}

document.addEventListener('DOMContentLoaded', function () {
    const operatorSearchInput = document.getElementById('operator_search');
    const onlyUnassignedInput = document.getElementById('only_unassigned');
    const destinationSearchInput = document.getElementById('destination_search');
    const onlyFavoritesInput = document.getElementById('only_favorites');

    if (operatorSearchInput) {
        operatorSearchInput.addEventListener('input', filterOperatorCardsLive);
    }

    if (onlyUnassignedInput) {
        onlyUnassignedInput.addEventListener('change', filterOperatorCardsLive);
    }

    if (destinationSearchInput) {
        destinationSearchInput.addEventListener('input', filterDestinationCardsLive);
    }

    if (onlyFavoritesInput) {
        onlyFavoritesInput.addEventListener('change', filterDestinationCardsLive);
    }

    filterOperatorCardsLive();
    filterDestinationCardsLive();
});
</script>

<?php if ($quickConflict): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const conflict = <?php echo json_encode($quickConflict, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    if (!conflict || !conflict.form) return;

    selectedDestinationId = String(conflict.form.id_cantiere || '');

    const destCard = document.querySelector('.destination-card[data-destination-id="' + selectedDestinationId + '"]');
    if (destCard) {
        selectedDestinationName = destCard.getAttribute('data-destination-name') || '';
    }

    const conflictIsMulti = String(conflict.form.multi_mode || '0') === '1';
    const conflictPartial = String(conflict.form.partial_save || '0') === '1';

    if (String(conflict.form.move_mode || '0') === '1') {
        quickMode = 'move';
        selectedOperatorId = String(conflict.form.id_dipendente || '');
        document.getElementById('quickAssignTurnoId').value = String(conflict.form.id_turno || '');
        document.getElementById('quickAssignMoveMode').value = '1';
        document.getElementById('quickAssignMultiMode').value = '0';
        document.getElementById('quickAssignPartialSave').value = '0';
        document.getElementById('quickAssignDipendentiJson').value = '';
        document.getElementById('quickAssignAssignmentsJson').value = '';

        const opCard = document.querySelector('.operator-card[data-operator-id="' + selectedOperatorId + '"]');
        if (opCard) {
            setSingleOperatorSelection(opCard);
        }

        showSingleFields();
        document.getElementById('quickPreviewOperatore').value = selectedOperatorName;
        document.getElementById('quickMultiOperatorsWrap').style.display = 'none';
        document.getElementById('quickPreviewOperatoriLista').value = '';
        document.getElementById('quickForceBtn').style.display = 'inline-flex';
        document.getElementById('quickPartialBtn').style.display = 'none';
    } else {
        quickMode = 'new';
        document.getElementById('quickAssignTurnoId').value = '';
        document.getElementById('quickAssignMoveMode').value = '0';

        if (conflictIsMulti && conflict.form.id_dipendenti && Array.isArray(conflict.form.id_dipendenti)) {
            selectedOperatorIds = conflict.form.id_dipendenti.map(function(id) { return String(id); });
            selectedOperatorNamesMap = {};

            selectedOperatorIds.forEach(function(opId) {
                const opCard = document.querySelector('.operator-card[data-operator-id="' + opId + '"]');
                if (opCard) {
                    selectedOperatorNamesMap[opId] = opCard.getAttribute('data-operator-name') || '';
                }
            });

            refreshOperatorSelectionUI();

            const operatorsData = getSelectedOperatorsData();
            updateQuickPreviewForOperators(operatorsData);
            showMultiFields();
            document.getElementById('quickAssignMultiMode').value = '1';
            document.getElementById('quickAssignPartialSave').value = conflictPartial ? '1' : '0';
            document.getElementById('quickAssignDipendentiJson').value = JSON.stringify(selectedOperatorIds);

            const assignmentsByOperatorId = {};
            if (conflict.form.assignments && Array.isArray(conflict.form.assignments)) {
                conflict.form.assignments.forEach(function(item) {
                    assignmentsByOperatorId[String(item.id_dipendente || '')] = item;
                });
                document.getElementById('quickAssignAssignmentsJson').value = JSON.stringify(conflict.form.assignments);
            } else {
                document.getElementById('quickAssignAssignmentsJson').value = '';
            }

            document.getElementById('bulkOraInizio').value = '<?php echo h($defaultStart); ?>';
            document.getElementById('bulkOraFine').value = '<?php echo h($defaultEnd); ?>';
            document.getElementById('bulkResponsabile').checked = false;
            renderMultiRows(operatorsData, assignmentsByOperatorId);

            document.getElementById('quickForceBtn').style.display = 'inline-flex';
            document.getElementById('quickPartialBtn').style.display = 'inline-flex';
        } else {
            selectedOperatorId = String(conflict.form.id_dipendente || '');
            const opCard = document.querySelector('.operator-card[data-operator-id="' + selectedOperatorId + '"]');
            if (opCard) {
                setSingleOperatorSelection(opCard);
            }
            showSingleFields();
            document.getElementById('quickPreviewOperatore').value = selectedOperatorName;
            document.getElementById('quickMultiOperatorsWrap').style.display = 'none';
            document.getElementById('quickPreviewOperatoriLista').value = '';
            document.getElementById('quickAssignMultiMode').value = '0';
            document.getElementById('quickAssignPartialSave').value = '0';
            document.getElementById('quickAssignDipendentiJson').value = '';
            document.getElementById('quickAssignAssignmentsJson').value = '';
            document.getElementById('quickForceBtn').style.display = 'inline-flex';
            document.getElementById('quickPartialBtn').style.display = 'none';
        }
    }

    document.getElementById('quickPreviewDestinazione').value = selectedDestinationName;
    document.getElementById('quickModalOraInizio').value = conflict.form.ora_inizio || '<?php echo h($defaultStart); ?>';
    document.getElementById('quickModalOraFine').value = conflict.form.ora_fine || '<?php echo h($defaultEnd); ?>';
    document.getElementById('quickModalResponsabile').checked = String(conflict.form.is_responsabile || '0') === '1';
    document.getElementById('quickAssignForceSave').value = '0';
    document.getElementById('quickModalText').textContent = conflict.message || 'Conflitto rilevato.';
    document.getElementById('quickAssignOverlay').classList.add('visible');
});
</script>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>