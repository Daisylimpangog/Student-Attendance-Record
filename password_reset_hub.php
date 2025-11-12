<?php
/**
 * Password Reset Tools Hub
 */
session_start();
require_once __DIR__ . '/db.php';

$is_admin = ($_SESSION['user_role'] ?? '') === 'admin';
$is_logged_in = isset($_SESSION['user_id']);
?>
<?php include __DIR__ . '/partials/header.php'; ?>

<div class="container mt-5 mb-5">
    <div class="row">
        <div class="col-lg-12">
            
            <!-- Hero Section -->
            <div class="card border-0 shadow-sm mb-4 bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body text-white p-5">
                    <h1 class="mb-2">🔐 Password Recovery System</h1>
                    <p class="lead mb-0">Secure, easy-to-use password reset feature for your attendance system</p>
                </div>
            </div>
            
            <!-- Quick Links Section -->
            <div class="row g-4 mb-5">
                
                <!-- For All Users -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="bi bi-people-fill me-2"></i>For Everyone</h6>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <a href="forgot_password.php" class="list-group-item list-group-item-action px-0 border-0 py-2">
                                    <i class="bi bi-key me-2"></i>Request Password Reset
                                    <small class="d-block text-muted">Recover your account via email</small>
                                </a>
                                <a href="view_emails.php" class="list-group-item list-group-item-action px-0 border-0 py-2">
                                    <i class="bi bi-envelope me-2"></i>View Development Emails
                                    <small class="d-block text-muted">See password reset links (local testing)</small>
                                </a>
                                <a href="password_reset_setup.php" class="list-group-item list-group-item-action px-0 border-0 py-2">
                                    <i class="bi bi-speedometer2 me-2"></i>Setup Status
                                    <small class="d-block text-muted">Check system configuration</small>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- For Admins -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-danger text-white">
                            <h6 class="mb-0"><i class="bi bi-shield-exclamation me-2"></i>Admin Tools</h6>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <a href="forgot_password_status.php" class="list-group-item list-group-item-action px-0 border-0 py-2">
                                    <i class="bi bi-graph-up me-2"></i>Monitor Reset Requests
                                    <small class="d-block text-muted">View all password reset attempts</small>
                                </a>
                                <a href="system_check.php" class="list-group-item list-group-item-action px-0 border-0 py-2">
                                    <i class="bi bi-clipboard-check me-2"></i>System Check
                                    <small class="d-block text-muted">Verify all components are working</small>
                                </a>
                                <a href="index.php" class="list-group-item list-group-item-action px-0 border-0 py-2">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>Admin Dashboard
                                    <small class="d-block text-muted">Back to main interface</small>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Documentation Section -->
            <div class="row g-4 mb-5">
                <div class="col-lg-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bi bi-book me-2"></i>Documentation</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="d-flex align-items-start">
                                        <div class="me-3">📄</div>
                                        <div>
                                            <strong>Quick Start</strong><br>
                                            <small class="text-muted">PASSWORD_RESET_QUICK_START.md</small><br>
                                            <small>5-minute setup guide</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex align-items-start">
                                        <div class="me-3">📖</div>
                                        <div>
                                            <strong>Setup Guide</strong><br>
                                            <small class="text-muted">SETUP_GUIDE.md</small><br>
                                            <small>Configuration & customization</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex align-items-start">
                                        <div class="me-3">⚙️</div>
                                        <div>
                                            <strong>Email Config</strong><br>
                                            <small class="text-muted">EMAIL_CONFIGURATION.md</small><br>
                                            <small>SMTP setup for production</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex align-items-start">
                                        <div class="me-3">📚</div>
                                        <div>
                                            <strong>Technical Docs</strong><br>
                                            <small class="text-muted">FORGOT_PASSWORD_README.md</small><br>
                                            <small>Full technical documentation</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex align-items-start">
                                        <div class="me-3">📋</div>
                                        <div>
                                            <strong>Implementation</strong><br>
                                            <small class="text-muted">IMPLEMENTATION_SUMMARY.md</small><br>
                                            <small>Complete overview & reference</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Features Section -->
            <div class="row g-4 mb-5">
                <div class="col-lg-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bi bi-star-fill me-2"></i>Key Features</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <h6 class="mb-3">🔒 Security</h6>
                                    <ul class="list-unstyled small">
                                        <li>✓ 256-bit random tokens</li>
                                        <li>✓ 24-hour expiry</li>
                                        <li>✓ One-time use</li>
                                        <li>✓ Bcrypt hashing</li>
                                    </ul>
                                </div>
                                <div class="col-md-4">
                                    <h6 class="mb-3">📧 Email</h6>
                                    <ul class="list-unstyled small">
                                        <li>✓ Development mode (local)</li>
                                        <li>✓ Production ready</li>
                                        <li>✓ HTML templates</li>
                                        <li>✓ Mailtrap support</li>
                                    </ul>
                                </div>
                                <div class="col-md-4">
                                    <h6 class="mb-3">👥 Admin</h6>
                                    <ul class="list-unstyled small">
                                        <li>✓ Monitor requests</li>
                                        <li>✓ View token status</li>
                                        <li>✓ Audit logs</li>
                                        <li>✓ System check</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- How to Test -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bi bi-play-circle me-2"></i>How to Test (3 Steps)</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <div class="card border-primary border-2">
                                        <div class="card-body text-center">
                                            <div class="display-5 mb-3">1️⃣</div>
                                            <h6>Request Reset</h6>
                                            <p class="small text-muted mb-3">Go to <strong>forgot_password.php</strong> and enter an email</p>
                                            <a href="forgot_password.php" class="btn btn-sm btn-primary">Try It</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card border-info border-2">
                                        <div class="card-body text-center">
                                            <div class="display-5 mb-3">2️⃣</div>
                                            <h6>View Email</h6>
                                            <p class="small text-muted mb-3">Check <strong>view_emails.php</strong> for the reset link</p>
                                            <a href="view_emails.php" class="btn btn-sm btn-info">View</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card border-success border-2">
                                        <div class="card-body text-center">
                                            <div class="display-5 mb-3">3️⃣</div>
                                            <h6>Reset Password</h6>
                                            <p class="small text-muted mb-3">Click the link and enter your new password</p>
                                            <a href="password_reset_setup.php" class="btn btn-sm btn-success">Setup</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<style>
.bg-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
.border-2 {
    border-width: 2px !important;
}
</style>

<?php include __DIR__ . '/partials/footer.php'; ?>
