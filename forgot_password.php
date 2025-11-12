<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/mail_helper.php';

$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (!$email) {
        $errors[] = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        // Check if user exists
        $stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // For security: don't reveal if email exists
            $success = 'If an account exists with that email, a password reset link has been sent.';
        } else {
            // Generate reset token
            $token = generate_reset_token();
            $expires_at = get_reset_token_expiry(24); // 24 hours
            
            try {
                // Store token in database
                $stmt = $pdo->prepare(
                    'INSERT INTO password_reset_tokens (user_id, email, token, expires_at, used) 
                     VALUES (?, ?, ?, ?, 0)'
                );
                $stmt->execute([$user['id'], $user['email'], $token, $expires_at]);
                
                // Build reset link
                $reset_link = 'http://' . $_SERVER['HTTP_HOST'] . '/CHPCEBU-Attendance/reset_password.php?token=' . urlencode($token);
                
                // Send email
                $email_sent = send_password_reset_email($user['email'], $user['name'], $reset_link);
                
                if ($email_sent) {
                    $success = 'If an account exists with that email, a password reset link has been sent.';
                } else {
                    // Token was created but email failed - still show success message for security
                    $success = 'If an account exists with that email, a password reset link has been sent.';
                    // Log this for admin review
                    error_log("Password reset email failed for: {$email}");
                }
            } catch (PDOException $e) {
                error_log("Database error during password reset: " . $e->getMessage());
                $errors[] = 'Database error: ' . $e->getMessage(); // Show actual error for debugging
            }
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
                        <h2 class="mb-2">Forgot Your Password?</h2>
                        <p class="text-muted">Enter your email address and we'll send you a link to reset your password.</p>
                    </div>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        
                        <div class="alert alert-info">
                            <strong>💡 Development Mode:</strong> In production, an email would be sent. For development, 
                            <a href="view_emails.php" class="alert-link">view saved emails here</a> to see the password reset link.
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($errors): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php foreach ($errors as $error): ?>
                                <div><i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></div>
                            <?php endforeach; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- New flow: Email + Face verification -> submit request for admin approval -->
                    <div class="forgot-form">
                        <div class="mb-3">
                            <label class="form-label" for="resetEmail">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input 
                                    id="resetEmail" 
                                    class="form-control" 
                                    type="email" 
                                    required 
                                    placeholder="you@school.edu"
                                    value="<?php echo htmlspecialchars($email ?? ''); ?>"
                                    autocomplete="email"
                                >
                            </div>
                            <small class="text-muted d-block mt-2">Enter the email address associated with your account, then verify your identity by face.</small>
                        </div>

                        <div id="fpControls" class="mb-3">
                            <button id="startFaceBtn" class="btn btn-outline-primary w-100">Start Face Verification</button>
                        </div>

                        <div id="fpArea" style="display:none;">
                            <div class="mb-2">
                                <video id="fpVideo" width="320" height="240" autoplay muted style="border-radius:8px; border:1px solid #ddd; width:100%; max-width:420px;"></video>
                            </div>
                            <div id="fpStatus" class="small text-muted mb-3">Idle</div>

                            <div id="passwordBox" style="display:none;">
                                <label class="form-label" for="newPassword">New Password</label>
                                <input id="newPassword" class="form-control mb-2" type="password" placeholder="Enter new password (min 8 chars)">
                                <button id="submitRequestBtn" class="btn btn-success w-100">Submit Reset Request</button>
                            </div>
                        </div>

                        <div id="fpResult" class="mt-3"></div>
                    </div>
                    
                    <div class="text-center mt-4 pt-3 border-top">
                        <p class="text-muted small mb-2">Remember your password?</p>
                        <a href="index.php" class="btn btn-link btn-sm">Back to Sign In</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .forgot-form {
        margin-top: 20px;
    }
    
    .card {
        border-radius: 10px;
        margin-top: 30px;
    }
</style>

<?php include __DIR__ . '/partials/footer.php'; ?>

<!-- Face verification script -->
<script src="/CHPCEBU-Attendance/assets/js/face.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
    const startBtn = document.getElementById('startFaceBtn');
    const emailInput = document.getElementById('resetEmail');
    const fpArea = document.getElementById('fpArea');
    const fpStatus = document.getElementById('fpStatus');
    const fpVideoSel = '#fpVideo';
    const fpResult = document.getElementById('fpResult');
    const pwBox = document.getElementById('passwordBox');
    const submitBtn = document.getElementById('submitRequestBtn');

    function showFailModal() {
        // Create a simple Bootstrap modal or fallback alert
        try {
            const modalHtml = `
            <div class="modal fade" id="faceFailModal" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title">Verification Failed</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                    <p>Sorry your facial Verification Doesn't Match or Identity</p>
                  </div>
                </div>
              </div>
            </div>`;
            const tmp = document.createElement('div'); tmp.innerHTML = modalHtml;
            document.body.appendChild(tmp.firstElementChild);
            const m = new bootstrap.Modal(document.getElementById('faceFailModal'));
            m.show();
        } catch (e) {
            alert("Sorry your facial Verification Doesn't Match or Identity");
        }
    }

    startBtn.addEventListener('click', async function(e){
        e.preventDefault();
        const email = emailInput.value.trim();
        fpResult.innerText = '';
        if (!email) {
            fpResult.innerHTML = '<div class="text-danger small">Please enter your email first.</div>';
            return;
        }

        fpStatus.innerText = 'Requesting enrolled descriptor for ' + email + '...';
        // fetch descriptor by email
        try {
            const r = await fetch('/CHPCEBU-Attendance/get_face_descriptor_by_email.php?email=' + encodeURIComponent(email));
            const data = await r.json();
            if (!data.descriptor) {
                fpStatus.innerText = data.error || 'No enrolled descriptor found.';
                fpResult.innerHTML = '<div class="text-danger small">No enrolled face found for that email.</div>';
                return;
            }

            // show video area
            fpArea.style.display = 'block';
            fpStatus.innerText = 'Loading models and starting camera...';
            try {
                await FACE.loadModels(fpStatus);
                await FACE.startVideo(fpVideoSel);
            } catch (err) {
                fpStatus.innerText = 'Camera/models error: ' + (err.message || err);
                return;
            }

            fpStatus.innerText = 'Detecting your live face... Please position your face in front of the camera.';
            // capture live descriptor
            const liveDesc = await FACE.captureDescriptorFromVideo(fpVideoSel);
            if (!liveDesc) {
                fpStatus.innerText = 'No face detected. Try again.';
                showFailModal();
                return;
            }

            // compare
            const dist = FACE.compareDescriptors(liveDesc, data.descriptor);
            fpStatus.innerText = 'Distance: ' + dist.toFixed(4);
            const threshold = 0.6;
            if (dist <= threshold) {
                fpStatus.innerText = 'Face verified. You may now enter a new password to submit the request.';
                pwBox.style.display = 'block';
                // stop camera stream to save resources
                try {
                    const v = document.querySelector(fpVideoSel);
                    if (v && v.srcObject) {
                        v.srcObject.getTracks().forEach(t => t.stop());
                    }
                } catch (e) {}
            } else {
                fpStatus.innerText = 'Face did not match.';
                showFailModal();
            }

        } catch (err) {
            fpStatus.innerText = 'Error fetching descriptor: ' + (err.message || err);
            console.error(err);
        }
    });

    submitBtn.addEventListener('click', async function(e){
        e.preventDefault();
        const email = emailInput.value.trim();
        const newPassword = document.getElementById('newPassword').value;
        if (!newPassword || newPassword.length < 8) {
            fpResult.innerHTML = '<div class="text-danger small">Password must be at least 8 characters.</div>';
            return;
        }
        fpResult.innerHTML = '<div class="text-muted small">Submitting request...</div>';
        try {
            const res = await fetch('/CHPCEBU-Attendance/submit_password_reset_request.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, new_password: newPassword, face_verified: 1 })
            });
            const j = await res.json();
            if (res.ok && j.ok) {
                fpResult.innerHTML = '<div class="text-success small">Request submitted. An administrator will review and approve the change.</div>';
                pwBox.style.display = 'none';
            } else {
                fpResult.innerHTML = '<div class="text-danger small">Error: ' + (j.error || j.message || 'Unknown') + '</div>';
            }
        } catch (err) {
            fpResult.innerHTML = '<div class="text-danger small">Submission error: ' + (err.message || err) + '</div>';
        }
    });
});
</script>
