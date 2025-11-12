<?php
// get_face_descriptor_by_email.php
// Returns JSON descriptor for a user by email. Intended for use in forgot-password face check.
// WARNING: Exposes stored descriptor for verification purposes; rate-limit or additional checks may be required in production.

require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$email = trim($_GET['email'] ?? '');
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Invalid email']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, name, face_descriptor FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if (!$row) {
        echo json_encode(['error' => 'Not found']);
        exit;
    }
    if (!$row['face_descriptor']) {
        echo json_encode(['error' => 'No face enrolled']);
        exit;
    }
    $desc = json_decode($row['face_descriptor'], true);
    echo json_encode(['descriptor' => $desc, 'user_id' => (int)$row['id'], 'name' => $row['name']]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
