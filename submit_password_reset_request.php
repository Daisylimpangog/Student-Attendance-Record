<?php
// submit_password_reset_request.php
// Accepts POST JSON: email, new_password_hash (or new_password plain) and face_verified flag. Creates a pending request for admin approval.

require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload || !isset($payload['email']) || !isset($payload['new_password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$email = trim($payload['email']);
$new_password = $payload['new_password'];
$face_verified = isset($payload['face_verified']) ? (int)$payload['face_verified'] : 0;

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email']);
    exit;
}

if (strlen($new_password) < 8) {
    http_response_code(400);
    echo json_encode(['error' => 'Password must be at least 8 characters']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    $userId = (int)$user['id'];
    // Require face verification flag (defense-in-depth: server expects face_verified == 1 for this flow)
    if ($face_verified !== 1) {
        http_response_code(400);
        echo json_encode(['error' => 'Face verification required']);
        exit;
    }

    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare('INSERT INTO password_reset_requests (user_id, email, new_password_hash, token, face_verified, status) VALUES (?, ?, ?, ?, ?, ? )');
    $stmt->execute([$userId, $email, $new_hash, $token, $face_verified, 'pending']);

    echo json_encode(['ok' => true, 'message' => 'Request submitted', 'token' => $token]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>