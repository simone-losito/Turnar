<?php
// modules/dashboard/special_overview.php

require_once __DIR__ . '/../../core/helpers.php';

require_login();
require_permission('dashboard.view');

$pageTitle = 'Dashboard speciali';
$pageSubtitle = 'Riepilogo rapido ferie, permessi, malattia e corsi';
$activeModule = 'dashboard';

$db = db_connect();

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function sp_hours(?string $start, ?string $end, float $pause): float
{
    $start = trim((string)$start);
    $end = trim((string)$end);
    if ($start === '' || $end === '') return 0.0;

    $a = strtotime('2000-01-01 ' . $start);
    $b = strtotime('2000-01-01 ' . $end);
    if ($a === false || $b === false) return 0.0;
    if ($b <= $a) $b = strtotime('2000-01-02 ' . $end);

    $hours = max(0, ($b - $a) / 3600);
    $pause = max(0.0, $pause);
    if ($pause > 0 && $hours >= (8 + $pause)) $hours -= $pause;

    return round(max(0, $hours), 2);
}

function sp_fmt(float $value): string
{
    return number_format($value, 2, ',', '.');
}

$today = new DateTimeImmutable('today', new DateTimeZone('Europe/Rome'));
$from = normalize_date_iso((string)get('data_da', $today->format('Y-m-01'))) ?: $today->format('Y-m-01');
$to = normalize_date_iso((string)get('data_a', $today->format('Y-m-d'))) ?: $today->format('Y-m-d');
if ($from > $to) [$from, $to] = [$to, $from];

$missingMigration = false;
$check = $db->query("SHOW COLUMNS FROM cantieri LIKE 'special_type'");
if (!$check || $check->num_rows === 0) $missingMigration = true;
if ($check instanceof mysqli_result) $check->free();

$totalRows = 0;
$totalAbsenceHours = 0.0;
$totalWorkHours = 0.0;
$absenceDays = [];
$byType = [];
$byOperator = [];
$latest = [];

if (!$missingMigration) {
    $sql = "
        SELECT e.data, e.ora_inizio, e.ora_fine, e.id_dipendente,
               d.nome, d.cognome,
               c.commessa, c.pausa_pranzo, c.is_special, c.special_type, c.counts_as_work, c.counts_as_absence
        FROM eventi_turni e
        INNER JOIN cantieri c ON c.id = e.id_cantiere
        INNER JOIN dipendenti d ON d.id = e.id_dipendente
        WHERE e.data BETWEEN ? AND ? AND c.is_special = 1
        ORDER BY e.data DESC, d.cognome ASC, d.nome ASC
        LIMIT 500
    ";

    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            $dest = [
                'commessa' => (string)($row['commessa'] ?? ''),
                'is_special' => (int)($row['is_special'] ?? 0),
                'special_type' => (string)($row['special_type'] ?? ''),
                'counts_as_work' => (int)($row['counts_as_work'] ?? 0),
                'counts_as_absence' => (int)($row['counts_as_absence'] ?? 0),
            ];

            $type = special_destination_type($dest);
            $label = special_destination_label($type);
            $hours = sp_hours($row['ora_inizio'] ?? '', $row['ora_fine'] ?? '', (float)($row['pausa_pranzo'] ?? 0));
            $isAbsence = destination_counts_as_absence($dest);
            $isWork = destination_counts_as_work($dest);
            $operator = trim((string)($row['cognome'] ?? '') . ' ' . (string)($row['nome'] ?? '')) ?: '-';
            $opId = (int)($row['id_dipendente'] ?? 0);
            $date = (string)($row['data'] ?? '');

            $totalRows++;
            if (!isset($byType[$type])) $byType[$type] = ['label' => $label, 'hours' => 0.0, 'rows' => 0];
            $byType[$type]['rows']++;
            $byType[$type]['hours'] += $hours;

            if (!isset($byOperator[$opId])) $byOperator[$opId] = ['name' => $operator, 'absence_hours' => 0.0, 'days' => []];

            if ($isAbsence) {
                $totalAbsenceHours += $hours;
                $absenceDays[$opId . '_' . $date] = true;
                $byOperator[$opId]['absence_hours'] += $hours;
                $byOperator[$opId]['days'][$date] = true;
            } elseif ($isWork) {
                $totalWorkHours += $hours;
            }

            if (count($latest) < 25) {
                $latest[] = [
                    'date' => $date,
                    'operator' => $operator,
                    'destination' => (string)($row['commessa'] ?? ''),
                    'type' => $type,
                    'label' => $label,
                    'hours' => $hours,
                    'kind' => $isAbsence ? 'Assenza' : ($isWork ? 'Lavoro speciale' : 'Neutro'),
                ];
            }
        }
        $stmt->close();
    }
}

usort($byOperator, static function(array $a, array $b): int {
    return (float)$b['absence_hours'] <=> (float)$a['absence_hours'];
});

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
.special-dash{display:grid;gap:18px}.special-card{background:var(--content-card-bg);border:1px solid var(--line);border-radius:24px;box-shadow:var(--shadow);padding:18px}.special-filter{display:flex;gap:12px;align-items:end;flex-wrap:wrap}.special-filter .field{min-width:180px}.special-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.special-label{font-size:12px;font-weight:900;color:var(--muted);text-transform:uppercase;letter-spacing:.05em}.special-value{font-size:32px;font-weight:900;color:var(--text);line-height:1;margin-top:8px}.special-note{font-size:12px;color:var(--muted);line-height:1.4;margin-top:8px}.special-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.special-list{display:grid;gap:10px}.special-row{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:12px;border:1px solid var(--line);border-radius:16px;background:color-mix(in srgb,var(--bg-3) 84%,transparent)}.special-row strong{color:var(--text)}.special-table-wrap{overflow:auto;border:1px solid var(--line);border-radius:18px}.special-table{min-width:760px;width:100%;border-collapse:collapse}.special-table th,.special-table td{padding:12px;border-bottom:1px solid var(--line);font-size:13px}.special-table th{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);background:color-mix(in srgb,var(--bg-3) 84%,transparent)}.special-table td{color:var(--text)}@media(max-width:1000px){.special-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.special-grid{grid-template-columns:1fr}}@media(max-width:640px){.special-kpis{grid-template-columns:1fr}.special-filter{display:grid}}
</style>

<div class="special-dash">
    <form class="special-card special-filter" method="get">
        <div class="field"><label>Dal</label><input type="date" name="data_da" value="<?= h($from) ?>"></div>
        <div class="field"><label>Al</label><input type="date" name="data_a" value="<?= h($to) ?>"></div>
        <button class="btn btn-primary" type="submit">Aggiorna</button>
        <a class="btn btn-ghost" href="<?= h(app_url('modules/reports/report_special_destinations.php?data_da=' . urlencode($from) . '&data_a=' . urlencode($to))) ?>">Report completo</a>
    </form>

    <?php if ($missingMigration): ?>
        <div class="special-card"><h3>Migrazione richiesta</h3><p class="text-muted">Esegui prima <strong>database/migrations/2026_04_27_special_destinations.sql</strong>.</p></div>
    <?php else: ?>
        <section class="special-kpis">
            <div class="special-card"><div class="special-label">Turni speciali</div><div class="special-value"><?= (int)$totalRows ?></div><div class="special-note">Nel periodo selezionato.</div></div>
            <div class="special-card"><div class="special-label">Ore assenza</div><div class="special-value"><?= h(sp_fmt($totalAbsenceHours)) ?></div><div class="special-note">Ferie, permessi, malattia.</div></div>
            <div class="special-card"><div class="special-label">Ore corsi</div><div class="special-value"><?= h(sp_fmt($totalWorkHours)) ?></div><div class="special-note">Speciali conteggiati come lavoro.</div></div>
            <div class="special-card"><div class="special-label">Giorni assenza</div><div class="special-value"><?= count($absenceDays) ?></div><div class="special-note">Conteggio operatore/giorno.</div></div>
        </section>

        <section class="special-grid">
            <div class="special-card"><h3>Per tipo</h3><div class="special-list">
                <?php if (empty($byType)): ?><div class="empty-state">Nessun dato.</div><?php endif; ?>
                <?php foreach ($byType as $type => $item): ?>
                    <div class="special-row"><div><strong><?= h($item['label']) ?></strong><div class="special-note"><?= (int)$item['rows'] ?> turni</div></div><span class="badge <?= h(special_destination_badge_class($type)) ?>"><?= h(sp_fmt((float)$item['hours'])) ?> h</span></div>
                <?php endforeach; ?>
            </div></div>

            <div class="special-card"><h3>Operatori con più assenze</h3><div class="special-list">
                <?php if (empty($byOperator)): ?><div class="empty-state">Nessun dato.</div><?php endif; ?>
                <?php foreach (array_slice($byOperator, 0, 8) as $item): ?>
                    <div class="special-row"><div><strong><?= h($item['name']) ?></strong><div class="special-note"><?= count($item['days']) ?> giorni</div></div><span class="badge badge-danger"><?= h(sp_fmt((float)$item['absence_hours'])) ?> h</span></div>
                <?php endforeach; ?>
            </div></div>
        </section>

        <section class="special-card"><h3>Ultimi movimenti</h3>
            <?php if (empty($latest)): ?><div class="empty-state">Nessun movimento speciale.</div><?php else: ?>
            <div class="special-table-wrap"><table class="special-table"><thead><tr><th>Data</th><th>Operatore</th><th>Destinazione</th><th>Tipo</th><th>Ore</th><th>Conteggio</th></tr></thead><tbody>
            <?php foreach ($latest as $row): ?>
                <tr><td><?= h(format_date_it($row['date'])) ?></td><td><?= h($row['operator']) ?></td><td><?= h($row['destination']) ?></td><td><span class="badge <?= h(special_destination_badge_class($row['type'])) ?>"><?= h($row['label']) ?></span></td><td><strong><?= h(sp_fmt((float)$row['hours'])) ?></strong></td><td><?= h($row['kind']) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>
