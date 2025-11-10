<?php
// ensure_seed_users.php
// Check that admin, teacher1 and student1 exist; insert them if missing using secure hashes.
require_once __DIR__ . '/db.php';

$seeds = [
    ['email' => 'admin@example.com', 'password' => 'Admin@123', 'name' => 'Administrator', 'role' => 'admin', 'kind' => 'teacher'],
    ['email' => 'teacher1@example.com', 'password' => 'Teacher@123', 'name' => 'Teacher One', 'role' => 'user', 'kind' => 'teacher'],
    ['email' => 'student1@example.com', 'password' => 'Student@123', 'name' => 'Student One', 'role' => 'user', 'kind' => 'student'],
];

foreach ($seeds as $s) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$s['email']]);
    $exists = $stmt->fetch();
    if ($exists) {
        echo "OK: {$s['email']} already exists (id={$exists['id']}).\n";
        continue;
    }

    // Ensure kind column exists
    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'kind'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE users ADD COLUMN kind ENUM('student','teacher') DEFAULT 'student'");
        echo "Info: added 'kind' column to users table.\n";
    }

    $hash = password_hash($s['password'], PASSWORD_DEFAULT);
    $ins = $pdo->prepare('INSERT INTO users (email, password, name, role, kind) VALUES (?, ?, ?, ?, ?)');
    $ins->execute([$s['email'], $hash, $s['name'], $s['role'], $s['kind']]);
    echo "Inserted: {$s['email']} with role={$s['role']} kind={$s['kind']}\n";
}

echo "Done.\n";
