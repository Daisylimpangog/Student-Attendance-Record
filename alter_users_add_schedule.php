<?php
// Migration: add schedule column to users table
require_once __DIR__ . '/db.php';
try {
    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'schedule'")->fetch();
    if ($col) {
        echo "schedule column already exists.\n";
        exit;
    }
    $pdo->exec("ALTER TABLE users ADD COLUMN schedule ENUM('Day','Night','Weekend') DEFAULT 'Day'");
    echo "Added schedule column to users table.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

