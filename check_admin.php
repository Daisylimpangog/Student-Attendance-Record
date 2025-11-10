<?php
// Diagnostic script: show admin user row and verify password hash
require_once __DIR__ . '/db.php';

try {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute(['admin@example.com']);
    $user = $stmt->fetch();
    if (!$user) {
        echo "User admin@example.com not found in users table.\n";
        exit(1);
    }

    echo "Found user:\n";
    print_r($user);

    $hash = $user['password'];
    echo "\nStored password hash: $hash\n";

    $plain = 'Admin@123';
    $ok = password_verify($plain, $hash);
    echo "password_verify('Admin@123', hash) => ";
    echo $ok ? "TRUE\n" : "FALSE\n";

    // Also show whether rehash is suggested (in case default algorithm changed)
    if ($ok) {
        echo "password_needs_rehash => " . (password_needs_rehash($hash, PASSWORD_DEFAULT) ? "YES\n" : "NO\n");
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
