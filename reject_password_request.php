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
    $_SESSION['flash_error'] = 'Invalid request id';
    header('Location: admin_password_requests.php');
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE password_reset_requests SET status = ? WHERE id = ?');
    $stmt->execute(['rejected', $id]);
    $_SESSION['flash_success'] = 'Password reset request rejected.';
    header('Location: admin_password_requests.php');
    exit;
} catch (Exception $e) {
    $_SESSION['flash_error'] = 'Error: ' . $e->getMessage();
    header('Location: admin_password_requests.php');
    exit;
}
?>