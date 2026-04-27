<?php
// index.php
// Dashboard principale Turnar
// Usa cantieri.is_special come unica logica per distinguere destinazioni speciali / operative

require_once __DIR__ . '/core/helpers.php';

require_login();

$pageTitle    = 'Dashboard';
$pageSubtitle = 'Cruscotto operativo giornaliero';
$activeModule = 'dashboard';

$db = db_connect();

$authUserId = function_exists('auth_id') ? (int)auth_id() : (int)($_SESSION['user_id'] ?? 0);

// --------------------------------------------------
// DATA SELEZIONATA
// --------------------------------------------------
$today = new DateTimeImmutable('today', new DateTimeZone('Europe/Rome'));
$selectedDate = isset($_GET['data']) ? trim((string)$_GET['data']) : $today->format('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = $today->format('Y-m-d');
}

try {
    $selectedDateObj = new DateTimeImmutable($selectedDate, new DateTimeZone('Europe/Rome'));
} catch (Throwable $e) {
    $selectedDateObj = $today;
    $selectedDate = $today->format('Y-m-d');
}

$prevDate = $selectedDateObj->modify('-1 day')->format('Y-m-d');
$nextDate = $selectedDateObj->modify('+1 day')->format('Y-m-d');
$isToday  = ($selectedDate === $today->format('Y-m-d'));

$giorniSettimana = [
    'Monday'    => 'Lunedì',
    'Tuesday'   => 'Martedì',
    'Wednesday' => 'Mercoledì',
    'Thursday'  => 'Giovedì',
    'Friday'    => 'Venerdì',
    'Saturday'  => 'Sabato',
    'Sunday'    => 'Domenica',
];

$mesi = [
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

$dayNameEn   = $selectedDateObj->format('l');
$dayNameIt   = $giorniSettimana[$dayNameEn] ?? $dayNameEn;
$humanDate   = $dayNameIt . ' ' . $selectedDateObj->format('d') . ' ' . ($mesi[(int)$selectedDateObj->format('n')] ?? '') . ' ' . $selectedDateObj->format('Y');
$compactDate = $dayNameIt . ' ' . $selectedDateObj->format('d/m/Y');

// --------------------------------------------------
// HELPER LOCALI
// --------------------------------------------------
if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function badgeClassForRole(?string $role): string
{
    $role = strtolower(trim((string)$role));

    return match ($role) {
        'responsabile' => 'badge badge-warning',
        'preposto' => 'badge badge-primary',
        default => 'badge',
    };
}

function fmtTimeRange(?string $start, ?string $end): string
{
    $start = trim((string)$start);
    $end   = trim((string)$end);

    if ($start === '' && $end === '') {
        return 'Orario non definito';
    }

    if ($start !== '' && $end !== '') {
        return substr($start, 0, 5) . ' - ' . substr($end, 0, 5);
    }

    return trim(substr($start, 0, 5) . ' ' . substr($end, 0, 5));
}

function detectFavoriteJoinColumn(mysqli $db): string
{
    $result = $db->query("SHOW COLUMNS FROM user_favorite_destinations");
    if (!$result) {
        return 'cantiere_id';
    }

    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'] ?? '';
    }
    $result->free();

    if (in_array('cantiere_id', $columns, true)) {
        return 'cantiere_id';
    }

    if (in_array('destination_id', $columns, true)) {
        return 'destination_id';
    }

    return 'cantiere_id';
}

// --------------------------------------------------
// PREFERITI UTENTE
// --------------------------------------------------
$favoriteJoinColumn = 'cantiere_id';
$favoritesEnabled = false;

$checkFavTable = $db->query("SHOW TABLES LIKE 'user_favorite_destinations'");
if ($checkFavTable && $checkFavTable->num_rows > 0) {
    $favoritesEnabled = true;
    $favoriteJoinColumn = detectFavoriteJoinColumn($db);
}
if ($checkFavTable instanceof mysqli_result) {
    $checkFavTable->free();
}

$favoriteIds = [];

if ($favoritesEnabled && $authUserId > 0) {
    $sqlFav = "SELECT `$favoriteJoinColumn` AS fav_id
               FROM user_favorite_destinations
               WHERE user_id = ?";
    $stmtFav = $db->prepare($sqlFav);
    if ($stmtFav) {
        $stmtFav->bind_param('i', $authUserId);
        $stmtFav->execute();
        $resFav = $stmtFav->get_result();
        while ($row = $resFav->fetch_assoc()) {
            $favoriteIds[] = (int)$row['fav_id'];
        }
        $stmtFav->close();
    }
}

$favoriteIds = array_values(array_unique(array_filter($favoriteIds)));

// --------------------------------------------------
// QUERY PRINCIPALE TURNI DEL GIORNO
// --------------------------------------------------
$assignments = [];
$allAssignedOperatorIds = [];

$sqlAssignments = "
    SELECT
        et.id,
        et.data,
        et.id_cantiere,
        et.id_dipendente,
        et.ora_inizio,
        et.ora_fine,
        et.is_capocantiere,

        c.commessa,
        c.cliente,
        c.codice_commessa,
        c.comune,
        c.tipologia,
        c.stato,
        c.attivo,
        c.visibile_calendario,
        c.is_special,

        d.nome,
        d.cognome,
        d.tipologia AS ruolo_operatore,
        d.preposto,
        d.capo_cantiere,
        d.attivo AS dipendente_attivo

    FROM eventi_turni et
    INNER JOIN cantieri c ON c.id = et.id_cantiere
    INNER JOIN dipendenti d ON d.id = et.id_dipendente
    WHERE et.data = ?
    ORDER BY
        c.is_special ASC,
        c.commessa ASC,
        et.ora_inizio ASC,
        d.cognome ASC,
        d.nome ASC
";

$stmtAssignments = $db->prepare($sqlAssignments);
if ($stmtAssignments) {
    $stmtAssignments->bind_param('s', $selectedDate);
    $stmtAssignments->execute();
    $resAssignments = $stmtAssignments->get_result();

    while ($row = $resAssignments->fetch_assoc()) {
        $row['id']                  = (int)$row['id'];
        $row['id_cantiere']         = (int)$row['id_cantiere'];
        $row['id_dipendente']       = (int)$row['id_dipendente'];
        $row['is_capocantiere']     = (int)$row['is_capocantiere'];
        $row['is_special']          = (int)$row['is_special'];
        $row['attivo']              = (int)$row['attivo'];
        $row['visibile_calendario'] = (int)$row['visibile_calendario'];
        $row['dipendente_attivo']   = (int)$row['dipendente_attivo'];

        $assignments[] = $row;
        $allAssignedOperatorIds[] = (int)$row['id_dipendente'];
    }

    $stmtAssignments->close();
}

$allAssignedOperatorIds = array_values(array_unique(array_filter($allAssignedOperatorIds)));

// --------------------------------------------------
// RAGGRUPPAMENTO PER CANTIERE / DESTINAZIONE
// --------------------------------------------------
$groupedDestinations = [];

foreach ($assignments as $row) {
    $destinationId = (int)$row['id_cantiere'];

    if (!isset($groupedDestinations[$destinationId])) {
        $groupedDestinations[$destinationId] = [
            'id' => $destinationId,
            'commessa' => (string)$row['commessa'],
            'cliente' => (string)$row['cliente'],
            'codice_commessa' => (string)$row['codice_commessa'],
            'comune' => (string)$row['comune'],
            'tipologia' => (string)$row['tipologia'],
            'stato' => (string)$row['stato'],
            'attivo' => (int)$row['attivo'],
            'visibile_calendario' => (int)$row['visibile_calendario'],
            'is_special' => (int)$row['is_special'],
            'is_favorite' => in_array($destinationId, $favoriteIds, true),
            'assignments' => [],
            'operator_ids' => [],
        ];
    }

    $groupedDestinations[$destinationId]['assignments'][] = $row;
    $groupedDestinations[$destinationId]['operator_ids'][] = (int)$row['id_dipendente'];
}

foreach ($groupedDestinations as &$group) {
    $group['operator_ids'] = array_values(array_unique(array_filter($group['operator_ids'])));
    $group['operator_count'] = count($group['operator_ids']);
}
unset($group);

// --------------------------------------------------
// TUTTE LE DESTINAZIONI ATTIVE
// --------------------------------------------------
$allActiveDestinations = [];

$sqlAllDestinations = "
    SELECT
        id,
        commessa,
        cliente,
        codice_commessa,
        comune,
        tipologia,
        stato,
        attivo,
        visibile_calendario,
        is_special
    FROM cantieri
    WHERE attivo = 1
    ORDER BY is_special ASC, commessa ASC
";

$resAllDestinations = $db->query($sqlAllDestinations);
if ($resAllDestinations) {
    while ($row = $resAllDestinations->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['attivo'] = (int)$row['attivo'];
        $row['visibile_calendario'] = (int)$row['visibile_calendario'];
        $row['is_special'] = (int)$row['is_special'];
        $row['is_favorite'] = in_array((int)$row['id'], $favoriteIds, true);
        $allActiveDestinations[(int)$row['id']] = $row;
    }
    $resAllDestinations->free();
}

// --------------------------------------------------
// DESTINAZIONI SENZA PERSONALE
// solo operative, non speciali
// --------------------------------------------------
$activeOperationalWithoutStaff = [];

foreach ($allActiveDestinations as $destination) {
    $destinationId = (int)$destination['id'];
    $isSpecial = (int)$destination['is_special'] === 1;
    $hasAssignments = isset($groupedDestinations[$destinationId]) && !empty($groupedDestinations[$destinationId]['assignments']);

    if (!$isSpecial && !$hasAssignments) {
        $activeOperationalWithoutStaff[] = $destination;
    }
}

// --------------------------------------------------
// OPERATORI LIBERI OGGI
// --------------------------------------------------
$freeOperators = [];

$sqlFreeOperators = "
    SELECT
        d.id,
        d.nome,
        d.cognome,
        d.tipologia,
        d.preposto,
        d.capo_cantiere,
        d.attivo
    FROM dipendenti d
    WHERE d.attivo = 1
    ORDER BY d.cognome ASC, d.nome ASC
";

$resFreeOperators = $db->query($sqlFreeOperators);
if ($resFreeOperators) {
    while ($row = $resFreeOperators->fetch_assoc()) {
        $operatorId = (int)$row['id'];
        if (!in_array($operatorId, $allAssignedOperatorIds, true)) {
            $freeOperators[] = $row;
        }
    }
    $resFreeOperators->free();
}

// --------------------------------------------------
// DESTINAZIONI DEL GIORNO SEPARATE
// --------------------------------------------------
$favoritesToday = [];
$operationalToday = [];
$specialToday = [];

foreach ($groupedDestinations as $destination) {
    if ((int)$destination['is_special'] === 1) {
        $specialToday[] = $destination;
    } else {
        if (!empty($destination['is_favorite'])) {
            $favoritesToday[] = $destination;
        } else {
            $operationalToday[] = $destination;
        }
    }
}

usort($favoritesToday, function ($a, $b) {
    return strcasecmp((string)$a['commessa'], (string)$b['commessa']);
});

usort($operationalToday, function ($a, $b) {
    return strcasecmp((string)$a['commessa'], (string)$b['commessa']);
});

usort($specialToday, function ($a, $b) {
    return strcasecmp((string)$a['commessa'], (string)$b['commessa']);
});

usort($activeOperationalWithoutStaff, function ($a, $b) {
    $aFav = !empty($a['is_favorite']) ? 1 : 0;
    $bFav = !empty($b['is_favorite']) ? 1 : 0;

    if ($aFav !== $bFav) {
        return $bFav <=> $aFav;
    }

    return strcasecmp((string)$a['commessa'], (string)$b['commessa']);
});

// --------------------------------------------------
// KPI
// --------------------------------------------------
$kpiTotalAssignments = count($assignments);
$kpiOperationalActiveToday = count($favoritesToday) + count($operationalToday);
$kpiSpecialActiveToday = count($specialToday);
$kpiFreeOperators = count($freeOperators);
$kpiDestinationsWithoutStaff = count($activeOperationalWithoutStaff);
$kpiFavoritesActiveToday = count($favoritesToday);

// --------------------------------------------------
// ULTIMI ACCESSI
// --------------------------------------------------
$latestLogins = [];
$sqlLogins = "
    SELECT
        id,
        username,
        role,
        last_login_at,
        is_active
    FROM users
    WHERE last_login_at IS NOT NULL
    ORDER BY last_login_at DESC
    LIMIT 6
";

$resLogins = $db->query($sqlLogins);
if ($resLogins) {
    while ($row = $resLogins->fetch_assoc()) {
        $latestLogins[] = $row;
    }
    $resLogins->free();
}

require_once __DIR__ . '/templates/layout_top.php';
?>

<div class="dashboard-shell">

    <section class="hero-bar">
        <div class="hero-left">
            <div class="hero-kicker">
                <span class="pill">Dashboard operativa</span>

                <?php if ($isToday): ?>
                    <span class="pill pill-warning">Oggi</span>
                <?php else: ?>
                    <span class="pill">Data selezionata</span>
                <?php endif; ?>
            </div>

            <div class="hero-date"><?= h($humanDate) ?></div>

            <div class="hero-sub">
                Vista giornaliera con separazione tra destinazioni operative e speciali basata sul flag database
                <strong>cantieri.is_special</strong>.
            </div>

            <div class="quick-actions">
                <a class="btn btn-secondary" href="<?= h(app_url('modules/turni/planning.php?data=' . $selectedDate)) ?>">Planning</a>
                <a class="btn btn-secondary" href="<?= h(app_url('modules/turni/calendar.php?date=' . $selectedDate)) ?>">Calendario</a>
                <a class="btn btn-secondary" href="<?= h(app_url('modules/destinations/index.php')) ?>">Destinazioni</a>
                <a class="btn btn-secondary" href="<?= h(app_url('modules/operators/index.php')) ?>">Operatori</a>
            </div>
        </div>

        <div class="hero-nav">
            <a class="btn btn-secondary" href="?data=<?= h($prevDate) ?>">← Giorno precedente</a>
            <a class="btn btn-primary" href="?data=<?= h($today->format('Y-m-d')) ?>">Oggi</a>
            <a class="btn btn-secondary" href="?data=<?= h($nextDate) ?>">Giorno successivo →</a>
        </div>
    </section>

    <section class="kpi-grid">
        <div class="card kpi-card">
            <div class="kpi-label">Turni del giorno</div>
            <div class="kpi-value"><?= (int)$kpiTotalAssignments ?></div>
            <div class="kpi-note">Assegnazioni totali su <?= h($compactDate) ?></div>
        </div>

        <div class="card kpi-card">
            <div class="kpi-label">Cantieri operativi attivi</div>
            <div class="kpi-value"><?= (int)$kpiOperationalActiveToday ?></div>
            <div class="kpi-note">Solo destinazioni non speciali con personale assegnato</div>
        </div>

        <div class="card kpi-card">
            <div class="kpi-label">Preferite attive</div>
            <div class="kpi-value"><?= (int)$kpiFavoritesActiveToday ?></div>
            <div class="kpi-note">Destinazioni preferite presenti oggi</div>
        </div>

        <div class="card kpi-card">
            <div class="kpi-label">Destinazioni speciali</div>
            <div class="kpi-value"><?= (int)$kpiSpecialActiveToday ?></div>
            <div class="kpi-note">Basate su <strong>cantieri.is_special</strong></div>
        </div>

        <div class="card kpi-card">
            <div class="kpi-label">Operatori liberi</div>
            <div class="kpi-value"><?= (int)$kpiFreeOperators ?></div>
            <div class="kpi-note">Attivi ma senza turno nel giorno selezionato</div>
        </div>

        <div class="card kpi-card">
            <div class="kpi-label">Cantieri senza personale</div>
            <div class="kpi-value"><?= (int)$kpiDestinationsWithoutStaff ?></div>
            <div class="kpi-note">Solo cantieri operativi attivi senza assegnazioni</div>
        </div>
    </section>

    <section class="section-grid">
        <div class="stack">

            <div class="card panel">
                <div class="panel-header">
                    <div class="panel-title-wrap">
                        <div class="panel-title">Destinazioni preferite con personale</div>
                        <div class="panel-subtitle">In evidenza per l’utente corrente</div>
                    </div>
                    <div class="badge badge-primary"><?= count($favoritesToday) ?> attive</div>
                </div>

                <div class="panel-body">
                    <?php if (empty($favoritesToday)): ?>
                        <div class="empty-state">
                            Nessuna destinazione preferita assegnata in questa giornata.
                        </div>
                    <?php else: ?>
                        <div class="accordion-list">
                            <?php foreach ($favoritesToday as $destination): ?>
                                <details class="accordion-card is-favorite">
                                    <summary class="accordion-summary">
                                        <div class="accordion-main">
                                            <div class="accordion-title-row">
                                                <span class="badge badge-warning">★</span>
                                                <div class="accordion-title"><?= h($destination['commessa']) ?></div>
                                            </div>

                                            <div class="meta-row">
                                                <?php if (!empty($destination['cliente'])): ?>
                                                    <span class="badge"><?= h($destination['cliente']) ?></span>
                                                <?php endif; ?>

                                                <?php if (!empty($destination['comune'])): ?>
                                                    <span class="badge"><?= h($destination['comune']) ?></span>
                                                <?php endif; ?>

                                                <?php if (!empty($destination['codice_commessa'])): ?>
                                                    <span class="badge">Cod. <?= h($destination['codice_commessa']) ?></span>
                                                <?php endif; ?>

                                                <span class="badge badge-warning">Preferita</span>
                                            </div>
                                        </div>

                                        <div class="accordion-side">
                                            <span class="pill pill-primary"><?= (int)$destination['operator_count'] ?> operatori</span>
                                            <span class="accordion-chevron">⌄</span>
                                        </div>
                                    </summary>

                                    <div class="accordion-content">
                                        <div class="item-list">
                                            <?php foreach ($destination['assignments'] as $assignment): ?>
                                                <?php
                                                $displayRole = 'Operatore';
                                                if ((int)$assignment['is_capocantiere'] === 1 || (int)$assignment['capo_cantiere'] === 1) {
                                                    $displayRole = 'Responsabile';
                                                } elseif ((int)$assignment['preposto'] === 1) {
                                                    $displayRole = 'Preposto';
                                                } elseif (!empty($assignment['ruolo_operatore'])) {
                                                    $displayRole = (string)$assignment['ruolo_operatore'];
                                                }
                                                ?>
                                                <div class="list-item">
                                                    <div class="list-item-main">
                                                        <div class="list-item-title">
                                                            <?= h(trim(($assignment['cognome'] ?? '') . ' ' . ($assignment['nome'] ?? ''))) ?>
                                                        </div>
                                                        <div class="meta-row">
                                                            <span class="<?= h(badgeClassForRole($displayRole)) ?>"><?= h($displayRole) ?></span>
                                                            <span class="badge badge-success"><?= h(fmtTimeRange($assignment['ora_inizio'], $assignment['ora_fine'])) ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card panel">
                <div class="panel-header">
                    <div class="panel-title-wrap">
                        <div class="panel-title">Cantieri operativi oggi</div>
                        <div class="panel-subtitle">Solo destinazioni non speciali</div>
                    </div>
                    <div class="badge badge-primary"><?= count($operationalToday) ?> attivi</div>
                </div>

                <div class="panel-body">
                    <?php if (empty($operationalToday)): ?>
                        <div class="empty-state">
                            Nessun cantiere operativo con personale assegnato in questa giornata.
                        </div>
                    <?php else: ?>
                        <div class="accordion-list">
                            <?php foreach ($operationalToday as $destination): ?>
                                <details class="accordion-card">
                                    <summary class="accordion-summary">
                                        <div class="accordion-main">
                                            <div class="accordion-title-row">
                                                <?php if (!empty($destination['is_favorite'])): ?>
                                                    <span class="badge badge-warning">★</span>
                                                <?php endif; ?>

                                                <div class="accordion-title"><?= h($destination['commessa']) ?></div>
                                            </div>

                                            <div class="meta-row">
                                                <?php if (!empty($destination['cliente'])): ?>
                                                    <span class="badge"><?= h($destination['cliente']) ?></span>
                                                <?php endif; ?>

                                                <?php if (!empty($destination['comune'])): ?>
                                                    <span class="badge"><?= h($destination['comune']) ?></span>
                                                <?php endif; ?>

                                                <?php if (!empty($destination['codice_commessa'])): ?>
                                                    <span class="badge">Cod. <?= h($destination['codice_commessa']) ?></span>
                                                <?php endif; ?>

                                                <?php if (!empty($destination['is_favorite'])): ?>
                                                    <span class="badge badge-warning">Preferita</span>
                                                <?php endif; ?>

                                                <?php if ((int)$destination['visibile_calendario'] === 0): ?>
                                                    <span class="badge">Calendario nascosto</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="accordion-side">
                                            <span class="pill pill-primary"><?= (int)$destination['operator_count'] ?> operatori</span>
                                            <span class="accordion-chevron">⌄</span>
                                        </div>
                                    </summary>

                                    <div class="accordion-content">
                                        <div class="item-list">
                                            <?php foreach ($destination['assignments'] as $assignment): ?>
                                                <?php
                                                $displayRole = 'Operatore';
                                                if ((int)$assignment['is_capocantiere'] === 1 || (int)$assignment['capo_cantiere'] === 1) {
                                                    $displayRole = 'Responsabile';
                                                } elseif ((int)$assignment['preposto'] === 1) {
                                                    $displayRole = 'Preposto';
                                                } elseif (!empty($assignment['ruolo_operatore'])) {
                                                    $displayRole = (string)$assignment['ruolo_operatore'];
                                                }
                                                ?>
                                                <div class="list-item">
                                                    <div class="list-item-main">
                                                        <div class="list-item-title">
                                                            <?= h(trim(($assignment['cognome'] ?? '') . ' ' . ($assignment['nome'] ?? ''))) ?>
                                                        </div>
                                                        <div class="meta-row">
                                                            <span class="<?= h(badgeClassForRole($displayRole)) ?>"><?= h($displayRole) ?></span>
                                                            <span class="badge badge-success"><?= h(fmtTimeRange($assignment['ora_inizio'], $assignment['ora_fine'])) ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card panel panel-special">
                <div class="panel-header">
                    <div class="panel-title-wrap">
                        <div class="panel-title">Destinazioni speciali</div>
                        <div class="panel-subtitle">Separate dai cantieri operativi usando il flag database</div>
                    </div>
                    <div class="badge badge-purple"><?= count($specialToday) ?> speciali</div>
                </div>

                <div class="panel-body">
                    <?php if (empty($specialToday)): ?>
                        <div class="empty-state">
                            Nessuna destinazione speciale utilizzata in questa giornata.
                        </div>
                    <?php else: ?>
                        <div class="accordion-list">
                            <?php foreach ($specialToday as $destination): ?>
                                <details class="accordion-card is-special">
                                    <summary class="accordion-summary">
                                        <div class="accordion-main">
                                            <div class="accordion-title-row">
                                                <div class="accordion-title"><?= h($destination['commessa']) ?></div>
                                            </div>

                                            <div class="meta-row">
                                                <span class="badge badge-purple">Speciale</span>

                                                <?php if (!empty($destination['cliente'])): ?>
                                                    <span class="badge"><?= h($destination['cliente']) ?></span>
                                                <?php endif; ?>

                                                <?php if (!empty($destination['comune'])): ?>
                                                    <span class="badge"><?= h($destination['comune']) ?></span>
                                                <?php endif; ?>

                                                <?php if (!empty($destination['codice_commessa'])): ?>
                                                    <span class="badge">Cod. <?= h($destination['codice_commessa']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="accordion-side">
                                            <span class="pill pill-purple"><?= (int)$destination['operator_count'] ?> operatori</span>
                                            <span class="accordion-chevron">⌄</span>
                                        </div>
                                    </summary>

                                    <div class="accordion-content">
                                        <div class="item-list">
                                            <?php foreach ($destination['assignments'] as $assignment): ?>
                                                <?php
                                                $displayRole = 'Operatore';
                                                if ((int)$assignment['is_capocantiere'] === 1 || (int)$assignment['capo_cantiere'] === 1) {
                                                    $displayRole = 'Responsabile';
                                                } elseif ((int)$assignment['preposto'] === 1) {
                                                    $displayRole = 'Preposto';
                                                } elseif (!empty($assignment['ruolo_operatore'])) {
                                                    $displayRole = (string)$assignment['ruolo_operatore'];
                                                }
                                                ?>
                                                <div class="list-item">
                                                    <div class="list-item-main">
                                                        <div class="list-item-title">
                                                            <?= h(trim(($assignment['cognome'] ?? '') . ' ' . ($assignment['nome'] ?? ''))) ?>
                                                        </div>
                                                        <div class="meta-row">
                                                            <span class="<?= h(badgeClassForRole($displayRole)) ?>"><?= h($displayRole) ?></span>
                                                            <span class="badge badge-success"><?= h(fmtTimeRange($assignment['ora_inizio'], $assignment['ora_fine'])) ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <div class="stack">

            <div class="card panel">
                <div class="panel-header">
                    <div class="panel-title-wrap">
                        <div class="panel-title">Cantieri attivi senza personale</div>
                        <div class="panel-subtitle">Solo destinazioni operative attive non assegnate</div>
                    </div>
                    <div class="badge badge-primary"><?= count($activeOperationalWithoutStaff) ?> vuoti</div>
                </div>

                <div class="panel-body">
                    <?php if (empty($activeOperationalWithoutStaff)): ?>
                        <div class="empty-state">
                            Nessun cantiere operativo attivo senza personale.
                        </div>
                    <?php else: ?>
                        <details class="accordion-card">
                            <summary class="accordion-summary">
                                <div class="accordion-main">
                                    <div class="accordion-title">Apri elenco cantieri senza personale</div>
                                    <div class="meta-row">
                                        <span class="badge badge-success">Tendina chiusa all’apertura</span>
                                    </div>
                                </div>

                                <div class="accordion-side">
                                    <span class="pill pill-primary"><?= count($activeOperationalWithoutStaff) ?> elementi</span>
                                    <span class="accordion-chevron">⌄</span>
                                </div>
                            </summary>

                            <div class="accordion-content">
                                <div class="item-list">
                                    <?php foreach ($activeOperationalWithoutStaff as $destination): ?>
                                        <div class="list-item">
                                            <div class="list-item-main">
                                                <div class="list-item-title"><?= h($destination['commessa']) ?></div>
                                                <div class="list-item-sub">
                                                    <?php
                                                    $parts = [];
                                                    if (!empty($destination['cliente'])) {
                                                        $parts[] = $destination['cliente'];
                                                    }
                                                    if (!empty($destination['comune'])) {
                                                        $parts[] = $destination['comune'];
                                                    }
                                                    if (!empty($destination['codice_commessa'])) {
                                                        $parts[] = 'Cod. ' . $destination['codice_commessa'];
                                                    }
                                                    echo h(implode(' • ', $parts));
                                                    ?>
                                                </div>
                                            </div>

                                            <div class="list-item-side">
                                                <?php if (!empty($destination['is_favorite'])): ?>
                                                    <span class="badge badge-warning">★ Preferita</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Senza personale</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </details>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card panel">
                <div class="panel-header">
                    <div class="panel-title-wrap">
                        <div class="panel-title">Operatori liberi oggi</div>
                        <div class="panel-subtitle">Personale attivo senza assegnazioni nel giorno selezionato</div>
                    </div>
                    <div class="badge badge-primary"><?= count($freeOperators) ?> liberi</div>
                </div>

                <div class="panel-body">
                    <?php if (empty($freeOperators)): ?>
                        <div class="empty-state">
                            Nessun operatore libero in questa giornata.
                        </div>
                    <?php else: ?>
                        <details class="accordion-card">
                            <summary class="accordion-summary">
                                <div class="accordion-main">
                                    <div class="accordion-title">Apri elenco operatori liberi</div>
                                    <div class="meta-row">
                                        <span class="badge badge-success">Tendina chiusa all’apertura</span>
                                    </div>
                                </div>

                                <div class="accordion-side">
                                    <span class="pill pill-primary"><?= count($freeOperators) ?> operatori</span>
                                    <span class="accordion-chevron">⌄</span>
                                </div>
                            </summary>

                            <div class="accordion-content">
                                <div class="item-list">
                                    <?php foreach ($freeOperators as $operator): ?>
                                        <?php
                                        $displayRole = 'Operatore';
                                        if ((int)($operator['capo_cantiere'] ?? 0) === 1) {
                                            $displayRole = 'Responsabile';
                                        } elseif ((int)($operator['preposto'] ?? 0) === 1) {
                                            $displayRole = 'Preposto';
                                        } elseif (!empty($operator['tipologia'])) {
                                            $displayRole = (string)$operator['tipologia'];
                                        }
                                        ?>

                                        <div class="list-item">
                                            <div class="list-item-main">
                                                <div class="list-item-title">
                                                    <?= h(trim(($operator['cognome'] ?? '') . ' ' . ($operator['nome'] ?? ''))) ?>
                                                </div>
                                                <div class="list-item-sub"><?= h($displayRole) ?></div>
                                            </div>

                                            <div class="list-item-side">
                                                <span class="<?= h(badgeClassForRole($displayRole)) ?>"><?= h($displayRole) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </details>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card panel">
                <div class="panel-header">
                    <div class="panel-title-wrap">
                        <div class="panel-title">Ultimi accessi</div>
                        <div class="panel-subtitle">Panoramica rapida attività utenti</div>
                    </div>
                    <div class="badge badge-primary"><?= count($latestLogins) ?> utenti</div>
                </div>

                <div class="panel-body">
                    <?php if (empty($latestLogins)): ?>
                        <div class="empty-state">
                            Nessun accesso recente disponibile.
                        </div>
                    <?php else: ?>
                        <div class="item-list">
                            <?php foreach ($latestLogins as $login): ?>
                                <div class="list-item">
                                    <div class="list-item-main">
                                        <div class="list-item-title"><?= h($login['username'] ?? '') ?></div>
                                        <div class="list-item-sub">
                                            <?= h(ucfirst((string)($login['role'] ?? 'utente'))) ?>
                                            <?php if (!empty($login['last_login_at'])): ?>
                                                • <?= h(date('d/m/Y H:i', strtotime((string)$login['last_login_at']))) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="list-item-side">
                                        <?php if ((int)($login['is_active'] ?? 0) === 1): ?>
                                            <span class="badge badge-success">Attivo</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Disattivo</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </section>
</div>

<?php require_once __DIR__ . '/templates/layout_bottom.php'; ?>