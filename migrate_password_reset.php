<?php
/**
 * Migration script to add password_reset_tokens table
 * Run this once to update existing databases
 */

session_start();
require_once __DIR__ . '/db.php';

$errors = [];
$success = [];

try {
    // Check if table already exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'password_reset_tokens'");
    $table_exists = $stmt->rowCount() > 0;
    
    if (!$table_exists) {
        $sql = "
            CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `user_id` INT NOT NULL,
              `email` VARCHAR(255) NOT NULL,
              `token` VARCHAR(255) NOT NULL UNIQUE,
              `expires_at` DATETIME NOT NULL,
              `used` TINYINT(1) DEFAULT 0,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
              INDEX `token_idx` (`token`),
              INDEX `email_idx` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        $pdo->exec($sql);
        $success[] = 'password_reset_tokens table created successfully!';
    } else {
        $success[] = 'password_reset_tokens table already exists.';
    }
    
} catch (PDOException $e) {
    $errors[] = 'Database error: ' . $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Database Migration - Password Reset</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Database Migration - Password Reset Feature</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($errors): ?>
                            <div class="alert alert-danger">
                                <h6>Errors:</h6>
                                <ul>
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <h6>Success:</h6>
                                <ul>
                                    <?php foreach ($success as $msg): ?>
                                        <li><?php echo htmlspecialchars($msg); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <h6>Next Steps:</h6>
                        <ol>
                            <li>Update your <code>config.php</code> file with email settings (if not already done).</li>
                            <li>Test the forgot password feature by navigating to <a href="forgot_password.php">forgot_password.php</a>.</li>
                            <li>You can delete this file after verification.</li>
                        </ol>
                        
                        <div class="mt-4">
                            <a href="index.php" class="btn btn-primary">Back to Login</a>
                            <a href="forgot_password.php" class="btn btn-secondary">Test Forgot Password</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
