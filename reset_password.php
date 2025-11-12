<?php
session_start();
require_once __DIR__ . '/db.php';

$token = $_GET['token'] ?? '';
$errors = [];
$success = '';
$token_valid = false;
$user_id = null;
$user_email = null;

// Validate token
if ($token) {
    $stmt = $pdo->prepare(
        'SELECT id, user_id, email, expires_at, used FROM password_reset_tokens 
         WHERE token = ? LIMIT 1'
    );
    $stmt->execute([$token]);
    $reset_record = $stmt->fetch();
    
    if (!$reset_record) {
        $errors[] = 'Invalid or expired reset link.';
    } elseif ($reset_record['used']) {
        $errors[] = 'This reset link has already been used.';
    } elseif (strtotime($reset_record['expires_at']) < time()) {
        $errors[] = 'This reset link has expired. Please request a new one.';
    } else {
        $token_valid = true;
        $user_id = $reset_record['user_id'];
        $user_email = $reset_record['email'];
    }
} else {
    $errors[] = 'No reset token provided.';
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Validate password
    if (!$password) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    } elseif ($password !== $password_confirm) {
        $errors[] = 'Passwords do not match.';
    } else {
        try {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Update user password
            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([$hashed_password, $user_id]);
            
            // Mark token as used
            $stmt = $pdo->prepare('UPDATE password_reset_tokens SET used = 1 WHERE token = ?');
            $stmt->execute([$token]);
            
            $success = 'Password reset successfully! You can now sign in with your new password.';
            $token_valid = false; // Hide the form after success
            
        } catch (PDOException $e) {
            error_log("Error resetting password: " . $e->getMessage());
            $errors[] = 'An error occurred while resetting your password. Please try again.';
        }
    }
}
?>
<?php include __DIR__ . '/partials/header.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <h2 class="mb-2">Reset Your Password</h2>
                        <p class="text-muted">Enter your new password below.</p>
                    </div>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="index.php" class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Go to Sign In
                            </a>
                        </div>
                    <?php elseif (!$token_valid): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php foreach ($errors as $error): ?>
                                <div><i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></div>
                            <?php endforeach; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        
                        <div class="text-center mt-4">
                            <p class="mb-2">Request a new reset link:</p>
                            <a href="forgot_password.php" class="btn btn-primary">
                                <i class="bi bi-arrow-counterclockwise me-2"></i>Request Password Reset
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Reset Form -->
                        <?php if ($errors): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php foreach ($errors as $error): ?>
                                    <div><i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></div>
                                <?php endforeach; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" class="reset-form">
                            <div class="mb-3">
                                <label class="form-label" for="newPassword">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input 
                                        id="newPassword" 
                                        class="form-control" 
                                        type="password" 
                                        name="password" 
                                        required
                                        placeholder="At least 8 characters"
                                        autocomplete="new-password"
                                    >
                                </div>
                                <small class="text-muted d-block mt-2">Minimum 8 characters. Use a mix of letters, numbers, and symbols for better security.</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label" for="confirmPassword">Confirm Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input 
                                        id="confirmPassword" 
                                        class="form-control" 
                                        type="password" 
                                        name="password_confirm" 
                                        required
                                        placeholder="Re-enter your password"
                                        autocomplete="new-password"
                                    >
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-2"></i>Reset Password
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                    
                    <div class="text-center mt-4 pt-3 border-top">
                        <p class="text-muted small">
                            <a href="index.php" class="btn btn-link btn-sm">Back to Sign In</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .reset-form {
        margin-top: 20px;
    }
    
    .card {
        border-radius: 10px;
        margin-top: 30px;
    }
</style>

<?php include __DIR__ . '/partials/footer.php'; ?>
