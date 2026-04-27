<?php
// core/notifications.php

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/app_notifications.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/push.php';

if (!function_exists('send_assignment_notification')) {
    function send_assignment_notification(array $turno): void
    {
        try {
            $mode = function_exists('assignment_notify_mode')
                ? assignment_notify_mode()
                : (string)setting('assignment_notify_mode', 'app');

            if (!in_array($mode, ['app', 'email', 'both', 'none'], true)) {
                $mode = 'app';
            }

            if ($mode === 'none') {
                return;
            }

            $dipendenteId = (int)($turno['id_dipendente'] ?? 0);
            $cantiereId   = (int)($turno['id_cantiere'] ?? 0);

            if ($dipendenteId <= 0) {
                return;
            }

            $db = db_connect();

            $stmtDip = $db->prepare("
                SELECT id, nome, cognome, email
                FROM dipendenti
                WHERE id = ?
                LIMIT 1
            ");

            if (!$stmtDip) {
                return;
            }

            $stmtDip->bind_param('i', $dipendenteId);
            $stmtDip->execute();
            $resDip = $stmtDip->get_result();
            $dip = $resDip ? $resDip->fetch_assoc() : null;
            $stmtDip->close();

            if (!$dip) {
                return;
            }

            $nomeCompleto = trim(((string)($dip['nome'] ?? '')) . ' ' . ((string)($dip['cognome'] ?? '')));
            $email = trim((string)($dip['email'] ?? ''));

            $cantiereNome = 'Destinazione';
            if ($cantiereId > 0) {
                $stmtCan = $db->prepare("
                    SELECT id, commessa
                    FROM cantieri
                    WHERE id = ?
                    LIMIT 1
                ");

                if ($stmtCan) {
                    $stmtCan->bind_param('i', $cantiereId);
                    $stmtCan->execute();
                    $resCan = $stmtCan->get_result();
                    $can = $resCan ? $resCan->fetch_assoc() : null;
                    $stmtCan->close();

                    if ($can && !empty($can['commessa'])) {
                        $cantiereNome = trim((string)$can['commessa']);
                    }
                }
            }

            $data = trim((string)($turno['data'] ?? ''));
            $oraInizio = trim((string)($turno['ora_inizio'] ?? ''));
            $oraFine   = trim((string)($turno['ora_fine'] ?? ''));

            $dataHuman = function_exists('format_date_it') ? format_date_it($data) : $data;
            $dateTs = strtotime($data);
            $month = $dateTs ? (int)date('n', $dateTs) : (int)date('n');
            $year = $dateTs ? (int)date('Y', $dateTs) : (int)date('Y');

            $titolo = 'Nuovo turno assegnato';
            $messaggio = "Ti è stato assegnato un turno per il giorno {$dataHuman} dalle {$oraInizio} alle {$oraFine} presso {$cantiereNome}.";
            $link = 'calendar.php?m=' . $month . '&y=' . $year . '&date=' . urlencode($data);

            if (in_array($mode, ['app', 'both'], true)) {
                send_app_notification(
                    $dipendenteId,
                    $messaggio,
                    $titolo,
                    'turno',
                    $link
                );

                send_browser_push_to_dipendente(
                    $dipendenteId,
                    $titolo,
                    $messaggio,
                    $link
                );
            }

            if (in_array($mode, ['email', 'both'], true) && $email !== '') {
                $subject = 'Nuovo turno assegnato - Turnar';

                $html = '
                    <div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.6;color:#111;">
                        <h2 style="margin:0 0 14px;">Nuovo turno assegnato</h2>
                        <p>Ciao ' . htmlspecialchars($nomeCompleto !== '' ? $nomeCompleto : 'operatore', ENT_QUOTES, 'UTF-8') . ',</p>
                        <p>ti è stato assegnato un nuovo turno con questi dettagli:</p>
                        <ul style="padding-left:18px;">
                            <li><strong>Data:</strong> ' . htmlspecialchars($dataHuman, ENT_QUOTES, 'UTF-8') . '</li>
                            <li><strong>Orario:</strong> ' . htmlspecialchars($oraInizio, ENT_QUOTES, 'UTF-8') . ' - ' . htmlspecialchars($oraFine, ENT_QUOTES, 'UTF-8') . '</li>
                            <li><strong>Destinazione:</strong> ' . htmlspecialchars($cantiereNome, ENT_QUOTES, 'UTF-8') . '</li>
                        </ul>
                        <p>Accedi a Turnar per vedere i dettagli aggiornati.</p>
                    </div>
                ';

                $alt = "Nuovo turno assegnato\n"
                    . "Data: {$dataHuman}\n"
                    . "Orario: {$oraInizio} - {$oraFine}\n"
                    . "Destinazione: {$cantiereNome}\n";

                send_email($email, $subject, $html, $alt);
            }

        } catch (Throwable $e) {
            // non blocchiamo mai il flusso principale
        }
    }
}