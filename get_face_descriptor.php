<?php
// get_face_descriptor.php
// Returns JSON descriptor for the specified user (admin may request any user; regular users only their own)
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (int)$_SESSION['user_id'];
if ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_id'] != $userId) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT face_descriptor FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row || !$row['face_descriptor']) {
        echo json_encode(['descriptor' => null]);
        exit;
    }
    $desc = json_decode($row['face_descriptor'], true);
    echo json_encode(['descriptor' => $desc]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
