<?php
// modules/users/edit.php

require_once __DIR__ . '/../../core/helpers.php';

require_login();

$id = (int)get('id', 0);
$isEdit = $id > 0;

if ($isEdit) {
    require_permission('users.edit');
} else {
    require_permission('users.create');
}

$pageTitle    = $isEdit ? 'Gestione Utente' : 'Nuovo utente';
$pageSubtitle = 'Gestione accesso, ruolo, scope e permessi';
$activeModule = 'users';

$db = db_connect();
$errors = [];

/* =========================
   BLOCCO MANAGER SICUREZZA
   ========================= */
$targetExistingUser = null;

if ($isEdit) {
    $stmtGuard = $db->prepare("SELECT id, role, is_administrative FROM users WHERE id = ? LIMIT 1");
    if ($stmtGuard) {
        $stmtGuard->bind_param('i', $id);
        $stmtGuard->execute();
        $resGuard = $stmtGuard->get_result();
        $targetExistingUser = $resGuard ? $resGuard->fetch_assoc() : null;
        $stmtGuard->close();
    }

    if (
        function_exists('is_manager') &&
        is_manager() &&
        $targetExistingUser &&
        (
            (string)($targetExistingUser['role'] ?? '') === ROLE_MASTER ||
            !empty($targetExistingUser['is_administrative'])
        )
    ) {
        http_response_code(403);
        exit('Accesso negato: un manager non può modificare utenti Master o amministrativi.');
    }
}
/* ========================= */

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function users_safe_redirect(string $url): void
{
    if (!headers_sent()) {
        header('Location: ' . $url);
    }
    exit;
}

function users_checkbox(string $key): int
{
    return isset($_POST[$key]) ? 1 : 0;
}

/* =========================
   VALIDAZIONE MANAGER (POST)
   ========================= */
function users_validate_manager_limits(array $form, array &$errors): void
{
    if (function_exists('is_manager') && is_manager()) {

        if ($form['role'] === ROLE_MASTER) {
            $errors[] = 'Un manager non può assegnare il ruolo Master.';
        }

        if (!empty($form['is_administrative'])) {
            $errors[] = 'Un manager non può rendere un utente amministrativo.';
        }

        if ($form['scope'] === SCOPE_GLOBAL) {
            $errors[] = 'Un manager non può assegnare scope Global.';
        }
    }
}
/* ========================= */

function users_username_exists(mysqli $db, string $username, int $excludeId = 0): bool
{
    $username = trim($username);
    if ($username === '') return false;

    if ($excludeId > 0) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1");
        $stmt->bind_param('si', $username, $excludeId);
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $username);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->fetch_assoc();
    $stmt->close();

    return (bool)$exists;
}

function users_email_exists(mysqli $db, string $email, int $excludeId = 0): bool
{
    $email = trim($email);
    if ($email === '') return false;

    if ($excludeId > 0) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
        $stmt->bind_param('si', $email, $excludeId);
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->fetch_assoc();
    $stmt->close();

    return (bool)$exists;
}

/* =========================
   SALVATAGGIO (PATCH)
   ========================= */

if (is_post()) {

    $form['role'] = normalize_role((string)post('role', ROLE_USER));
    $form['scope'] = normalize_scope((string)post('scope', SCOPE_SELF));
    $form['is_administrative'] = users_checkbox('is_administrative');

    /* 🔥 BLOCCO MANAGER */
    users_validate_manager_limits($form, $errors);
}

/* =========================
   RESTO FILE NON TOCCATO
   ========================= */

require_once __DIR__ . '/../../templates/layout_top.php';
?>
