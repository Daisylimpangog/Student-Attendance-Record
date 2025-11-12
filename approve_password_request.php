<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$id) {
    http_response_code(400);
    echo 'Invalid request id';
    exit;
}

try {
    // Fetch request
    $stmt = $pdo->prepare('SELECT * FROM password_reset_requests WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $req = $stmt->fetch();
    if (!$req) {
        $_SESSION['flash_error'] = 'Request not found';
        header('Location: admin_password_requests.php');
        exit;
    }
    if ($req['status'] !== 'pending') {
        $_SESSION['flash_error'] = 'Request is not pending';
        header('Location: admin_password_requests.php');
        exit;
    }

    // Update user's password
    $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
    $stmt->execute([$req['new_password_hash'], $req['user_id']]);

    // Mark request approved
    $stmt = $pdo->prepare('UPDATE password_reset_requests SET status = ?, face_verified = 1 WHERE id = ?');
    $stmt->execute(['approved', $id]);

    $_SESSION['flash_success'] = 'Password reset request approved and password updated.';
    header('Location: admin_password_requests.php');
    exit;
} catch (Exception $e) {
    $_SESSION['flash_error'] = 'Error: ' . $e->getMessage();
    header('Location: admin_password_requests.php');
    exit;
}
?>