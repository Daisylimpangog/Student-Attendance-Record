<?php
/**
 * Email Viewer for Development
 * View emails that would be sent (development/testing mode)
 */

require_once __DIR__ . '/db.php';

$emails_dir = __DIR__ . '/storage/emails';
$emails = [];

if (is_dir($emails_dir)) {
    $files = array_diff(scandir($emails_dir, SCANDIR_SORT_DESCENDING), ['.', '..']);
    foreach ($files as $file) {
        if (is_file($emails_dir . '/' . $file)) {
            $emails[] = [
                'filename' => $file,
                'path' => $emails_dir . '/' . $file,
                'time' => filectime($emails_dir . '/' . $file),
                'size' => filesize($emails_dir . '/' . $file)
            ];
        }
    }
}

// Get specific email to view
$view_email = $_GET['file'] ?? null;
$email_content = null;

if ($view_email && strpos($view_email, '/') === false && strpos($view_email, '\\') === false) {
    $file_path = $emails_dir . '/' . $view_email;
    if (file_exists($file_path) && is_file($file_path)) {
        $email_content = file_get_contents($file_path);
    }
}

// Delete email if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete') {
    $delete_file = $_POST['file'] ?? null;
    if ($delete_file && strpos($delete_file, '/') === false && strpos($delete_file, '\\') === false) {
        $file_path = $emails_dir . '/' . $delete_file;
        if (file_exists($file_path)) {
            unlink($file_path);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// Clear all emails if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'clear_all') {
    if (is_dir($emails_dir)) {
        $files = array_diff(scandir($emails_dir), ['.', '..']);
        foreach ($files as $file) {
            $file_path = $emails_dir . '/' . $file;
            if (is_file($file_path)) {
                unlink($file_path);
            }
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

?>
<?php include __DIR__ . '/partials/header.php'; ?>

<div class="container mt-5 mb-5">
    <div class="row">
        <div class="col-lg-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-envelope"></i> Development Email Viewer
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>📧 Development Mode:</strong> Emails are saved to files instead of being sent. 
                        This is normal for local development. To send real emails, configure SMTP in config.php.
                    </div>
                    
                    <?php if (!is_dir($emails_dir)): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            No emails have been sent yet. Try the <a href="forgot_password.php">Forgot Password</a> feature.
                        </div>
                    <?php elseif (count($emails) === 0): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            No emails in the folder yet. Try requesting a password reset.
                        </div>
                    <?php else: ?>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6><?php echo count($emails); ?> Email(s)</h6>
                            </div>
                            <div class="col-md-6 text-end">
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="action" value="clear_all">
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete all emails?')">
                                        <i class="bi bi-trash me-1"></i>Clear All
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <?php if ($view_email && $email_content): ?>
                            <!-- View Single Email -->
                            <div class="mb-4">
                                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary btn-sm mb-3">
                                    <i class="bi bi-arrow-left me-1"></i>Back to List
                                </a>
                                
                                <div class="card bg-light border">
                                    <div class="card-body">
                                        <pre style="white-space: pre-wrap; word-wrap: break-word; max-height: 600px; overflow-y: auto;">><?php echo htmlspecialchars($email_content); ?></pre>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="file" value="<?php echo htmlspecialchars($view_email); ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this email?')">
                                            <i class="bi bi-trash me-1"></i>Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- List All Emails -->
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>From</th>
                                            <th>Subject</th>
                                            <th>Time</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($emails as $email): ?>
                                            <?php
                                            $content = file_get_contents($email['path']);
                                            preg_match('/^TO: (.+)$/m', $content, $to_match);
                                            preg_match('/^SUBJECT: (.+)$/m', $content, $subject_match);
                                            $to = $to_match[1] ?? 'Unknown';
                                            $subject = $subject_match[1] ?? 'Unknown';
                                            ?>
                                            <tr>
                                                <td><small><?php echo htmlspecialchars($to); ?></small></td>
                                                <td>
                                                    <a href="?file=<?php echo urlencode($email['filename']); ?>">
                                                        <?php echo htmlspecialchars($subject); ?>
                                                    </a>
                                                </td>
                                                <td><small><?php echo date('M d, Y H:i:s', $email['time']); ?></small></td>
                                                <td>
                                                    <a href="?file=<?php echo urlencode($email['filename']); ?>" class="btn btn-sm btn-info" title="View">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <form method="post" style="display:inline;">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="file" value="<?php echo htmlspecialchars($email['filename']); ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')" title="Delete">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Setup Instructions -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0"><i class="bi bi-gear me-2"></i>How to Enable Real Email Sending</h6>
                </div>
                <div class="card-body">
                    <p><strong>Option 1: Use Mailtrap (Recommended for Testing)</strong></p>
                    <ol>
                        <li>Sign up at <a href="https://mailtrap.io" target="_blank">mailtrap.io</a> (free account)</li>
                        <li>Copy your SMTP credentials from Mailtrap inbox settings</li>
                        <li>Edit <code>config.php</code> and add:
                            <pre>
'smtp_enabled' => true,
'smtp_host' => 'smtp.mailtrap.io',
'smtp_port' => 2525,
'smtp_user' => 'your_mailtrap_user',
'smtp_pass' => 'your_mailtrap_pass',</pre>
                        </li>
                        <li>Update <code>includes/mail_helper.php</code> to use SMTP (or use PHPMailer)</li>
                    </ol>
                    
                    <p class="mt-3"><strong>Option 2: Use Gmail SMTP</strong></p>
                    <ol>
                        <li>Enable 2FA on your Gmail account</li>
                        <li>Generate App Password: <a href="https://myaccount.google.com/apppasswords" target="_blank">myaccount.google.com/apppasswords</a></li>
                        <li>Edit <code>config.php</code>:
                            <pre>
'smtp_enabled' => true,
'smtp_host' => 'smtp.gmail.com',
'smtp_port' => 587,
'smtp_user' => 'your-email@gmail.com',
'smtp_pass' => 'your-16-char-app-password',</pre>
                        </li>
                    </ol>
                    
                    <p class="mt-3"><strong>Option 3: Configure Windows SMTP (Advanced)</strong></p>
                    <ol>
                        <li>Open IIS on your Windows server</li>
                        <li>Configure SMTP relay settings</li>
                        <li>Update php.ini SMTP and smtp_port</li>
                        <li>Restart Apache/PHP</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
