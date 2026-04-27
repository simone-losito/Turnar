<?php
// modules/turni/save.php
// Endpoint JSON per controllo e salvataggio turni in Turnar

require_once __DIR__ . '/TurniService.php';
require_once __DIR__ . '/../../core/settings.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/notifications.php';

// Richiede login
require_login();

// Richiede ruolo almeno manager per gestire i turni
if (!can_manage_assignments()) {
    json_response([
        'success' => false,
        'message' => 'Permesso negato per la gestione turni.'
    ], 403);
}

// Solo POST
if (!is_post()) {
    json_response([
        'success' => false,
        'message' => 'Metodo non consentito. Usa POST.'
    ], 405);
}

// Lettura JSON
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    json_response([
        'success' => false,
        'message' => 'Payload JSON non valido.'
    ], 400);
}

$mode  = trim((string)($data['mode'] ?? 'check'));
$turns = $data['turns'] ?? [];

if (!in_array($mode, ['check', 'apply'], true)) {
    json_response([
        'success' => false,
        'message' => 'Modalità non valida. Usa "check" o "apply".'
    ], 400);
}

if (!is_array($turns) || empty($turns)) {
    json_response([
        'success' => false,
        'message' => 'Nessun turno ricevuto.'
    ], 400);
}

$service = new TurniService(db_connect());

// =========================================================
// PRE-VALIDAZIONE INPUT
// =========================================================
$validatedTurns = [];
$errors = [];

foreach ($turns as $index => $turno) {
    $riga = $index + 1;

    $dataTurno     = trim((string)($turno['data'] ?? ''));
    $idCantiere    = (int)($turno['id_cantiere'] ?? 0);
    $idDipendente  = (int)($turno['id_dipendente'] ?? 0);
    $oraInizio     = trim((string)($turno['ora_inizio'] ?? ''));
    $oraFine       = trim((string)($turno['ora_fine'] ?? ''));

    if ($dataTurno === '' || $idCantiere <= 0 || $idDipendente <= 0 || $oraInizio === '' || $oraFine === '') {
        $errors[] = "Turno #{$riga}: dati mancanti.";
        continue;
    }

    try {
        $segments = $service->explodeTurno($dataTurno, $oraInizio, $oraFine);

        $validatedTurns[] = [
            'data'          => $service->normalizeDate($dataTurno),
            'id_cantiere'   => $idCantiere,
            'id_dipendente' => $idDipendente,
            'ora_inizio'    => $service->normalizeTime($oraInizio),
            'ora_fine'      => $service->normalizeTime($oraFine),
            'segments'      => $segments,
        ];
    } catch (Throwable $e) {
        $errors[] = "Turno #{$riga}: " . $e->getMessage();
    }
}

if (!empty($errors)) {
    json_response([
        'success' => false,
        'message' => "Errori di validazione:\n- " . implode("\n- ", $errors)
    ], 422);
}

if (empty($validatedTurns)) {
    json_response([
        'success' => false,
        'message' => 'Nessun turno valido da elaborare.'
    ], 422);
}

// =========================================================
// MODE = CHECK
// =========================================================
if ($mode === 'check') {
    $allConflicts = [];

    foreach ($validatedTurns as $turno) {
        $conflicts = $service->checkConflitti($turno['segments'], (int)$turno['id_dipendente']);

        if (!empty($conflicts)) {
            $allConflicts[] = [
                'turno' => [
                    'data'          => $turno['data'],
                    'id_cantiere'   => $turno['id_cantiere'],
                    'id_dipendente' => $turno['id_dipendente'],
                    'ora_inizio'    => $turno['ora_inizio'],
                    'ora_fine'      => $turno['ora_fine'],
                ],
                'conflicts' => $conflicts,
            ];
        }
    }

    json_response([
        'success' => true,
        'mode' => 'check',
        'conflicts' => $allConflicts,
    ]);
}

// =========================================================
// MODE = APPLY
// =========================================================
$saved = 0;
$saveErrors = [];
$savedSegments = [];

foreach ($validatedTurns as $turno) {
    $result = $service->salvaTurno([
        'data'          => $turno['data'],
        'id_cantiere'   => $turno['id_cantiere'],
        'id_dipendente' => $turno['id_dipendente'],
        'ora_inizio'    => $turno['ora_inizio'],
        'ora_fine'      => $turno['ora_fine'],
    ]);

    if (!empty($result['success'])) {
        $saved++;
        $savedSegments = array_merge($savedSegments, $result['segments'] ?? []);

        try {
            $service->afterSave($turno);
        } catch (Throwable $e) {
            // non blocchiamo il salvataggio
        }

        try {
            send_assignment_notification($turno);
        } catch (Throwable $e) {
            // non blocchiamo il salvataggio
        }
    } else {
        $saveErrors[] = [
            'turno' => [
                'data'          => $turno['data'],
                'id_cantiere'   => $turno['id_cantiere'],
                'id_dipendente' => $turno['id_dipendente'],
                'ora_inizio'    => $turno['ora_inizio'],
                'ora_fine'      => $turno['ora_fine'],
            ],
            'error' => (string)($result['error'] ?? 'Errore sconosciuto'),
        ];
    }
}

// Audit non bloccante, solo se più avanti vorrai tabella/funzione dedicata
try {
    if (function_exists('setting')) {
        $dummy = setting('debug_mode', '1');
        unset($dummy);
    }
} catch (Throwable $e) {
    // no-op
}

if (!empty($saveErrors)) {
    json_response([
        'success' => false,
        'mode' => 'apply',
        'message' => 'Alcuni turni non sono stati salvati.',
        'saved_count' => $saved,
        'saved_segments' => $savedSegments,
        'errors' => $saveErrors,
    ], 500);
}

json_response([
    'success' => true,
    'mode' => 'apply',
    'message' => 'Turni salvati correttamente.',
    'saved_count' => $saved,
    'saved_segments' => $savedSegments,
]);