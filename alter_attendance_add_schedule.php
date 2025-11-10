<?php
// Migration: add schedule column to attendance table
require_once __DIR__ . '/db.php';
try {
    $col = $pdo->query("SHOW COLUMNS FROM attendance LIKE 'schedule'")->fetch();
    if ($col) {
        echo "schedule column already exists.\n";
        exit;
    }
    $pdo->exec("ALTER TABLE attendance ADD COLUMN schedule ENUM('Day','Night','Weekend') DEFAULT 'Day'");
    echo "Added schedule column to attendance table.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}