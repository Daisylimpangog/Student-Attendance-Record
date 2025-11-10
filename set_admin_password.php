<?php
// set_admin_password.php
// Reset the admin user's password to a fresh hash of 'Admin@123'
require_once __DIR__ . '/db.php';

try {
    $email = 'admin@example.com';
    $plain = 'Admin@123';
    $newHash = password_hash($plain, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE email = ?');
    $stmt->execute([$newHash, $email]);

    echo "Updated password hash for {$email}\n";

    // Verify
    $stmt = $pdo->prepare('SELECT password FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if (!$row) {
        echo "Could not fetch user after update.\n";
        exit(1);
    }
    $ok = password_verify($plain, $row['password']);
    echo "password_verify('Admin@123', new_hash) => " . ($ok ? "TRUE\n" : "FALSE\n");
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
