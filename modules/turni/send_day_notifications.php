<?php
// modules/turni/send_day_notifications.php

require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/app_notifications.php';
require_once __DIR__ . '/../../core/push.php';
require_once __DIR__ . '/TurniRepository.php';

require_login();
require_permission('assignments.edit');

$db = db_connect();
$repo = new TurniRepository($db);

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function send_notify_minutes(?string $time): int
{
    $time = trim((string)$time);
    if ($time === '') return 0;
    $parts = explode(':', $time);
    $h = isset($parts[0]) ? (int)$parts[0] : 0;
    $m = isset($parts[1]) ? (int)$parts[1] : 0;
    return ($h * 60) + $m;
}

function send_notify_interval(array $turno): array
{
    $start = send_notify_minutes((string)($turno['ora_inizio'] ?? ''));
    $end = send_notify_minutes((string)($turno['ora_fine'] ?? ''));
    if ($end <= $start) $end += 1440;
    return [$start, $end];
}

function send_notify_turno_label(array $turno): string
{
    $dest = trim((string)($turno['destinazione_nome'] ?? 'Destinazione'));
    $comune = trim((string)($turno['destinazione_comune'] ?? ''));
    $orario = format_time_it((string)($turno['ora_inizio'] ?? '')) . ' - ' . format_time_it((string)($turno['ora_fine'] ?? ''));
    $label = $orario . ' · ' . $dest;
    if ($comune !== '') $label .= ' (' . $comune . ')';
    return $label;
}

$date = normalize_date_iso((string)get('data', today_date())) ?: today_date();
$confirm = ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)post('confirm_send', '') === '1');
$forceSend = ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)post('force_send', '') === '1');

$turni = $repo->getTurniByData($date);

$byDipendente = [];
foreach ($turni as $turno) {
    $dipId = (int)($turno['id_dipendente'] ?? 0);
    if ($dipId <= 0) continue;

    if (!isset($byDipendente[$dipId])) {
        $byDipendente[$dipId] = [
            'dipendente_id' => $dipId,
            'operatore' => trim((string)($turno['operatore_nome'] ?? 'Operatore')),
            'turni' => [],
            'conflicts' => [],
            'conflict_turn_ids' => [],
        ];
    }

    $byDipendente[$dipId]['turni'][] = $turno;
}

$totalConflicts = 0;
foreach ($byDipendente as $dipId => $item) {
    $items = $item['turni'];
    $countItems = count($items);

    for ($i = 0; $i < $countItems; $i++) {
        [$aStart, $aEnd] = send_notify_interval($items[$i]);
        $aId = (int)($items[$i]['id'] ?? 0);

        for ($j = $i + 1; $j < $countItems; $j++) {
            [$bStart, $bEnd] = send_notify_interval($items[$j]);
            $bId = (int)($items[$j]['id'] ?? 0);

            $overlap = !($aEnd <= $bStart || $aStart >= $bEnd);
            if ($overlap) {
                $byDipendente[$dipId]['conflicts'][] = [
                    'a' => $items[$i],
                    'b' => $items[$j],
                ];
                if ($aId > 0) $byDipendente[$dipId]['conflict_turn_ids'][$aId] = true;
                if ($bId > 0) $byDipendente[$dipId]['conflict_turn_ids'][$bId] = true;
                $totalConflicts++;
            }
        }
    }
}

$hasConflicts = $totalConflicts > 0;
$blockedByConflicts = $confirm && $hasConflicts && !$forceSend;

$sent = false;
$results = [];

if ($confirm && !$blockedByConflicts) {
    foreach ($byDipendente as $dipId => $item) {
        $lines = [];
        foreach ($item['turni'] as $turno) {
            $lines[] = send_notify_turno_label($turno);
        }

        $title = 'Turni aggiornati';
        $message = 'Sono stati pubblicati/aggiornati i tuoi turni del ' . format_date_it($date) . ":\n" . implode("\n", $lines);
        if (!empty($item['conflicts'])) {
            $message .= "\n\nAttenzione: sono presenti turni sovrapposti. Verifica il calendario.";
        }
        $link = 'calendar.php?date=' . urlencode($date);

        $saved = app_notification_create((int)$dipId, $title, $message, 'turno', $link);
        $pushed = send_browser_push_to_dipendente((int)$dipId, $title, $message, $link);

        $results[] = [
            'operatore' => (string)$item['operatore'],
            'saved' => $saved,
            'pushed' => $pushed,
            'forced' => !empty($item['conflicts']),
        ];
    }

    $sent = true;
}

$pageTitle = 'Invia notifiche turni';
$pageSubtitle = 'Invio manuale e controllato delle notifiche dopo verifica giornata';
$activeModule = 'assignments';

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
.send-box{display:grid;gap:16px}.send-card{background:var(--content-card-bg);border:1px solid var(--line);border-radius:24px;box-shadow:var(--shadow);padding:18px}.send-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.send-kpi{border:1px solid var(--line);border-radius:18px;padding:14px;background:color-mix(in srgb,var(--bg-3) 84%,transparent)}.send-kpi.warn{border-color:rgba(248,113,113,.36);background:rgba(248,113,113,.10)}.send-label{font-size:12px;color:var(--muted);font-weight:900;text-transform:uppercase;letter-spacing:.05em}.send-value{font-size:28px;color:var(--text);font-weight:950;margin-top:8px}.send-list{display:grid;gap:10px}.send-row{padding:12px;border:1px solid var(--line);border-radius:16px;background:color-mix(in srgb,var(--bg-3) 84%,transparent)}.send-row.has-conflict{border-color:rgba(248,113,113,.38);background:linear-gradient(180deg,rgba(248,113,113,.13),color-mix(in srgb,var(--bg-3) 82%,transparent))}.send-row strong{color:var(--text)}.send-muted{color:var(--muted);font-size:12px;margin-top:4px;line-height:1.45}.send-turn-line{padding:7px 9px;border-radius:12px;margin-top:6px;background:color-mix(in srgb,var(--bg-3) 78%,transparent);border:1px solid var(--line);font-size:12px;color:var(--text)}.send-turn-line.conflict{border-color:rgba(248,113,113,.44);background:rgba(248,113,113,.16);color:#fecaca;font-weight:900}.send-actions{display:flex;gap:10px;flex-wrap:wrap}.send-alert{padding:14px;border-radius:18px;border:1px solid rgba(251,191,36,.32);background:rgba(251,191,36,.12);color:var(--text);line-height:1.55}.send-danger{border-color:rgba(248,113,113,.38);background:rgba(248,113,113,.13)}.send-ok{border-color:rgba(34,197,94,.32);background:rgba(34,197,94,.12)}.send-force{margin-top:14px;padding:12px 14px;border:1px solid rgba(248,113,113,.34);background:rgba(248,113,113,.10);border-radius:16px;display:flex;gap:10px;align-items:flex-start;color:var(--text)}.send-force input{width:auto;margin-top:3px}.send-conflict-box{display:grid;gap:10px;margin-top:12px}.send-conflict-item{padding:12px;border-radius:16px;border:1px solid rgba(248,113,113,.34);background:rgba(248,113,113,.12)}@media(max-width:900px){.send-kpis{grid-template-columns:1fr}.send-actions .btn{width:100%}}
</style>

<div class="send-box">
    <section class="send-card">
        <form method="get" class="toolbar" style="margin:0;">
            <div class="toolbar-left">
                <label class="send-label" for="data">Giornata da notificare</label>
                <input type="date" id="data" name="data" value="<?= h($date) ?>" class="field-sm">
                <button class="btn btn-secondary" type="submit">Carica giornata</button>
            </div>
            <div class="toolbar-right">
                <a class="btn btn-ghost" href="<?= h(app_url('modules/turni/index.php')) ?>">Torna ai turni</a>
            </div>
        </form>
    </section>

    <section class="send-kpis">
        <div class="send-kpi"><div class="send-label">Data</div><div class="send-value"><?= h(format_date_it($date)) ?></div></div>
        <div class="send-kpi"><div class="send-label">Operatori</div><div class="send-value"><?= count($byDipendente) ?></div></div>
        <div class="send-kpi"><div class="send-label">Turni</div><div class="send-value"><?= count($turni) ?></div></div>
        <div class="send-kpi <?= $hasConflicts ? 'warn' : '' ?>"><div class="send-label">Interferenze</div><div class="send-value"><?= (int)$totalConflicts ?></div></div>
    </section>

    <?php if ($blockedByConflicts): ?>
        <section class="send-card"><div class="send-alert send-danger"><strong>Invio bloccato per sicurezza.</strong><br>Ci sono interferenze nella giornata. Controllale sotto. Per inviare comunque devi spuntare la conferma extra “Ho controllato le interferenze”.</div></section>
    <?php elseif ($hasConflicts): ?>
        <section class="send-card"><div class="send-alert send-danger"><strong>Attenzione: sono presenti interferenze.</strong><br>Uno o più dipendenti hanno turni sovrapposti nella stessa giornata. Le righe interessate sono evidenziate in rosso. Puoi correggere il planning oppure forzare l’invio dopo controllo.</div></section>
    <?php else: ?>
        <section class="send-card"><div class="send-alert send-ok"><strong>Nessuna interferenza rilevata.</strong><br>Puoi procedere con l’invio notifiche della giornata.</div></section>
    <?php endif; ?>

    <?php if ($sent): ?>
        <section class="send-card">
            <div class="send-alert send-ok"><strong>Invio completato.</strong> Sono state create le notifiche app e, dove possibile, inviate le push browser.</div>
            <div class="send-list" style="margin-top:12px;">
                <?php foreach ($results as $row): ?>
                    <div class="send-row <?= !empty($row['forced']) ? 'has-conflict' : '' ?>">
                        <strong><?= h($row['operatore']) ?></strong>
                        <div class="send-muted">
                            Notifica app: <?= !empty($row['saved']) ? 'OK' : 'NON salvata' ?> · Push telefono: <?= !empty($row['pushed']) ? 'inviata' : 'non disponibile/non configurata' ?>
                            <?= !empty($row['forced']) ? ' · inviato con interferenze segnalate' : '' ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($hasConflicts): ?>
        <section class="send-card">
            <h3 style="margin-top:0;">Interferenze rilevate</h3>
            <div class="send-conflict-box">
                <?php foreach ($byDipendente as $item): ?>
                    <?php if (empty($item['conflicts'])) continue; ?>
                    <div class="send-conflict-item">
                        <strong><?= h($item['operatore']) ?></strong>
                        <?php foreach ($item['conflicts'] as $conflict): ?>
                            <div class="send-muted">
                                Sovrapposizione tra:<br>
                                <span class="send-turn-line conflict"><?= h(send_notify_turno_label($conflict['a'])) ?></span>
                                <span class="send-turn-line conflict"><?= h(send_notify_turno_label($conflict['b'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="send-card">
        <h3 style="margin-top:0;">Anteprima destinatari</h3>
        <?php if (empty($byDipendente)): ?>
            <div class="empty-state">Nessun turno presente per questa giornata.</div>
        <?php else: ?>
            <div class="send-list">
                <?php foreach ($byDipendente as $item): ?>
                    <?php $operatorHasConflict = !empty($item['conflicts']); ?>
                    <div class="send-row <?= $operatorHasConflict ? 'has-conflict' : '' ?>">
                        <strong><?= h($item['operatore']) ?></strong>
                        <?php foreach ($item['turni'] as $turno): ?>
                            <?php $turnId = (int)($turno['id'] ?? 0); $isConflictTurn = $turnId > 0 && !empty($item['conflict_turn_ids'][$turnId]); ?>
                            <div class="send-turn-line <?= $isConflictTurn ? 'conflict' : '' ?>">
                                <?= h(send_notify_turno_label($turno)) ?>
                                <?= $isConflictTurn ? ' · INTERFERENZA' : '' ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <form method="post" class="send-actions" style="margin-top:16px;" onsubmit="return confirm('Sei sicuro di voler inviare i turni del <?= h(format_date_it($date)) ?> a <?= count($byDipendente) ?> operatori?<?= $hasConflicts ? ' ATTENZIONE: sono presenti interferenze.' : '' ?>');">
                <input type="hidden" name="confirm_send" value="1">
                <?php if ($hasConflicts): ?>
                    <label class="send-force">
                        <input type="checkbox" name="force_send" value="1" required>
                        <span><strong>Ho controllato le interferenze e voglio inviare comunque.</strong><br>Usa questa opzione solo se le sovrapposizioni sono volute o già verificate.</span>
                    </label>
                <?php endif; ?>
                <button class="btn btn-primary" type="submit"><?= $hasConflicts ? 'Forza e invia notifiche' : 'Invia notifiche giornata' ?></button>
                <a class="btn btn-ghost" href="<?= h(app_url('modules/turni/index.php')) ?>">Annulla</a>
            </form>
        <?php endif; ?>
    </section>
</div>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>
