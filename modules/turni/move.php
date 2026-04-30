<?php
// modules/turni/move.php
// Endpoint JSON per spostare un turno esistente via drag & drop

require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/settings.php';
require_once __DIR__ . '/../../core/notifications.php';

require_login();
require_module('assignments');

if (!can_manage_assignments()) {
    json_response([
        'success' => false,
        'message' => 'Permesso negato per la gestione turni.',
    ], 403);
}

if (!is_post()) {
    json_response([
        'success' => false,
        'message' => 'Metodo non consentito.',
    ], 405);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    json_response([
        'success' => false,
        'message' => 'Payload JSON non valido.',
    ], 400);
}

$turnoId = (int)($payload['turno_id'] ?? $payload['id'] ?? 0);
$newDestinationId = (int)($payload['id_cantiere'] ?? $payload['destination_id'] ?? 0);
$newDate = trim((string)($payload['data'] ?? $payload['date'] ?? ''));
$force = !empty($payload['force']);

if ($turnoId <= 0) {
    json_response([
        'success' => false,
        'message' => 'ID turno mancante.',
    ], 422);
}

$db = db_connect();

$stmt = $db->prepare("SELECT * FROM eventi_turni WHERE id = ? LIMIT 1");
if (!$stmt) {
    json_response([
        'success' => false,
        'message' => 'Errore preparazione lettura turno.',
    ], 500);
}
$stmt->bind_param('i', $turnoId);
$stmt->execute();
$res = $stmt->get_result();
$turno = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$turno) {
    json_response([
        'success' => false,
        'message' => 'Turno non trovato.',
    ], 404);
}

$currentDestinationId = (int)($turno['id_cantiere'] ?? 0);
$currentDate = (string)($turno['data'] ?? '');
$dipendenteId = (int)($turno['id_dipendente'] ?? 0);
$oraInizio = (string)($turno['ora_inizio'] ?? '');
$oraFine = (string)($turno['ora_fine'] ?? '');
$isCapocantiere = (int)($turno['is_capocantiere'] ?? 0);

if ($dipendenteId <= 0 || $currentDestinationId <= 0 || $currentDate === '') {
    json_response([
        'success' => false,
        'message' => 'Turno non valido o incompleto.',
    ], 422);
}

if ($newDestinationId <= 0) {
    $newDestinationId = $currentDestinationId;
}

if ($newDate === '') {
    $newDate = $currentDate;
}

$newDate = normalize_date_iso($newDate);
if (!$newDate) {
    json_response([
        'success' => false,
        'message' => 'Data destinazione non valida.',
    ], 422);
}

if ($newDestinationId === $currentDestinationId && $newDate === $currentDate) {
    json_response([
        'success' => true,
        'message' => 'Nessuna modifica da applicare.',
        'unchanged' => true,
    ]);
}

// Verifica destinazione
$stmt = $db->prepare("SELECT id, commessa FROM cantieri WHERE id = ? LIMIT 1");
if (!$stmt) {
    json_response([
        'success' => false,
        'message' => 'Errore preparazione controllo destinazione.',
    ], 500);
}
$stmt->bind_param('i', $newDestinationId);
$stmt->execute();
$res = $stmt->get_result();
$destination = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$destination) {
    json_response([
        'success' => false,
        'message' => 'Destinazione non trovata.',
    ], 404);
}

// Controllo conflitti se cambia data. Se cambia solo cantiere nello stesso giorno/orario non crea nuovo conflitto.
$conflicts = [];
if ($newDate !== $currentDate) {
    $stmt = $db->prepare("\n        SELECT id, data, id_cantiere, id_dipendente, ora_inizio, ora_fine\n        FROM eventi_turni\n        WHERE id <> ?\n          AND data = ?\n          AND id_dipendente = ?\n          AND NOT (ora_fine <= ? OR ora_inizio >= ?)\n    ");

    if (!$stmt) {
        json_response([
            'success' => false,
            'message' => 'Errore preparazione controllo conflitti.',
        ], 500);
    }

    $stmt->bind_param('isiss', $turnoId, $newDate, $dipendenteId, $oraInizio, $oraFine);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res ? $res->fetch_assoc() : null) {
        if (!$row) {
            break;
        }
        $conflicts[] = $row;
    }
    $stmt->close();
}

if (!empty($conflicts) && !$force) {
    json_response([
        'success' => false,
        'requires_force' => true,
        'message' => 'Spostamento non eseguito: esistono conflitti orari per questo operatore.',
        'conflicts' => $conflicts,
    ], 409);
}

try {
    $db->begin_transaction();

    if (!empty($conflicts) && $force) {
        $stmt = $db->prepare("\n            DELETE FROM eventi_turni\n            WHERE id <> ?\n              AND data = ?\n              AND id_dipendente = ?\n              AND NOT (ora_fine <= ? OR ora_inizio >= ?)\n        ");
        if (!$stmt) {
            throw new RuntimeException('Errore preparazione eliminazione conflitti.');
        }
        $stmt->bind_param('isiss', $turnoId, $newDate, $dipendenteId, $oraInizio, $oraFine);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $db->prepare("\n        UPDATE eventi_turni\n        SET data = ?, id_cantiere = ?, is_capocantiere = ?\n        WHERE id = ?\n        LIMIT 1\n    ");
    if (!$stmt) {
        throw new RuntimeException('Errore preparazione spostamento turno.');
    }

    $stmt->bind_param('siii', $newDate, $newDestinationId, $isCapocantiere, $turnoId);
    $stmt->execute();
    $stmt->close();

    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    json_response([
        'success' => false,
        'message' => 'Errore spostamento turno: ' . $e->getMessage(),
    ], 500);
}

$notificationPayload = [
    'data' => $newDate,
    'id_cantiere' => $newDestinationId,
    'id_dipendente' => $dipendenteId,
    'ora_inizio' => $oraInizio,
    'ora_fine' => $oraFine,
];

try {
    send_assignment_notification($notificationPayload);
} catch (Throwable $e) {
    // Non blocca lo spostamento.
}

json_response([
    'success' => true,
    'message' => 'Turno spostato correttamente.',
    'turno' => [
        'id' => $turnoId,
        'data' => $newDate,
        'id_cantiere' => $newDestinationId,
        'destinazione' => (string)($destination['commessa'] ?? ''),
        'id_dipendente' => $dipendenteId,
        'ora_inizio' => $oraInizio,
        'ora_fine' => $oraFine,
    ],
]);
