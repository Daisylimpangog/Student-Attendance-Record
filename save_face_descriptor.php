<?php
// save_face_descriptor.php
// Accepts POST: user_id, descriptor (JSON array). Requires admin or matching user.
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload || !isset($payload['user_id']) || !isset($payload['descriptor'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$userId = (int)$payload['user_id'];
$descriptor = $payload['descriptor'];

// only admin or same user may save
if ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_id'] != $userId) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE users SET face_descriptor = ? WHERE id = ?');
    $stmt->execute([json_encode($descriptor), $userId]);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
