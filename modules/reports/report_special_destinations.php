<?php
// modules/reports/report_special_destinations.php

require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../turni/TurniRepository.php';

require_login();
require_permission('reports.view');

$pageTitle    = 'Report destinazioni speciali';
$pageSubtitle = 'Ferie, permessi, malattia, corsi e attività speciali';
$activeModule = 'reports';

$db = db_connect();
$turniRepo = new TurniRepository($db);

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function special_report_time_to_minutes(?string $time): int
{
    $time = trim((string)$time);
    if ($time === '') {
        return 0;
    }

    $parts = explode(':', $time);
    $h = isset($parts[0]) ? (int)$parts[0] : 0;
    $m = isset($parts[1]) ? (int)$parts[1] : 0;

    return max(0, ($h * 60) + $m);
}

function special_report_gross_minutes(?string $start, ?string $end): int
{
    $startMin = special_report_time_to_minutes($start);
    $endMin   = special_report_time_to_minutes($end);

    if ($endMin <= $startMin) {
        $endMin += 1440;
    }

    return max(0, $endMin - $startMin);
}

function special_report_net_hours(?string $start, ?string $end, float $pausaPranzo): float
{
    $grossMinutes = special_report_gross_minutes($start, $end);
    if ($grossMinutes <= 0) {
        return 0.0;
    }

    $grossHours = $grossMinutes / 60;
    $pausa = max(0.0, $pausaPranzo);

    // Regola Turnar: la pausa si scala solo se il turno raggiunge 8 ore effettive + pausa.
    if ($pausa > 0 && $grossHours >= (8 + $pausa)) {
        $grossHours -= $pausa;
    }

    return round(max(0.0, $grossHours), 2);
}

function special_report_format_hours(float $hours): string
{
    return number_format($hours, 2, ',', '.');
}

function special_report_has_column(mysqli $db, string $table, string $column): bool
{
    $table = trim($table);
    $column = trim($column);

    if ($table === '' || $column === '') {
        return false;
    }

    $stmt = $db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res instanceof mysqli_result && $res->num_rows > 0;
    $stmt->close();

    return $exists;
}

$today = new DateTimeImmutable('today', new DateTimeZone('Europe/Rome'));
$defaultFrom = $today->format('Y-01-01');
$defaultTo   = $today->format('Y-m-d');

$dateFrom = normalize_date_iso((string)get('data_da', $defaultFrom)) ?: $defaultFrom;
$dateTo   = normalize_date_iso((string)get('data_a', $defaultTo)) ?: $defaultTo;

if ($dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

$operatorId = (int)get('operatore_id', 0);
$specialTypeFilter = normalize_special_destination_type((string)get('special_type', ''));
$rawSpecialType = trim((string)get('special_type', ''));
if ($rawSpecialType === '' || $rawSpecialType === 'all') {
    $specialTypeFilter = 'all';
}

$operatori = $turniRepo->getOperatori();
$specialTypes = special_destination_types();

$missingColumns = [];
foreach (['is_special', 'special_type', 'counts_as_work', 'counts_as_absence'] as $requiredColumn) {
    if (!special_report_has_column($db, 'cantieri', $requiredColumn)) {
        $missingColumns[] = $requiredColumn;
    }
}

$rows = [];
$summaryByType = [];
$summaryByOperator = [];
$totalTurns = 0;
$totalWorkHours = 0.0;
$totalAbsenceHours = 0.0;
$totalNeutralHours = 0.0;
$absenceDays = [];
$workDays = [];

if (empty($missingColumns)) {
    $sql = "
        SELECT
            e.id,
            e.data,
            e.ora_inizio,
            e.ora_fine,
            e.id_dipendente,
            e.id_cantiere,
            d.nome,
            d.cognome,
            c.commessa,
            c.comune,
            c.pausa_pranzo,
            c.is_special,
            c.special_type,
            c.counts_as_work,
            c.counts_as_absence
        FROM eventi_turni e
        INNER JOIN cantieri c ON c.id = e.id_cantiere
        INNER JOIN dipendenti d ON d.id = e.id_dipendente
        WHERE e.data BETWEEN ? AND ?
          AND c.is_special = 1
    ";

    $types = 'ss';
    $params = [$dateFrom, $dateTo];

    if ($operatorId > 0) {
        $sql .= " AND e.id_dipendente = ?";
        $types .= 'i';
        $params[] = $operatorId;
    }

    if ($specialTypeFilter !== 'all') {
        $sql .= " AND c.special_type = ?";
        $types .= 's';
        $params[] = $specialTypeFilter;
    }

    $sql .= " ORDER BY e.data ASC, d.cognome ASC, d.nome ASC, c.commessa ASC, e.ora_inizio ASC";

    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            $destination = [
                'commessa' => (string)($row['commessa'] ?? ''),
                'is_special' => (int)($row['is_special'] ?? 0),
                'special_type' => (string)($row['special_type'] ?? ''),
                'counts_as_work' => (int)($row['counts_as_work'] ?? 0),
                'counts_as_absence' => (int)($row['counts_as_absence'] ?? 0),
            ];

            $type = special_destination_type($destination);
            $typeLabel = special_destination_label($type);
            $badgeClass = special_destination_badge_class($type);
            $hours = special_report_net_hours($row['ora_inizio'] ?? '', $row['ora_fine'] ?? '', (float)($row['pausa_pranzo'] ?? 0));
            $isAbsence = destination_counts_as_absence($destination);
            $isWork = destination_counts_as_work($destination);
            $operatorName = trim((string)($row['cognome'] ?? '') . ' ' . (string)($row['nome'] ?? '')) ?: '-';
            $date = (string)($row['data'] ?? '');

            if ($isAbsence) {
                $totalAbsenceHours += $hours;
                $absenceDays[$date . '_' . (int)$row['id_dipendente']] = true;
            } elseif ($isWork) {
                $totalWorkHours += $hours;
                $workDays[$date . '_' . (int)$row['id_dipendente']] = true;
            } else {
                $totalNeutralHours += $hours;
            }

            if (!isset($summaryByType[$type])) {
                $summaryByType[$type] = [
                    'label' => $typeLabel,
                    'badge_class' => $badgeClass,
                    'turns' => 0,
                    'work_hours' => 0.0,
                    'absence_hours' => 0.0,
                    'neutral_hours' => 0.0,
                    'days' => [],
                ];
            }

            $summaryByType[$type]['turns']++;
            $summaryByType[$type]['days'][$date] = true;
            if ($isAbsence) {
                $summaryByType[$type]['absence_hours'] += $hours;
            } elseif ($isWork) {
                $summaryByType[$type]['work_hours'] += $hours;
            } else {
                $summaryByType[$type]['neutral_hours'] += $hours;
            }

            $operatorKey = (int)($row['id_dipendente'] ?? 0);
            if (!isset($summaryByOperator[$operatorKey])) {
                $summaryByOperator[$operatorKey] = [
                    'operator' => $operatorName,
                    'turns' => 0,
                    'work_hours' => 0.0,
                    'absence_hours' => 0.0,
                    'neutral_hours' => 0.0,
                    'absence_days' => [],
                ];
            }

            $summaryByOperator[$operatorKey]['turns']++;
            if ($isAbsence) {
                $summaryByOperator[$operatorKey]['absence_hours'] += $hours;
                $summaryByOperator[$operatorKey]['absence_days'][$date] = true;
            } elseif ($isWork) {
                $summaryByOperator[$operatorKey]['work_hours'] += $hours;
            } else {
                $summaryByOperator[$operatorKey]['neutral_hours'] += $hours;
            }

            $rows[] = [
                'id' => (int)($row['id'] ?? 0),
                'data' => $date,
                'operatore' => $operatorName,
                'destinazione' => trim((string)($row['commessa'] ?? '')) ?: '-',
                'comune' => trim((string)($row['comune'] ?? '')),
                'ora_inizio' => trim((string)($row['ora_inizio'] ?? '')),
                'ora_fine' => trim((string)($row['ora_fine'] ?? '')),
                'hours' => $hours,
                'type' => $type,
                'type_label' => $typeLabel,
                'badge_class' => $badgeClass,
                'is_absence' => $isAbsence,
                'is_work' => $isWork,
            ];
        }

        $stmt->close();
    }
}

$totalTurns = count($rows);

uasort($summaryByType, static function (array $a, array $b): int {
    return strcasecmp((string)$a['label'], (string)$b['label']);
});

uasort($summaryByOperator, static function (array $a, array $b): int {
    return strcasecmp((string)$a['operator'], (string)$b['operator']);
});

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
.special-report-shell{display:grid;gap:18px;}
.special-report-kpi{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;}
.special-report-card{background:var(--content-card-bg);border:1px solid var(--line);border-radius:24px;box-shadow:var(--shadow);padding:18px;}
.special-report-card h3{margin:0 0 10px;color:var(--text);}
.special-report-filter{display:grid;grid-template-columns:repeat(4,minmax(170px,1fr)) auto;gap:12px;align-items:end;}
.special-report-kpi-label{font-size:12px;color:var(--muted);font-weight:800;text-transform:uppercase;letter-spacing:.05em;}
.special-report-kpi-value{margin-top:8px;font-size:30px;line-height:1;font-weight:900;color:var(--text);}
.special-report-kpi-note{margin-top:8px;font-size:12px;color:var(--muted);line-height:1.4;}
.special-report-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.special-report-table-wrap{overflow:auto;border:1px solid var(--line);border-radius:18px;}
.special-report-table{min-width:900px;width:100%;border-collapse:collapse;}
.special-report-table th,.special-report-table td{padding:12px;border-bottom:1px solid var(--line);font-size:13px;vertical-align:top;}
.special-report-table th{color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.05em;background:color-mix(in srgb,var(--bg-3) 82%,transparent);}
.special-report-table td{color:var(--text);}
.special-report-table tr:last-child td{border-bottom:0;}
.special-report-mini-list{display:grid;gap:10px;}
.special-report-mini-row{display:grid;grid-template-columns:1.2fr .7fr .7fr .7fr;gap:10px;align-items:center;padding:12px;border:1px solid var(--line);border-radius:16px;background:color-mix(in srgb,var(--bg-3) 84%,transparent);}
.special-report-name{font-weight:900;color:var(--text);}
.special-report-muted{color:var(--muted);font-size:12px;line-height:1.45;}
@media(max-width:1200px){.special-report-kpi{grid-template-columns:repeat(2,minmax(0,1fr));}.special-report-grid-2{grid-template-columns:1fr;}.special-report-filter{grid-template-columns:1fr 1fr;}}
@media(max-width:720px){.special-report-kpi,.special-report-filter{grid-template-columns:1fr;}}
</style>

<div class="special-report-shell">

    <form method="get" class="special-report-card special-report-filter">
        <div class="field">
            <label for="data_da">Dal</label>
            <input type="date" id="data_da" name="data_da" value="<?= h($dateFrom) ?>">
        </div>

        <div class="field">
            <label for="data_a">Al</label>
            <input type="date" id="data_a" name="data_a" value="<?= h($dateTo) ?>">
        </div>

        <div class="field">
            <label for="operatore_id">Operatore</label>
            <select id="operatore_id" name="operatore_id">
                <option value="0">Tutti gli operatori</option>
                <?php foreach ($operatori as $op): ?>
                    <?php $opId = (int)($op['id'] ?? 0); ?>
                    <option value="<?= $opId ?>" <?= $operatorId === $opId ? 'selected' : '' ?>>
                        <?= h(trim((string)($op['cognome'] ?? '') . ' ' . (string)($op['nome'] ?? ''))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="special_type">Tipo speciale</label>
            <select id="special_type" name="special_type">
                <option value="all">Tutti i tipi</option>
                <?php foreach ($specialTypes as $typeKey => $typeData): ?>
                    <option value="<?= h($typeKey) ?>" <?= $specialTypeFilter === $typeKey ? 'selected' : '' ?>>
                        <?= h($typeData['label'] ?? $typeKey) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="row-center">
            <button class="btn btn-primary" type="submit">Filtra</button>
            <a class="btn btn-ghost" href="<?= h(app_url('modules/reports/report_special_destinations.php')) ?>">Reset</a>
        </div>
    </form>

    <?php if (!empty($missingColumns)): ?>
        <div class="special-report-card">
            <h3>Migrazione database necessaria</h3>
            <p class="text-muted">
                Mancano queste colonne nella tabella cantieri: <strong><?= h(implode(', ', $missingColumns)) ?></strong>.<br>
                Esegui la migration <strong>database/migrations/2026_04_27_special_destinations.sql</strong> e poi ricarica questa pagina.
            </p>
        </div>
    <?php else: ?>

        <section class="special-report-kpi">
            <div class="special-report-card">
                <div class="special-report-kpi-label">Turni speciali</div>
                <div class="special-report-kpi-value"><?= (int)$totalTurns ?></div>
                <div class="special-report-kpi-note">Righe trovate nel periodo selezionato.</div>
            </div>

            <div class="special-report-card">
                <div class="special-report-kpi-label">Ore assenza</div>
                <div class="special-report-kpi-value"><?= h(special_report_format_hours($totalAbsenceHours)) ?></div>
                <div class="special-report-kpi-note">Ferie, permessi, malattia e altri tipi marcati assenza.</div>
            </div>

            <div class="special-report-card">
                <div class="special-report-kpi-label">Ore lavoro speciale</div>
                <div class="special-report-kpi-value"><?= h(special_report_format_hours($totalWorkHours)) ?></div>
                <div class="special-report-kpi-note">Corsi/formazione e speciali marcati come lavoro.</div>
            </div>

            <div class="special-report-card">
                <div class="special-report-kpi-label">Ore neutre</div>
                <div class="special-report-kpi-value"><?= h(special_report_format_hours($totalNeutralHours)) ?></div>
                <div class="special-report-kpi-note">Speciali non conteggiati né come lavoro né come assenza.</div>
            </div>

            <div class="special-report-card">
                <div class="special-report-kpi-label">Giorni assenza</div>
                <div class="special-report-kpi-value"><?= count($absenceDays) ?></div>
                <div class="special-report-kpi-note">Conteggio per operatore/giorno.</div>
            </div>
        </section>

        <section class="special-report-grid-2">
            <div class="special-report-card">
                <h3>Riepilogo per tipo</h3>
                <div class="special-report-mini-list">
                    <?php if (empty($summaryByType)): ?>
                        <div class="empty-state">Nessun tipo speciale trovato nel periodo.</div>
                    <?php else: ?>
                        <?php foreach ($summaryByType as $type => $item): ?>
                            <div class="special-report-mini-row">
                                <div>
                                    <div class="special-report-name"><?= h($item['label']) ?></div>
                                    <span class="badge <?= h($item['badge_class']) ?>"><?= h($type) ?></span>
                                </div>
                                <div><strong><?= (int)$item['turns'] ?></strong><div class="special-report-muted">turni</div></div>
                                <div><strong><?= count($item['days']) ?></strong><div class="special-report-muted">giorni</div></div>
                                <div>
                                    <strong><?= h(special_report_format_hours((float)$item['absence_hours'])) ?></strong>
                                    <div class="special-report-muted">h assenza</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="special-report-card">
                <h3>Riepilogo per operatore</h3>
                <div class="special-report-mini-list">
                    <?php if (empty($summaryByOperator)): ?>
                        <div class="empty-state">Nessun operatore trovato nel periodo.</div>
                    <?php else: ?>
                        <?php foreach ($summaryByOperator as $item): ?>
                            <div class="special-report-mini-row">
                                <div class="special-report-name"><?= h($item['operator']) ?></div>
                                <div><strong><?= (int)$item['turns'] ?></strong><div class="special-report-muted">turni</div></div>
                                <div><strong><?= count($item['absence_days']) ?></strong><div class="special-report-muted">gg assenza</div></div>
                                <div>
                                    <strong><?= h(special_report_format_hours((float)$item['absence_hours'])) ?></strong>
                                    <div class="special-report-muted">h assenza</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="special-report-card">
            <h3>Dettaglio turni speciali</h3>
            <?php if (empty($rows)): ?>
                <div class="empty-state">Nessuna destinazione speciale trovata con i filtri selezionati.</div>
            <?php else: ?>
                <div class="special-report-table-wrap">
                    <table class="special-report-table">
                        <thead>
                        <tr>
                            <th>Data</th>
                            <th>Operatore</th>
                            <th>Destinazione</th>
                            <th>Tipo</th>
                            <th>Orario</th>
                            <th>Ore</th>
                            <th>Conteggio</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= h(format_date_it($row['data'])) ?></td>
                                <td><?= h($row['operatore']) ?></td>
                                <td>
                                    <strong><?= h($row['destinazione']) ?></strong>
                                    <?php if ($row['comune'] !== ''): ?>
                                        <div class="special-report-muted"><?= h($row['comune']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= h($row['badge_class']) ?>"><?= h($row['type_label']) ?></span></td>
                                <td><?= h(format_time_it($row['ora_inizio'])) ?> - <?= h(format_time_it($row['ora_fine'])) ?></td>
                                <td><strong><?= h(special_report_format_hours((float)$row['hours'])) ?></strong></td>
                                <td>
                                    <?php if ($row['is_absence']): ?>
                                        <span class="badge badge-danger">Assenza</span>
                                    <?php elseif ($row['is_work']): ?>
                                        <span class="badge badge-success">Lavoro</span>
                                    <?php else: ?>
                                        <span class="badge">Neutro</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>
