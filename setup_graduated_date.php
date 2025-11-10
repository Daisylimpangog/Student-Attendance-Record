<?php
// Database migration to add graduated_date column
require_once __DIR__ . '/db.php';

$message = '';
try {
    // First check if column exists to avoid errors
    $check = $pdo->query("SHOW COLUMNS FROM users LIKE 'graduated_date'");
    if ($check->rowCount() === 0) {
        // Add graduated_date column (nullable, will be set when status changes to Graduated)
        $pdo->exec("ALTER TABLE users ADD COLUMN graduated_date DATE NULL DEFAULT NULL");
        
        // Set current date for any existing Graduated users that don't have a date
        $pdo->exec("UPDATE users SET graduated_date = CURDATE() WHERE status = 'Graduated' AND graduated_date IS NULL");
        
        $message = "Success: Added graduated_date column and set default dates for existing graduated users.";
    } else {
        $message = "Column graduated_date already exists.";
    }
} catch (PDOException $e) {
    $message = "Error: " . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Graduated Date - Database Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Database Migration Status</h4>
                <div class="alert <?php echo strpos($message, 'Error') === 0 ? 'alert-danger' : 'alert-success'; ?>">
                    <?php echo $message; ?>
                </div>
                <a href="graduated_students.php" class="btn btn-primary">Go to Graduated Students</a>
            </div>
        </div>
    </div>
</body>
</html>