<?php
// modules/operators/index.php

require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/components/op_base.php';
require_once __DIR__ . '/components/op_badges.php';
require_once __DIR__ . '/components/op_card.php';

require_login();
require_permission('operators.view');

$pageTitle    = 'Personale';
$pageSubtitle = 'Anteprima refactor gestione anagrafica personale';
$activeModule = 'operators';

$canCreateOperator = can('operators.create');
$canEditOperator   = can('operators.edit');
$canDeleteOperator = can('operators.delete');
$canViewUsers      = can('users.view');
$canEditUsers      = can('users.edit');

$permissions = [
    'edit' => $canEditOperator,
    'delete' => $canDeleteOperator,
    'viewUsers' => $canViewUsers,
    'editUsers' => $canEditUsers,
];

$db = db_connect();

function calculate_operator_net_shift_hours_new(?string $oraInizio, ?string $oraFine, $pausaPranzo): float
{
    $oraInizio = trim((string)$oraInizio);
    $oraFine   = trim((string)$oraFine);
    if ($oraInizio === '' || $oraFine === '') return 0.0;

    $start = DateTime::createFromFormat('H:i:s', $oraInizio) ?: DateTime::createFromFormat('H:i', $oraInizio);
    $end   = DateTime::createFromFormat('H:i:s', $oraFine) ?: DateTime::createFromFormat('H:i', $oraFine);
    if (!$start || !$end) return 0.0;

    $startMinutes = ((int)$start->format('H') * 60) + (int)$start->format('i');
    $endMinutes   = ((int)$end->format('H') * 60) + (int)$end->format('i');
    if ($endMinutes <= $startMinutes) $endMinutes += 1440;

    $grossMinutes = $endMinutes - $startMinutes;
    if ($grossMinutes <= 0) return 0.0;

    $pausa = trim((string)$pausaPranzo);
    $pausa = $pausa !== '' ? (float)str_replace(',', '.', $pausa) : 0.0;
    $pausaMinutes = (int)round($pausa * 60);

    if ($grossMinutes >= 480 && $pausaMinutes > 0) $grossMinutes -= $pausaMinutes;
    if ($grossMinutes < 0) $grossMinutes = 0;

    return round($grossMinutes / 60, 2);
}

function get_operator_attendance_map_new(mysqli $db): array
{
    $year = (int)date('Y');
    $map = [];
    $sql = "
        SELECT et.id_dipendente, et.ora_inizio, et.ora_fine,
               c.pausa_pranzo, c.is_special, c.counts_as_work, c.counts_as_absence
        FROM eventi_turni et
        INNER JOIN cantieri c ON c.id = et.id_cantiere
        WHERE YEAR(et.data) = ?
    ";
    $stmt = $db->prepare($sql);
    if (!$stmt) return $map;
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $dipendenteId = (int)($row['id_dipendente'] ?? 0);
            if ($dipendenteId <= 0) continue;
            if (!isset($map[$dipendenteId])) {
                $map[$dipendenteId] = [
                    'worked_hours' => 0.0,
                    'absence_hours' => 0.0,
                    'neutral_special_hours' => 0.0,
                    'percentage_absence' => 0.0,
                ];
            }
            $hours = calculate_operator_net_shift_hours_new($row['ora_inizio'] ?? '', $row['ora_fine'] ?? '', $row['pausa_pranzo'] ?? 0);
            if ($hours <= 0) continue;

            $countsAsWork = isset($row['counts_as_work']) ? (int)$row['counts_as_work'] : 1;
            $countsAsAbsence = isset($row['counts_as_absence']) ? (int)$row['counts_as_absence'] : 0;
            $isSpecial = !empty($row['is_special']) ? 1 : 0;

            if ($countsAsWork === 1) $map[$dipendenteId]['worked_hours'] += $hours;
            elseif ($countsAsAbsence === 1) $map[$dipendenteId]['absence_hours'] += $hours;
            elseif ($isSpecial === 1) $map[$dipendenteId]['neutral_special_hours'] += $hours;
        }
    }
    $stmt->close();

    foreach ($map as $dipId => $stats) {
        $worked = (float)$stats['worked_hours'];
        $absence = (float)$stats['absence_hours'];
        $base = $worked + $absence;
        $map[$dipId]['worked_hours'] = round($worked, 2);
        $map[$dipId]['absence_hours'] = round($absence, 2);
        $map[$dipId]['neutral_special_hours'] = round((float)$stats['neutral_special_hours'], 2);
        $map[$dipId]['percentage_absence'] = $base > 0 ? round(($absence / $base) * 100, 1) : 0.0;
    }
    return $map;
}

$attendanceMap = get_operator_attendance_map_new($db);

$sql = "
    SELECT d.*, u.id AS user_id, u.username AS user_username, u.role AS user_role,
           u.is_active AS user_is_active, u.can_login_web, u.can_login_app
    FROM dipendenti d
    LEFT JOIN users u ON u.dipendente_id = d.id
    ORDER BY d.cognome ASC, d.nome ASC
";
$res = $db->query($sql);
$operatori = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $dipId = (int)($row['id'] ?? 0);
        $row['attendance'] = $attendanceMap[$dipId] ?? [
            'worked_hours' => 0.0,
            'absence_hours' => 0.0,
            'neutral_special_hours' => 0.0,
            'percentage_absence' => 0.0,
        ];
        $operatori[] = $row;
    }
}
$totaleOperatori = count($operatori);

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<div class="content-card operators-refactor-page">
    <div class="toolbar" autocomplete="off">
        <div class="toolbar-left">
            <input type="text" id="operatorsSearchInput" placeholder="Cerca nome, cognome, email, telefono, username, ruolo o matricola..." class="toolbar-search" autocomplete="off">
            <select id="operatorsStatusSelect" class="field-sm">
                <option value="all">Tutti</option>
                <option value="active">Attivi</option>
                <option value="inactive">Disattivi</option>
                <option value="with-account">Con account</option>
                <option value="without-account">Senza account</option>
                <option value="account-active">Con account attivo</option>
                <option value="web-access">Con accesso web</option>
                <option value="app-access">Con accesso app</option>
                <option value="preposto">Preposto</option>
                <option value="responsabile">Responsabile</option>
            </select>
            <button type="button" id="operatorsResetBtn" class="btn btn-ghost">Reset</button>
        </div>
        <div class="toolbar-right">
            <?php if ($canCreateOperator): ?>
                <a href="<?php echo op_h(app_url('modules/operators/edit.php')); ?>" class="btn btn-primary">+ Nuova persona</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="text-muted mb-3" style="font-size:13px;">
        <span id="operatorsVisibleCount"><?php echo $totaleOperatori; ?></span>
        persone visibili su <?php echo $totaleOperatori; ?>
    </div>

    <?php if (!empty($operatori)): ?>
        <div class="operators-grid-wrap" id="operatorsGridWrap">
            <div class="operators-grid" id="operatorsGrid">
                <?php foreach ($operatori as $op): ?>
                    <?php echo op_card($op, $permissions); ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state">Nessuna persona presente.</div>
    <?php endif; ?>
</div>

<script>
(function(){
    const searchInput = document.getElementById('operatorsSearchInput');
    const statusSelect = document.getElementById('operatorsStatusSelect');
    const resetBtn = document.getElementById('operatorsResetBtn');
    const visibleCount = document.getElementById('operatorsVisibleCount');
    const items = Array.from(document.querySelectorAll('.operator-card'));

    function itemMatchesStatus(item, status){
        if(status === 'all') return true;
        if(status === 'active' || status === 'inactive') return item.dataset.status === status;
        if(status === 'with-account' || status === 'without-account') return item.dataset.account === status;
        if(status === 'account-active') return item.dataset.accountActive === '1';
        if(status === 'web-access') return item.dataset.webAccess === '1';
        if(status === 'app-access') return item.dataset.appAccess === '1';
        if(status === 'preposto') return item.dataset.preposto === '1';
        if(status === 'responsabile') return item.dataset.responsabile === '1';
        return true;
    }

    function applyFilters(){
        const q = (searchInput?.value || '').trim().toLowerCase();
        const status = statusSelect?.value || 'all';
        let count = 0;
        items.forEach(item => {
            const search = item.dataset.search || item.textContent.toLowerCase();
            const ok = (!q || search.includes(q)) && itemMatchesStatus(item, status);
            item.style.display = ok ? '' : 'none';
            if(ok) count++;
        });
        if(visibleCount) visibleCount.textContent = String(count);
    }

    searchInput?.addEventListener('input', applyFilters);
    statusSelect?.addEventListener('change', applyFilters);
    resetBtn?.addEventListener('click', function(){
        if(searchInput) searchInput.value = '';
        if(statusSelect) statusSelect.value = 'all';
        applyFilters();
    });
    applyFilters();
})();
</script>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>
