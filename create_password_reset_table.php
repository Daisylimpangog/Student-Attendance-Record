<?php
/**
 * Create password_reset_tokens table
 * Run this once to set up the table
 */

require_once __DIR__ . '/db.php';

$errors = [];
$success = [];

try {
    // Check if table already exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'password_reset_tokens'");
    $table_exists = $stmt->rowCount() > 0;
    
    if ($table_exists) {
        $success[] = '✓ password_reset_tokens table already exists!';
    } else {
        // Create the password_reset_tokens table
        $sql = "
            CREATE TABLE `password_reset_tokens` (
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
        $success[] = '✓ password_reset_tokens table created successfully!';
    }

    // Create password_reset_requests table (for admin approval workflow)
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'password_reset_requests'");
        if ($stmt->rowCount() === 0) {
            $sql2 = "
                CREATE TABLE `password_reset_requests` (
                  `id` INT AUTO_INCREMENT PRIMARY KEY,
                  `user_id` INT NOT NULL,
                  `email` VARCHAR(255) NOT NULL,
                  `new_password_hash` VARCHAR(255) NOT NULL,
                  `token` VARCHAR(255) NOT NULL UNIQUE,
                  `face_verified` TINYINT(1) DEFAULT 0,
                  `status` ENUM('pending','approved','rejected') DEFAULT 'pending',
                  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                  INDEX `email_idx` (`email`),
                  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";
            $pdo->exec($sql2);
            $success[] = '✓ password_reset_requests table created successfully!';
        } else {
            $success[] = '✓ password_reset_requests table already exists!';
        }
    } catch (PDOException $e) {
        $errors[] = '✗ Error creating password_reset_requests: ' . $e->getMessage();
    }
    
    // Verify table exists now
    $stmt = $pdo->query("SHOW TABLES LIKE 'password_reset_tokens'");
    if ($stmt->rowCount() > 0) {
        $success[] = '✓ Verification: Table is ready to use!';
        
        // Show table info
        $stmt = $pdo->query("DESCRIBE password_reset_tokens");
        $columns = $stmt->fetchAll();
        $success[] = "\n✓ Table has " . count($columns) . " columns";
    } else {
        $errors[] = '✗ Table verification failed';
    }
    
} catch (PDOException $e) {
    $errors[] = '✗ Error: ' . $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Database Table</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background: #f8f9fa; }
        .container { max-width: 600px; margin-top: 40px; }
        .card { border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-database-check"></i> Setup Database Table</h5>
            </div>
            <div class="card-body">
                
                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <h6 class="mb-2">❌ Errors:</h6>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <h6 class="mb-2">✅ Success:</h6>
                        <ul class="mb-0">
                            <?php foreach ($success as $msg): ?>
                                <li><?php echo nl2br(htmlspecialchars($msg)); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (!$errors && $success): ?>
                    <div class="mt-4">
                        <h6>🎉 Table is ready! You can now:</h6>
                        <ol>
                            <li><a href="forgot_password.php">Test Forgot Password</a></li>
                            <li><a href="index.php">Back to Login</a></li>
                            <li><a href="forgot_password_status.php">View Admin Dashboard</a></li>
                        </ol>
                    </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
</body>
</html>
