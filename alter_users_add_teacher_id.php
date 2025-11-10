<?php
// Migration: add teacher_id column to users table
require_once __DIR__ . '/db.php';
try {
    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'teacher_id'")->fetch();
    if ($col) {
        echo "teacher_id column already exists.\n";
        exit;
    }
    $pdo->exec("ALTER TABLE users ADD COLUMN teacher_id INT NULL DEFAULT NULL");
    echo "Added teacher_id column to users table.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

