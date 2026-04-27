<?php
// modules/destinations/edit.php

require_once __DIR__ . '/../../core/helpers.php';

require_login();

$pageTitle    = 'Destinazione';
$pageSubtitle = 'Crea o modifica destinazione';
$activeModule = 'destinations';

$db = db_connect();

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function normalize_date_for_db(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if ($dt && $dt->format('Y-m-d') === $value) {
        return $value;
    }

    return null;
}

function normalize_decimal($value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $value = str_replace(',', '.', $value);
    if (!is_numeric($value)) {
        return null;
    }

    return number_format((float)$value, 2, '.', '');
}

function ensure_upload_dir(string $dir): bool
{
    if (is_dir($dir)) {
        return true;
    }
    return @mkdir($dir, 0775, true);
}

function upload_destination_photo(array $file, string $baseDir, ?string &$error = null): ?string
{
    if (
        !isset($file['error'], $file['tmp_name'], $file['name']) ||
        (int)$file['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Errore durante upload foto.';
        return null;
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        $error = 'File upload non valido.';
        return null;
    }

    $allowedMime = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    if (!isset($allowedMime[$mime])) {
        $error = 'Formato immagine non supportato. Usa JPG, PNG, WEBP o GIF.';
        return null;
    }

    if ((int)$file['size'] > 5 * 1024 * 1024) {
        $error = 'La foto supera il limite di 5 MB.';
        return null;
    }

    if (!ensure_upload_dir($baseDir)) {
        $error = 'Impossibile creare la cartella upload destinazioni.';
        return null;
    }

    $ext = $allowedMime[$mime];
    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '-', pathinfo((string)$file['name'], PATHINFO_FILENAME));
    $safeName = trim((string)$safeName, '-');
    if ($safeName === '') {
        $safeName = 'destinazione';
    }

    try {
        $random = bin2hex(random_bytes(4));
    } catch (Throwable $e) {
        $random = (string)mt_rand(1000, 9999);
    }

    $newName = 'dest_' . date('Ymd_His') . '_' . $random . '_' . $safeName . '.' . $ext;
    $destPath = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        $error = 'Impossibile salvare la foto caricata.';
        return null;
    }

    return 'uploads/destinations/' . $newName;
}

function delete_destination_photo_file(?string $photoPath): void
{
    $photoPath = trim((string)$photoPath);
    if ($photoPath === '') {
        return;
    }

    $prefix = 'uploads/destinations/';
    $normalized = ltrim(str_replace('\\', '/', $photoPath), '/');

    if (strpos($normalized, $prefix) !== 0) {
        return;
    }

    $fullPath = dirname(__DIR__, 2) . '/' . $normalized;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

$errors = [];

// valori default
$form = [
    'commessa'             => '',
    'cliente'              => '',
    'codice_commessa'      => '',
    'indirizzo'            => '',
    'comune'               => '',
    'tipologia'            => '',
    'stato'                => '',
    'cig'                  => '',
    'cup'                  => '',
    'data_inizio'          => '',
    'data_fine_prevista'   => '',
    'data_fine_effettiva'  => '',
    'importo_previsto'     => '',
    'note_operativo'       => '',
    'note'                 => '',
    'foto'                 => '',
    'attivo'               => 1,
    'visibile_calendario'  => 1,
    'pausa_pranzo'         => '0.00',
    'is_special'           => 0,
    'counts_as_work'       => 1,
    'counts_as_absence'    => 0,
];

// caricamento record esistente
if ($isEdit) {
    $stmt = $db->prepare("
        SELECT
            id,
            commessa,
            cliente,
            codice_commessa,
            indirizzo,
            comune,
            tipologia,
            stato,
            cig,
            cup,
            data_inizio,
            data_fine_prevista,
            data_fine_effettiva,
            importo_previsto,
            note_operativo,
            note,
            foto,
            attivo,
            visibile_calendario,
            pausa_pranzo,
            is_special,
            counts_as_work,
            counts_as_absence
        FROM cantieri
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        die('Errore query caricamento destinazione.');
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $existing = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$existing) {
        die('Destinazione non trovata.');
    }

    $form = [
        'commessa'             => (string)($existing['commessa'] ?? ''),
        'cliente'              => (string)($existing['cliente'] ?? ''),
        'codice_commessa'      => (string)($existing['codice_commessa'] ?? ''),
        'indirizzo'            => (string)($existing['indirizzo'] ?? ''),
        'comune'               => (string)($existing['comune'] ?? ''),
        'tipologia'            => (string)($existing['tipologia'] ?? ''),
        'stato'                => (string)($existing['stato'] ?? ''),
        'cig'                  => (string)($existing['cig'] ?? ''),
        'cup'                  => (string)($existing['cup'] ?? ''),
        'data_inizio'          => (string)($existing['data_inizio'] ?? ''),
        'data_fine_prevista'   => (string)($existing['data_fine_prevista'] ?? ''),
        'data_fine_effettiva'  => (string)($existing['data_fine_effettiva'] ?? ''),
        'importo_previsto'     => (string)($existing['importo_previsto'] ?? ''),
        'note_operativo'       => (string)($existing['note_operativo'] ?? ''),
        'note'                 => (string)($existing['note'] ?? ''),
        'foto'                 => (string)($existing['foto'] ?? ''),
        'attivo'               => (int)($existing['attivo'] ?? 1),
        'visibile_calendario'  => (int)($existing['visibile_calendario'] ?? 1),
        'pausa_pranzo'         => (string)($existing['pausa_pranzo'] ?? '0.00'),
        'is_special'           => (int)($existing['is_special'] ?? 0),
        'counts_as_work'       => (int)($existing['counts_as_work'] ?? 1),
        'counts_as_absence'    => (int)($existing['counts_as_absence'] ?? 0),
    ];
}

// submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldPhotoPath = $form['foto'];

    $form['commessa']            = trim((string)($_POST['commessa'] ?? ''));
    $form['cliente']             = trim((string)($_POST['cliente'] ?? ''));
    $form['codice_commessa']     = trim((string)($_POST['codice_commessa'] ?? ''));
    $form['indirizzo']           = trim((string)($_POST['indirizzo'] ?? ''));
    $form['comune']              = trim((string)($_POST['comune'] ?? ''));
    $form['tipologia']           = trim((string)($_POST['tipologia'] ?? ''));
    $form['stato']               = trim((string)($_POST['stato'] ?? ''));
    $form['cig']                 = trim((string)($_POST['cig'] ?? ''));
    $form['cup']                 = trim((string)($_POST['cup'] ?? ''));
    $form['data_inizio']         = trim((string)($_POST['data_inizio'] ?? ''));
    $form['data_fine_prevista']  = trim((string)($_POST['data_fine_prevista'] ?? ''));
    $form['data_fine_effettiva'] = trim((string)($_POST['data_fine_effettiva'] ?? ''));
    $form['importo_previsto']    = trim((string)($_POST['importo_previsto'] ?? ''));
    $form['note_operativo']      = trim((string)($_POST['note_operativo'] ?? ''));
    $form['note']                = trim((string)($_POST['note'] ?? ''));
    $form['attivo']              = isset($_POST['attivo']) ? 1 : 0;
    $form['visibile_calendario'] = isset($_POST['visibile_calendario']) ? 1 : 0;
    $form['is_special']          = isset($_POST['is_special']) ? 1 : 0;
    $form['counts_as_work']      = isset($_POST['counts_as_work']) ? 1 : 0;
    $form['counts_as_absence']   = isset($_POST['counts_as_absence']) ? 1 : 0;
    $form['pausa_pranzo']        = trim((string)($_POST['pausa_pranzo'] ?? '0.00'));

    if ($form['commessa'] === '') {
        $errors[] = 'Il campo commessa è obbligatorio.';
    }

    $dataInizio        = normalize_date_for_db($form['data_inizio']);
    $dataFinePrevista  = normalize_date_for_db($form['data_fine_prevista']);
    $dataFineEffettiva = normalize_date_for_db($form['data_fine_effettiva']);
    $importoPrevisto   = normalize_decimal($form['importo_previsto']);
    $pausaPranzo       = normalize_decimal($form['pausa_pranzo']);

    if ($form['data_inizio'] !== '' && $dataInizio === null) {
        $errors[] = 'Data inizio non valida.';
    }
    if ($form['data_fine_prevista'] !== '' && $dataFinePrevista === null) {
        $errors[] = 'Data fine prevista non valida.';
    }
    if ($form['data_fine_effettiva'] !== '' && $dataFineEffettiva === null) {
        $errors[] = 'Data fine effettiva non valida.';
    }
    if ($form['importo_previsto'] !== '' && $importoPrevisto === null) {
        $errors[] = 'Importo previsto non valido.';
    }
    if ($pausaPranzo === null) {
        $errors[] = 'Pausa pranzo non valida.';
    } else {
        $allowedPausa = ['0.00', '0.50', '1.00'];
        if (!in_array($pausaPranzo, $allowedPausa, true)) {
            $errors[] = 'La pausa pranzo deve essere 0.00, 0.50 oppure 1.00.';
        }
    }

    // se NON è speciale, torna sempre standard
    if ((int)$form['is_special'] !== 1) {
        $form['counts_as_work'] = 1;
        $form['counts_as_absence'] = 0;
    }

    if ((int)$form['counts_as_work'] === 1 && (int)$form['counts_as_absence'] === 1) {
        $errors[] = 'Una destinazione non può essere contemporaneamente conteggiata come ore lavorate e come assenza.';
    }

    $uploadedNewPhoto = false;
    $uploadError = null;

    if (isset($_FILES['foto']) && (int)($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $uploadedPhoto = upload_destination_photo(
            $_FILES['foto'],
            dirname(__DIR__, 2) . '/uploads/destinations',
            $uploadError
        );

        if ($uploadedPhoto !== null) {
            $form['foto'] = $uploadedPhoto;
            $uploadedNewPhoto = true;
        } elseif ($uploadError !== null) {
            $errors[] = $uploadError;
        }
    }

    if (isset($_POST['remove_photo']) && $_POST['remove_photo'] === '1') {
        $form['foto'] = '';
    }

    if (empty($errors)) {
        if ($isEdit) {
            $sql = "
                UPDATE cantieri SET
                    commessa = ?,
                    cliente = ?,
                    codice_commessa = ?,
                    indirizzo = ?,
                    comune = ?,
                    tipologia = ?,
                    stato = ?,
                    cig = ?,
                    cup = ?,
                    data_inizio = ?,
                    data_fine_prevista = ?,
                    data_fine_effettiva = ?,
                    importo_previsto = ?,
                    note_operativo = ?,
                    note = ?,
                    foto = ?,
                    attivo = ?,
                    visibile_calendario = ?,
                    pausa_pranzo = ?,
                    is_special = ?,
                    counts_as_work = ?,
                    counts_as_absence = ?
                WHERE id = ?
                LIMIT 1
            ";

            $stmt = $db->prepare($sql);
            if (!$stmt) {
                $errors[] = 'Errore nella preparazione update destinazione: ' . $db->error;
            } else {
                $types = 'ssssssssssssssssiisiiii';

                $stmt->bind_param(
                    $types,
                    $form['commessa'],
                    $form['cliente'],
                    $form['codice_commessa'],
                    $form['indirizzo'],
                    $form['comune'],
                    $form['tipologia'],
                    $form['stato'],
                    $form['cig'],
                    $form['cup'],
                    $dataInizio,
                    $dataFinePrevista,
                    $dataFineEffettiva,
                    $importoPrevisto,
                    $form['note_operativo'],
                    $form['note'],
                    $form['foto'],
                    $form['attivo'],
                    $form['visibile_calendario'],
                    $pausaPranzo,
                    $form['is_special'],
                    $form['counts_as_work'],
                    $form['counts_as_absence'],
                    $id
                );

                if ($stmt->execute()) {
                    $stmt->close();

                    if ($uploadedNewPhoto && $oldPhotoPath !== '' && $oldPhotoPath !== $form['foto']) {
                        delete_destination_photo_file($oldPhotoPath);
                    }

                    if (isset($_POST['remove_photo']) && $_POST['remove_photo'] === '1' && $oldPhotoPath !== '') {
                        delete_destination_photo_file($oldPhotoPath);
                    }

                    header('Location: index.php?updated=1');
                    exit;
                } else {
                    $errors[] = 'Errore durante il salvataggio della destinazione: ' . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            $sql = "
                INSERT INTO cantieri (
                    commessa,
                    cliente,
                    codice_commessa,
                    indirizzo,
                    comune,
                    tipologia,
                    stato,
                    cig,
                    cup,
                    data_inizio,
                    data_fine_prevista,
                    data_fine_effettiva,
                    importo_previsto,
                    note_operativo,
                    note,
                    foto,
                    attivo,
                    visibile_calendario,
                    pausa_pranzo,
                    is_special,
                    counts_as_work,
                    counts_as_absence
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ";

            $stmt = $db->prepare($sql);
            if (!$stmt) {
                $errors[] = 'Errore nella preparazione inserimento destinazione: ' . $db->error;
            } else {
                $types = 'ssssssssssssssssiisiii';

                $stmt->bind_param(
                    $types,
                    $form['commessa'],
                    $form['cliente'],
                    $form['codice_commessa'],
                    $form['indirizzo'],
                    $form['comune'],
                    $form['tipologia'],
                    $form['stato'],
                    $form['cig'],
                    $form['cup'],
                    $dataInizio,
                    $dataFinePrevista,
                    $dataFineEffettiva,
                    $importoPrevisto,
                    $form['note_operativo'],
                    $form['note'],
                    $form['foto'],
                    $form['attivo'],
                    $form['visibile_calendario'],
                    $pausaPranzo,
                    $form['is_special'],
                    $form['counts_as_work'],
                    $form['counts_as_absence']
                );

                if ($stmt->execute()) {
                    $stmt->close();
                    header('Location: index.php?created=1');
                    exit;
                } else {
                    $errors[] = 'Errore durante la creazione della destinazione: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
.dest-edit-wrap{
    display:flex;
    flex-direction:column;
    gap:20px;
}

.dest-hero{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:14px;
    padding:22px;
    border-radius:22px;
    border:1px solid var(--line);
    background:
        radial-gradient(circle at top left, color-mix(in srgb, var(--primary) 16%, transparent), transparent 30%),
        radial-gradient(circle at top right, color-mix(in srgb, var(--primary-2) 14%, transparent), transparent 28%),
        linear-gradient(135deg, color-mix(in srgb, var(--card) 96%, transparent), color-mix(in srgb, var(--bg-2) 96%, transparent));
    box-shadow:0 18px 40px rgba(0,0,0,.14);
}

.dest-hero h1{
    margin:0;
    font-size:28px;
    line-height:1.1;
    color:var(--text);
    font-weight:900;
}

.dest-hero p{
    margin:8px 0 0 0;
    color:var(--muted);
    font-size:14px;
    line-height:1.55;
}

.dest-card{
    border-radius:22px;
    border:1px solid var(--line);
    background:var(--content-card-bg);
    box-shadow:0 18px 40px rgba(0,0,0,.10);
    overflow:hidden;
}

.dest-card-head{
    padding:18px 22px;
    border-bottom:1px solid var(--line);
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
}

.dest-card-title{
    color:var(--text);
    font-weight:900;
    font-size:18px;
}

.dest-card-sub{
    color:var(--muted);
    font-size:13px;
    margin-top:4px;
}

.form-grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:16px;
}

.form-grid-1{
    display:grid;
    grid-template-columns:1fr;
    gap:16px;
}

.check-grid{
    display:grid;
    grid-template-columns:repeat(3, minmax(0, 1fr));
    gap:14px;
    margin-bottom:18px;
}

.check-grid-logic{
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:14px;
    margin-top:18px;
}

.toggle-card{
    display:flex;
    gap:12px;
    align-items:flex-start;
    padding:16px;
    border-radius:18px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 82%, transparent);
}

.toggle-card.special{
    border-color:color-mix(in srgb, var(--primary-2) 28%, transparent);
    background:linear-gradient(180deg, color-mix(in srgb, var(--primary-2) 10%, transparent), color-mix(in srgb, var(--bg-3) 82%, transparent));
}

.toggle-card.logic-work{
    border-color:rgba(34,197,94,.24);
    background:linear-gradient(180deg, rgba(34,197,94,.09), color-mix(in srgb, var(--bg-3) 82%, transparent));
}

.toggle-card.logic-absence{
    border-color:rgba(239,68,68,.24);
    background:linear-gradient(180deg, rgba(239,68,68,.09), color-mix(in srgb, var(--bg-3) 82%, transparent));
}

.toggle-card.disabled-like{
    opacity:.55;
}

.toggle-card input[type="checkbox"]{
    width:20px;
    height:20px;
    margin-top:2px;
    accent-color:var(--primary-2);
    flex-shrink:0;
}

.toggle-main{
    display:flex;
    flex-direction:column;
    gap:4px;
}

.toggle-title{
    color:var(--text);
    font-weight:900;
    font-size:14px;
}

.toggle-sub{
    color:var(--muted);
    font-size:12px;
    line-height:1.4;
}

.logic-note{
    margin-top:14px;
    padding:14px 16px;
    border-radius:16px;
    border:1px solid color-mix(in srgb, var(--primary) 18%, transparent);
    background:color-mix(in srgb, var(--primary) 8%, transparent);
    color:var(--text);
    font-size:13px;
    line-height:1.5;
}

.photo-box{
    display:flex;
    align-items:flex-start;
    gap:16px;
    flex-wrap:wrap;
}

.photo-preview{
    width:160px;
    height:120px;
    border-radius:16px;
    object-fit:cover;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 86%, transparent);
    display:block;
}

.photo-empty{
    width:160px;
    height:120px;
    border-radius:16px;
    border:1px dashed var(--line);
    display:flex;
    align-items:center;
    justify-content:center;
    color:var(--muted);
    background:color-mix(in srgb, var(--bg-3) 78%, transparent);
    font-size:13px;
    font-weight:700;
}

.section-note{
    margin-top:6px;
    color:var(--muted);
    font-size:12px;
    line-height:1.45;
}

.actions{
    display:flex;
    justify-content:flex-end;
    gap:12px;
    margin-top:22px;
    flex-wrap:wrap;
}

.alert-error{
    margin-bottom:16px;
    padding:14px 16px;
    border-radius:18px;
    border:1px solid rgba(248,113,113,.30);
    background:rgba(248,113,113,.12);
    color:#991b1b;
}

@media (max-width: 900px){
    .form-grid,
    .check-grid,
    .check-grid-logic{
        grid-template-columns:1fr;
    }

    .dest-hero{
        flex-direction:column;
        align-items:flex-start;
    }
}
</style>

<div class="dest-edit-wrap">

    <section class="dest-hero">
        <div>
            <h1><?= $isEdit ? 'Modifica destinazione' : 'Nuova destinazione' ?></h1>
            <p>
                Gestione completa cantiere / destinazione con supporto al nuovo flag
                <strong>destinazione speciale</strong> e alla logica report/assenze.
            </p>
        </div>

        <a href="index.php" class="btn btn-ghost">← Torna alle destinazioni</a>
    </section>

    <section class="dest-card">
        <div class="dest-card-head">
            <div>
                <div class="dest-card-title">Dati destinazione</div>
                <div class="dest-card-sub">Compila i campi principali e le impostazioni operative.</div>
            </div>
        </div>

        <div class="card">

            <?php if (!empty($errors)): ?>
                <div class="alert-error">
                    <?php foreach ($errors as $err): ?>
                        <div>• <?= h($err) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" autocomplete="off">

                <div class="check-grid">
                    <div class="toggle-card">
                        <input type="checkbox" name="attivo" id="attivo" value="1" <?= (int)$form['attivo'] === 1 ? 'checked' : '' ?>>
                        <div class="toggle-main">
                            <label class="toggle-title" for="attivo">Destinazione attiva</label>
                            <div class="toggle-sub">Se disattiva, non viene proposta come cantiere operativo standard.</div>
                        </div>
                    </div>

                    <div class="toggle-card">
                        <input type="checkbox" name="visibile_calendario" id="visibile_calendario" value="1" <?= (int)$form['visibile_calendario'] === 1 ? 'checked' : '' ?>>
                        <div class="toggle-main">
                            <label class="toggle-title" for="visibile_calendario">Visibile nel calendario</label>
                            <div class="toggle-sub">Mostra la destinazione nelle viste calendario e planning.</div>
                        </div>
                    </div>

                    <div class="toggle-card special">
                        <input type="checkbox" name="is_special" id="is_special" value="1" <?= (int)$form['is_special'] === 1 ? 'checked' : '' ?>>
                        <div class="toggle-main">
                            <label class="toggle-title" for="is_special">Destinazione speciale</label>
                            <div class="toggle-sub">
                                Usa il flag <strong>cantieri.is_special</strong>. Serve per ferie, permessi, malattia,
                                corsi, visite mediche e simili.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="check-grid-logic">
                    <div class="toggle-card logic-work" id="workLogicCard">
                        <input type="checkbox" name="counts_as_work" id="counts_as_work" value="1" <?= (int)$form['counts_as_work'] === 1 ? 'checked' : '' ?>>
                        <div class="toggle-main">
                            <label class="toggle-title" for="counts_as_work">Conteggia come ore lavorate</label>
                            <div class="toggle-sub">
                                Utile per corsi di formazione, attività interne, visite considerate presenza lavorativa.
                            </div>
                        </div>
                    </div>

                    <div class="toggle-card logic-absence" id="absenceLogicCard">
                        <input type="checkbox" name="counts_as_absence" id="counts_as_absence" value="1" <?= (int)$form['counts_as_absence'] === 1 ? 'checked' : '' ?>>
                        <div class="toggle-main">
                            <label class="toggle-title" for="counts_as_absence">Conteggia come assenza</label>
                            <div class="toggle-sub">
                                Utile per ferie, permessi, malattia e destinazioni che dovranno pesare nella percentuale assenze.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="logic-note">
                    <strong>Logica consigliata:</strong><br>
                    • destinazione normale → ore lavorate = sì, assenza = no<br>
                    • ferie / permessi / malattia → ore lavorate = no, assenza = sì<br>
                    • corso di formazione → ore lavorate = sì, assenza = no
                </div>

                <div class="form-grid mt-4">
                    <div class="field">
                        <label for="commessa">Commessa *</label>
                        <input type="text" id="commessa" name="commessa" value="<?= h($form['commessa']) ?>" required>
                    </div>

                    <div class="field">
                        <label for="cliente">Cliente</label>
                        <input type="text" id="cliente" name="cliente" value="<?= h($form['cliente']) ?>">
                    </div>

                    <div class="field">
                        <label for="codice_commessa">Codice commessa</label>
                        <input type="text" id="codice_commessa" name="codice_commessa" value="<?= h($form['codice_commessa']) ?>">
                    </div>

                    <div class="field">
                        <label for="tipologia">Tipologia</label>
                        <input type="text" id="tipologia" name="tipologia" value="<?= h($form['tipologia']) ?>">
                    </div>

                    <div class="field">
                        <label for="indirizzo">Indirizzo</label>
                        <input type="text" id="indirizzo" name="indirizzo" value="<?= h($form['indirizzo']) ?>">
                    </div>

                    <div class="field">
                        <label for="comune">Comune</label>
                        <input type="text" id="comune" name="comune" value="<?= h($form['comune']) ?>">
                    </div>

                    <div class="field">
                        <label for="stato">Stato</label>
                        <input type="text" id="stato" name="stato" value="<?= h($form['stato']) ?>">
                    </div>

                    <div class="field">
                        <label for="pausa_pranzo">Pausa pranzo</label>
                        <select id="pausa_pranzo" name="pausa_pranzo">
                            <option value="0.00" <?= $form['pausa_pranzo'] === '0.00' ? 'selected' : '' ?>>0.00 = Nessuna</option>
                            <option value="0.50" <?= $form['pausa_pranzo'] === '0.50' ? 'selected' : '' ?>>0.50 = 30 minuti</option>
                            <option value="1.00" <?= $form['pausa_pranzo'] === '1.00' ? 'selected' : '' ?>>1.00 = 60 minuti</option>
                        </select>
                        <div class="section-note">
                            La regola di calcolo ore dei report continuerà a usare questa pausa solo secondo la logica progetto.
                        </div>
                    </div>

                    <div class="field">
                        <label for="cig">CIG</label>
                        <input type="text" id="cig" name="cig" value="<?= h($form['cig']) ?>">
                    </div>

                    <div class="field">
                        <label for="cup">CUP</label>
                        <input type="text" id="cup" name="cup" value="<?= h($form['cup']) ?>">
                    </div>

                    <div class="field">
                        <label for="importo_previsto">Importo previsto</label>
                        <input type="text" id="importo_previsto" name="importo_previsto" value="<?= h($form['importo_previsto']) ?>" placeholder="Es. 15000.00">
                    </div>

                    <div class="field">
                        <label for="data_inizio">Data inizio</label>
                        <input type="date" id="data_inizio" name="data_inizio" value="<?= h($form['data_inizio']) ?>">
                    </div>

                    <div class="field">
                        <label for="data_fine_prevista">Data fine prevista</label>
                        <input type="date" id="data_fine_prevista" name="data_fine_prevista" value="<?= h($form['data_fine_prevista']) ?>">
                    </div>

                    <div class="field">
                        <label for="data_fine_effettiva">Data fine effettiva</label>
                        <input type="date" id="data_fine_effettiva" name="data_fine_effettiva" value="<?= h($form['data_fine_effettiva']) ?>">
                    </div>
                </div>

                <div class="form-grid-1 mt-4">
                    <div class="field">
                        <label for="foto">Foto destinazione</label>
                        <div class="photo-box">
                            <?php if (!empty($form['foto'])): ?>
                                <img
                                    class="photo-preview"
                                    id="photoPreviewImage"
                                    src="<?= h(app_url($form['foto'])) ?>"
                                    alt="Foto destinazione"
                                >
                                <div class="photo-empty" id="photoPreviewEmpty" style="display:none;">Nessuna foto</div>
                            <?php else: ?>
                                <img
                                    class="photo-preview"
                                    id="photoPreviewImage"
                                    src=""
                                    alt="Foto destinazione"
                                    style="display:none;"
                                >
                                <div class="photo-empty" id="photoPreviewEmpty">Nessuna foto</div>
                            <?php endif; ?>

                            <div style="display:flex; flex-direction:column; gap:10px; min-width:260px; flex:1;">
                                <input type="file" id="foto" name="foto" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">

                                <?php if (!empty($form['foto'])): ?>
                                    <label style="display:flex; align-items:center; gap:8px; color:var(--text); font-size:13px; font-weight:700;">
                                        <input type="checkbox" name="remove_photo" id="remove_photo" value="1" style="accent-color:#ef4444; width:auto;">
                                        Rimuovi foto attuale
                                    </label>
                                <?php else: ?>
                                    <label style="display:flex; align-items:center; gap:8px; color:var(--muted); font-size:13px; font-weight:700;">
                                        <input type="checkbox" id="remove_photo_dummy" disabled style="accent-color:#ef4444; width:auto;">
                                        Nessuna foto attuale da rimuovere
                                    </label>
                                <?php endif; ?>

                                <div class="section-note">
                                    Cartella upload: <strong>uploads/destinations</strong> — nel database viene salvato solo il path.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="field">
                        <label for="note_operativo">Note operative</label>
                        <textarea id="note_operativo" name="note_operativo"><?= h($form['note_operativo']) ?></textarea>
                    </div>

                    <div class="field">
                        <label for="note">Note generali</label>
                        <textarea id="note" name="note"><?= h($form['note']) ?></textarea>
                    </div>
                </div>

                <div class="actions">
                    <a href="index.php" class="btn btn-ghost">Annulla</a>
                    <button type="submit" class="btn btn-primary">
                        <?= $isEdit ? 'Salva modifiche' : 'Crea destinazione' ?>
                    </button>
                </div>

            </form>
        </div>
    </section>
</div>

<script>
(function () {
    const fileInput = document.getElementById('foto');
    const previewImg = document.getElementById('photoPreviewImage');
    const previewEmpty = document.getElementById('photoPreviewEmpty');
    const removePhoto = document.getElementById('remove_photo');

    const isSpecial = document.getElementById('is_special');
    const countsAsWork = document.getElementById('counts_as_work');
    const countsAsAbsence = document.getElementById('counts_as_absence');
    const workLogicCard = document.getElementById('workLogicCard');
    const absenceLogicCard = document.getElementById('absenceLogicCard');

    function showEmpty() {
        if (!previewImg || !previewEmpty) return;
        previewImg.style.display = 'none';
        previewImg.src = '';
        previewEmpty.style.display = 'flex';
    }

    function showImage(src) {
        if (!previewImg || !previewEmpty) return;
        previewImg.src = src;
        previewImg.style.display = 'block';
        previewEmpty.style.display = 'none';
    }

    if (fileInput && previewImg && previewEmpty) {
        fileInput.addEventListener('change', function () {
            const file = this.files && this.files[0] ? this.files[0] : null;

            if (!file) {
                <?php if (!empty($form['foto'])): ?>
                    showImage(<?= json_encode(app_url($form['foto'])) ?>);
                <?php else: ?>
                    showEmpty();
                <?php endif; ?>
                return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                showImage(e.target.result);
                if (removePhoto) {
                    removePhoto.checked = false;
                }
            };
            reader.readAsDataURL(file);
        });

        if (removePhoto) {
            removePhoto.addEventListener('change', function () {
                if (this.checked) {
                    fileInput.value = '';
                    showEmpty();
                } else {
                    <?php if (!empty($form['foto'])): ?>
                        showImage(<?= json_encode(app_url($form['foto'])) ?>);
                    <?php endif; ?>
                }
            });
        }
    }

    function syncSpecialLogicUi() {
        if (!isSpecial || !countsAsWork || !countsAsAbsence) {
            return;
        }

        const specialOn = isSpecial.checked;

        if (!specialOn) {
            countsAsWork.checked = true;
            countsAsAbsence.checked = false;
            countsAsWork.disabled = true;
            countsAsAbsence.disabled = true;

            if (workLogicCard) workLogicCard.classList.add('disabled-like');
            if (absenceLogicCard) absenceLogicCard.classList.add('disabled-like');
            return;
        }

        countsAsWork.disabled = false;
        countsAsAbsence.disabled = false;

        if (workLogicCard) workLogicCard.classList.remove('disabled-like');
        if (absenceLogicCard) absenceLogicCard.classList.remove('disabled-like');
    }

    if (isSpecial) {
        isSpecial.addEventListener('change', syncSpecialLogicUi);
    }

    if (countsAsWork && countsAsAbsence) {
        countsAsWork.addEventListener('change', function () {
            if (this.checked) {
                countsAsAbsence.checked = false;
            }
        });

        countsAsAbsence.addEventListener('change', function () {
            if (this.checked) {
                countsAsWork.checked = false;
            }
        });
    }

    syncSpecialLogicUi();
})();
</script>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>