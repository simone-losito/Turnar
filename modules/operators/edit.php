<?php
// modules/operators/edit.php

require_once __DIR__ . '/../../core/helpers.php';

require_login();

$id = (int)get('id', 0);
$isEdit = $id > 0;

if ($isEdit) {
    require_permission('operators.edit');
} else {
    require_permission('operators.create');
}

$pageTitle    = 'Personale';
$pageSubtitle = 'Creazione e modifica anagrafica del personale';
$activeModule = 'operators';

$canOpenUsers = can('users.view') || can('users.edit');

$db = db_connect();

$errors = [];

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function safe_redirect(string $url): void
{
    if (!headers_sent()) {
        header('Location: ' . $url);
    }
    exit;
}

function operators_upload_dir_path(): string
{
    return dirname(__DIR__, 2) . '/uploads/operators';
}

function operators_upload_dir_url(): string
{
    return app_url('uploads/operators');
}

function normalize_checkbox(string $key): int
{
    return isset($_POST[$key]) ? 1 : 0;
}

function sanitize_filename_piece(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'file';
    }

    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $value = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string)$value);
    $value = trim((string)$value, '_');

    return $value !== '' ? strtolower($value) : 'file';
}

function handle_operator_photo_upload(array &$errors, ?string $oldPhoto = null): ?string
{
    if (
        !isset($_FILES['foto']) ||
        !is_array($_FILES['foto']) ||
        (int)($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
    ) {
        return $oldPhoto;
    }

    $fileError = (int)($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($fileError !== UPLOAD_ERR_OK) {
        $errors[] = 'Errore durante il caricamento della foto.';
        return $oldPhoto;
    }

    $tmpPath = (string)($_FILES['foto']['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        $errors[] = 'File foto non valido.';
        return $oldPhoto;
    }

    $maxBytes = 5 * 1024 * 1024;
    $fileSize = (int)($_FILES['foto']['size'] ?? 0);
    if ($fileSize <= 0 || $fileSize > $maxBytes) {
        $errors[] = 'La foto supera il limite massimo di 5 MB.';
        return $oldPhoto;
    }

    $imageInfo = @getimagesize($tmpPath);
    if (!$imageInfo || empty($imageInfo['mime'])) {
        $errors[] = 'Il file caricato non è un\'immagine valida.';
        return $oldPhoto;
    }

    $mime = strtolower((string)$imageInfo['mime']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        $errors[] = 'Formato foto non supportato. Usa JPG, PNG o WEBP.';
        return $oldPhoto;
    }

    $uploadDir = operators_upload_dir_path();
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            $errors[] = 'Impossibile creare la cartella upload personale.';
            return $oldPhoto;
        }
    }

    $nome     = sanitize_filename_piece((string)post('nome', ''));
    $cognome  = sanitize_filename_piece((string)post('cognome', ''));
    $ext      = $allowed[$mime];
    $fileName = $nome . '_' . $cognome . '_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;

    $destPath = $uploadDir . '/' . $fileName;
    if (!move_uploaded_file($tmpPath, $destPath)) {
        $errors[] = 'Impossibile salvare la foto caricata.';
        return $oldPhoto;
    }

    return operators_upload_dir_url() . '/' . $fileName;
}

function find_operator_user(mysqli $db, int $dipendenteId): ?array
{
    if ($dipendenteId <= 0) {
        return null;
    }

    $stmt = $db->prepare("SELECT * FROM users WHERE dipendente_id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $dipendenteId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return is_array($row) ? $row : null;
}

function generate_username_base(string $nome, string $cognome): string
{
    $nome = trim($nome);
    $cognome = trim($cognome);

    $full = trim($nome . '.' . $cognome);
    if ($full === '.' || $full === '') {
        $full = trim($nome . $cognome);
    }

    $full = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $full);
    $full = strtolower((string)$full);
    $full = preg_replace('/[^a-z0-9\.]+/', '.', (string)$full);
    $full = preg_replace('/\.{2,}/', '.', (string)$full);
    $full = trim((string)$full, '.');

    return $full !== '' ? $full : 'utente';
}

function username_exists_in_users(mysqli $db, string $username, int $excludeUserId = 0): bool
{
    $username = trim($username);
    if ($username === '') {
        return false;
    }

    if ($excludeUserId > 0) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $username, $excludeUserId);
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $username);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->fetch_assoc();
    $stmt->close();

    return (bool)$exists;
}

function generate_unique_username(mysqli $db, string $nome, string $cognome, int $excludeUserId = 0): string
{
    $base = generate_username_base($nome, $cognome);
    $candidate = $base;
    $i = 2;

    while (username_exists_in_users($db, $candidate, $excludeUserId)) {
        $candidate = $base . '.' . $i;
        $i++;
    }

    return $candidate;
}

function generate_initial_password(string $nome): string
{
    $nome = trim($nome);
    if ($nome === '') {
        return 'utente';
    }

    $nome = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);
    $nome = strtolower((string)$nome);
    $nome = preg_replace('/[^a-z0-9]+/', '', (string)$nome);
    $nome = trim((string)$nome);

    return $nome !== '' ? $nome : 'utente';
}

function sync_operator_user_account(
    mysqli $db,
    int $dipendenteId,
    array $form,
    ?array $existingUser,
    array &$errors
): ?array {
    if ($dipendenteId <= 0) {
        $errors[] = 'Dipendente non valido per la sincronizzazione account.';
        return null;
    }

    $email   = trim((string)($form['email'] ?? ''));
    $attivo  = !empty($form['attivo']) ? 1 : 0;
    $nome    = trim((string)($form['nome'] ?? ''));
    $cognome = trim((string)($form['cognome'] ?? ''));

    $hasExistingUser = is_array($existingUser) && !empty($existingUser['id']);

    if ($hasExistingUser) {
        $existingUserId = (int)$existingUser['id'];
        $username = trim((string)($existingUser['username'] ?? ''));
        if ($username === '') {
            $username = generate_unique_username($db, $nome, $cognome, $existingUserId);
        }

        $sql = "
            UPDATE users SET
                dipendente_id = ?,
                username = ?,
                email = ?,
                is_active = ?,
                updated_at = NOW()
            WHERE id = ?
        ";

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            $errors[] = 'Errore preparazione aggiornamento account utente.';
            return null;
        }

        $stmt->bind_param(
            'issii',
            $dipendenteId,
            $username,
            $email,
            $attivo,
            $existingUserId
        );

        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            $errors[] = 'Errore durante l\'aggiornamento dell\'account utente.';
            return null;
        }

        $updated = find_operator_user($db, $dipendenteId);
        return $updated ?: $existingUser;
    }

    $username = generate_unique_username($db, $nome, $cognome);
    $plainPassword = generate_initial_password($nome);
    $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);

    $role  = defined('ROLE_USER') ? ROLE_USER : 'user';
    $scope = defined('SCOPE_SELF') ? SCOPE_SELF : 'self';

    $canLoginWeb = 0;
    $canLoginApp = 1;
    $mustChangePassword = 1;
    $isAdministrative = 0;

    $sql = "
        INSERT INTO users (
            dipendente_id,
            role,
            scope,
            username,
            password_hash,
            email,
            is_active,
            can_login_web,
            can_login_app,
            must_change_password,
            is_administrative,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        $errors[] = 'Errore preparazione creazione account utente.';
        return null;
    }

    $stmt->bind_param(
        'isssssiiiii',
        $dipendenteId,
        $role,
        $scope,
        $username,
        $passwordHash,
        $email,
        $attivo,
        $canLoginWeb,
        $canLoginApp,
        $mustChangePassword,
        $isAdministrative
    );

    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        $errors[] = 'Errore durante la creazione dell\'account utente.';
        return null;
    }

    return find_operator_user($db, $dipendenteId);
}

function account_status_label(?array $userAccount): string
{
    if (!$userAccount) {
        return 'Nessun account';
    }

    return !empty($userAccount['is_active']) ? 'Attivo' : 'Disattivato';
}

function calculate_net_shift_hours(?string $oraInizio, ?string $oraFine, $pausaPranzo): float
{
    $oraInizio = trim((string)$oraInizio);
    $oraFine   = trim((string)$oraFine);

    if ($oraInizio === '' || $oraFine === '') {
        return 0.0;
    }

    $start = DateTime::createFromFormat('H:i:s', $oraInizio) ?: DateTime::createFromFormat('H:i', $oraInizio);
    $end   = DateTime::createFromFormat('H:i:s', $oraFine)   ?: DateTime::createFromFormat('H:i', $oraFine);

    if (!$start || !$end) {
        return 0.0;
    }

    $startMinutes = ((int)$start->format('H') * 60) + (int)$start->format('i');
    $endMinutes   = ((int)$end->format('H') * 60) + (int)$end->format('i');

    if ($endMinutes <= $startMinutes) {
        $endMinutes += 1440;
    }

    $grossMinutes = $endMinutes - $startMinutes;
    if ($grossMinutes <= 0) {
        return 0.0;
    }

    $pausa = trim((string)$pausaPranzo);
    $pausa = $pausa !== '' ? (float)str_replace(',', '.', $pausa) : 0.0;
    $pausaMinutes = (int)round($pausa * 60);

    if ($grossMinutes >= 480 && $pausaMinutes > 0) {
        $grossMinutes -= $pausaMinutes;
    }

    if ($grossMinutes < 0) {
        $grossMinutes = 0;
    }

    return round($grossMinutes / 60, 2);
}

function get_operator_attendance_stats(mysqli $db, int $dipendenteId): array
{
    $year = (int)date('Y');

    $stats = [
        'year' => $year,
        'worked_hours' => 0.0,
        'absence_hours' => 0.0,
        'neutral_special_hours' => 0.0,
        'total_assignments' => 0,
        'percentage_absence' => 0.0,
    ];

    if ($dipendenteId <= 0) {
        return $stats;
    }

    $sql = "
        SELECT
            et.id,
            et.ora_inizio,
            et.ora_fine,
            c.pausa_pranzo,
            c.is_special,
            c.counts_as_work,
            c.counts_as_absence
        FROM eventi_turni et
        INNER JOIN cantieri c ON c.id = et.id_cantiere
        WHERE et.id_dipendente = ?
          AND YEAR(et.data) = ?
    ";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return $stats;
    }

    $stmt->bind_param('ii', $dipendenteId, $year);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $hours = calculate_net_shift_hours(
                (string)($row['ora_inizio'] ?? ''),
                (string)($row['ora_fine'] ?? ''),
                $row['pausa_pranzo'] ?? 0
            );

            if ($hours <= 0) {
                continue;
            }

            $stats['total_assignments']++;

            $countsAsWork = isset($row['counts_as_work']) ? (int)$row['counts_as_work'] : 1;
            $countsAsAbsence = isset($row['counts_as_absence']) ? (int)$row['counts_as_absence'] : 0;
            $isSpecial = !empty($row['is_special']) ? 1 : 0;

            if ($countsAsWork === 1) {
                $stats['worked_hours'] += $hours;
            } elseif ($countsAsAbsence === 1) {
                $stats['absence_hours'] += $hours;
            } elseif ($isSpecial === 1) {
                $stats['neutral_special_hours'] += $hours;
            }
        }
    }

    $stmt->close();

    $stats['worked_hours'] = round($stats['worked_hours'], 2);
    $stats['absence_hours'] = round($stats['absence_hours'], 2);
    $stats['neutral_special_hours'] = round($stats['neutral_special_hours'], 2);

    $baseHours = $stats['worked_hours'] + $stats['absence_hours'];
    if ($baseHours > 0) {
        $stats['percentage_absence'] = round(($stats['absence_hours'] / $baseHours) * 100, 1);
    }

    return $stats;
}

// --------------------------------------------------
// SUGGERIMENTI DINAMICI
// --------------------------------------------------
$tipologie = [];
$resTipologie = $db->query("SELECT DISTINCT tipologia FROM dipendenti WHERE tipologia IS NOT NULL AND TRIM(tipologia) <> '' ORDER BY tipologia ASC");
if ($resTipologie) {
    while ($row = $resTipologie->fetch_assoc()) {
        $tipologie[] = (string)$row['tipologia'];
    }
}

$livelli = [];
$resLivelli = $db->query("SELECT DISTINCT livello FROM dipendenti WHERE livello IS NOT NULL AND TRIM(livello) <> '' ORDER BY livello ASC");
if ($resLivelli) {
    while ($row = $resLivelli->fetch_assoc()) {
        $livelli[] = (string)$row['livello'];
    }
}

// --------------------------------------------------
// DEFAULT FORM
// --------------------------------------------------
$form = [
    'id'                      => 0,
    'nome'                    => '',
    'cognome'                 => '',
    'matricola'               => '',
    'foto'                    => '',
    'telefono'                => '',
    'email'                   => '',
    'codice_fiscale'          => '',
    'iban'                    => '',
    'indirizzo_residenza'     => '',
    'data_assunzione'         => '',
    'tipo_contratto'          => 'indeterminato',
    'data_scadenza_contratto' => '',
    'tipologia'               => '',
    'livello'                 => '',
    'preposto'                => 0,
    'capo_cantiere'           => 0,
    'attivo'                  => 1,
];

$userAccount = null;
$createdAutoCredentials = null;
$attendanceStats = null;

// --------------------------------------------------
// CARICAMENTO RECORD IN MODIFICA
// --------------------------------------------------
if ($isEdit && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $db->prepare("SELECT * FROM dipendenti WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $existing = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    } else {
        $existing = null;
    }

    if (!$existing) {
        safe_redirect(app_url('modules/operators/index.php'));
    }

    foreach ($form as $key => $value) {
        if (array_key_exists($key, $existing)) {
            $form[$key] = $existing[$key] ?? $value;
        }
    }

    $userAccount = find_operator_user($db, $id);
    $attendanceStats = get_operator_attendance_stats($db, $id);
    $pageTitle = 'Modifica personale';
}

// --------------------------------------------------
// SALVATAGGIO
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)post('id', 0);
    $isEdit = $id > 0;

    $oldPhoto = '';
    $existingUser = null;

    if ($isEdit) {
        $stmtOld = $db->prepare("SELECT foto FROM dipendenti WHERE id = ? LIMIT 1");
        if ($stmtOld) {
            $stmtOld->bind_param("i", $id);
            $stmtOld->execute();
            $resOld = $stmtOld->get_result();
            $oldRow = $resOld ? $resOld->fetch_assoc() : null;
            $stmtOld->close();
        } else {
            $oldRow = null;
        }

        if (!$oldRow) {
            $errors[] = 'Persona non trovata.';
        } else {
            $oldPhoto = (string)($oldRow['foto'] ?? '');
        }

        $existingUser = find_operator_user($db, $id);
    }

    $form['id']                      = $id;
    $form['nome']                    = trim((string)post('nome', ''));
    $form['cognome']                 = trim((string)post('cognome', ''));
    $form['matricola']               = trim((string)post('matricola', ''));
    $form['telefono']                = trim((string)post('telefono', ''));
    $form['email']                   = trim((string)post('email', ''));
    $form['codice_fiscale']          = strtoupper(trim((string)post('codice_fiscale', '')));
    $form['iban']                    = strtoupper(trim((string)post('iban', '')));
    $form['indirizzo_residenza']     = trim((string)post('indirizzo_residenza', ''));
    $form['data_assunzione']         = trim((string)post('data_assunzione', ''));
    $form['tipo_contratto']          = trim((string)post('tipo_contratto', '')) === 'determinato' ? 'determinato' : 'indeterminato';
    $form['data_scadenza_contratto'] = trim((string)post('data_scadenza_contratto', ''));
    $form['tipologia']               = trim((string)post('tipologia', ''));
    $form['livello']                 = trim((string)post('livello', ''));
    $form['preposto']                = normalize_checkbox('preposto');
    $form['capo_cantiere']           = normalize_checkbox('capo_cantiere');
    $form['attivo']                  = normalize_checkbox('attivo');

    if ($form['nome'] === '') {
        $errors[] = 'Il nome è obbligatorio.';
    }

    if ($form['cognome'] === '') {
        $errors[] = 'Il cognome è obbligatorio.';
    }

    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email non valida.';
    }

    if ($form['codice_fiscale'] !== '' && strlen($form['codice_fiscale']) !== 16) {
        $errors[] = 'Il codice fiscale deve avere 16 caratteri.';
    }

    if ($form['data_assunzione'] !== '' && !normalize_date_iso($form['data_assunzione'])) {
        $errors[] = 'Data assunzione non valida.';
    }

    if ($form['data_scadenza_contratto'] !== '' && !normalize_date_iso($form['data_scadenza_contratto'])) {
        $errors[] = 'Data scadenza contratto non valida.';
    }

    if ($form['tipo_contratto'] === 'determinato' && $form['data_scadenza_contratto'] === '') {
        $errors[] = 'Per il contratto determinato devi indicare la data di scadenza.';
    }

    if ($form['matricola'] !== '') {
        if ($isEdit) {
            $stmtMat = $db->prepare("SELECT id FROM dipendenti WHERE matricola = ? AND id <> ? LIMIT 1");
            if ($stmtMat) {
                $stmtMat->bind_param("si", $form['matricola'], $id);
            }
        } else {
            $stmtMat = $db->prepare("SELECT id FROM dipendenti WHERE matricola = ? LIMIT 1");
            if ($stmtMat) {
                $stmtMat->bind_param("s", $form['matricola']);
            }
        }

        if (!empty($stmtMat)) {
            $stmtMat->execute();
            $resMat = $stmtMat->get_result();
            if ($resMat && $resMat->fetch_assoc()) {
                $errors[] = 'La matricola esiste già.';
            }
            $stmtMat->close();
        }
    }

    if (empty($errors)) {
        $uploadedPhoto = handle_operator_photo_upload($errors, $oldPhoto);
        if ($uploadedPhoto !== null) {
            $form['foto'] = $uploadedPhoto;
        }
    }

    if (empty($errors)) {
        $dataAssunzione = $form['data_assunzione'] !== '' ? $form['data_assunzione'] : null;
        $dataScadenza   = $form['data_scadenza_contratto'] !== '' ? $form['data_scadenza_contratto'] : null;
        $matricola      = $form['matricola'] !== '' ? $form['matricola'] : null;
        $foto           = $form['foto'] !== '' ? $form['foto'] : null;
        $telefono       = $form['telefono'] !== '' ? $form['telefono'] : null;
        $email          = $form['email'] !== '' ? $form['email'] : null;
        $cf             = $form['codice_fiscale'] !== '' ? $form['codice_fiscale'] : null;
        $iban           = $form['iban'] !== '' ? $form['iban'] : null;
        $indirizzo      = $form['indirizzo_residenza'] !== '' ? $form['indirizzo_residenza'] : null;
        $tipologia      = $form['tipologia'] !== '' ? $form['tipologia'] : null;
        $livello        = $form['livello'] !== '' ? $form['livello'] : null;

        try {
            $db->begin_transaction();

            if ($isEdit) {
                $sql = "UPDATE dipendenti SET
                            nome = ?,
                            cognome = ?,
                            matricola = ?,
                            foto = ?,
                            telefono = ?,
                            email = ?,
                            codice_fiscale = ?,
                            iban = ?,
                            indirizzo_residenza = ?,
                            data_assunzione = ?,
                            tipo_contratto = ?,
                            data_scadenza_contratto = ?,
                            tipologia = ?,
                            livello = ?,
                            preposto = ?,
                            capo_cantiere = ?,
                            attivo = ?
                        WHERE id = ?";

                $stmt = $db->prepare($sql);
                if (!$stmt) {
                    throw new RuntimeException('Errore preparazione query di aggiornamento personale.');
                }

                $stmt->bind_param(
                    "ssssssssssssssiiii",
                    $form['nome'],
                    $form['cognome'],
                    $matricola,
                    $foto,
                    $telefono,
                    $email,
                    $cf,
                    $iban,
                    $indirizzo,
                    $dataAssunzione,
                    $form['tipo_contratto'],
                    $dataScadenza,
                    $tipologia,
                    $livello,
                    $form['preposto'],
                    $form['capo_cantiere'],
                    $form['attivo'],
                    $id
                );

                $ok = $stmt->execute();
                $stmt->close();

                if (!$ok) {
                    throw new RuntimeException('Errore durante il salvataggio delle modifiche del personale.');
                }

                $savedId = $id;
            } else {
                $sql = "INSERT INTO dipendenti (
                            nome,
                            cognome,
                            matricola,
                            foto,
                            telefono,
                            email,
                            codice_fiscale,
                            iban,
                            indirizzo_residenza,
                            data_assunzione,
                            tipo_contratto,
                            data_scadenza_contratto,
                            tipologia,
                            livello,
                            preposto,
                            capo_cantiere,
                            attivo
                        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

                $stmt = $db->prepare($sql);
                if (!$stmt) {
                    throw new RuntimeException('Errore preparazione query di creazione personale.');
                }

                $stmt->bind_param(
                    "ssssssssssssssiii",
                    $form['nome'],
                    $form['cognome'],
                    $matricola,
                    $foto,
                    $telefono,
                    $email,
                    $cf,
                    $iban,
                    $indirizzo,
                    $dataAssunzione,
                    $form['tipo_contratto'],
                    $dataScadenza,
                    $tipologia,
                    $livello,
                    $form['preposto'],
                    $form['capo_cantiere'],
                    $form['attivo']
                );

                $ok = $stmt->execute();
                $savedId = $ok ? (int)$stmt->insert_id : 0;
                $stmt->close();

                if (!$ok || $savedId <= 0) {
                    throw new RuntimeException('Errore durante la creazione della persona.');
                }
            }

            $beforeUser = $existingUser;
            $userAccount = sync_operator_user_account($db, $savedId, $form, $existingUser, $errors);

            if (!$userAccount || !empty($errors)) {
                throw new RuntimeException('Errore sincronizzazione account utente.');
            }

            if (!$beforeUser && $userAccount) {
                $createdAutoCredentials = [
                    'username' => (string)($userAccount['username'] ?? ''),
                    'password' => generate_initial_password((string)$form['nome']),
                ];
            }

            $db->commit();

            if ($createdAutoCredentials) {
                $_SESSION['turnar_auto_user_created'] = $createdAutoCredentials;
            } else {
                unset($_SESSION['turnar_auto_user_created']);
            }

            if ($isEdit) {
                safe_redirect(app_url('modules/operators/edit.php?id=' . $savedId . '&saved=1'));
            }

            safe_redirect(app_url('modules/operators/edit.php?id=' . $savedId . '&created=1'));
        } catch (Throwable $e) {
            $db->rollback();

            if (empty($errors)) {
                $errors[] = $e->getMessage();
            }
        }
    }

    $pageTitle = $isEdit ? 'Modifica personale' : 'Nuovo personale';
}

if (!$isEdit && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $pageTitle = 'Nuovo personale';
}

$saved = (int)get('saved', 0) === 1;
$created = (int)get('created', 0) === 1;
$autoUserCreated = $_SESSION['turnar_auto_user_created'] ?? null;
unset($_SESSION['turnar_auto_user_created']);

if ($isEdit && !$userAccount) {
    $userAccount = find_operator_user($db, $id);
}

if ($isEdit && $attendanceStats === null) {
    $attendanceStats = get_operator_attendance_stats($db, $id);
}

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
.operator-grid{
    display:grid;
    grid-template-columns:minmax(320px, 420px) minmax(0, 1fr);
    gap:18px;
}

.operator-left-stack{
    display:flex;
    flex-direction:column;
    gap:18px;
}

.operator-photo-wrap{
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:14px;
}

.operator-photo{
    width:180px;
    height:180px;
    border-radius:24px;
    overflow:hidden;
    border:1px solid var(--line);
    background:linear-gradient(135deg, color-mix(in srgb, var(--primary) 20%, transparent), color-mix(in srgb, var(--primary-2) 16%, transparent));
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:56px;
    font-weight:800;
    color:#fff;
    box-shadow:0 14px 30px rgba(0,0,0,.12);
}

.operator-photo img{
    width:100%;
    height:100%;
    object-fit:cover;
}

.operator-form-grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0,1fr));
    gap:14px;
}

.field.full{
    grid-column:1 / -1;
}

.check-row{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
}

.check-pill{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:10px 14px;
    border-radius:999px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 88%, transparent);
}

.actions-row{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-top:18px;
}

.muted-note{
    color:var(--muted);
    font-size:12px;
    line-height:1.5;
}

.credentials-box{
    margin-top:8px;
    padding:14px;
    border-radius:18px;
    border:1px solid color-mix(in srgb, var(--primary) 20%, transparent);
    background:color-mix(in srgb, var(--primary) 6%, transparent);
}

.credentials-box-title{
    font-size:13px;
    font-weight:800;
    margin-bottom:6px;
    color:var(--text);
}

.credentials-box-note{
    color:var(--muted);
    font-size:12px;
    line-height:1.6;
}

.credentials-grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0,1fr));
    gap:12px;
    margin-top:12px;
}

.credentials-item{
    padding:12px 14px;
    border-radius:16px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 88%, transparent);
}

.credentials-label{
    font-size:11px;
    color:var(--muted);
    text-transform:uppercase;
    letter-spacing:.05em;
    margin-bottom:6px;
}

.credentials-value{
    font-size:14px;
    font-weight:700;
    color:var(--text);
    word-break:break-word;
}

.attendance-card{
    background:
        radial-gradient(circle at top right, rgba(239,68,68,.10), transparent 30%),
        radial-gradient(circle at top left, rgba(34,197,94,.10), transparent 30%),
        var(--content-card-bg);
}

.attendance-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    margin-bottom:14px;
}

.attendance-title{
    font-size:18px;
    font-weight:900;
    color:var(--text);
}

.attendance-sub{
    color:var(--muted);
    font-size:12px;
    line-height:1.5;
    margin-top:4px;
}

.attendance-percent{
    min-width:88px;
    text-align:right;
    font-size:28px;
    font-weight:900;
    line-height:1;
    color:#dc2626;
}

.attendance-bar{
    width:100%;
    height:16px;
    border-radius:999px;
    background:color-mix(in srgb, var(--bg-3) 88%, transparent);
    border:1px solid var(--line);
    overflow:hidden;
    position:relative;
}

.attendance-bar-fill{
    height:100%;
    border-radius:999px;
    background:linear-gradient(90deg, rgba(34,197,94,.92), rgba(251,191,36,.95), rgba(239,68,68,.95));
}

.attendance-scale{
    display:flex;
    justify-content:space-between;
    gap:10px;
    margin-top:8px;
    font-size:11px;
    color:var(--muted);
}

.attendance-grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0,1fr));
    gap:12px;
    margin-top:16px;
}

.attendance-item{
    padding:12px 14px;
    border-radius:16px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 88%, transparent);
}

.attendance-item.work{
    border-color:rgba(34,197,94,.22);
    background:rgba(34,197,94,.08);
}

.attendance-item.absence{
    border-color:rgba(239,68,68,.22);
    background:rgba(239,68,68,.08);
}

.attendance-item.neutral{
    border-color:color-mix(in srgb, var(--primary) 22%, transparent);
    background:color-mix(in srgb, var(--primary) 8%, transparent);
}

.attendance-item.total{
    border-color:var(--line);
}

.attendance-label{
    font-size:11px;
    color:var(--muted);
    text-transform:uppercase;
    letter-spacing:.05em;
    margin-bottom:6px;
}

.attendance-value{
    font-size:18px;
    font-weight:900;
    color:var(--text);
}

.attendance-note{
    margin-top:14px;
    color:var(--muted);
    font-size:12px;
    line-height:1.6;
}

.alert-success,
.alert-info,
.alert-error{
    margin-bottom:16px;
    padding:14px 16px;
    border-radius:18px;
    border:1px solid var(--line);
}

.alert-success{
    border-color:rgba(52,211,153,.28);
    background:rgba(52,211,153,.12);
    color:#166534;
}

.alert-info{
    border-color:color-mix(in srgb, var(--primary) 28%, transparent);
    background:color-mix(in srgb, var(--primary) 10%, transparent);
    color:var(--text);
}

.alert-error{
    border-color:rgba(248,113,113,.30);
    background:rgba(248,113,113,.12);
    color:#991b1b;
}

@media (max-width: 980px){
    .operator-grid{
        grid-template-columns:1fr;
    }

    .operator-form-grid,
    .credentials-grid,
    .attendance-grid{
        grid-template-columns:1fr;
    }
}
</style>

<?php if ($saved): ?>
    <div class="alert-success">Modifiche salvate correttamente.</div>
<?php endif; ?>

<?php if ($created): ?>
    <div class="alert-success">Persona creata correttamente.</div>
<?php endif; ?>

<?php if (is_array($autoUserCreated) && !empty($autoUserCreated['username'])): ?>
    <div class="alert-info">
        <strong>Account creato automaticamente.</strong><br>
        Username iniziale: <strong><?php echo h((string)$autoUserCreated['username']); ?></strong><br>
        Password iniziale: <strong><?php echo h((string)$autoUserCreated['password']); ?></strong><br>
        Al primo accesso verrà richiesto il cambio password.
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert-error">
        <strong>Controlla questi punti:</strong>
        <div class="mt-2">
            <?php foreach ($errors as $error): ?>
                <div>• <?php echo h($error); ?></div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="operator-grid">
    <div class="operator-left-stack">
        <div class="card">
            <div class="operator-photo-wrap">
                <div class="operator-photo">
                    <?php if (!empty($form['foto'])): ?>
                        <img src="<?php echo h($form['foto']); ?>" alt="Foto personale">
                    <?php else: ?>
                        <?php
                        $initials = '';
                        if ($form['nome'] !== '') {
                            $initials .= mb_strtoupper(mb_substr($form['nome'], 0, 1, 'UTF-8'), 'UTF-8');
                        }
                        if ($form['cognome'] !== '') {
                            $initials .= mb_strtoupper(mb_substr($form['cognome'], 0, 1, 'UTF-8'), 'UTF-8');
                        }
                        echo h($initials !== '' ? $initials : 'PS');
                        ?>
                    <?php endif; ?>
                </div>

                <div class="text-center">
                    <div style="font-size:20px; font-weight:800; color:var(--text);">
                        <?php echo h(trim($form['nome'] . ' ' . $form['cognome']) ?: 'Nuova persona'); ?>
                    </div>
                    <div class="muted-note mt-1">
                        Foto caricabile da gestionale o da app. Il ruolo operativo è gestito con il campo <strong>tipologia</strong>.
                    </div>
                </div>
            </div>
        </div>

        <?php if ($isEdit && is_array($attendanceStats)): ?>
            <?php
            $absencePercent = max(0, min(100, (float)$attendanceStats['percentage_absence']));
            ?>
            <div class="card attendance-card">
                <div class="attendance-head">
                    <div>
                        <div class="attendance-title">Percentuale assenze</div>
                        <div class="attendance-sub">
                            Calcolo anno <?php echo (int)$attendanceStats['year']; ?> basato sui turni del dipendente.<br>
                            La percentuale usa solo le ore con destinazioni marcate come <strong>lavoro</strong> o <strong>assenza</strong>.
                        </div>
                    </div>
                    <div class="attendance-percent"><?php echo h(number_format($absencePercent, 1, ',', '.')); ?>%</div>
                </div>

                <div class="attendance-bar">
                    <div class="attendance-bar-fill" style="width: <?php echo h((string)$absencePercent); ?>%;"></div>
                </div>

                <div class="attendance-scale">
                    <span>0%</span>
                    <span>50%</span>
                    <span>100%</span>
                </div>

                <div class="attendance-grid">
                    <div class="attendance-item work">
                        <div class="attendance-label">Ore lavorate</div>
                        <div class="attendance-value"><?php echo h(number_format((float)$attendanceStats['worked_hours'], 2, ',', '.')); ?> h</div>
                    </div>

                    <div class="attendance-item absence">
                        <div class="attendance-label">Ore assenza</div>
                        <div class="attendance-value"><?php echo h(number_format((float)$attendanceStats['absence_hours'], 2, ',', '.')); ?> h</div>
                    </div>

                    <div class="attendance-item neutral">
                        <div class="attendance-label">Ore speciali neutre</div>
                        <div class="attendance-value"><?php echo h(number_format((float)$attendanceStats['neutral_special_hours'], 2, ',', '.')); ?> h</div>
                    </div>

                    <div class="attendance-item total">
                        <div class="attendance-label">Turni anno corrente</div>
                        <div class="attendance-value"><?php echo (int)$attendanceStats['total_assignments']; ?></div>
                    </div>
                </div>

                <div class="attendance-note">
                    <strong>Nota:</strong> ferie, permessi e malattia entreranno nella barra solo se la destinazione ha
                    <strong>counts_as_absence = 1</strong>. Corsi di formazione e simili entreranno come lavoro solo se hanno
                    <strong>counts_as_work = 1</strong>. Le destinazioni speciali senza nessuno dei due flag restano escluse dalla percentuale.
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo (int)$form['id']; ?>">

            <div class="operator-form-grid">
                <div class="field">
                    <label>Nome *</label>
                    <input type="text" name="nome" value="<?php echo h((string)$form['nome']); ?>" required>
                </div>

                <div class="field">
                    <label>Cognome *</label>
                    <input type="text" name="cognome" value="<?php echo h((string)$form['cognome']); ?>" required>
                </div>

                <div class="field">
                    <label>Matricola</label>
                    <input type="text" name="matricola" value="<?php echo h((string)$form['matricola']); ?>">
                </div>

                <div class="field">
                    <label>Foto</label>
                    <input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                </div>

                <div class="field">
                    <label>Telefono</label>
                    <input type="text" name="telefono" value="<?php echo h((string)$form['telefono']); ?>">
                </div>

                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo h((string)$form['email']); ?>">
                </div>

                <div class="field">
                    <label>Ruolo / Tipologia</label>
                    <input type="text" name="tipologia" list="tipologie-list" value="<?php echo h((string)$form['tipologia']); ?>" placeholder="Es. tecnico, elettricista, magazzino...">
                    <datalist id="tipologie-list">
                        <?php foreach ($tipologie as $tipologia): ?>
                            <option value="<?php echo h($tipologia); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="field">
                    <label>Livello</label>
                    <input type="text" name="livello" list="livelli-list" value="<?php echo h((string)$form['livello']); ?>" placeholder="Es. senior, apprendista...">
                    <datalist id="livelli-list">
                        <?php foreach ($livelli as $livello): ?>
                            <option value="<?php echo h($livello); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="field">
                    <label>Codice fiscale</label>
                    <input type="text" name="codice_fiscale" maxlength="16" value="<?php echo h((string)$form['codice_fiscale']); ?>">
                </div>

                <div class="field">
                    <label>IBAN</label>
                    <input type="text" name="iban" value="<?php echo h((string)$form['iban']); ?>">
                </div>

                <div class="field full">
                    <label>Indirizzo residenza</label>
                    <textarea name="indirizzo_residenza"><?php echo h((string)$form['indirizzo_residenza']); ?></textarea>
                </div>

                <div class="field">
                    <label>Data assunzione</label>
                    <input type="date" name="data_assunzione" value="<?php echo h((string)$form['data_assunzione']); ?>">
                </div>

                <div class="field">
                    <label>Tipo contratto</label>
                    <select name="tipo_contratto">
                        <option value="indeterminato" <?php echo $form['tipo_contratto'] === 'indeterminato' ? 'selected' : ''; ?>>Indeterminato</option>
                        <option value="determinato" <?php echo $form['tipo_contratto'] === 'determinato' ? 'selected' : ''; ?>>Determinato</option>
                    </select>
                </div>

                <div class="field">
                    <label>Scadenza contratto</label>
                    <input type="date" name="data_scadenza_contratto" value="<?php echo h((string)$form['data_scadenza_contratto']); ?>">
                </div>

                <div class="field full">
                    <div class="credentials-box">
                        <div class="credentials-box-title">Accesso software / app</div>
                        <div class="credentials-box-note">
                            Le credenziali non si gestiscono più qui.<br>
                            Quando crei o salvi una persona, il sistema crea o aggiorna automaticamente il suo account collegato nella sezione <strong>Gestione Utenti</strong>.<br>
                            Credenziali iniziali per nuovi account: <strong>username = nome.cognome</strong> · <strong>password = nome</strong> · cambio password obbligatorio al primo accesso.
                        </div>

                        <div class="credentials-grid">
                            <div class="credentials-item">
                                <div class="credentials-label">Account collegato</div>
                                <div class="credentials-value">
                                    <?php echo $userAccount ? 'Sì' : 'Verrà creato automaticamente'; ?>
                                </div>
                            </div>

                            <div class="credentials-item">
                                <div class="credentials-label">Stato account</div>
                                <div class="credentials-value">
                                    <?php echo h(account_status_label($userAccount)); ?>
                                </div>
                            </div>

                            <div class="credentials-item">
                                <div class="credentials-label">Username</div>
                                <div class="credentials-value">
                                    <?php
                                    if ($userAccount && !empty($userAccount['username'])) {
                                        echo h((string)$userAccount['username']);
                                    } else {
                                        echo h(generate_username_base((string)$form['nome'], (string)$form['cognome']));
                                    }
                                    ?>
                                </div>
                            </div>

                            <div class="credentials-item">
                                <div class="credentials-label">Gestione credenziali</div>
                                <div class="credentials-value">
                                    Solo da Gestione Utenti
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="field full">
                    <label>Stato e flag</label>
                    <div class="check-row">
                        <label class="check-pill">
                            <input type="checkbox" name="attivo" value="1" <?php echo !empty($form['attivo']) ? 'checked' : ''; ?> style="width:auto;">
                            <span>Attivo</span>
                        </label>

                        <label class="check-pill">
                            <input type="checkbox" name="preposto" value="1" <?php echo !empty($form['preposto']) ? 'checked' : ''; ?> style="width:auto;">
                            <span>Preposto</span>
                        </label>

                        <label class="check-pill">
                            <input type="checkbox" name="capo_cantiere" value="1" <?php echo !empty($form['capo_cantiere']) ? 'checked' : ''; ?> style="width:auto;">
                            <span>Responsabile</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="actions-row">
                <button type="submit" class="btn btn-primary">
                    <?php echo $isEdit ? 'Salva modifiche' : 'Crea persona'; ?>
                </button>

                <a href="<?php echo h(app_url('modules/operators/index.php')); ?>" class="btn btn-ghost">
                    Torna alla lista
                </a>

                <?php if ($isEdit && can('operators.create')): ?>
                    <a href="<?php echo h(app_url('modules/operators/edit.php')); ?>" class="btn btn-secondary">
                        Nuova persona
                    </a>
                <?php endif; ?>

                <?php if ($canOpenUsers && $userAccount && !empty($userAccount['id'])): ?>
                    <a href="<?php echo h(app_url('modules/users/edit.php?id=' . (int)$userAccount['id'])); ?>" class="btn btn-secondary">
                        Apri Gestione Utente
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>