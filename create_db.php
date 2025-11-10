<?php
// create_db.php
// Creates the configured database (if missing) and imports init.sql.
// Usage (browser): http://localhost/CHPCEBU-Attendance/create_db.php
// Or run from project root in CMD/PowerShell: php create_db.php

header('Content-Type: text/plain; charset=utf-8');

try {
    $config = require __DIR__ . '/config.php';

    // Connect to MySQL server without specifying a database
    $dsn = "mysql:host={$config['db_host']};charset={$config['db_charset']}";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $dbName = $config['db_name'];
    // Create database if not exists
    $createSql = "CREATE DATABASE IF NOT EXISTS `" . str_replace('`','``',$dbName) . "` CHARACTER SET {$config['db_charset']} COLLATE utf8mb4_unicode_ci";
    $pdo->exec($createSql);

    // Use the database
    $pdo->exec("USE `" . str_replace('`','``',$dbName) . "`");

    $initFile = __DIR__ . '/init.sql';
    if (!file_exists($initFile)) {
        throw new RuntimeException('init.sql not found in project root.');
    }

    $sql = file_get_contents($initFile);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('init.sql is empty or unreadable.');
    }

    // Execute entire SQL file. If your SQL file is large or contains complex DELIMITER blocks,
    // consider importing via phpMyAdmin or the mysql CLI instead.
    $pdo->exec($sql);

    echo "OK: Database '{$dbName}' created (if it did not exist) and init.sql imported.\n";
    echo "You can now open http://localhost/CHPCEBU-Attendance/ and login with seeded admin (admin@example.com / Admin@123)\n";
} catch (PDOException $e) {
    echo "PDO Error: " . $e->getMessage() . "\n";
    echo "Check that MySQL is running and the credentials in config.php are correct.\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
