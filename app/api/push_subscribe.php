<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../core/push.php';

require_mobile_login();

header('Content-Type: application/json; charset=utf-8');

$dipendenteId = auth_dipendente_id();
if (!$dipendenteId) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Dipendente non associato.'
    ]);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Payload non valido.'
    ]);
    exit;
}

$action = trim((string)($data['action'] ?? 'subscribe'));

if ($action === 'unsubscribe') {
    $endpoint = trim((string)($data['endpoint'] ?? ''));
    $ok = disable_push_subscription_by_endpoint($endpoint);

    echo json_encode([
        'success' => $ok ? true : false,
        'message' => $ok ? 'Push disattivate.' : 'Disattivazione non riuscita.'
    ]);
    exit;
}

$subscription = $data['subscription'] ?? [];
if (!is_array($subscription)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Subscription mancante.'
    ]);
    exit;
}

$endpoint = trim((string)($subscription['endpoint'] ?? ''));
$keys = $subscription['keys'] ?? [];
$publicKey = is_array($keys) ? trim((string)($keys['p256dh'] ?? '')) : '';
$authToken = is_array($keys) ? trim((string)($keys['auth'] ?? '')) : '';
$contentEncoding = trim((string)($subscription['contentEncoding'] ?? ''));
$userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

$ok = save_push_subscription(
    $dipendenteId,
    $endpoint,
    $publicKey,
    $authToken,
    $contentEncoding,
    $userAgent
);

echo json_encode([
    'success' => $ok ? true : false,
    'message' => $ok ? 'Push attivate.' : 'Salvataggio subscription non riuscito.'
]);
exit;