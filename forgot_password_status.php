<?php
/**
 * Test/Demo script for forgot password feature
 * This page shows the status of the password reset system
 */

session_start();
require_once __DIR__ . '/db.php';

// Check if user is admin
$is_admin = ($_SESSION['user_role'] ?? '') === 'admin';

$system_status = [];
$issues = [];

// Check if password_reset_tokens table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'password_reset_tokens'");
    $table_exists = $stmt->rowCount() > 0;
    $system_status['Database table'] = $table_exists ? '✓ Created' : '✗ Missing';
    if (!$table_exists) {
        $issues[] = 'Password reset tokens table not found. Run migration.';
    }
} catch (Exception $e) {
    $system_status['Database table'] = '✗ Error checking';
    $issues[] = 'Error checking database: ' . $e->getMessage();
}

// Check if forgot_password.php exists
$system_status['Forgot Password Page'] = file_exists(__DIR__ . '/forgot_password.php') ? '✓ Present' : '✗ Missing';

// Check if reset_password.php exists
$system_status['Reset Password Page'] = file_exists(__DIR__ . '/reset_password.php') ? '✓ Present' : '✗ Missing';

// Check if mail_helper.php exists
$system_status['Mail Helper'] = file_exists(__DIR__ . '/includes/mail_helper.php') ? '✓ Present' : '✗ Missing';

// Check config.php for email settings
$config = require __DIR__ . '/config.php';
$system_status['Email From'] = isset($config['email_from']) ? '✓ Configured: ' . $config['email_from'] : '✗ Not configured';

// Check active tokens in database
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(used=0) as active FROM password_reset_tokens");
    $result = $stmt->fetch();
    if ($result) {
        $system_status['Active Tokens'] = ($result['active'] ?? 0) . ' (Total: ' . ($result['total'] ?? 0) . ')';
    }
} catch (Exception $e) {
    // Table may not exist yet
}

// Get recent password resets (admin only)
$recent_resets = [];
if ($is_admin) {
    try {
        $stmt = $pdo->query(
            "SELECT pr.id, pr.email, u.name, pr.created_at, pr.expires_at, pr.used
             FROM password_reset_tokens pr
             LEFT JOIN users u ON pr.user_id = u.id
             ORDER BY pr.created_at DESC
             LIMIT 10"
        );
        $recent_resets = $stmt->fetchAll();
    } catch (Exception $e) {
        // Table may not exist
    }
}
?>
<?php include __DIR__ . '/partials/header.php'; ?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <!-- System Status Card -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-tools me-2"></i>Forgot Password System Status
                    </h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <?php foreach ($system_status as $component => $status): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($component); ?></strong></td>
                                    <td><?php echo htmlspecialchars($status); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Issues Alert -->
            <?php if ($issues): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <h6 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i>Issues Found</h6>
                    <ul class="mb-0">
                        <?php foreach ($issues as $issue): ?>
                            <li><?php echo htmlspecialchars($issue); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                
                <div class="alert alert-info">
                    <strong>Solution:</strong> Run the <a href="migrate_password_reset.php" class="alert-link">migration script</a> to create missing database tables.
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i><strong>All Systems Green!</strong> The forgot password feature is ready to use.
                </div>
            <?php endif; ?>
            
            <!-- Quick Links -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-link-45deg me-2"></i>Quick Links
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <a href="forgot_password.php" class="btn btn-outline-primary w-100">
                                <i class="bi bi-key me-2"></i>Test Forgot Password
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="index.php" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Back to Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Features Overview -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-info-circle me-2"></i>Features
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-3">
                            <strong><i class="bi bi-check-circle text-success me-2"></i>Email Verification</strong>
                            <p class="text-muted mb-0">Users receive password reset links via email</p>
                        </li>
                        <li class="mb-3">
                            <strong><i class="bi bi-check-circle text-success me-2"></i>Secure Tokens</strong>
                            <p class="text-muted mb-0">256-bit random tokens with 24-hour expiry</p>
                        </li>
                        <li class="mb-3">
                            <strong><i class="bi bi-check-circle text-success me-2"></i>One-Time Use</strong>
                            <p class="text-muted mb-0">Tokens can only be used once to prevent abuse</p>
                        </li>
                        <li class="mb-3">
                            <strong><i class="bi bi-check-circle text-success me-2"></i>Secure Passwords</strong>
                            <p class="text-muted mb-0">New passwords are hashed with bcrypt</p>
                        </li>
                        <li>
                            <strong><i class="bi bi-check-circle text-success me-2"></i>Admin Logging</strong>
                            <p class="text-muted mb-0">Track password reset requests (admin view below)</p>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Admin: Recent Resets -->
            <?php if ($is_admin && $recent_resets): ?>
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-shield-exclamation me-2"></i>Recent Password Reset Requests (Admin Only)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Email</th>
                                        <th>Name</th>
                                        <th>Requested</th>
                                        <th>Expires</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_resets as $reset): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($reset['id']); ?></td>
                                            <td><?php echo htmlspecialchars($reset['email']); ?></td>
                                            <td><?php echo htmlspecialchars($reset['name'] ?? 'N/A'); ?></td>
                                            <td>
                                                <small><?php echo date('M d, Y H:i', strtotime($reset['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <small><?php echo date('M d, Y H:i', strtotime($reset['expires_at'])); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($reset['used']): ?>
                                                    <span class="badge bg-success">Used</span>
                                                <?php elseif (strtotime($reset['expires_at']) < time()): ?>
                                                    <span class="badge bg-secondary">Expired</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Active</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Documentation -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-file-text me-2"></i>Documentation
                    </h5>
                </div>
                <div class="card-body">
                    <p>For detailed documentation and troubleshooting, see <code>FORGOT_PASSWORD_README.md</code></p>
                    <h6 class="mt-3">User Flow:</h6>
                    <ol>
                        <li>User clicks "Forgot password?" on login page</li>
                        <li>User enters email address on forgot_password.php</li>
                        <li>Email sent with secure reset link</li>
                        <li>User clicks link and enters new password on reset_password.php</li>
                        <li>Password updated and user can login with new credentials</li>
                    </ol>
                </div>
            </div>
            
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
