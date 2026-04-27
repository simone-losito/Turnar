<?php
// modules/turni/edit_assignment.php

require_once __DIR__ . '/TurniRepository.php';

require_login();
require_module('assignments');

if (!can_manage_assignments()) {
    redirect('modules/turni/planning.php');
}

$pageTitle    = 'Modifica assegnazione';
$pageSubtitle = 'Gestione singolo turno in Turnar';
$activeModule = 'assignments';

$db = db_connect();
$repo = new TurniRepository($db);

$id = (int)get('id', post('id', 0));
if ($id <= 0) {
    redirect('modules/turni/planning.php');
}

$errorMessage       = '';
$successMessage     = '';
$conflittoDettaglio = '';
$showForceSaveBox   = false;

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function load_turno_by_id(mysqli $db, int $id): ?array
{
    $sql = "
        SELECT
            e.*,
            d.nome,
            d.cognome,
            c.commessa
        FROM eventi_turni e
        LEFT JOIN dipendenti d ON d.id = e.id_dipendente
        LEFT JOIN cantieri c   ON c.id = e.id_cantiere
        WHERE e.id = ?
        LIMIT 1
    ";

    $stmt = $db->prepare($sql);
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

    $row['id']              = (int)($row['id'] ?? 0);
    $row['id_cantiere']     = (int)($row['id_cantiere'] ?? 0);
    $row['id_dipendente']   = (int)($row['id_dipendente'] ?? 0);
    $row['is_capocantiere'] = (int)($row['is_capocantiere'] ?? 0);

    return $row;
}

function find_overlap_excluding_id(
    mysqli $db,
    int $currentId,
    string $data,
    int $idDipendente,
    string $oraInizio,
    string $oraFine
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
        WHERE e.id <> ?
          AND e.data = ?
          AND e.id_dipendente = ?
          AND NOT (e.ora_fine <= ? OR e.ora_inizio >= ?)
        LIMIT 1
    ";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('isiss', $currentId, $data, $idDipendente, $oraInizio, $oraFine);

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

function delete_overlaps_excluding_id(
    mysqli $db,
    int $currentId,
    string $data,
    int $idDipendente,
    string $oraInizio,
    string $oraFine
): bool {
    $sql = "
        DELETE FROM eventi_turni
        WHERE id <> ?
          AND data = ?
          AND id_dipendente = ?
          AND NOT (ora_fine <= ? OR ora_inizio >= ?)
    ";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('isiss', $currentId, $data, $idDipendente, $oraInizio, $oraFine);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

$turno = load_turno_by_id($db, $id);
if (!$turno) {
    redirect('modules/turni/planning.php');
}

$form = [
    'id'              => $turno['id'],
    'data'            => trim((string)($turno['data'] ?? today_date())),
    'id_dipendente'   => (int)($turno['id_dipendente'] ?? 0),
    'id_cantiere'     => (int)($turno['id_cantiere'] ?? 0),
    'ora_inizio'      => substr((string)($turno['ora_inizio'] ?? '07:00:00'), 0, 5),
    'ora_fine'        => substr((string)($turno['ora_fine'] ?? '16:00:00'), 0, 5),
    'is_responsabile' => !empty($turno['is_capocantiere']) ? 1 : 0,
];

$operatori    = $repo->getOperatori();
$destinazioni = $repo->getDestinazioni();

if (is_post()) {
    $action = trim((string)post('action', 'save'));
    $forceSave = post('force_save', '0') === '1';

    $form = [
        'id'              => $id,
        'data'            => normalize_date_iso((string)post('data', $form['data'])) ?: $form['data'],
        'id_dipendente'   => (int)post('id_dipendente', 0),
        'id_cantiere'     => (int)post('id_cantiere', 0),
        'ora_inizio'      => trim((string)post('ora_inizio', '')),
        'ora_fine'        => trim((string)post('ora_fine', '')),
        'is_responsabile' => post('is_responsabile', '0') === '1' ? 1 : 0,
    ];

    if ($action === 'delete') {
        $stmt = $db->prepare("DELETE FROM eventi_turni WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $stmt->close();
                redirect('modules/turni/planning.php?data=' . urlencode($form['data']));
            }
            $stmt->close();
        }
        $errorMessage = 'Errore durante la cancellazione del turno.';
    } else {
        if ($form['data'] === '') {
            $errorMessage = 'La data è obbligatoria.';
        } elseif ($form['id_dipendente'] <= 0) {
            $errorMessage = 'Seleziona un operatore.';
        } elseif ($form['id_cantiere'] <= 0) {
            $errorMessage = 'Seleziona una destinazione operativa.';
        } elseif ($form['ora_inizio'] === '') {
            $errorMessage = 'Inserisci l’ora di inizio.';
        } elseif ($form['ora_fine'] === '') {
            $errorMessage = 'Inserisci l’ora di fine.';
        } elseif ($form['ora_inizio'] === $form['ora_fine']) {
            $errorMessage = 'Ora inizio e ora fine non possono coincidere.';
        } else {
            $oraInizioDb = $form['ora_inizio'] . ':00';
            $oraFineDb   = $form['ora_fine'] . ':00';

            $conflitto = find_overlap_excluding_id(
                $db,
                $id,
                $form['data'],
                $form['id_dipendente'],
                $oraInizioDb,
                $oraFineDb
            );

            if ($conflitto && !$forceSave) {
                $nomeDip = trim((string)($conflitto['cognome'] ?? '') . ' ' . (string)($conflitto['nome'] ?? ''));
                $nomeDest = trim((string)($conflitto['commessa'] ?? ''));
                $oraConfDa = substr((string)($conflitto['ora_inizio'] ?? ''), 0, 5);
                $oraConfA  = substr((string)($conflitto['ora_fine'] ?? ''), 0, 5);

                $errorMessage = 'Esiste già un turno sovrapposto per questo operatore nella stessa data.';
                $conflittoDettaglio =
                    'Conflitto trovato:' . "\n" .
                    'Operatore: ' . ($nomeDip !== '' ? $nomeDip : 'N/D') . "\n" .
                    'Destinazione: ' . ($nomeDest !== '' ? $nomeDest : 'N/D') . "\n" .
                    'Orario esistente: ' . $oraConfDa . ' → ' . $oraConfA . "\n\n" .
                    'Sei sicuro di voler modificare il turno?';
                $showForceSaveBox = true;
            } else {
                $db->begin_transaction();

                try {
                    if ($conflitto && $forceSave) {
                        if (!delete_overlaps_excluding_id($db, $id, $form['data'], $form['id_dipendente'], $oraInizioDb, $oraFineDb)) {
                            throw new Exception('Impossibile eliminare i turni in conflitto.');
                        }
                    }

                    $sql = "
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
                    ";

                    $stmt = $db->prepare($sql);
                    if (!$stmt) {
                        throw new Exception('Impossibile preparare l’aggiornamento del turno.');
                    }

                    $stmt->bind_param(
                        'siissii',
                        $form['data'],
                        $form['id_cantiere'],
                        $form['id_dipendente'],
                        $oraInizioDb,
                        $oraFineDb,
                        $form['is_responsabile'],
                        $id
                    );

                    if (!$stmt->execute()) {
                        $stmt->close();
                        throw new Exception('Errore durante l’aggiornamento del turno.');
                    }

                    $stmt->close();
                    $db->commit();

                    $successMessage = $forceSave
                        ? 'Turno aggiornato correttamente. I conflitti sono stati sovrascritti.'
                        : 'Turno aggiornato correttamente.';

                    $turno = load_turno_by_id($db, $id);
                    if ($turno) {
                        $form = [
                            'id'              => $turno['id'],
                            'data'            => trim((string)($turno['data'] ?? today_date())),
                            'id_dipendente'   => (int)($turno['id_dipendente'] ?? 0),
                            'id_cantiere'     => (int)($turno['id_cantiere'] ?? 0),
                            'ora_inizio'      => substr((string)($turno['ora_inizio'] ?? '07:00:00'), 0, 5),
                            'ora_fine'        => substr((string)($turno['ora_fine'] ?? '16:00:00'), 0, 5),
                            'is_responsabile' => !empty($turno['is_capocantiere']) ? 1 : 0,
                        ];
                    }

                    $showForceSaveBox = false;
                    $conflittoDettaglio = '';
                } catch (Throwable $e) {
                    $db->rollback();
                    $errorMessage = $e->getMessage();
                }
            }
        }
    }
}

$planningUrl = app_url('modules/turni/planning.php?data=' . urlencode($form['data']));

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
.assignment-page{
    display:grid;
    gap:18px;
}

.assignment-hero{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
}

.assignment-hero-title{
    margin:0 0 8px;
    font-size:24px;
    font-weight:900;
    color:var(--text);
}

.assignment-hero-sub{
    margin:0;
    color:var(--muted);
    line-height:1.6;
    font-size:14px;
}

.assignment-wrap{
    display:grid;
    grid-template-columns:minmax(320px, 900px);
    gap:18px;
}

.assignment-card{
    background:var(--content-card-bg);
    border:1px solid var(--line);
    border-radius:24px;
    box-shadow:var(--shadow);
    padding:20px;
}

.assignment-card h2{
    margin:0 0 10px;
    font-size:22px;
    font-weight:900;
    color:var(--text);
}

.assignment-sub{
    margin:0 0 18px;
    color:var(--muted);
    line-height:1.6;
    font-size:14px;
}

.assignment-form{
    display:grid;
    grid-template-columns:repeat(2,minmax(220px,1fr));
    gap:16px;
}

.field{
    display:flex;
    flex-direction:column;
    gap:7px;
}

.field.full{
    grid-column:1 / -1;
}

.field label{
    font-size:12px;
    color:var(--muted);
    font-weight:700;
    letter-spacing:.03em;
}

.field input,
.field select{
    width:100%;
    padding:12px 13px;
    border-radius:14px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 88%, transparent);
    color:var(--text);
    outline:none;
    font-size:14px;
}

.field input:focus,
.field select:focus{
    border-color:color-mix(in srgb, var(--primary) 44%, transparent);
    box-shadow:0 0 0 3px color-mix(in srgb, var(--primary) 14%, transparent);
}

.field select option{
    color:#111827;
}

.check-row{
    grid-column:1 / -1;
    display:flex;
    align-items:center;
    gap:10px;
    padding:12px 14px;
    border-radius:14px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 84%, transparent);
    color:var(--text);
}

.check-row input{
    width:auto;
    margin:0;
}

.actions{
    grid-column:1 / -1;
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
    margin-top:6px;
}

.alert{
    margin-bottom:16px;
    padding:14px 16px;
    border-radius:18px;
    line-height:1.6;
    font-size:14px;
    border:1px solid var(--line);
}

.alert-success{
    background:rgba(52,211,153,.12);
    border-color:rgba(52,211,153,.25);
    color:#166534;
}

.alert-error{
    background:rgba(248,113,113,.10);
    border-color:rgba(248,113,113,.24);
    color:#b91c1c;
    white-space:pre-line;
}

.helper-box{
    margin-top:18px;
    padding:14px 16px;
    border-radius:18px;
    border:1px solid rgba(251,191,36,.20);
    background:rgba(251,191,36,.08);
    color:#92400e;
    line-height:1.6;
    font-size:13px;
}

.assignment-info-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:12px;
    margin:0 0 18px;
}

.assignment-info-item{
    padding:14px;
    border-radius:18px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 86%, transparent);
}

.assignment-info-label{
    font-size:11px;
    color:var(--muted);
    text-transform:uppercase;
    letter-spacing:.05em;
    margin-bottom:6px;
    font-weight:700;
}

.assignment-info-value{
    font-size:15px;
    font-weight:800;
    color:var(--text);
    word-break:break-word;
}

@media (max-width: 900px){
    .assignment-form,
    .assignment-info-grid{
        grid-template-columns:1fr;
    }
}
</style>

<div class="assignment-page">

    <div class="card">
        <div class="assignment-hero">
            <div>
                <h1 class="assignment-hero-title">Modifica turno</h1>
                <p class="assignment-hero-sub">
                    Gestisci un singolo turno, aggiorna operatore, destinazione, orari oppure rimuovilo.
                </p>
            </div>

            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a class="btn btn-ghost" href="<?php echo h($planningUrl); ?>">
                    ← Torna al planning
                </a>
            </div>
        </div>
    </div>

    <div class="assignment-wrap">
        <section class="assignment-card">
            <h2>Turno #<?php echo (int)$id; ?></h2>
            <p class="assignment-sub">
                Aggiorna il turno esistente oppure cancellalo. L’etichetta operativa usa il termine <strong>Responsabile</strong>.
            </p>

            <div class="assignment-info-grid">
                <div class="assignment-info-item">
                    <div class="assignment-info-label">Data attuale</div>
                    <div class="assignment-info-value"><?php echo h(format_date_it($form['data'])); ?></div>
                </div>

                <div class="assignment-info-item">
                    <div class="assignment-info-label">Orario</div>
                    <div class="assignment-info-value"><?php echo h($form['ora_inizio'] . ' → ' . $form['ora_fine']); ?></div>
                </div>

                <div class="assignment-info-item">
                    <div class="assignment-info-label">Ruolo turno</div>
                    <div class="assignment-info-value"><?php echo !empty($form['is_responsabile']) ? 'Responsabile' : 'Standard'; ?></div>
                </div>
            </div>

            <?php if ($successMessage !== ''): ?>
                <div class="alert alert-success">
                    <?php echo h($successMessage); ?>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage !== ''): ?>
                <div class="alert alert-error"><?php echo h($errorMessage); ?></div>
            <?php endif; ?>

            <?php if ($conflittoDettaglio !== ''): ?>
                <div class="alert alert-error"><?php echo h($conflittoDettaglio); ?></div>
            <?php endif; ?>

            <form method="post" class="assignment-form">
                <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                <input type="hidden" name="force_save" value="0">

                <div class="field">
                    <label for="data">Data</label>
                    <input type="date" id="data" name="data" value="<?php echo h($form['data']); ?>">
                </div>

                <div class="field">
                    <label for="id_dipendente">Operatore</label>
                    <select id="id_dipendente" name="id_dipendente">
                        <option value="0">Seleziona operatore</option>
                        <?php foreach ($operatori as $op): ?>
                            <option
                                value="<?php echo (int)$op['id']; ?>"
                                <?php echo (int)$form['id_dipendente'] === (int)$op['id'] ? 'selected' : ''; ?>
                            >
                                <?php echo h((string)($op['display_name'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field full">
                    <label for="id_cantiere">Destinazione operativa</label>
                    <select id="id_cantiere" name="id_cantiere">
                        <option value="0">Seleziona destinazione</option>
                        <?php foreach ($destinazioni as $dest): ?>
                            <option
                                value="<?php echo (int)$dest['id']; ?>"
                                <?php echo (int)$form['id_cantiere'] === (int)$dest['id'] ? 'selected' : ''; ?>
                            >
                                <?php
                                $label = (string)($dest['commessa'] ?? '');
                                if (!empty($dest['comune'])) {
                                    $label .= ' · ' . $dest['comune'];
                                }
                                if (!empty($dest['tipologia'])) {
                                    $label .= ' · ' . $dest['tipologia'];
                                }
                                echo h($label);
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="ora_inizio">Ora inizio</label>
                    <input type="time" id="ora_inizio" name="ora_inizio" value="<?php echo h($form['ora_inizio']); ?>">
                </div>

                <div class="field">
                    <label for="ora_fine">Ora fine</label>
                    <input type="time" id="ora_fine" name="ora_fine" value="<?php echo h($form['ora_fine']); ?>">
                </div>

                <label class="check-row">
                    <input type="checkbox" name="is_responsabile" value="1" <?php echo !empty($form['is_responsabile']) ? 'checked' : ''; ?>>
                    <span>Segna questo turno come <strong>Responsabile</strong></span>
                </label>

                <div class="actions">
                    <button type="submit" name="action" value="save" class="btn btn-primary">Salva modifiche</button>

                    <?php if ($showForceSaveBox): ?>
                        <button
                            type="submit"
                            name="action"
                            value="save"
                            class="btn btn-warning"
                            onclick="this.form.force_save.value='1';"
                        >
                            Conferma e salva comunque
                        </button>
                    <?php endif; ?>

                    <a class="btn btn-ghost" href="<?php echo h($planningUrl); ?>">Torna al planning</a>

                    <button
                        type="submit"
                        name="action"
                        value="delete"
                        class="btn btn-danger"
                        onclick="return confirm('Vuoi davvero cancellare questo turno?');"
                    >
                        Cancella turno
                    </button>
                </div>
            </form>

            <div class="helper-box">
                Questo file è allineato con la nuova grafica Turnar e con la dicitura utente <strong>Responsabile</strong> in tutta la parte visibile.
            </div>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>