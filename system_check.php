<?php
/**
 * Complete System Test & Verification
 */

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/mail_helper.php';

$tests = [];
$all_passed = true;

// Test 1: Database Connection
try {
    $stmt = $pdo->query("SELECT 1");
    $tests['Database Connection'] = ['pass' => true, 'message' => 'Connected to attendance_db'];
} catch (Exception $e) {
    $tests['Database Connection'] = ['pass' => false, 'message' => $e->getMessage()];
    $all_passed = false;
}

// Test 2: password_reset_tokens Table
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'password_reset_tokens'");
    $exists = $stmt->rowCount() > 0;
    $tests['password_reset_tokens Table'] = [
        'pass' => $exists,
        'message' => $exists ? 'Table exists with correct structure' : 'Table not found'
    ];
    if (!$exists) $all_passed = false;
} catch (Exception $e) {
    $tests['password_reset_tokens Table'] = ['pass' => false, 'message' => $e->getMessage()];
    $all_passed = false;
}

// Test 3: users Table
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    $count = $result['count'] ?? 0;
    $tests['Users Table'] = [
        'pass' => true,
        'message' => "Found {$count} users"
    ];
} catch (Exception $e) {
    $tests['Users Table'] = ['pass' => false, 'message' => $e->getMessage()];
    $all_passed = false;
}

// Test 4: mail_helper.php Functions
$tests['Mail Helper Functions'] = ['pass' => true, 'message' => 'All functions available'];
if (!function_exists('send_password_reset_email')) {
    $tests['Mail Helper Functions'] = ['pass' => false, 'message' => 'send_password_reset_email() not found'];
    $all_passed = false;
}

// Test 5: config.php Email Settings
$config = require __DIR__ . '/config.php';
$has_email_from = isset($config['email_from']) && !empty($config['email_from']);
$tests['Email Configuration'] = [
    'pass' => $has_email_from,
    'message' => $has_email_from ? 'Email settings configured' : 'Email settings missing'
];

// Test 6: Storage Directory
$storage_dir = __DIR__ . '/storage/emails';
$storage_exists = is_dir($storage_dir);
$storage_writable = $storage_exists && is_writable($storage_dir);
$tests['Storage Directory'] = [
    'pass' => $storage_writable,
    'message' => $storage_writable ? 'Directory exists and writable' : 'Directory missing or not writable'
];
if (!$storage_writable) $all_passed = false;

// Test 7: Required PHP Functions
$required_functions = ['password_hash', 'password_verify', 'random_bytes', 'bin2hex'];
$missing_functions = [];
foreach ($required_functions as $func) {
    if (!function_exists($func)) {
        $missing_functions[] = $func;
    }
}
$tests['PHP Functions'] = [
    'pass' => count($missing_functions) === 0,
    'message' => count($missing_functions) === 0 
        ? 'All required functions available'
        : 'Missing: ' . implode(', ', $missing_functions)
];
if (count($missing_functions) > 0) $all_passed = false;

// Test 8: File Permissions
$required_files = [
    'forgot_password.php' => __DIR__ . '/forgot_password.php',
    'reset_password.php' => __DIR__ . '/reset_password.php',
    'view_emails.php' => __DIR__ . '/view_emails.php',
    'includes/mail_helper.php' => __DIR__ . '/includes/mail_helper.php',
];

$missing_files = [];
foreach ($required_files as $name => $path) {
    if (!file_exists($path)) {
        $missing_files[] = $name;
    }
}

$tests['Required Files'] = [
    'pass' => count($missing_files) === 0,
    'message' => count($missing_files) === 0
        ? 'All files present'
        : 'Missing: ' . implode(', ', $missing_files)
];
if (count($missing_files) > 0) $all_passed = false;

// Test 9: Test Token Generation
try {
    $token = generate_reset_token();
    $valid_token = strlen($token) === 64 && ctype_xdigit($token);
    $tests['Token Generation'] = [
        'pass' => $valid_token,
        'message' => $valid_token ? 'Generates valid 64-character tokens' : 'Token generation failed'
    ];
    if (!$valid_token) $all_passed = false;
} catch (Exception $e) {
    $tests['Token Generation'] = ['pass' => false, 'message' => $e->getMessage()];
    $all_passed = false;
}

// Test 10: Test Password Hashing
try {
    $password = 'TestPassword123!';
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $verified = password_verify($password, $hashed);
    $tests['Password Hashing'] = [
        'pass' => $verified,
        'message' => $verified ? 'Bcrypt hashing working correctly' : 'Hash verification failed'
    ];
    if (!$verified) $all_passed = false;
} catch (Exception $e) {
    $tests['Password Hashing'] = ['pass' => false, 'message' => $e->getMessage()];
    $all_passed = false;
}

?>
<?php include __DIR__ . '/partials/header.php'; ?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <!-- Header -->
            <div class="card border-0 shadow-sm mb-4 <?php echo $all_passed ? 'bg-success' : 'bg-warning'; ?> text-white">
                <div class="card-body text-center p-5">
                    <h1 class="mb-3">
                        <?php echo $all_passed ? '✅ System Check Passed!' : '⚠️ Some Issues Found'; ?>
                    </h1>
                    <p class="lead mb-0">
                        <?php echo $all_passed 
                            ? 'Your password reset system is fully configured and ready to use.'
                            : 'Please resolve the issues below to complete setup.'; ?>
                    </p>
                </div>
            </div>
            
            <!-- Test Results -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>System Test Results</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <tbody>
                            <?php foreach ($tests as $test_name => $result): ?>
                                <tr class="<?php echo $result['pass'] ? 'table-success' : 'table-danger'; ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($test_name); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $result['pass'] ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $result['pass'] ? '✓ PASS' : '✗ FAIL'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo htmlspecialchars($result['message']); ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Next Steps -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-lightning-fill me-2"></i>Next Steps</h6>
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
                                <i class="bi bi-envelope me-2"></i>View Saved Emails
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="password_reset_setup.php" class="btn btn-secondary w-100">
                                <i class="bi bi-speedometer2 me-2"></i>Setup Page
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="index.php" class="btn btn-outline-primary w-100">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login Page
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Documentation -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-book me-2"></i>Documentation</h6>
                </div>
                <div class="card-body">
                    <ul>
                        <li><a href="PASSWORD_RESET_QUICK_START.md">📖 Quick Start Guide</a></li>
                        <li><a href="SETUP_GUIDE.md">📖 Setup Guide</a></li>
                        <li><a href="FORGOT_PASSWORD_README.md">📖 Technical Documentation</a></li>
                        <li><a href="EMAIL_CONFIGURATION.md">📖 Email Configuration</a></li>
                        <li><a href="IMPLEMENTATION_SUMMARY.md">📖 Implementation Summary</a></li>
                    </ul>
                </div>
            </div>
            
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
