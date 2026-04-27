<?php
// modules/turni/move_assignment_date.php

require_once __DIR__ . '/TurniRepository.php';

require_login();
require_module('assignments');

if (!can_manage_assignments()) {
    redirect('modules/turni/calendar.php');
}

$db = db_connect();

$id = (int)post('id', 0);
$newDate = normalize_date_iso((string)post('new_date', '')) ?: '';
$returnUrl = trim((string)post('return_url', ''));
$force = post('force', '0') === '1';

if ($returnUrl === '') {
    $returnUrl = app_url('modules/turni/calendar.php');
}

if ($id <= 0 || $newDate === '') {
    $_SESSION['turnar_calendar_error'] = 'Dati spostamento non validi.';
    header('Location: ' . $returnUrl);
    exit;
}

$sqlSelect = "
    SELECT
        id,
        data,
        id_cantiere,
        id_dipendente,
        ora_inizio,
        ora_fine
    FROM eventi_turni
    WHERE id = ?
    LIMIT 1
";

$stmtSelect = $db->prepare($sqlSelect);
if (!$stmtSelect) {
    $_SESSION['turnar_calendar_error'] = 'Impossibile preparare la lettura del turno.';
    header('Location: ' . $returnUrl);
    exit;
}

$stmtSelect->bind_param('i', $id);

if (!$stmtSelect->execute()) {
    $stmtSelect->close();
    $_SESSION['turnar_calendar_error'] = 'Errore durante la lettura del turno.';
    header('Location: ' . $returnUrl);
    exit;
}

$res = $stmtSelect->get_result();
$turno = $res ? $res->fetch_assoc() : null;

if ($res) {
    $res->free();
}
$stmtSelect->close();

if (!$turno) {
    $_SESSION['turnar_calendar_error'] = 'Turno non trovato.';
    header('Location: ' . $returnUrl);
    exit;
}

$oldDate = (string)($turno['data'] ?? '');
$idDipendente = (int)($turno['id_dipendente'] ?? 0);
$oraInizio = (string)($turno['ora_inizio'] ?? '');
$oraFine = (string)($turno['ora_fine'] ?? '');

if ($oldDate === $newDate) {
    $_SESSION['turnar_calendar_notice'] = 'Il turno era già nella data selezionata.';
    header('Location: ' . $returnUrl);
    exit;
}

// Controllo conflitto stesso dipendente nella nuova data
$sqlConflict = "
    SELECT id
    FROM eventi_turni
    WHERE data = ?
      AND id_dipendente = ?
      AND id <> ?
      AND NOT (ora_fine <= ? OR ora_inizio >= ?)
    LIMIT 1
";

$stmtConflict = $db->prepare($sqlConflict);
if (!$stmtConflict) {
    $_SESSION['turnar_calendar_error'] = 'Impossibile verificare i conflitti.';
    header('Location: ' . $returnUrl);
    exit;
}

$stmtConflict->bind_param('siiss', $newDate, $idDipendente, $id, $oraInizio, $oraFine);

if (!$stmtConflict->execute()) {
    $stmtConflict->close();
    $_SESSION['turnar_calendar_error'] = 'Errore durante il controllo conflitti.';
    header('Location: ' . $returnUrl);
    exit;
}

$resConflict = $stmtConflict->get_result();
$hasConflict = $resConflict && $resConflict->fetch_assoc();

if ($resConflict) {
    $resConflict->free();
}
$stmtConflict->close();

if ($hasConflict && !$force) {
    $_SESSION['turnar_calendar_error'] = 'Spostamento non eseguito: esiste già un turno in conflitto per questo dipendente nella nuova data.';
    header('Location: ' . $returnUrl);
    exit;
}

$db->begin_transaction();

try {
    if ($hasConflict && $force) {
        $sqlDeleteConflicts = "
            DELETE FROM eventi_turni
            WHERE data = ?
              AND id_dipendente = ?
              AND id <> ?
              AND NOT (ora_fine <= ? OR ora_inizio >= ?)
        ";

        $stmtDelete = $db->prepare($sqlDeleteConflicts);
        if (!$stmtDelete) {
            throw new Exception('Impossibile preparare l\'eliminazione dei conflitti.');
        }

        $stmtDelete->bind_param('siiss', $newDate, $idDipendente, $id, $oraInizio, $oraFine);

        if (!$stmtDelete->execute()) {
            $stmtDelete->close();
            throw new Exception('Errore durante l\'eliminazione dei turni in conflitto.');
        }

        $stmtDelete->close();
    }

    // Aggiorna solo la data
    $sqlUpdate = "
        UPDATE eventi_turni
        SET data = ?, updated_at = NOW()
        WHERE id = ?
        LIMIT 1
    ";

    $stmtUpdate = $db->prepare($sqlUpdate);
    if (!$stmtUpdate) {
        throw new Exception('Impossibile preparare l\'aggiornamento del turno.');
    }

    $stmtUpdate->bind_param('si', $newDate, $id);

    if (!$stmtUpdate->execute()) {
        $stmtUpdate->close();
        throw new Exception('Errore durante lo spostamento del turno.');
    }

    $stmtUpdate->close();
    $db->commit();

    if ($hasConflict && $force) {
        $_SESSION['turnar_calendar_notice'] = 'Turno spostato con successo al ' . date('d/m/Y', strtotime($newDate)) . ' con forzatura conflitti.';
    } else {
        $_SESSION['turnar_calendar_notice'] = 'Turno spostato con successo al ' . date('d/m/Y', strtotime($newDate)) . '.';
    }
} catch (Throwable $e) {
    $db->rollback();
    $_SESSION['turnar_calendar_error'] = 'Errore: ' . $e->getMessage();
}

header('Location: ' . $returnUrl);
exit;