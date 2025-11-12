<?php
/**
 * Quick Setup Status Page
 */
require_once __DIR__ . '/db.php';

// Check system status
$status = [
    'database_table' => false,
    'emails_dir' => false,
    'config_file' => false,
];

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'password_reset_tokens'");
    $status['database_table'] = $stmt->rowCount() > 0;
} catch (Exception $e) {}

$status['emails_dir'] = is_dir(__DIR__ . '/storage/emails') || @mkdir(__DIR__ . '/storage/emails', 0755, true);
$status['config_file'] = file_exists(__DIR__ . '/config.php');

?>
<?php include __DIR__ . '/partials/header.php'; ?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <!-- Hero Section -->
            <div class="card border-0 shadow-sm mb-4 bg-primary text-white">
                <div class="card-body text-center p-5">
                    <h1 class="mb-3">🎉 Password Reset Feature Ready!</h1>
                    <p class="lead mb-0">Your system is now set up for password recovery via email.</p>
                </div>
            </div>
            
            <!-- System Status -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-check2-circle me-2"></i>System Status</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <td><strong>Database Table</strong></td>
                                <td><?php echo $status['database_table'] ? '<span class="badge bg-success">✓ Ready</span>' : '<span class="badge bg-danger">✗ Missing</span>'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Storage Folder</strong></td>
                                <td><?php echo $status['emails_dir'] ? '<span class="badge bg-success">✓ Ready</span>' : '<span class="badge bg-danger">✗ Missing</span>'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Configuration</strong></td>
                                <td><?php echo $status['config_file'] ? '<span class="badge bg-success">✓ Ready</span>' : '<span class="badge bg-danger">✗ Missing</span>'; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-lightning-fill me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <a href="forgot_password.php" class="btn btn-primary w-100">
                                <i class="bi bi-key me-2"></i>Test Forgot Password
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="view_emails.php" class="btn btn-info w-100">
                                <i class="bi bi-envelope me-2"></i>View Emails
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="index.php" class="btn btn-secondary w-100">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login Page
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="forgot_password_status.php" class="btn btn-warning w-100">
                                <i class="bi bi-speedometer2 me-2"></i>Admin Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- How It Works -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-gear me-2"></i>How to Test It</h6>
                </div>
                <div class="card-body">
                    <ol>
                        <li>
                            <strong>Request Password Reset</strong><br>
                            <small class="text-muted">Go to <a href="forgot_password.php">forgot_password.php</a> and enter an email address</small>
                        </li>
                        <li>
                            <strong>View the Email</strong><br>
                            <small class="text-muted">Go to <a href="view_emails.php">view_emails.php</a> to see the password reset link</small>
                        </li>
                        <li>
                            <strong>Click the Link</strong><br>
                            <small class="text-muted">Copy and paste the reset link from the email viewer</small>
                        </li>
                        <li>
                            <strong>Reset Your Password</strong><br>
                            <small class="text-muted">Enter a new password (minimum 8 characters)</small>
                        </li>
                        <li>
                            <strong>Login</strong><br>
                            <small class="text-muted">Use your new password to sign in</small>
                        </li>
                    </ol>
                </div>
            </div>
            
            <!-- Email Mode -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-mail me-2"></i>Email Mode: <span class="badge bg-warning">Development</span></h6>
                </div>
                <div class="card-body">
                    <p><strong>Current Setting:</strong> Development Mode (emails saved to files)</p>
                    <p class="text-muted mb-3">This is normal for local development. In production, emails are sent via SMTP.</p>
                    
                    <h6>To Enable Real Email Sending:</h6>
                    <ul>
                        <li>
                            <strong>Option 1 (Easiest):</strong> 
                            <a href="EMAIL_CONFIGURATION.md">Configure Mailtrap</a> (testing service)
                        </li>
                        <li>
                            <strong>Option 2:</strong> 
                            <a href="EMAIL_CONFIGURATION.md">Configure Gmail SMTP</a>
                        </li>
                        <li>
                            <strong>Option 3:</strong> 
                            <a href="EMAIL_CONFIGURATION.md">Configure AWS SES or SendGrid</a> (production)
                        </li>
                    </ul>
                    <p class="mt-3 text-muted small">See EMAIL_CONFIGURATION.md for detailed setup instructions.</p>
                </div>
            </div>
            
            <!-- Features -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-star-fill me-2"></i>Features</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="d-flex">
                                <div class="me-3">✅</div>
                                <div>
                                    <strong>Secure Tokens</strong><br>
                                    <small class="text-muted">256-bit cryptographic tokens</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex">
                                <div class="me-3">✅</div>
                                <div>
                                    <strong>24-Hour Expiry</strong><br>
                                    <small class="text-muted">Links expire automatically</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex">
                                <div class="me-3">✅</div>
                                <div>
                                    <strong>One-Time Use</strong><br>
                                    <small class="text-muted">Tokens can't be reused</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex">
                                <div class="me-3">✅</div>
                                <div>
                                    <strong>Bcrypt Hashing</strong><br>
                                    <small class="text-muted">Passwords securely hashed</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex">
                                <div class="me-3">✅</div>
                                <div>
                                    <strong>Email Templates</strong><br>
                                    <small class="text-muted">Professional HTML emails</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex">
                                <div class="me-3">✅</div>
                                <div>
                                    <strong>Admin Monitoring</strong><br>
                                    <small class="text-muted">Track reset requests</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
