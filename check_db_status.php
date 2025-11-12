<?php
require_once __DIR__ . '/db.php';

echo "<h2>Database Status Check</h2>";
echo "<pre>";

// Check if password_reset_tokens table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'password_reset_tokens'");
    $result = $stmt->fetch();
    
    if ($result) {
        echo "✓ Table 'password_reset_tokens' EXISTS\n\n";
        
        // Show table structure
        echo "Table Structure:\n";
        $stmt = $pdo->query("DESCRIBE password_reset_tokens");
        $columns = $stmt->fetchAll();
        foreach ($columns as $col) {
            echo "  - {$col['Field']} ({$col['Type']}) {$col['Null']} {$col['Key']}\n";
        }
    } else {
        echo "✗ Table 'password_reset_tokens' DOES NOT EXIST\n";
        echo "\nSolution: Run migrate_password_reset.php\n";
    }
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
}

echo "\n--- Database Connection Test ---\n";
try {
    $stmt = $pdo->query("SELECT 1");
    echo "✓ Database connection OK\n";
} catch (Exception $e) {
    echo "✗ Database connection FAILED: " . $e->getMessage() . "\n";
}

echo "\n--- Users Table Check ---\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "✓ Users table exists with " . $result['count'] . " users\n";
} catch (Exception $e) {
    echo "✗ Users table error: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
