<?php
// modules/turni/check_assignment_move_conflicts.php

require_once __DIR__ . '/TurniRepository.php';

require_login();
require_module('assignments');

header('Content-Type: application/json; charset=UTF-8');

if (!can_manage_assignments()) {
    echo json_encode([
        'success' => false,
        'message' => 'Non autorizzato.'
    ]);
    exit;
}

$db = db_connect();

$id = (int)get('id', 0);
$rangeStart = normalize_date_iso((string)get('range_start', '')) ?: '';
$rangeEnd   = normalize_date_iso((string)get('range_end', '')) ?: '';
$targetDate = normalize_date_iso((string)get('target_date', '')) ?: '';

if ($id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID turno non valido.'
    ]);
    exit;
}

$sqlTurno = "
    SELECT
        id,
        data,
        id_dipendente,
        ora_inizio,
        ora_fine
    FROM eventi_turni
    WHERE id = ?
    LIMIT 1
";

$stmtTurno = $db->prepare($sqlTurno);
if (!$stmtTurno) {
    echo json_encode([
        'success' => false,
        'message' => 'Errore preparazione turno.'
    ]);
    exit;
}

$stmtTurno->bind_param('i', $id);

if (!$stmtTurno->execute()) {
    $stmtTurno->close();
    echo json_encode([
        'success' => false,
        'message' => 'Errore esecuzione turno.'
    ]);
    exit;
}

$resTurno = $stmtTurno->get_result();
$turno = $resTurno ? $resTurno->fetch_assoc() : null;

if ($resTurno) {
    $resTurno->free();
}
$stmtTurno->close();

if (!$turno) {
    echo json_encode([
        'success' => false,
        'message' => 'Turno non trovato.'
    ]);
    exit;
}

$idDipendente = (int)($turno['id_dipendente'] ?? 0);
$oraInizio = (string)($turno['ora_inizio'] ?? '');
$oraFine = (string)($turno['ora_fine'] ?? '');

if ($targetDate !== '') {
    $sqlCheck = "
        SELECT id
        FROM eventi_turni
        WHERE data = ?
          AND id_dipendente = ?
          AND id <> ?
          AND NOT (ora_fine <= ? OR ora_inizio >= ?)
        LIMIT 1
    ";

    $stmtCheck = $db->prepare($sqlCheck);
    if (!$stmtCheck) {
        echo json_encode([
            'success' => false,
            'message' => 'Errore preparazione controllo target.'
        ]);
        exit;
    }

    $stmtCheck->bind_param('siiss', $targetDate, $idDipendente, $id, $oraInizio, $oraFine);

    if (!$stmtCheck->execute()) {
        $stmtCheck->close();
        echo json_encode([
            'success' => false,
            'message' => 'Errore esecuzione controllo target.'
        ]);
        exit;
    }

    $resCheck = $stmtCheck->get_result();
    $hasConflict = $resCheck && $resCheck->fetch_assoc();

    if ($resCheck) {
        $resCheck->free();
    }
    $stmtCheck->close();

    echo json_encode([
        'success' => true,
        'has_conflict' => $hasConflict ? true : false
    ]);
    exit;
}

if ($rangeStart !== '' && $rangeEnd !== '') {
    $sqlRange = "
        SELECT DISTINCT data
        FROM eventi_turni
        WHERE data BETWEEN ? AND ?
          AND id_dipendente = ?
          AND id <> ?
          AND NOT (ora_fine <= ? OR ora_inizio >= ?)
        ORDER BY data ASC
    ";

    $stmtRange = $db->prepare($sqlRange);
    if (!$stmtRange) {
        echo json_encode([
            'success' => false,
            'message' => 'Errore preparazione controllo intervallo.'
        ]);
        exit;
    }

    $stmtRange->bind_param('ssiiss', $rangeStart, $rangeEnd, $idDipendente, $id, $oraInizio, $oraFine);

    if (!$stmtRange->execute()) {
        $stmtRange->close();
        echo json_encode([
            'success' => false,
            'message' => 'Errore esecuzione controllo intervallo.'
        ]);
        exit;
    }

    $resRange = $stmtRange->get_result();
    $conflictDates = [];

    if ($resRange) {
        while ($row = $resRange->fetch_assoc()) {
            if (!empty($row['data'])) {
                $conflictDates[] = (string)$row['data'];
            }
        }
        $resRange->free();
    }

    $stmtRange->close();

    echo json_encode([
        'success' => true,
        'conflict_dates' => $conflictDates
    ]);
    exit;
}

echo json_encode([
    'success' => false,
    'message' => 'Parametri insufficienti.'
]);
exit;