<?php
// One-time setup script to add graduated_date column
require_once __DIR__ . '/db.php';

try {
    // First check if column exists to avoid errors
    $check = $pdo->query("SHOW COLUMNS FROM users LIKE 'graduated_date'");
    if ($check->rowCount() === 0) {
        // Add graduated_date column (nullable, will be set when status changes to Graduated)
        $pdo->exec("ALTER TABLE users ADD COLUMN graduated_date DATE NULL DEFAULT NULL");
        
        // Set current date for any existing Graduated users that don't have a date
        $pdo->exec("UPDATE users SET graduated_date = CURDATE() WHERE status = 'Graduated' AND graduated_date IS NULL");
        
        echo "Success: Added graduated_date column and set default dates for existing graduated users.";
    } else {
        echo "Column graduated_date already exists.";
    }
} catch (PDOException $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
}