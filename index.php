<?php
session_start();
require_once __DIR__ . '/db.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $errors[] = 'Please fill both fields.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            // login
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];
            
            // Check user status
            if ($user['kind'] === 'student') {
                $status = $user['status'] ?? 'Ongoing';
                if ($status === 'Graduated') {
                    header('Location: graduated.php');
                } elseif ($status === 'Freeze') {
                    header('Location: frozen.php');
                } else {
                    header('Location: attendance.php');
                }
            } else {
                header('Location: attendance.php');
            }
            exit;
        } else {
            $errors[] = 'Invalid credentials.';
        }
    }
}
?>
<?php include __DIR__ . '/partials/header.php'; ?>

<div class="dashboard-container">
        <div class="row align-items-center">
                <div class="col-md-7">
                        <h1 class="mb-3">Center For Healthcare Profession Cebu, Inc.</h1>
                        <p class="muted">Welcome — use your school credentials to access the attendance system. Administrators can manage users and view reports.</p>
                        <p class="mt-4">
                                <a class="btn btn-lg btn-primary" href="#" data-bs-toggle="modal" data-bs-target="#loginModal" data-icon="bi-box-arrow-in-right">Sign in</a>
                        </p>
                </div>
            <div class="col-md-5 d-none d-md-block">
                    <div class="card p-4">
                                <h3 class="mb-2">About</h3>
                        <p class="muted">A simple attendance monitoring app for schools. Clock in/out, review recent records, and manage users.</p>
                        </div>
                </div>
        </div>

    <!-- Login Modal -->
        <div class="modal fade login-modal" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-body d-flex p-0">
                        <div class="login-left">
                            <div class="logo">
                                <!-- School logo (place your logo at assets/img/logo.png) -->
                                <img src="/CHPCEBU-Attendance/assets/image/logo.png" alt="CHPCEBU logo" class="img-fluid" />
                                <h4 class="mt-3">Center for Healthcare Profession Cebu, Inc.</h4>
                                <p class="small">Attendance Portal</p>
                            </div>
                        </div>
                        <div class="login-right">
                            <h3 id="loginModalLabel">Sign in to your account</h3>
                            <p class="muted">Enter your email and password to continue.</p>
                            <?php if ($errors): ?>
                                <div class="errors"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
                            <?php endif; ?>
                            <form method="post" class="mt-2">
                                <div class="mb-3">
                                    <label class="form-label" for="loginEmail">Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                        <input id="loginEmail" class="form-control" type="email" name="email" required value="<?php echo htmlspecialchars($email ?? ''); ?>" placeholder="you@school.edu" autocomplete="email">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="loginPassword">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                        <input id="loginPassword" class="form-control" type="password" name="password" required placeholder="Password" autocomplete="current-password">
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="1" id="rememberMe" name="remember">
                                        <label class="form-check-label" for="rememberMe">Remember me</label>
                                    </div>
                                    <div><a href="#" class="small">Forgot password?</a></div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <button id="loginSubmit" class="btn btn-primary btn-submit" type="submit" data-icon="bi-box-arrow-in-right">
                                            <span class="btn-label">Sign in</span>
                                            <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                                        </button>
                                    </div>
                                    <div>
                                        <a href="#" class="btn btn-link" data-bs-dismiss="modal">Cancel</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    function openIfNeeded(){
        var should = (location.hash === '#login') || <?php echo !empty($errors) ? 'true' : 'false'; ?>;
        if (!should) return;
        if (window.bootstrap && bootstrap.Modal) {
            var m = new bootstrap.Modal(document.getElementById('loginModal'));
            m.show();
        } else {
            setTimeout(openIfNeeded, 60);
        }
    }
    openIfNeeded();
});
</script>

<script>
// Prevent double submit and show small spinner
document.addEventListener('DOMContentLoaded', function(){
    var form = document.querySelector('#loginModal form');
    if (!form) return;
    form.addEventListener('submit', function(e){
        var btn = document.getElementById('loginSubmit');
        if (!btn) return;
        btn.setAttribute('aria-busy', 'true');
        var sp = btn.querySelector('.spinner-border');
        if (sp) sp.classList.remove('d-none');
        // disable the submit button to avoid double submits but keep inputs enabled so their values are sent
        btn.disabled = true;
        // make inputs readonly to prevent edits while request is in flight (readonly fields are still submitted)
        Array.from(form.querySelectorAll('input[type="email"], input[type="password"]')).forEach(function(x){ x.setAttribute('readonly', 'readonly'); });
    });
});
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
