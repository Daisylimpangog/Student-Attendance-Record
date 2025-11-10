<?php
// setup_announcements.php - Adds announcement functionality to the system
session_start();
$message = '';
$error = '';
$success = false;

try {
    require_once __DIR__ . '/db.php';

    // Verify database connection
    try {
        $pdo->query("SELECT 1");
    } catch (PDOException $e) {
        throw new Exception("Database connection failed. Please ensure database is properly configured in config.php");
    }

    // Create announcements table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `announcements` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `content` TEXT NOT NULL,
        `created_by` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `target_role` ENUM('student','teacher','all') NOT NULL DEFAULT 'all',
        `is_active` BOOLEAN DEFAULT TRUE,
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Create announcements_read table to track which users have read which announcements
    $pdo->exec("CREATE TABLE IF NOT EXISTS `announcements_read` (
        `announcement_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `read_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`announcement_id`, `user_id`),
        FOREIGN KEY (`announcement_id`) REFERENCES `announcements`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Verify tables were created
    $tables = $pdo->query("SHOW TABLES LIKE 'announcement%'")->fetchAll();
    if (count($tables) === 2) {
        $message = "Success: Created announcements and announcements_read tables.";
        $success = true;
    } else {
        throw new Exception("Tables were not created properly.");
    }
} catch (Exception $e) {
    $error = "Error: " . htmlspecialchars($e->getMessage());
}

// In the meantime, let's modify header.php to avoid errors if tables don't exist
$headerContent = file_get_contents(__DIR__ . '/partials/header.php');
if ($headerContent !== false) {
    $safeHeader = preg_replace(
        '/(\$stmt = \$pdo->prepare\(".*?FROM announcements.*?"\);.*?\$stmt->execute\(\[.*?\]\);)/s',
        'if($pdo->query("SHOW TABLES LIKE \'announcements\'")->rowCount() > 0) { $1 }',
        $headerContent
    );
    if ($safeHeader !== $headerContent) {
        file_put_contents(__DIR__ . '/partials/header.php', $safeHeader);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Announcements - Database Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .setup-instructions {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
        }
    </style>
</head>
<body class="container mt-5">
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Announcements Setup</h5>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $success ? 'success' : 'info'; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <div class="setup-instructions">
                <h6>Next Steps:</h6>
                <ol>
                    <li>Return to the admin panel</li>
                    <li>You should now see a "Manage Announcements" link in the navigation</li>
                    <li>Create your first announcement to test the system</li>
                </ol>
            </div>
            
            <div class="mt-3">
                <a href="admin.php" class="btn btn-primary">Return to Admin Panel</a>
                <?php if (!$success): ?>
                    <button onclick="location.reload()" class="btn btn-outline-primary">Try Again</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>