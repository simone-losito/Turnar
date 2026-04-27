<?php
// modules/turni/quick_assign.php

require_once __DIR__ . '/TurniRepository.php';

require_login();
require_module('assignments');

if (!can_manage_assignments()) {
    redirect('modules/turni/planning.php');
}

$db = db_connect();

$data = normalize_date_iso((string)post('data', get('data', today_date()))) ?: today_date();
$idDipendente = (int)post('id_dipendente', get('id_dipendente', 0));
$idCantiere   = (int)post('id_cantiere', get('id_cantiere', 0));
$idTurno      = (int)post('id_turno', get('id_turno', 0));

$oraInizio = trim((string)post('ora_inizio', (string)setting('default_shift_start', '07:00')));
$oraFine   = trim((string)post('ora_fine', (string)setting('default_shift_end', '16:00')));
$isResponsabile = post('is_responsabile', '0') === '1' ? 1 : 0;
$forceSave = post('force_save', '0') === '1';
$moveMode  = post('move_mode', '0') === '1';
$multiMode = post('multi_mode', '0') === '1';
$partialSave = post('partial_save', '0') === '1';

$assignmentsJson = (string)post('assignments_json', '');
$idDipendentiJson = (string)post('id_dipendenti_json', '');

function redirect_planning_quick_assign(string $data): void
{
    redirect('modules/turni/planning.php?data=' . urlencode($data));
}

function build_form_payload_single(
    string $data,
    int $idDipendente,
    int $idCantiere,
    int $idTurno,
    bool $moveMode,
    string $oraInizio,
    string $oraFine,
    int $isResponsabile
): array {
    return [
        'data' => $data,
        'id_dipendente' => $idDipendente,
        'id_cantiere' => $idCantiere,
        'id_turno' => $idTurno,
        'move_mode' => $moveMode ? 1 : 0,
        'multi_mode' => 0,
        'partial_save' => 0,
        'ora_inizio' => $oraInizio,
        'ora_fine' => $oraFine,
        'is_responsabile' => $isResponsabile ? 1 : 0,
    ];
}

function build_form_payload_multi(
    string $data,
    int $idCantiere,
    array $assignments,
    int $partialSave = 0
): array {
    $idDipendenti = [];

    foreach ($assignments as $row) {
        $idDip = (int)($row['id_dipendente'] ?? 0);
        if ($idDip > 0) {
            $idDipendenti[] = $idDip;
        }
    }

    return [
        'data' => $data,
        'id_cantiere' => $idCantiere,
        'move_mode' => 0,
        'multi_mode' => 1,
        'partial_save' => $partialSave ? 1 : 0,
        'id_dipendenti' => array_values($idDipendenti),
        'assignments' => $assignments,
    ];
}

function push_conflict_and_redirect(array $payload, string $message, string $data): void
{
    $_SESSION['turnar_quick_assign_conflict'] = [
        'form' => $payload,
        'message' => $message,
    ];

    redirect_planning_quick_assign($data);
}

function normalize_multi_assignments(string $assignmentsJson, string $idDipendentiJson): array
{
    $rows = [];

    if ($assignmentsJson !== '') {
        $decoded = json_decode($assignmentsJson, true);
        if (is_array($decoded)) {
            $rows = $decoded;
        }
    }

    if (empty($rows) && $idDipendentiJson !== '') {
        $decodedIds = json_decode($idDipendentiJson, true);
        if (is_array($decodedIds)) {
            foreach ($decodedIds as $idDip) {
                $rows[] = [
                    'id_dipendente'   => (int)$idDip,
                    'ora_inizio'      => '',
                    'ora_fine'        => '',
                    'is_responsabile' => 0,
                ];
            }
        }
    }

    $normalized = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $idDip = (int)($row['id_dipendente'] ?? 0);
        $oraInizio = trim((string)($row['ora_inizio'] ?? ''));
        $oraFine = trim((string)($row['ora_fine'] ?? ''));
        $isResp = ((string)($row['is_responsabile'] ?? '0') === '1') ? 1 : 0;
        $nome = trim((string)($row['nome'] ?? ''));

        if ($idDip <= 0) {
            continue;
        }

        $normalized[] = [
            'id_dipendente'   => $idDip,
            'nome'            => $nome,
            'ora_inizio'      => $oraInizio,
            'ora_fine'        => $oraFine,
            'is_responsabile' => $isResp,
        ];
    }

    return $normalized;
}

function find_turno_by_id_for_quick_assign(mysqli $db, int $idTurno): ?array
{
    $sql = "
        SELECT
            id,
            data,
            id_cantiere,
            id_dipendente,
            ora_inizio,
            ora_fine,
            is_capocantiere
        FROM eventi_turni
        WHERE id = ?
        LIMIT 1
    ";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $idTurno);

    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }

    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;

    if ($res) {
        $res->free();
    }
    $stmt->close();

    return $row ?: null;
}

function find_overlap_for_quick_assign(
    mysqli $db,
    string $data,
    int $idDipendente,
    string $oraInizio,
    string $oraFine,
    int $excludeTurnoId = 0
): ?array {
    $sql = "
        SELECT
            e.id,
            e.data,
            e.ora_inizio,
            e.ora_fine,
            e.id_cantiere,
            c.commessa,
            d.nome,
            d.cognome
        FROM eventi_turni e
        LEFT JOIN cantieri c   ON c.id = e.id_cantiere
        LEFT JOIN dipendenti d ON d.id = e.id_dipendente
        WHERE e.data = ?
          AND e.id_dipendente = ?
          AND NOT (e.ora_fine <= ? OR e.ora_inizio >= ?)
    ";

    if ($excludeTurnoId > 0) {
        $sql .= " AND e.id <> ? ";
    }

    $sql .= " LIMIT 1 ";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return null;
    }

    if ($excludeTurnoId > 0) {
        $stmt->bind_param('sissi', $data, $idDipendente, $oraInizio, $oraFine, $excludeTurnoId);
    } else {
        $stmt->bind_param('siss', $data, $idDipendente, $oraInizio, $oraFine);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }

    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;

    if ($res) {
        $res->free();
    }
    $stmt->close();

    return $row ?: null;
}

function delete_overlaps_for_quick_assign(
    mysqli $db,
    string $data,
    int $idDipendente,
    string $oraInizio,
    string $oraFine,
    int $excludeTurnoId = 0
): bool {
    $sql = "
        DELETE FROM eventi_turni
        WHERE data = ?
          AND id_dipendente = ?
          AND NOT (ora_fine <= ? OR ora_inizio >= ?)
    ";

    if ($excludeTurnoId > 0) {
        $sql .= " AND id <> ? ";
    }

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return false;
    }

    if ($excludeTurnoId > 0) {
        $stmt->bind_param('sissi', $data, $idDipendente, $oraInizio, $oraFine, $excludeTurnoId);
    } else {
        $stmt->bind_param('siss', $data, $idDipendente, $oraInizio, $oraFine);
    }

    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function fetch_operator_names_map(mysqli $db, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($v) {
        return $v > 0;
    })));

    if (empty($ids)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    $sql = "
        SELECT id, nome, cognome
        FROM dipendenti
        WHERE id IN ($placeholders)
    ";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$ids);

    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $res = $stmt->get_result();
    $map = [];

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $id = (int)($row['id'] ?? 0);
            $nome = trim((string)($row['cognome'] ?? '') . ' ' . (string)($row['nome'] ?? ''));
            if ($id > 0) {
                $map[$id] = $nome !== '' ? $nome : ('ID ' . $id);
            }
        }
        $res->free();
    }

    $stmt->close();
    return $map;
}

/**
 * VALIDAZIONE MULTI
 */
if ($multiMode && !$moveMode) {
    if ($idCantiere <= 0) {
        redirect_planning_quick_assign($data);
    }

    $assignments = normalize_multi_assignments($assignmentsJson, $idDipendentiJson);

    if (empty($assignments)) {
        redirect_planning_quick_assign($data);
    }

    $operatorIds = array_map(function ($row) {
        return (int)$row['id_dipendente'];
    }, $assignments);

    $nameMap = fetch_operator_names_map($db, $operatorIds);

    foreach ($assignments as &$row) {
        $idDip = (int)$row['id_dipendente'];
        if ($idDip <= 0) {
            redirect_planning_quick_assign($data);
        }

        if ($row['nome'] === '' && isset($nameMap[$idDip])) {
            $row['nome'] = $nameMap[$idDip];
        }

        if ($row['ora_inizio'] === '' || $row['ora_fine'] === '' || $row['ora_inizio'] === $row['ora_fine']) {
            push_conflict_and_redirect(
                build_form_payload_multi($data, $idCantiere, $assignments, $partialSave ? 1 : 0),
                'Compila correttamente gli orari di tutti gli operatori prima di confermare.',
                $data
            );
        }
    }
    unset($row);

    $conflicts = [];
    $validAssignments = [];

    foreach ($assignments as $row) {
        $idDip = (int)$row['id_dipendente'];
        $oraInizioRow = trim((string)$row['ora_inizio']);
        $oraFineRow = trim((string)$row['ora_fine']);
        $oraInizioDbRow = $oraInizioRow . ':00';
        $oraFineDbRow = $oraFineRow . ':00';

        $conflitto = find_overlap_for_quick_assign($db, $data, $idDip, $oraInizioDbRow, $oraFineDbRow, 0);

        if ($conflitto) {
            $nomeDip = trim((string)($conflitto['cognome'] ?? '') . ' ' . (string)($conflitto['nome'] ?? ''));
            $nomeDest = trim((string)($conflitto['commessa'] ?? ''));
            $oraConfDa = substr((string)($conflitto['ora_inizio'] ?? ''), 0, 5);
            $oraConfA  = substr((string)($conflitto['ora_fine'] ?? ''), 0, 5);

            $conflicts[] = [
                'row' => $row,
                'text' =>
                    'Operatore: ' . ($row['nome'] !== '' ? $row['nome'] : ($nomeDip !== '' ? $nomeDip : ('ID ' . $idDip))) . "\n" .
                    'Destinazione esistente: ' . ($nomeDest !== '' ? $nomeDest : 'N/D') . "\n" .
                    'Orario esistente: ' . $oraConfDa . ' → ' . $oraConfA
            ];
        } else {
            $validAssignments[] = $row;
        }
    }

    if (!empty($conflicts) && !$forceSave && !$partialSave) {
        $message =
            "Conflitti trovati:\n\n" .
            implode("\n\n", array_map(function ($item) {
                return $item['text'];
            }, $conflicts)) .
            "\n\nSei sicuro di voler creare o sostituire questi turni?";

        push_conflict_and_redirect(
            build_form_payload_multi($data, $idCantiere, $assignments, 0),
            $message,
            $data
        );
    }

    if ($partialSave && empty($validAssignments)) {
        $message =
            "Nessun turno salvato.\n\n" .
            "Tutti gli operatori selezionati hanno un conflitto sugli orari indicati.";

        push_conflict_and_redirect(
            build_form_payload_multi($data, $idCantiere, $assignments, 1),
            $message,
            $data
        );
    }

    $rowsToSave = $assignments;
    if ($partialSave) {
        $rowsToSave = $validAssignments;
    }

    $db->begin_transaction();

    try {
        if ($forceSave) {
            foreach ($rowsToSave as $row) {
                $idDip = (int)$row['id_dipendente'];
                $oraInizioDbRow = trim((string)$row['ora_inizio']) . ':00';
                $oraFineDbRow = trim((string)$row['ora_fine']) . ':00';

                if (!delete_overlaps_for_quick_assign($db, $data, $idDip, $oraInizioDbRow, $oraFineDbRow, 0)) {
                    throw new Exception('Impossibile eliminare i turni in conflitto per uno o più operatori.');
                }
            }
        }

        $stmtInsert = $db->prepare("
            INSERT INTO eventi_turni
            (data, id_cantiere, id_dipendente, ora_inizio, ora_fine, is_capocantiere, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        if (!$stmtInsert) {
            throw new Exception('Impossibile preparare il salvataggio rapido multiplo.');
        }

        foreach ($rowsToSave as $row) {
            $idDip = (int)$row['id_dipendente'];
            $oraInizioDbRow = trim((string)$row['ora_inizio']) . ':00';
            $oraFineDbRow = trim((string)$row['ora_fine']) . ':00';
            $isRespRow = ((string)($row['is_responsabile'] ?? '0') === '1') ? 1 : 0;

            $stmtInsert->bind_param(
                'siissi',
                $data,
                $idCantiere,
                $idDip,
                $oraInizioDbRow,
                $oraFineDbRow,
                $isRespRow
            );

            if (!$stmtInsert->execute()) {
                $stmtInsert->close();
                throw new Exception('Errore durante il salvataggio rapido multiplo.');
            }
        }

        $stmtInsert->close();
        $db->commit();
        unset($_SESSION['turnar_quick_assign_conflict']);

        if ($partialSave && !empty($conflicts)) {
            $_SESSION['turnar_quick_assign_conflict'] = [
                'form' => build_form_payload_multi($data, $idCantiere, $assignments, 1),
                'message' =>
                    "Salvati solo i turni validi.\n\n" .
                    "Operatori non salvati per conflitto:\n\n" .
                    implode("\n\n", array_map(function ($item) {
                        return $item['text'];
                    }, $conflicts))
            ];
        }
    } catch (Throwable $e) {
        $db->rollback();
        $_SESSION['turnar_quick_assign_conflict'] = [
            'form' => build_form_payload_multi($data, $idCantiere, $assignments, $partialSave ? 1 : 0),
            'message' => 'Errore: ' . $e->getMessage(),
        ];
    }

    redirect_planning_quick_assign($data);
}

/**
 * MODALITÀ SINGOLA / SPOSTAMENTO
 */
if ($idDipendente <= 0 || $idCantiere <= 0) {
    redirect_planning_quick_assign($data);
}

if ($oraInizio === '' || $oraFine === '' || $oraInizio === $oraFine) {
    redirect_planning_quick_assign($data);
}

$oraInizioDb = $oraInizio . ':00';
$oraFineDb   = $oraFine . ':00';

$turnoEsistenteDaMuovere = null;
$excludeTurnoId = 0;

if ($moveMode && $idTurno > 0) {
    $turnoEsistenteDaMuovere = find_turno_by_id_for_quick_assign($db, $idTurno);

    if (!$turnoEsistenteDaMuovere) {
        push_conflict_and_redirect(
            build_form_payload_single($data, $idDipendente, $idCantiere, $idTurno, true, $oraInizio, $oraFine, $isResponsabile),
            'Errore: turno da spostare non trovato.',
            $data
        );
    }

    $excludeTurnoId = (int)$turnoEsistenteDaMuovere['id'];

    if ($idDipendente <= 0) {
        $idDipendente = (int)($turnoEsistenteDaMuovere['id_dipendente'] ?? 0);
    }

    if ($oraInizio === '' || $oraFine === '' || $oraInizio === $oraFine) {
        $oraInizio = substr((string)($turnoEsistenteDaMuovere['ora_inizio'] ?? ''), 0, 5);
        $oraFine   = substr((string)($turnoEsistenteDaMuovere['ora_fine'] ?? ''), 0, 5);
        $oraInizioDb = $oraInizio . ':00';
        $oraFineDb   = $oraFine . ':00';
    }
}

$conflitto = find_overlap_for_quick_assign($db, $data, $idDipendente, $oraInizioDb, $oraFineDb, $excludeTurnoId);

if ($conflitto && !$forceSave) {
    $nomeDip = trim((string)($conflitto['cognome'] ?? '') . ' ' . (string)($conflitto['nome'] ?? ''));
    $nomeDest = trim((string)($conflitto['commessa'] ?? ''));
    $oraConfDa = substr((string)($conflitto['ora_inizio'] ?? ''), 0, 5);
    $oraConfA  = substr((string)($conflitto['ora_fine'] ?? ''), 0, 5);

    $message =
        'Conflitto trovato:' . "\n" .
        'Operatore: ' . ($nomeDip !== '' ? $nomeDip : 'N/D') . "\n" .
        'Destinazione esistente: ' . ($nomeDest !== '' ? $nomeDest : 'N/D') . "\n" .
        'Orario esistente: ' . $oraConfDa . ' → ' . $oraConfA . "\n\n" .
        ($moveMode
            ? 'Sei sicuro di voler spostare o sostituire questo turno?'
            : 'Sei sicuro di voler creare o sostituire questo turno?');

    push_conflict_and_redirect(
        build_form_payload_single($data, $idDipendente, $idCantiere, $idTurno, $moveMode, $oraInizio, $oraFine, $isResponsabile),
        $message,
        $data
    );
}

$db->begin_transaction();

try {
    if ($conflitto && $forceSave) {
        if (!delete_overlaps_for_quick_assign($db, $data, $idDipendente, $oraInizioDb, $oraFineDb, $excludeTurnoId)) {
            throw new Exception('Impossibile eliminare i turni in conflitto.');
        }
    }

    if ($moveMode && $idTurno > 0) {
        $stmtUpdate = $db->prepare("
            UPDATE eventi_turni
            SET
                data = ?,
                id_cantiere = ?,
                id_dipendente = ?,
                ora_inizio = ?,
                ora_fine = ?,
                is_capocantiere = ?,
                updated_at = NOW()
            WHERE id = ?
            LIMIT 1
        ");
        if (!$stmtUpdate) {
            throw new Exception('Impossibile preparare lo spostamento rapido.');
        }

        $stmtUpdate->bind_param(
            'siissii',
            $data,
            $idCantiere,
            $idDipendente,
            $oraInizioDb,
            $oraFineDb,
            $isResponsabile,
            $idTurno
        );

        if (!$stmtUpdate->execute()) {
            $stmtUpdate->close();
            throw new Exception('Errore durante lo spostamento rapido.');
        }

        if ((int)$stmtUpdate->affected_rows < 0) {
            $stmtUpdate->close();
            throw new Exception('Nessun turno aggiornato.');
        }

        $stmtUpdate->close();
    } else {
        $stmtInsert = $db->prepare("
            INSERT INTO eventi_turni
            (data, id_cantiere, id_dipendente, ora_inizio, ora_fine, is_capocantiere, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        if (!$stmtInsert) {
            throw new Exception('Impossibile preparare il salvataggio rapido.');
        }

        $stmtInsert->bind_param('siissi', $data, $idCantiere, $idDipendente, $oraInizioDb, $oraFineDb, $isResponsabile);

        if (!$stmtInsert->execute()) {
            $stmtInsert->close();
            throw new Exception('Errore durante il salvataggio rapido.');
        }

        $stmtInsert->close();
    }

    $db->commit();
    unset($_SESSION['turnar_quick_assign_conflict']);
} catch (Throwable $e) {
    $db->rollback();
    $_SESSION['turnar_quick_assign_conflict'] = [
        'form' => build_form_payload_single($data, $idDipendente, $idCantiere, $idTurno, $moveMode, $oraInizio, $oraFine, $isResponsabile),
        'message' => 'Errore: ' . $e->getMessage(),
    ];
}

redirect_planning_quick_assign($data);