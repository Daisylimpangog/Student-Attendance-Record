<?php
// Migration: add face_descriptor column if missing
require_once __DIR__ . '/db.php';
try {
    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'face_descriptor'")->fetch();
    if ($col) {
        echo "face_descriptor column already exists.\n";
        exit;
    }
    $pdo->exec("ALTER TABLE users ADD COLUMN face_descriptor TEXT DEFAULT NULL");
    echo "Added face_descriptor column to users table.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
