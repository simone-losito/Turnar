<?php
// modules/turni/TurniRepository.php

require_once __DIR__ . '/TurniService.php';

class TurniRepository
{
    private mysqli $db;

    public function __construct(?mysqli $db = null)
    {
        $this->db = $db ?: db_connect();
    }

    // --------------------------------------------------
    // CHECK TABELLE
    // --------------------------------------------------
    public function tableExists(string $tableName): bool
    {
        $tableName = trim($tableName);
        if ($tableName === '') {
            return false;
        }

        $safe = $this->db->real_escape_string($tableName);
        $sql  = "SHOW TABLES LIKE '{$safe}'";
        $res  = $this->db->query($sql);

        if ($res instanceof mysqli_result) {
            $exists = $res->num_rows > 0;
            $res->free();
            return $exists;
        }

        return false;
    }

    // --------------------------------------------------
    // OPERATORI
    // Fase iniziale: legge dalla tabella legacy "dipendenti"
    // --------------------------------------------------
    public function getOperatori(array $filters = []): array
    {
        if (!$this->tableExists('dipendenti')) {
            return [];
        }

        $search = trim((string)($filters['search'] ?? ''));

        $sql = "
            SELECT
                id,
                nome,
                cognome,
                email,
                telefono,
                tipologia,
                livello,
                preposto,
                capo_cantiere
            FROM dipendenti
            WHERE 1=1
        ";

        $types  = '';
        $params = [];

        if ($search !== '') {
            $sql .= " AND (
                nome LIKE ?
                OR cognome LIKE ?
                OR CONCAT(cognome, ' ', nome) LIKE ?
                OR email LIKE ?
                OR telefono LIKE ?
                OR tipologia LIKE ?
                OR livello LIKE ?
            )";
            $like = '%' . $search . '%';
            $types .= 'sssssss';
            array_push($params, $like, $like, $like, $like, $like, $like, $like);
        }

        $sql .= " ORDER BY cognome ASC, nome ASC";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            return [];
        }

        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }

        $res = $stmt->get_result();
        $rows = [];

        while ($row = $res->fetch_assoc()) {
            $row['id']            = (int)($row['id'] ?? 0);
            $row['preposto']      = (int)($row['preposto'] ?? 0);
            $row['capo_cantiere'] = (int)($row['capo_cantiere'] ?? 0);
            $row['display_name']  = trim(($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? ''));
            $rows[] = $row;
        }

        $res->free();
        $stmt->close();

        return $rows;
    }

    public function countOperatori(): int
    {
        if (!$this->tableExists('dipendenti')) {
            return 0;
        }

        $sql = "SELECT COUNT(*) AS cnt FROM dipendenti";
        $res = $this->db->query($sql);

        if ($res instanceof mysqli_result) {
            $row = $res->fetch_assoc();
            $res->free();
            return (int)($row['cnt'] ?? 0);
        }

        return 0;
    }

    // --------------------------------------------------
    // DESTINAZIONI OPERATIVE
    // Fase iniziale: legge dalla tabella legacy "cantieri"
    // --------------------------------------------------
    public function getDestinazioni(array $filters = []): array
    {
        if (!$this->tableExists('cantieri')) {
            return [];
        }

        $search = trim((string)($filters['search'] ?? ''));

        $sql = "
            SELECT
                id,
                commessa,
                comune,
                tipologia,
                data_inizio,
                attivo,
                pausa_pranzo
            FROM cantieri
            WHERE 1=1
        ";

        $types  = '';
        $params = [];

        if ($search !== '') {
            $sql .= " AND (
                commessa LIKE ?
                OR comune LIKE ?
                OR tipologia LIKE ?
            )";
            $like = '%' . $search . '%';
            $types .= 'sss';
            array_push($params, $like, $like, $like);
        }

        $sql .= " ORDER BY commessa ASC";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            return [];
        }

        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }

        $res = $stmt->get_result();
        $rows = [];

        while ($row = $res->fetch_assoc()) {
            $row['id'] = (int)($row['id'] ?? 0);
            $row['attivo'] = isset($row['attivo']) ? (int)$row['attivo'] : 1;
            $rows[] = $row;
        }

        $res->free();
        $stmt->close();

        return $rows;
    }

    public function countDestinazioni(bool $soloAttive = false): int
    {
        if (!$this->tableExists('cantieri')) {
            return 0;
        }

        $sql = "SELECT COUNT(*) AS cnt FROM cantieri";
        if ($soloAttive) {
            $sql .= " WHERE attivo = 1 OR attivo IS NULL";
        }

        $res = $this->db->query($sql);

        if ($res instanceof mysqli_result) {
            $row = $res->fetch_assoc();
            $res->free();
            return (int)($row['cnt'] ?? 0);
        }

        return 0;
    }

    // --------------------------------------------------
    // NORMALIZZAZIONE RIGA TURNO
    // --------------------------------------------------
    private function normalizeTurnoRow(array $row): array
    {
        $row['id'] = (int)($row['id'] ?? 0);
        $row['id_cantiere'] = (int)($row['id_cantiere'] ?? 0);
        $row['id_dipendente'] = (int)($row['id_dipendente'] ?? 0);
        $row['is_capocantiere'] = (int)($row['is_capocantiere'] ?? 0);
        $row['operatore_nome'] = trim(($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? ''));

        return $row;
    }

    // --------------------------------------------------
    // QUERY BASE TURNI
    // --------------------------------------------------
    private function getTurniBaseSelect(): string
    {
        return "
            SELECT
                e.id,
                e.data,
                e.id_cantiere,
                e.id_dipendente,
                e.ora_inizio,
                e.ora_fine,
                e.is_capocantiere,
                c.commessa AS destinazione_nome,
                c.comune AS destinazione_comune,
                c.tipologia AS destinazione_tipologia,
                d.nome,
                d.cognome
            FROM eventi_turni e
            LEFT JOIN cantieri c   ON c.id = e.id_cantiere
            LEFT JOIN dipendenti d ON d.id = e.id_dipendente
        ";
    }

    // --------------------------------------------------
    // TURNI DEL GIORNO
    // Fase iniziale: legge dalla tabella legacy "eventi_turni"
    // --------------------------------------------------
    public function getTurniByData(string $dataIso): array
    {
        $dataIso = normalize_date_iso($dataIso);
        if (!$dataIso || !$this->tableExists('eventi_turni')) {
            return [];
        }

        $sql = $this->getTurniBaseSelect() . "
            WHERE e.data = ?
            ORDER BY e.ora_inizio ASC, c.commessa ASC, d.cognome ASC, d.nome ASC
        ";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('s', $dataIso);

        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }

        $res = $stmt->get_result();
        $rows = [];

        while ($row = $res->fetch_assoc()) {
            $rows[] = $this->normalizeTurnoRow($row);
        }

        $res->free();
        $stmt->close();

        return $rows;
    }

    public function getTurniBetweenDates(string $fromIso, string $toIso): array
    {
        $fromIso = normalize_date_iso($fromIso);
        $toIso   = normalize_date_iso($toIso);

        if (!$fromIso || !$toIso || !$this->tableExists('eventi_turni')) {
            return [];
        }

        if ($fromIso > $toIso) {
            [$fromIso, $toIso] = [$toIso, $fromIso];
        }

        $sql = $this->getTurniBaseSelect() . "
            WHERE e.data BETWEEN ? AND ?
            ORDER BY e.data ASC, c.commessa ASC, e.ora_inizio ASC, d.cognome ASC, d.nome ASC
        ";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('ss', $fromIso, $toIso);

        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }

        $res = $stmt->get_result();
        $rows = [];

        while ($row = $res->fetch_assoc()) {
            $rows[] = $this->normalizeTurnoRow($row);
        }

        $res->free();
        $stmt->close();

        return $rows;
    }

    public function getTurniGroupedByDateBetween(string $fromIso, string $toIso): array
    {
        $turni = $this->getTurniBetweenDates($fromIso, $toIso);
        $grouped = [];

        foreach ($turni as $turno) {
            $data = (string)($turno['data'] ?? '');
            if ($data === '') {
                continue;
            }

            if (!isset($grouped[$data])) {
                $grouped[$data] = [];
            }

            $grouped[$data][] = $turno;
        }

        return $grouped;
    }

    public function getTurnoById(int $id): ?array
    {
        if ($id <= 0 || !$this->tableExists('eventi_turni')) {
            return null;
        }

        $sql = $this->getTurniBaseSelect() . "
            WHERE e.id = ?
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $id);

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

        if (!$row) {
            return null;
        }

        return $this->normalizeTurnoRow($row);
    }

    public function countTurniByData(string $dataIso): int
    {
        $dataIso = normalize_date_iso($dataIso);
        if (!$dataIso || !$this->tableExists('eventi_turni')) {
            return 0;
        }

        $sql = "SELECT COUNT(*) AS cnt FROM eventi_turni WHERE data = ?";
        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param('s', $dataIso);

        if (!$stmt->execute()) {
            $stmt->close();
            return 0;
        }

        $res = $stmt->get_result();
        $count = 0;

        if ($res instanceof mysqli_result) {
            $row = $res->fetch_assoc();
            $count = (int)($row['cnt'] ?? 0);
            $res->free();
        }

        $stmt->close();
        return $count;
    }

    public function countOperatoriAssegnatiByData(string $dataIso): int
    {
        $dataIso = normalize_date_iso($dataIso);
        if (!$dataIso || !$this->tableExists('eventi_turni')) {
            return 0;
        }

        $sql = "SELECT COUNT(DISTINCT id_dipendente) AS cnt FROM eventi_turni WHERE data = ?";
        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param('s', $dataIso);

        if (!$stmt->execute()) {
            $stmt->close();
            return 0;
        }

        $res = $stmt->get_result();
        $count = 0;

        if ($res instanceof mysqli_result) {
            $row = $res->fetch_assoc();
            $count = (int)($row['cnt'] ?? 0);
            $res->free();
        }

        $stmt->close();
        return $count;
    }

    // --------------------------------------------------
    // CONTATORI DASHBOARD
    // --------------------------------------------------
    public function getDashboardStats(string $dataIso): array
    {
        $totOperatori    = $this->countOperatori();
        $totDestinazioni = $this->countDestinazioni(true);
        $totTurni        = $this->countTurniByData($dataIso);
        $totAssegnati    = $this->countOperatoriAssegnatiByData($dataIso);

        return [
            'tot_operatori'     => $totOperatori,
            'tot_destinazioni'  => $totDestinazioni,
            'tot_turni'         => $totTurni,
            'tot_assegnati'     => $totAssegnati,
            'tot_non_assegnati' => max(0, $totOperatori - $totAssegnati),
        ];
    }
}