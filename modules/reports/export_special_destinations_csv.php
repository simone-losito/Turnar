<?php
// modules/reports/export_special_destinations_csv.php

require_once __DIR__ . '/../../core/helpers.php';

require_login();
require_permission('reports.view');

$db = db_connect();

function csv_special_hours(?string $start, ?string $end, float $pause): float
{
    $start = trim((string)$start);
    $end = trim((string)$end);

    if ($start === '' || $end === '') {
        return 0.0;
    }

    $a = strtotime('2000-01-01 ' . $start);
    $b = strtotime('2000-01-01 ' . $end);

    if ($a === false || $b === false) {
        return 0.0;
    }

    if ($b <= $a) {
        $b = strtotime('2000-01-02 ' . $end);
    }

    $hours = max(0.0, ($b - $a) / 3600);
    $pause = max(0.0, $pause);

    if ($pause > 0 && $hours >= (8 + $pause)) {
        $hours -= $pause;
    }

    return round(max(0.0, $hours), 2);
}

function csv_special_has_column(mysqli $db, string $column): bool
{
    $stmt = $db->prepare("SHOW COLUMNS FROM cantieri LIKE ?");
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
$from = normalize_date_iso((string)get('data_da', $today->format('Y-m-01'))) ?: $today->format('Y-m-01');
$to = normalize_date_iso((string)get('data_a', $today->format('Y-m-d'))) ?: $today->format('Y-m-d');

if ($from > $to) {
    [$from, $to] = [$to, $from];
}

$operatorId = (int)get('operatore_id', 0);
$specialTypeRaw = trim((string)get('special_type', 'all'));
$specialType = ($specialTypeRaw === '' || $specialTypeRaw === 'all') ? 'all' : normalize_special_destination_type($specialTypeRaw);

if (!csv_special_has_column($db, 'special_type')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Migration non eseguita: manca cantieri.special_type";
    exit;
}

$filename = 'turnar_report_speciali_' . $from . '_' . $to . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');

fputcsv($out, [
    'Data',
    'Operatore',
    'Destinazione',
    'Tipo speciale',
    'Ora inizio',
    'Ora fine',
    'Pausa pranzo',
    'Ore nette',
    'Conteggio',
], ';');

$sql = "
    SELECT
        e.data,
        e.ora_inizio,
        e.ora_fine,
        e.id_dipendente,
        d.nome,
        d.cognome,
        c.commessa,
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
        $hours = csv_special_hours($row['ora_inizio'] ?? '', $row['ora_fine'] ?? '', (float)($row['pausa_pranzo'] ?? 0));
        $counting = destination_counts_as_absence($destination) ? 'Assenza' : (destination_counts_as_work($destination) ? 'Lavoro speciale' : 'Neutro');
        $operator = trim((string)($row['cognome'] ?? '') . ' ' . (string)($row['nome'] ?? '')) ?: '-';

        fputcsv($out, [
            format_date_it((string)($row['data'] ?? '')),
            $operator,
            (string)($row['commessa'] ?? ''),
            special_destination_label($type),
            format_time_it((string)($row['ora_inizio'] ?? '')),
            format_time_it((string)($row['ora_fine'] ?? '')),
            number_format((float)($row['pausa_pranzo'] ?? 0), 2, ',', '.'),
            number_format($hours, 2, ',', '.'),
            $counting,
        ], ';');
    }

    $stmt->close();
}

fclose($out);
exit;
