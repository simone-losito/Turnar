<?php
// modules/operators/index.php

require_once __DIR__ . '/../../core/helpers.php';

require_login();
require_permission('operators.view');

$pageTitle    = 'Personale';
$pageSubtitle = 'Gestione anagrafica del personale';
$activeModule = 'operators';

$canCreateOperator = can('operators.create');
$canEditOperator   = can('operators.edit');
$canDeleteOperator = can('operators.delete');
$canViewUsers      = can('users.view');
$canEditUsers      = can('users.edit');

// DB
$db = db_connect();

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function calculate_operator_net_shift_hours(?string $oraInizio, ?string $oraFine, $pausaPranzo): float
{
    $oraInizio = trim((string)$oraInizio);
    $oraFine   = trim((string)$oraFine);

    if ($oraInizio === '' || $oraFine === '') {
        return 0.0;
    }

    $start = DateTime::createFromFormat('H:i:s', $oraInizio) ?: DateTime::createFromFormat('H:i', $oraInizio);
    $end   = DateTime::createFromFormat('H:i:s', $oraFine)   ?: DateTime::createFromFormat('H:i', $oraFine);

    if (!$start || !$end) {
        return 0.0;
    }

    $startMinutes = ((int)$start->format('H') * 60) + (int)$start->format('i');
    $endMinutes   = ((int)$end->format('H') * 60) + (int)$end->format('i');

    if ($endMinutes <= $startMinutes) {
        $endMinutes += 1440;
    }

    $grossMinutes = $endMinutes - $startMinutes;
    if ($grossMinutes <= 0) {
        return 0.0;
    }

    $pausa = trim((string)$pausaPranzo);
    $pausa = $pausa !== '' ? (float)str_replace(',', '.', $pausa) : 0.0;
    $pausaMinutes = (int)round($pausa * 60);

    // Regola progetto: pausa si scala solo se il turno lordo è almeno 8 ore
    if ($grossMinutes >= 480 && $pausaMinutes > 0) {
        $grossMinutes -= $pausaMinutes;
    }

    if ($grossMinutes < 0) {
        $grossMinutes = 0;
    }

    return round($grossMinutes / 60, 2);
}

function get_operator_attendance_map(mysqli $db): array
{
    $year = (int)date('Y');
    $map = [];

    $sql = "
        SELECT
            et.id_dipendente,
            et.ora_inizio,
            et.ora_fine,
            c.pausa_pranzo,
            c.is_special,
            c.counts_as_work,
            c.counts_as_absence
        FROM eventi_turni et
        INNER JOIN cantieri c ON c.id = et.id_cantiere
        WHERE YEAR(et.data) = ?
    ";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return $map;
    }

    $stmt->bind_param('i', $year);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $dipendenteId = (int)($row['id_dipendente'] ?? 0);
            if ($dipendenteId <= 0) {
                continue;
            }

            if (!isset($map[$dipendenteId])) {
                $map[$dipendenteId] = [
                    'worked_hours' => 0.0,
                    'absence_hours' => 0.0,
                    'neutral_special_hours' => 0.0,
                    'percentage_absence' => 0.0,
                ];
            }

            $hours = calculate_operator_net_shift_hours(
                (string)($row['ora_inizio'] ?? ''),
                (string)($row['ora_fine'] ?? ''),
                $row['pausa_pranzo'] ?? 0
            );

            if ($hours <= 0) {
                continue;
            }

            $countsAsWork = isset($row['counts_as_work']) ? (int)$row['counts_as_work'] : 1;
            $countsAsAbsence = isset($row['counts_as_absence']) ? (int)$row['counts_as_absence'] : 0;
            $isSpecial = !empty($row['is_special']) ? 1 : 0;

            if ($countsAsWork === 1) {
                $map[$dipendenteId]['worked_hours'] += $hours;
            } elseif ($countsAsAbsence === 1) {
                $map[$dipendenteId]['absence_hours'] += $hours;
            } elseif ($isSpecial === 1) {
                $map[$dipendenteId]['neutral_special_hours'] += $hours;
            }
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

$attendanceMap = get_operator_attendance_map($db);

// Carico personale + eventuale account collegato
$sql = "
    SELECT
        d.*,
        u.id AS user_id,
        u.username AS user_username,
        u.role AS user_role,
        u.is_active AS user_is_active,
        u.can_login_web,
        u.can_login_app
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

<style>
.operators-grid-wrap{
    display:block;
}

.operators-grid-wrap.hidden{
    display:none;
}

.operators-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(300px,1fr));
    gap:16px;
}

.operator-card{
    display:flex;
    flex-direction:column;
    gap:14px;
}

.operator-top{
    display:flex;
    align-items:center;
    gap:12px;
    min-width:0;
}

.operator-avatar img,
.operator-row-avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
}

.operator-main,
.operator-row-title{
    min-width:0;
}

.operator-name,
.operator-row-name{
    color:var(--text);
    line-height:1.2;
    word-break:break-word;
}

.operator-name{
    font-size:16px;
    font-weight:800;
}

.operator-row-name{
    font-size:15px;
    font-weight:900;
}

.operator-role{
    margin-top:5px;
    font-size:12px;
    color:var(--muted);
}

.operator-badges,
.operator-row-badges{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    margin-top:8px;
}

.operator-row-badges{
    margin-top:5px;
    gap:6px;
}

.mini-pill{
    display:inline-flex;
    align-items:center;
    padding:6px 10px;
    border-radius:var(--badge-radius);
    font-size:11px;
    font-weight:700;
    border:1px solid var(--line);
    line-height:1;
}

.mini-pill.account-on{
    background:rgba(52,211,153,.14);
    color:#059669;
    border-color:rgba(52,211,153,.24);
}

.mini-pill.account-off{
    background:rgba(248,113,113,.14);
    color:#dc2626;
    border-color:rgba(248,113,113,.28);
}

.mini-pill.user-role{
    background:color-mix(in srgb, var(--primary) 18%, transparent);
    color:color-mix(in srgb, var(--primary) 68%, var(--text));
    border-color:color-mix(in srgb, var(--primary) 26%, transparent);
}

.mini-pill.app-flag{
    background:color-mix(in srgb, var(--primary-2) 18%, transparent);
    color:color-mix(in srgb, var(--primary-2) 70%, var(--text));
    border-color:color-mix(in srgb, var(--primary-2) 28%, transparent);
}

.mini-pill.attendance{
    background:rgba(248,113,113,.14);
    color:#dc2626;
    border-color:rgba(248,113,113,.28);
}

.mini-pill.flag-role{
    background:rgba(251,191,36,.14);
    color:#b45309;
    border-color:rgba(251,191,36,.26);
}

.operator-info{
    display:grid;
    gap:8px;
    font-size:13px;
    color:var(--text);
}

.operator-info-row{
    display:flex;
    gap:8px;
    align-items:flex-start;
    min-width:0;
}

.operator-info-label{
    color:var(--muted);
    min-width:110px;
    flex:0 0 auto;
}

.operator-info-value{
    word-break:break-word;
    color:var(--text);
}

.operator-footer{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
    margin-top:auto;
}

.operator-actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}

.operators-list-wrap{
    display:none;
}

.operators-list-wrap.active{
    display:block;
}

.operators-list{
    display:flex;
    flex-direction:column;
    gap:10px;
}

.operator-row{
    display:grid;
    grid-template-columns:minmax(280px, 2.2fr) minmax(130px, .9fr) minmax(180px, 1.2fr) minmax(140px, .9fr) minmax(190px, 1.2fr) auto;
    gap:12px;
    align-items:center;
}

.operator-row-main{
    display:flex;
    align-items:center;
    gap:12px;
    min-width:0;
}

.operator-row-col{
    min-width:0;
    display:flex;
    flex-direction:column;
    gap:4px;
}

.operator-row-label{
    color:var(--muted);
    font-size:11px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.04em;
}

.operator-row-value{
    color:var(--text);
    font-size:13px;
    font-weight:700;
    word-break:break-word;
}

.operator-row-actions{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:8px;
    flex-wrap:wrap;
}

@media (max-width: 1280px){
    .operator-row{
        grid-template-columns:1fr;
        align-items:flex-start;
    }

    .operator-row-actions{
        justify-content:flex-start;
    }
}

@media (max-width: 720px){
    .operators-grid{
        grid-template-columns:1fr;
    }

    .operator-info-label{
        min-width:88px;
    }
}
</style>

<div class="content-card">

    <div class="toolbar" autocomplete="off">
        <div class="toolbar-left">
            <input
                type="text"
                id="operatorsSearchInput"
                placeholder="Cerca nome, cognome, email, telefono, username, ruolo o matricola..."
                class="toolbar-search"
                autocomplete="off"
            >

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

            <div class="view-toggle">
                <button type="button" class="toggle-item active" id="viewCardsBtn">Vista card</button>
                <button type="button" class="toggle-item" id="viewListBtn">Vista elenco</button>
            </div>

            <button type="button" id="operatorsResetBtn" class="btn btn-ghost">Reset</button>
        </div>

        <div class="toolbar-right">
            <?php if ($canCreateOperator): ?>
                <a href="<?php echo h(app_url('modules/operators/edit.php')); ?>" class="btn btn-primary">+ Nuova persona</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="text-muted mb-3" style="font-size:13px;">
        <span id="operatorsVisibleCount"><?php echo $totaleOperatori; ?></span>
        person<?php echo $totaleOperatori === 1 ? 'a' : 'e'; ?>
        <span id="operatorsCounterSuffix"><?php echo $totaleOperatori === 1 ? ' visibile' : ' visibili'; ?></span>
        su <?php echo $totaleOperatori; ?>
    </div>

    <?php if (!empty($operatori)): ?>

        <div class="operators-grid-wrap" id="operatorsGridWrap">
            <div class="operators-grid" id="operatorsGrid">
                <?php foreach ($operatori as $op): ?>
                    <?php
                        $foto = trim((string)($op['foto'] ?? ''));
                        $nome = trim((string)($op['nome'] ?? ''));
                        $cognome = trim((string)($op['cognome'] ?? ''));
                        $nomeCompleto = trim($nome . ' ' . $cognome);
                        $nomeInvertito = trim($cognome . ' ' . $nome);
                        $tipologia = trim((string)($op['tipologia'] ?? ''));
                        $telefono = trim((string)($op['telefono'] ?? ''));
                        $email = trim((string)($op['email'] ?? ''));
                        $matricola = trim((string)($op['matricola'] ?? ''));
                        $livello = trim((string)($op['livello'] ?? ''));
                        $attivo = !empty($op['attivo']);
                        $isPreposto = !empty($op['preposto']);
                        $isResponsabile = !empty($op['capo_cantiere']);

                        $userId = (int)($op['user_id'] ?? 0);
                        $hasAccount = $userId > 0;
                        $userUsername = trim((string)($op['user_username'] ?? ''));
                        $userRole = trim((string)($op['user_role'] ?? ''));
                        $userIsActive = $hasAccount ? !empty($op['user_is_active']) : false;
                        $canLoginWeb = $hasAccount ? !empty($op['can_login_web']) : false;
                        $canLoginApp = $hasAccount ? !empty($op['can_login_app']) : false;

                        $attendance = is_array($op['attendance'] ?? null) ? $op['attendance'] : [];
                        $absencePercent = (float)($attendance['percentage_absence'] ?? 0);

                        $iniziali = '';
                        if ($nome !== '') {
                            $iniziali .= mb_strtoupper(mb_substr($nome, 0, 1, 'UTF-8'), 'UTF-8');
                        }
                        if ($cognome !== '') {
                            $iniziali .= mb_strtoupper(mb_substr($cognome, 0, 1, 'UTF-8'), 'UTF-8');
                        }
                        if ($iniziali === '') {
                            $iniziali = 'PS';
                        }

                        $searchBlob = implode(' ', [
                            $nome,
                            $cognome,
                            $nomeCompleto,
                            $nomeInvertito,
                            $email,
                            $telefono,
                            $tipologia,
                            $matricola,
                            $livello,
                            $userUsername,
                            $userRole,
                            $isPreposto ? 'preposto' : '',
                            $isResponsabile ? 'responsabile' : '',
                            'assenza',
                            (string)$absencePercent,
                        ]);

                        $accountFilter = $hasAccount ? 'with-account' : 'without-account';
                        $userRoleLabel = $userRole !== '' && function_exists('role_label')
                            ? role_label($userRole)
                            : ($userRole !== '' ? ucfirst($userRole) : '');
                    ?>

                    <div
                        class="entity-card operator-card"
                        data-search="<?php echo h(mb_strtolower($searchBlob, 'UTF-8')); ?>"
                        data-status="<?php echo $attivo ? 'active' : 'inactive'; ?>"
                        data-account="<?php echo h($accountFilter); ?>"
                        data-account-active="<?php echo $userIsActive ? '1' : '0'; ?>"
                        data-web-access="<?php echo $canLoginWeb ? '1' : '0'; ?>"
                        data-app-access="<?php echo $canLoginApp ? '1' : '0'; ?>"
                        data-preposto="<?php echo $isPreposto ? '1' : '0'; ?>"
                        data-responsabile="<?php echo $isResponsabile ? '1' : '0'; ?>"
                    >
                        <div class="operator-top">
                            <div class="entity-avatar lg">
                                <?php if ($foto !== ''): ?>
                                    <img src="<?php echo h($foto); ?>" alt="Foto personale">
                                <?php else: ?>
                                    <?php echo h($iniziali); ?>
                                <?php endif; ?>
                            </div>

                            <div class="operator-main">
                                <div class="operator-name"><?php echo h($nomeCompleto !== '' ? $nomeCompleto : 'Persona senza nome'); ?></div>
                                <div class="operator-role">
                                    <?php echo h($tipologia !== '' ? $tipologia : 'Ruolo non indicato'); ?>
                                </div>

                                <div class="operator-badges">
                                    <?php if ($hasAccount): ?>
                                        <span class="mini-pill <?php echo $userIsActive ? 'account-on' : 'account-off'; ?>">
                                            <?php echo $userIsActive ? 'Account attivo' : 'Account disattivo'; ?>
                                        </span>

                                        <?php if ($userRoleLabel !== ''): ?>
                                            <span class="mini-pill user-role"><?php echo h($userRoleLabel); ?></span>
                                        <?php endif; ?>

                                        <?php if ($canLoginWeb): ?>
                                            <span class="mini-pill app-flag">Web</span>
                                        <?php endif; ?>

                                        <?php if ($canLoginApp): ?>
                                            <span class="mini-pill app-flag">App</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="mini-pill account-off">Nessun account</span>
                                    <?php endif; ?>

                                    <?php if ($isPreposto): ?>
                                        <span class="mini-pill flag-role">Preposto</span>
                                    <?php endif; ?>

                                    <?php if ($isResponsabile): ?>
                                        <span class="mini-pill flag-role">Responsabile</span>
                                    <?php endif; ?>

                                    <span class="mini-pill attendance">Assenze <?php echo h(number_format($absencePercent, 1, ',', '.')); ?>%</span>
                                </div>
                            </div>
                        </div>

                        <div class="operator-info">
                            <div class="operator-info-row">
                                <div class="operator-info-label">Email</div>
                                <div class="operator-info-value"><?php echo h($email !== '' ? $email : '-'); ?></div>
                            </div>

                            <div class="operator-info-row">
                                <div class="operator-info-label">Telefono</div>
                                <div class="operator-info-value"><?php echo h($telefono !== '' ? $telefono : '-'); ?></div>
                            </div>

                            <div class="operator-info-row">
                                <div class="operator-info-label">Matricola</div>
                                <div class="operator-info-value"><?php echo h($matricola !== '' ? $matricola : '-'); ?></div>
                            </div>

                            <div class="operator-info-row">
                                <div class="operator-info-label">Livello</div>
                                <div class="operator-info-value"><?php echo h($livello !== '' ? $livello : '-'); ?></div>
                            </div>

                            <div class="operator-info-row">
                                <div class="operator-info-label">Username</div>
                                <div class="operator-info-value"><?php echo h($userUsername !== '' ? $userUsername : '-'); ?></div>
                            </div>
                        </div>

                        <div class="operator-footer">
                            <span class="status-pill <?php echo $attivo ? 'is-active' : 'is-inactive'; ?>">
                                <?php echo $attivo ? 'Attivo' : 'Disattivo'; ?>
                            </span>

                            <div class="operator-actions">
                                <?php if ($canEditOperator): ?>
                                    <a href="<?php echo h(app_url('modules/operators/edit.php?id=' . (int)$op['id'])); ?>" class="btn btn-secondary btn-sm">
                                        Modifica
                                    </a>
                                <?php endif; ?>

                                <?php if ($hasAccount && ($canViewUsers || $canEditUsers)): ?>
                                    <a href="<?php echo h(app_url('modules/users/edit.php?id=' . $userId)); ?>" class="btn btn-ghost btn-sm">
                                        Utente
                                    </a>
                                <?php endif; ?>

                                <?php if ($canDeleteOperator): ?>
                                    <a
                                        href="<?php echo h(app_url('modules/operators/delete.php?id=' . (int)$op['id'])); ?>"
                                        class="btn btn-danger btn-sm"
                                        onclick="return confirm('Sei sicuro di voler eliminare questa persona?');"
                                    >
                                        Elimina
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="operators-list-wrap" id="operatorsListWrap">
            <div class="operators-list" id="operatorsList">
                <?php foreach ($operatori as $op): ?>
                    <?php
                        $foto = trim((string)($op['foto'] ?? ''));
                        $nome = trim((string)($op['nome'] ?? ''));
                        $cognome = trim((string)($op['cognome'] ?? ''));
                        $nomeCompleto = trim($nome . ' ' . $cognome);
                        $nomeInvertito = trim($cognome . ' ' . $nome);
                        $tipologia = trim((string)($op['tipologia'] ?? ''));
                        $telefono = trim((string)($op['telefono'] ?? ''));
                        $email = trim((string)($op['email'] ?? ''));
                        $matricola = trim((string)($op['matricola'] ?? ''));
                        $livello = trim((string)($op['livello'] ?? ''));
                        $attivo = !empty($op['attivo']);
                        $isPreposto = !empty($op['preposto']);
                        $isResponsabile = !empty($op['capo_cantiere']);

                        $userId = (int)($op['user_id'] ?? 0);
                        $hasAccount = $userId > 0;
                        $userUsername = trim((string)($op['user_username'] ?? ''));
                        $userRole = trim((string)($op['user_role'] ?? ''));
                        $userIsActive = $hasAccount ? !empty($op['user_is_active']) : false;
                        $canLoginWeb = $hasAccount ? !empty($op['can_login_web']) : false;
                        $canLoginApp = $hasAccount ? !empty($op['can_login_app']) : false;

                        $attendance = is_array($op['attendance'] ?? null) ? $op['attendance'] : [];
                        $absencePercent = (float)($attendance['percentage_absence'] ?? 0);

                        $iniziali = '';
                        if ($nome !== '') {
                            $iniziali .= mb_strtoupper(mb_substr($nome, 0, 1, 'UTF-8'), 'UTF-8');
                        }
                        if ($cognome !== '') {
                            $iniziali .= mb_strtoupper(mb_substr($cognome, 0, 1, 'UTF-8'), 'UTF-8');
                        }
                        if ($iniziali === '') {
                            $iniziali = 'PS';
                        }

                        $searchBlob = implode(' ', [
                            $nome,
                            $cognome,
                            $nomeCompleto,
                            $nomeInvertito,
                            $email,
                            $telefono,
                            $tipologia,
                            $matricola,
                            $livello,
                            $userUsername,
                            $userRole,
                            $isPreposto ? 'preposto' : '',
                            $isResponsabile ? 'responsabile' : '',
                            'assenza',
                            (string)$absencePercent,
                        ]);

                        $accountFilter = $hasAccount ? 'with-account' : 'without-account';
                        $userRoleLabel = $userRole !== '' && function_exists('role_label')
                            ? role_label($userRole)
                            : ($userRole !== '' ? ucfirst($userRole) : '');
                    ?>

                    <div
                        class="entity-row operator-row"
                        data-search="<?php echo h(mb_strtolower($searchBlob, 'UTF-8')); ?>"
                        data-status="<?php echo $attivo ? 'active' : 'inactive'; ?>"
                        data-account="<?php echo h($accountFilter); ?>"
                        data-account-active="<?php echo $userIsActive ? '1' : '0'; ?>"
                        data-web-access="<?php echo $canLoginWeb ? '1' : '0'; ?>"
                        data-app-access="<?php echo $canLoginApp ? '1' : '0'; ?>"
                        data-preposto="<?php echo $isPreposto ? '1' : '0'; ?>"
                        data-responsabile="<?php echo $isResponsabile ? '1' : '0'; ?>"
                    >
                        <div class="operator-row-main">
                            <div class="entity-avatar md">
                                <?php if ($foto !== ''): ?>
                                    <img src="<?php echo h($foto); ?>" alt="Foto personale">
                                <?php else: ?>
                                    <?php echo h($iniziali); ?>
                                <?php endif; ?>
                            </div>

                            <div class="operator-row-title">
                                <div class="operator-row-name">
                                    <?php echo h($nomeCompleto !== '' ? $nomeCompleto : 'Persona senza nome'); ?>
                                </div>
                                <div class="operator-row-badges">
                                    <?php if ($tipologia !== ''): ?>
                                        <span class="mini-pill user-role"><?php echo h($tipologia); ?></span>
                                    <?php endif; ?>

                                    <?php if ($isPreposto): ?>
                                        <span class="mini-pill flag-role">Preposto</span>
                                    <?php endif; ?>

                                    <?php if ($isResponsabile): ?>
                                        <span class="mini-pill flag-role">Responsabile</span>
                                    <?php endif; ?>

                                    <span class="mini-pill attendance">Assenze <?php echo h(number_format($absencePercent, 1, ',', '.')); ?>%</span>

                                    <?php if ($hasAccount): ?>
                                        <span class="mini-pill <?php echo $userIsActive ? 'account-on' : 'account-off'; ?>">
                                            <?php echo $userIsActive ? 'Account attivo' : 'Account disattivo'; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="mini-pill account-off">Nessun account</span>
                                    <?php endif; ?>

                                    <?php if ($canLoginWeb): ?>
                                        <span class="mini-pill app-flag">Web</span>
                                    <?php endif; ?>

                                    <?php if ($canLoginApp): ?>
                                        <span class="mini-pill app-flag">App</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="operator-row-col">
                            <div class="operator-row-label">Email / Telefono</div>
                            <div class="operator-row-value">
                                <?php
                                $parts = [];
                                if ($email !== '') {
                                    $parts[] = $email;
                                }
                                if ($telefono !== '') {
                                    $parts[] = $telefono;
                                }
                                echo h(!empty($parts) ? implode(' • ', $parts) : '-');
                                ?>
                            </div>
                        </div>

                        <div class="operator-row-col">
                            <div class="operator-row-label">Matricola / Livello</div>
                            <div class="operator-row-value">
                                <?php
                                $parts = [];
                                if ($matricola !== '') {
                                    $parts[] = 'Matr. ' . $matricola;
                                }
                                if ($livello !== '') {
                                    $parts[] = $livello;
                                }
                                echo h(!empty($parts) ? implode(' • ', $parts) : '-');
                                ?>
                            </div>
                        </div>

                        <div class="operator-row-col">
                            <div class="operator-row-label">Stato</div>
                            <div class="operator-row-value">
                                <span class="status-pill <?php echo $attivo ? 'is-active' : 'is-inactive'; ?>">
                                    <?php echo $attivo ? 'Attivo' : 'Disattivo'; ?>
                                </span>
                            </div>
                        </div>

                        <div class="operator-row-col">
                            <div class="operator-row-label">Account / Username</div>
                            <div class="operator-row-value">
                                <?php
                                $parts = [];
                                if ($userRoleLabel !== '') {
                                    $parts[] = $userRoleLabel;
                                }
                                if ($userUsername !== '') {
                                    $parts[] = $userUsername;
                                }
                                echo h(!empty($parts) ? implode(' • ', $parts) : ($hasAccount ? 'Account collegato' : 'Nessun account'));
                                ?>
                            </div>
                        </div>

                        <div class="operator-row-actions">
                            <?php if ($canEditOperator): ?>
                                <a href="<?php echo h(app_url('modules/operators/edit.php?id=' . (int)$op['id'])); ?>" class="btn btn-secondary btn-sm">
                                    Modifica
                                </a>
                            <?php endif; ?>

                            <?php if ($hasAccount && ($canViewUsers || $canEditUsers)): ?>
                                <a href="<?php echo h(app_url('modules/users/edit.php?id=' . $userId)); ?>" class="btn btn-ghost btn-sm">
                                    Utente
                                </a>
                            <?php endif; ?>

                            <?php if ($canDeleteOperator): ?>
                                <a
                                    href="<?php echo h(app_url('modules/operators/delete.php?id=' . (int)$op['id'])); ?>"
                                    class="btn btn-danger btn-sm"
                                    onclick="return confirm('Sei sicuro di voler eliminare questa persona?');"
                                >
                                    Elimina
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="empty-state hidden-by-filter" id="operatorsEmptyState">
            <h3 class="empty-state-title">Nessuna persona trovata</h3>
            <div class="empty-state-text">Nessuna persona corrisponde ai filtri attuali. Prova a cambiare ricerca o selezione.</div>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <h3 class="empty-state-title">Nessuna persona presente</h3>
            <div class="empty-state-text">Non ci sono ancora anagrafiche del personale nel sistema.</div>
        </div>
    <?php endif; ?>

</div>

<script>
(function () {
    const searchInput = document.getElementById('operatorsSearchInput');
    const statusSelect = document.getElementById('operatorsStatusSelect');
    const resetBtn = document.getElementById('operatorsResetBtn');
    const visibleCount = document.getElementById('operatorsVisibleCount');
    const counterSuffix = document.getElementById('operatorsCounterSuffix');
    const emptyState = document.getElementById('operatorsEmptyState');

    const gridWrap = document.getElementById('operatorsGridWrap');
    const listWrap = document.getElementById('operatorsListWrap');
    const cardsBtn = document.getElementById('viewCardsBtn');
    const listBtn = document.getElementById('viewListBtn');

    const allItems = Array.from(document.querySelectorAll('.operator-card, .operator-row'));

    if (!searchInput || !statusSelect || !visibleCount || !counterSuffix) {
        return;
    }

    function normalizeText(value) {
        return (value || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function setView(view) {
        const finalView = (view === 'list') ? 'list' : 'cards';

        if (gridWrap && listWrap && cardsBtn && listBtn) {
            if (finalView === 'list') {
                gridWrap.classList.add('hidden');
                listWrap.classList.add('active');
                cardsBtn.classList.remove('active');
                listBtn.classList.add('active');
            } else {
                gridWrap.classList.remove('hidden');
                listWrap.classList.remove('active');
                cardsBtn.classList.add('active');
                listBtn.classList.remove('active');
            }
        }

        try {
            localStorage.setItem('turnar_operators_view', finalView);
        } catch (e) {}
    }

    function applyFilters() {
        const query = normalizeText(searchInput.value);
        const tokens = query === '' ? [] : query.split(' ').filter(Boolean);
        const selectedStatus = statusSelect.value;

        let matched = 0;
        const countedIds = new Set();

        allItems.forEach(function (item) {
            const searchText = normalizeText(item.getAttribute('data-search') || '');
            const cardStatus = item.getAttribute('data-status') || 'inactive';
            const accountStatus = item.getAttribute('data-account') || 'without-account';
            const accountActive = item.getAttribute('data-account-active') === '1';
            const webAccess = item.getAttribute('data-web-access') === '1';
            const appAccess = item.getAttribute('data-app-access') === '1';
            const isPreposto = item.getAttribute('data-preposto') === '1';
            const isResponsabile = item.getAttribute('data-responsabile') === '1';

            const matchesStatus =
                selectedStatus === 'all' ||
                (selectedStatus === 'active' && cardStatus === 'active') ||
                (selectedStatus === 'inactive' && cardStatus === 'inactive') ||
                (selectedStatus === 'with-account' && accountStatus === 'with-account') ||
                (selectedStatus === 'without-account' && accountStatus === 'without-account') ||
                (selectedStatus === 'account-active' && accountActive) ||
                (selectedStatus === 'web-access' && webAccess) ||
                (selectedStatus === 'app-access' && appAccess) ||
                (selectedStatus === 'preposto' && isPreposto) ||
                (selectedStatus === 'responsabile' && isResponsabile);

            const matchesSearch =
                tokens.length === 0 ||
                tokens.every(function (token) {
                    return searchText.includes(token);
                });

            const visible = matchesStatus && matchesSearch;

            item.classList.toggle('hidden-by-filter', !visible);

            if (visible) {
                const editHref = item.querySelector('a[href*="modules/operators/edit.php?id="]');
                let uniqueId = '';

                if (editHref) {
                    uniqueId = editHref.getAttribute('href') || '';
                } else {
                    uniqueId = item.getAttribute('data-search') || Math.random().toString();
                }

                if (!countedIds.has(uniqueId)) {
                    countedIds.add(uniqueId);
                    matched++;
                }
            }
        });

        visibleCount.textContent = matched.toString();
        counterSuffix.textContent = matched === 1 ? ' visibile' : ' visibili';

        if (emptyState) {
            emptyState.classList.toggle('hidden-by-filter', matched !== 0);
        }
    }

    searchInput.addEventListener('input', applyFilters);
    statusSelect.addEventListener('change', applyFilters);

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            searchInput.value = '';
            statusSelect.value = 'all';
            applyFilters();
            searchInput.focus();
        });
    }

    if (cardsBtn) {
        cardsBtn.addEventListener('click', function () {
            setView('cards');
        });
    }

    if (listBtn) {
        listBtn.addEventListener('click', function () {
            setView('list');
        });
    }

    let savedView = 'cards';
    try {
        savedView = localStorage.getItem('turnar_operators_view') || 'cards';
    } catch (e) {}

    setView(savedView);
    applyFilters();
})();
</script>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>