<?php
// modules/reports/report_special_destinations_print.php

require_once __DIR__ . '/../../core/helpers.php';

require_login();
require_permission('reports.view');

$db = db_connect();

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function print_special_hours(?string $start, ?string $end, float $pause): float
{
    $start = trim((string)$start);
    $end = trim((string)$end);
    if ($start === '' || $end === '') return 0.0;

    $a = strtotime('2000-01-01 ' . $start);
    $b = strtotime('2000-01-01 ' . $end);
    if ($a === false || $b === false) return 0.0;
    if ($b <= $a) $b = strtotime('2000-01-02 ' . $end);

    $hours = max(0.0, ($b - $a) / 3600);
    $pause = max(0.0, $pause);
    if ($pause > 0 && $hours >= (8 + $pause)) $hours -= $pause;

    return round(max(0.0, $hours), 2);
}

function print_special_fmt(float $value): string
{
    return number_format($value, 2, ',', '.');
}

function print_special_has_column(mysqli $db, string $column): bool
{
    $stmt = $db->prepare("SHOW COLUMNS FROM cantieri LIKE ?");
    if (!$stmt) return false;
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res instanceof mysqli_result && $res->num_rows > 0;
    $stmt->close();
    return $ok;
}

$today = new DateTimeImmutable('today', new DateTimeZone('Europe/Rome'));
$from = normalize_date_iso((string)get('data_da', $today->format('Y-m-01'))) ?: $today->format('Y-m-01');
$to = normalize_date_iso((string)get('data_a', $today->format('Y-m-d'))) ?: $today->format('Y-m-d');
if ($from > $to) [$from, $to] = [$to, $from];

$operatorId = (int)get('operatore_id', 0);
$specialTypeRaw = trim((string)get('special_type', 'all'));
$specialType = ($specialTypeRaw === '' || $specialTypeRaw === 'all') ? 'all' : normalize_special_destination_type($specialTypeRaw);

$rows = [];
$totalAbsenceHours = 0.0;
$totalWorkHours = 0.0;
$totalNeutralHours = 0.0;
$absenceDays = [];
$missingMigration = !print_special_has_column($db, 'special_type');

if (!$missingMigration) {
    $sql = "
        SELECT e.data, e.ora_inizio, e.ora_fine, e.id_dipendente,
               d.nome, d.cognome,
               c.commessa, c.comune, c.pausa_pranzo, c.is_special, c.special_type, c.counts_as_work, c.counts_as_absence
        FROM eventi_turni e
        INNER JOIN cantieri c ON c.id = e.id_cantiere
        INNER JOIN dipendenti d ON d.id = e.id_dipendente
        WHERE e.data BETWEEN ? AND ? AND c.is_special = 1
    ";

    $types = 'ss';
    $params = [$from, $to];

    if ($operatorId > 0) {
        $sql .= " AND e.id_dipendente = ?";
        $types .= 'i';
        $params[] = $operatorId;
    }

    if ($specialType !== 'all') {
        $sql .= " AND c.special_type = ?";
        $types .= 's';
        $params[] = $specialType;
    }

    $sql .= " ORDER BY e.data ASC, d.cognome ASC, d.nome ASC, e.ora_inizio ASC";

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
            $hours = print_special_hours($row['ora_inizio'] ?? '', $row['ora_fine'] ?? '', (float)($row['pausa_pranzo'] ?? 0));
            $isAbsence = destination_counts_as_absence($destination);
            $isWork = destination_counts_as_work($destination);
            $operator = trim((string)($row['cognome'] ?? '') . ' ' . (string)($row['nome'] ?? '')) ?: '-';
            $date = (string)($row['data'] ?? '');

            if ($isAbsence) {
                $totalAbsenceHours += $hours;
                $absenceDays[(int)$row['id_dipendente'] . '_' . $date] = true;
                $counting = 'Assenza';
            } elseif ($isWork) {
                $totalWorkHours += $hours;
                $counting = 'Lavoro speciale';
            } else {
                $totalNeutralHours += $hours;
                $counting = 'Neutro';
            }

            $rows[] = [
                'data' => $date,
                'operatore' => $operator,
                'destinazione' => trim((string)($row['commessa'] ?? '')) ?: '-',
                'comune' => trim((string)($row['comune'] ?? '')),
                'tipo' => special_destination_label($type),
                'ora_inizio' => trim((string)($row['ora_inizio'] ?? '')),
                'ora_fine' => trim((string)($row['ora_fine'] ?? '')),
                'ore' => $hours,
                'conteggio' => $counting,
            ];
        }
        $stmt->close();
    }
}

$appTitle = app_name();
$generatedAt = format_datetime_it(now_datetime());
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Report HR stampabile · <?= h($appTitle) ?></title>
<style>
*{box-sizing:border-box}body{margin:0;padding:28px;font-family:Arial,sans-serif;color:#111827;background:#fff}.sheet{max-width:1120px;margin:0 auto}.head{display:flex;justify-content:space-between;gap:20px;border-bottom:3px solid #111827;padding-bottom:16px;margin-bottom:18px}.brand h1{margin:0;font-size:28px}.brand p{margin:6px 0 0;color:#4b5563}.meta{text-align:right;font-size:12px;color:#4b5563;line-height:1.6}.kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:18px 0}.kpi{border:1px solid #d1d5db;border-radius:12px;padding:12px}.kpi-label{font-size:11px;text-transform:uppercase;color:#6b7280;font-weight:700}.kpi-value{font-size:24px;font-weight:900;margin-top:5px}.alert{padding:14px;border:1px solid #fca5a5;background:#fef2f2;border-radius:12px;color:#991b1b}table{width:100%;border-collapse:collapse;margin-top:16px}th,td{padding:9px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:12px;vertical-align:top}th{background:#f3f4f6;text-transform:uppercase;font-size:10px;letter-spacing:.05em;color:#374151}.right{text-align:right}.badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#eef2ff;color:#3730a3;font-weight:700;font-size:11px}.foot{margin-top:18px;color:#6b7280;font-size:11px;text-align:right}.actions{display:flex;gap:10px;justify-content:flex-end;margin-bottom:16px}.btn{border:1px solid #111827;background:#111827;color:#fff;border-radius:999px;padding:10px 14px;text-decoration:none;font-weight:700;cursor:pointer}@media print{body{padding:0}.actions{display:none}.sheet{max-width:none}.kpi{break-inside:avoid}tr{break-inside:avoid}}
</style>
</head>
<body>
<div class="sheet">
    <div class="actions"><button class="btn" onclick="window.print()">Stampa / salva PDF</button></div>

    <header class="head">
        <div class="brand">
            <h1><?= h($appTitle) ?></h1>
            <p>Report HR destinazioni speciali</p>
        </div>
        <div class="meta">
            Periodo: <strong><?= h(format_date_it($from)) ?> - <?= h(format_date_it($to)) ?></strong><br>
            Generato: <?= h($generatedAt) ?><br>
            Utente: <?= h(auth_display_name()) ?>
        </div>
    </header>

    <?php if ($missingMigration): ?>
        <div class="alert">Migrazione non eseguita: manca la colonna cantieri.special_type.</div>
    <?php else: ?>
        <section class="kpis">
            <div class="kpi"><div class="kpi-label">Righe</div><div class="kpi-value"><?= count($rows) ?></div></div>
            <div class="kpi"><div class="kpi-label">Ore assenza</div><div class="kpi-value"><?= h(print_special_fmt($totalAbsenceHours)) ?></div></div>
            <div class="kpi"><div class="kpi-label">Ore lavoro speciale</div><div class="kpi-value"><?= h(print_special_fmt($totalWorkHours)) ?></div></div>
            <div class="kpi"><div class="kpi-label">Giorni assenza</div><div class="kpi-value"><?= count($absenceDays) ?></div></div>
        </section>

        <table>
            <thead>
            <tr><th>Data</th><th>Operatore</th><th>Destinazione</th><th>Tipo</th><th>Orario</th><th class="right">Ore</th><th>Conteggio</th></tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="7">Nessun dato nel periodo selezionato.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= h(format_date_it($row['data'])) ?></td>
                    <td><?= h($row['operatore']) ?></td>
                    <td><?= h($row['destinazione']) ?><?php if ($row['comune'] !== ''): ?><br><small><?= h($row['comune']) ?></small><?php endif; ?></td>
                    <td><span class="badge"><?= h($row['tipo']) ?></span></td>
                    <td><?= h(format_time_it($row['ora_inizio'])) ?> - <?= h(format_time_it($row['ora_fine'])) ?></td>
                    <td class="right"><strong><?= h(print_special_fmt((float)$row['ore'])) ?></strong></td>
                    <td><?= h($row['conteggio']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="foot">Documento generato da Turnar.</div>
</div>
</body>
</html>
