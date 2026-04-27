<?php
require_once __DIR__ . '/../../config/config.php';
require_login();

$data = json_decode(file_get_contents('php://input'), true);

$id = (int)$data['id'];
$newDate = $data['new_date'];

$repo = new TurniRepository(db_connect());

$conflict = $repo->checkConflictForMove($id, $newDate);

echo json_encode([
    'conflict' => $conflict ? true : false
]);