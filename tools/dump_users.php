<?php
require_once __DIR__ . '/../db.php';
try {
    $stmt = $pdo->query("SELECT id, email, name, role, password FROM users ORDER BY id ASC LIMIT 20");
    $rows = $stmt->fetchAll();
    if (!$rows) {
        echo "No users found.\n";
        exit(0);
    }
    foreach ($rows as $r) {
        echo sprintf("%d | %-30s | %-20s | %-10s | %s\n", $r['id'], $r['email'], $r['name'], $r['role'], $r['password']);
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
