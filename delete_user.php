<?php
session_start();
require_once __DIR__ . '/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php');
    exit;
}

$userId = (int)($_POST['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: admin.php');
    exit;
}

// prevent deleting yourself
if ($userId === (int)$_SESSION['user_id']) {
    $_SESSION['flash'] = 'You cannot delete your own account.';
    header('Location: admin.php');
    exit;
}

// prevent deleting last admin
$stmt = $pdo->prepare('SELECT COUNT(*) as c FROM users WHERE role = "admin"');
$stmt->execute();
$cnt = (int)$stmt->fetchColumn();
$check = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
$check->execute([$userId]);
$r = $check->fetch();
if ($r && $r['role'] === 'admin' && $cnt <= 1) {
    $_SESSION['flash'] = 'Cannot delete the last admin account.';
    header('Location: admin.php');
    exit;
}

$del = $pdo->prepare('DELETE FROM users WHERE id = ?');
$del->execute([$userId]);
$_SESSION['flash'] = 'User deleted.';
header('Location: admin.php');
exit;

