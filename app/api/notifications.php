<?php
// app/api/notifications.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../core/app_notifications.php';

require_mobile_login();

header('Content-Type: application/json; charset=utf-8');

$dipendenteId = auth_dipendente_id();

if (!$dipendenteId) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Dipendente non associato all’utente.'
    ]);
    exit;
}

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'list'));

if ($action === 'mark_all_read') {
    $ok = app_notification_mark_all_as_read($dipendenteId);

    echo json_encode([
        'success' => $ok ? true : false,
        'message' => $ok ? 'Notifiche segnate come lette.' : 'Operazione non riuscita.',
    ]);
    exit;
}

if ($action === 'mark_read') {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    $ok = app_notification_mark_as_read($id, $dipendenteId);

    echo json_encode([
        'success' => $ok ? true : false,
        'message' => $ok ? 'Notifica letta.' : 'Operazione non riuscita.',
    ]);
    exit;
}

$items = app_notification_list_for_dipendente($dipendenteId, 50);
$unread = app_notification_unread_count($dipendenteId);

echo json_encode([
    'success' => true,
    'unread_count' => $unread,
    'items' => $items,
], JSON_UNESCAPED_UNICODE);
exit;