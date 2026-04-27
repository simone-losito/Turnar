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

$date = normalize_date_iso((string)get('data', today_date())) ?: today_date();
$confirm = ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)post('confirm_send', '') === '1');

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
        ];
    }

    $byDipendente[$dipId]['turni'][] = $turno;
}

$sent = false;
$results = [];

if ($confirm) {
    foreach ($byDipendente as $dipId => $item) {
        $lines = [];
        foreach ($item['turni'] as $turno) {
            $dest = trim((string)($turno['destinazione_nome'] ?? 'Destinazione'));
            $comune = trim((string)($turno['destinazione_comune'] ?? ''));
            $orario = format_time_it((string)($turno['ora_inizio'] ?? '')) . ' - ' . format_time_it((string)($turno['ora_fine'] ?? ''));
            $label = $orario . ' · ' . $dest;
            if ($comune !== '') $label .= ' (' . $comune . ')';
            $lines[] = $label;
        }

        $title = 'Turni aggiornati';
        $message = 'Sono stati pubblicati/aggiornati i tuoi turni del ' . format_date_it($date) . ":\n" . implode("\n", $lines);
        $link = 'calendar.php?date=' . urlencode($date);

        $saved = app_notification_create((int)$dipId, $title, $message, 'turno', $link);
        $pushed = send_browser_push_to_dipendente((int)$dipId, $title, $message, $link);

        $results[] = [
            'operatore' => (string)$item['operatore'],
            'saved' => $saved,
            'pushed' => $pushed,
        ];
    }

    $sent = true;
}

$pageTitle = 'Invia notifiche turni';
$pageSubtitle = 'Invio manuale delle notifiche dopo conferma operatore';
$activeModule = 'assignments';

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
.send-box{display:grid;gap:16px}.send-card{background:var(--content-card-bg);border:1px solid var(--line);border-radius:24px;box-shadow:var(--shadow);padding:18px}.send-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.send-kpi{border:1px solid var(--line);border-radius:18px;padding:14px;background:color-mix(in srgb,var(--bg-3) 84%,transparent)}.send-label{font-size:12px;color:var(--muted);font-weight:900;text-transform:uppercase;letter-spacing:.05em}.send-value{font-size:28px;color:var(--text);font-weight:950;margin-top:8px}.send-list{display:grid;gap:10px}.send-row{padding:12px;border:1px solid var(--line);border-radius:16px;background:color-mix(in srgb,var(--bg-3) 84%,transparent)}.send-row strong{color:var(--text)}.send-muted{color:var(--muted);font-size:12px;margin-top:4px;line-height:1.45}.send-actions{display:flex;gap:10px;flex-wrap:wrap}.send-alert{padding:14px;border-radius:18px;border:1px solid rgba(251,191,36,.32);background:rgba(251,191,36,.12);color:var(--text);line-height:1.55}.send-ok{border-color:rgba(34,197,94,.32);background:rgba(34,197,94,.12)}@media(max-width:800px){.send-kpis{grid-template-columns:1fr}.send-actions .btn{width:100%}}
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
        <div class="send-kpi"><div class="send-label">Operatori coinvolti</div><div class="send-value"><?= count($byDipendente) ?></div></div>
        <div class="send-kpi"><div class="send-label">Turni totali</div><div class="send-value"><?= count($turni) ?></div></div>
    </section>

    <section class="send-card">
        <div class="send-alert">
            <strong>Invio manuale controllato.</strong><br>
            Le notifiche partono solo quando premi conferma. Puoi spostare 20 persone, fare prove e correggere errori senza disturbare nessuno.
        </div>
    </section>

    <?php if ($sent): ?>
        <section class="send-card">
            <div class="send-alert send-ok"><strong>Invio completato.</strong> Sono state create le notifiche app e, dove possibile, inviate le push browser.</div>
            <div class="send-list" style="margin-top:12px;">
                <?php foreach ($results as $row): ?>
                    <div class="send-row">
                        <strong><?= h($row['operatore']) ?></strong>
                        <div class="send-muted">
                            Notifica app: <?= !empty($row['saved']) ? 'OK' : 'NON salvata' ?> · Push telefono: <?= !empty($row['pushed']) ? 'inviata' : 'non disponibile/non configurata' ?>
                        </div>
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
                    <div class="send-row">
                        <strong><?= h($item['operatore']) ?></strong>
                        <?php foreach ($item['turni'] as $turno): ?>
                            <div class="send-muted">
                                <?= h(format_time_it((string)($turno['ora_inizio'] ?? ''))) ?> - <?= h(format_time_it((string)($turno['ora_fine'] ?? ''))) ?> ·
                                <?= h((string)($turno['destinazione_nome'] ?? '')) ?>
                                <?php if (trim((string)($turno['destinazione_comune'] ?? '')) !== ''): ?>
                                    (<?= h((string)$turno['destinazione_comune']) ?>)
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <form method="post" class="send-actions" style="margin-top:16px;" onsubmit="return confirm('Confermi invio notifiche ai <?= count($byDipendente) ?> operatori coinvolti?');">
                <input type="hidden" name="confirm_send" value="1">
                <button class="btn btn-primary" type="submit">Invia notifiche giornata</button>
                <a class="btn btn-ghost" href="<?= h(app_url('modules/turni/index.php')) ?>">Annulla</a>
            </form>
        <?php endif; ?>
    </section>
</div>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>
