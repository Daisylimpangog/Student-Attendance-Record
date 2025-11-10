<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/avatar_helper.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$userId = (int)$_SESSION['user_id'];
$userEmail = $_SESSION['user_email'];

// Get user details
$stmt = $pdo->prepare('SELECT name, created_at, status FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Get last attendance before freeze
$stmt = $pdo->prepare('SELECT recorded_at FROM attendance WHERE user_id = ? ORDER BY recorded_at DESC LIMIT 1');
$stmt->execute([$userId]);
$lastAttendance = $stmt->fetch();
?>
<?php include __DIR__ . '/partials/header.php'; ?>

<div class="wrap">
    <div class="container py-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 text-center">
                <div class="mb-4">
                    <?php echo get_user_avatar([
                        'email' => $userEmail,
                        'name' => $user['name'] ?? $userEmail,
                        'profile_picture' => ''
                    ], 'mx-auto mb-3 avatar-lg', 'lg'); ?>
                    <h2 class="mb-1"><?php echo htmlspecialchars($user['name']); ?></h2>
                    <div class="badge bg-warning text-dark mb-3">Account Frozen</div>
                </div>

                <?php if (!empty($_SESSION['flash'])): ?>
                    <div class="alert alert-warning">
                        <?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?>
                    </div>
                <?php endif; ?>

                <div class="text-muted mb-4">
                    <p>Your account is currently frozen and attendance marking has been suspended.</p>
                    <?php if ($lastAttendance): ?>
                        <p>Last attendance record: <?php echo date('F j, Y g:i A', strtotime($lastAttendance['recorded_at'])); ?></p>
                    <?php endif; ?>
                    <p>Please contact your administrator to reactivate your account.</p>
                </div>

                <div class="d-flex justify-content-center gap-2">
                    <a href="logout.php" class="btn btn-secondary">Logout</a>
                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                        <a href="admin.php" class="btn btn-primary">Admin Panel</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>