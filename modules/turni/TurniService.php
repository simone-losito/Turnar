<?php
// modules/turni/TurniService.php

require_once __DIR__ . '/../../core/settings.php';

class TurniService
{
    private mysqli $db;

    public function __construct(?mysqli $db = null)
    {
        $this->db = $db ?: db_connect();
    }

    // =========================================================
    // NORMALIZZAZIONI BASE
    // =========================================================

    public function normalizeDate(string $date): ?string
    {
        $ts = strtotime($date);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    public function normalizeTime(string $time): ?string
    {
        $time = trim($time);

        if ($time === '24:00') {
            return '24:00:00';
        }

        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time . ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            return $time;
        }

        return null;
    }

    private function nextDate(string $date): string
    {
        return date('Y-m-d', strtotime($date . ' +1 day'));
    }

    // =========================================================
    // ESPLOSIONE TURNI (come già fai tu)
    // =========================================================

    public function explodeTurno(string $data, string $start, string $end): array
    {
        $data  = $this->normalizeDate($data);
        $start = $this->normalizeTime($start);
        $end   = $this->normalizeTime($end);

        if (!$data || !$start || !$end) {
            throw new Exception('Formato turno non valido');
        }

        if ($start === $end) {
            throw new Exception('Inizio e fine coincidono');
        }

        $startSec = strtotime($start);
        $endSec   = strtotime($end);

        // stesso giorno
        if ($startSec < $endSec) {
            return [[
                'data' => $data,
                'ora_inizio' => $start,
                'ora_fine' => $end
            ]];
        }

        // fine giornata (00:00)
        if ($end === '00:00:00') {
            return [[
                'data' => $data,
                'ora_inizio' => $start,
                'ora_fine' => '24:00:00'
            ]];
        }

        // passa mezzanotte
        return [
            [
                'data' => $data,
                'ora_inizio' => $start,
                'ora_fine' => '24:00:00'
            ],
            [
                'data' => $this->nextDate($data),
                'ora_inizio' => '00:00:00',
                'ora_fine' => $end
            ]
        ];
    }

    // =========================================================
    // CHECK CONFLITTI
    // =========================================================

    public function checkConflitti(array $segments, int $dipendenteId): array
    {
        $conflicts = [];

        $sql = "SELECT * FROM eventi_turni
                WHERE data = ?
                AND id_dipendente = ?
                AND NOT (ora_fine <= ? OR ora_inizio >= ?)";

        $stmt = $this->db->prepare($sql);

        foreach ($segments as $seg) {

            $stmt->bind_param(
                'siss',
                $seg['data'],
                $dipendenteId,
                $seg['ora_inizio'],
                $seg['ora_fine']
            );

            $stmt->execute();
            $res = $stmt->get_result();

            while ($row = $res->fetch_assoc()) {
                $conflicts[] = $row;
            }
        }

        return $conflicts;
    }

    // =========================================================
    // SALVATAGGIO TURNO
    // =========================================================

    public function salvaTurno(array $turno): array
    {
        $this->db->begin_transaction();

        try {

            $segments = $this->explodeTurno(
                $turno['data'],
                $turno['ora_inizio'],
                $turno['ora_fine']
            );

            foreach ($segments as $seg) {

                // cancella overlap
                $del = $this->db->prepare("
                    DELETE FROM eventi_turni
                    WHERE data = ?
                    AND id_dipendente = ?
                    AND NOT (ora_fine <= ? OR ora_inizio >= ?)
                ");

                $del->bind_param(
                    'siss',
                    $seg['data'],
                    $turno['id_dipendente'],
                    $seg['ora_inizio'],
                    $seg['ora_fine']
                );

                $del->execute();

                // inserisce
                $ins = $this->db->prepare("
                    INSERT INTO eventi_turni
                    (data, id_cantiere, id_dipendente, ora_inizio, ora_fine)
                    VALUES (?, ?, ?, ?, ?)
                ");

                $ins->bind_param(
                    'siiss',
                    $seg['data'],
                    $turno['id_cantiere'],
                    $turno['id_dipendente'],
                    $seg['ora_inizio'],
                    $seg['ora_fine']
                );

                $ins->execute();
            }

            $this->db->commit();

            return [
                'success' => true,
                'segments' => $segments
            ];

        } catch (Throwable $e) {
            $this->db->rollback();

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // =========================================================
    // FUTURO: notifiche / mail
    // =========================================================

    public function afterSave(array $turno): void
    {
        if (app_notifications_enabled()) {
            // TODO: push notification
        }

        if (app_email_enabled()) {
            // TODO: invio mail
        }
    }
}