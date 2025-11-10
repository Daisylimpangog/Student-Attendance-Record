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
$stmt = $pdo->prepare('SELECT name, created_at FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Get attendance history
$stmt = $pdo->prepare('SELECT COUNT(*) as total_days, 
    SUM(CASE WHEN type = "in" THEN 1 ELSE 0 END) as days_present,
    SUM(CASE WHEN type = "absent" THEN 1 ELSE 0 END) as days_absent
    FROM attendance WHERE user_id = ?');
$stmt->execute([$userId]);
$stats = $stmt->fetch();
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
                    <p class="text-muted">Graduated Student</p>
                </div>

                <?php if (!empty($_SESSION['flash'])): ?>
                    <div class="alert alert-info">
                        <?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?>
                    </div>
                <?php endif; ?>

                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="p-3 border rounded bg-light">
                            <h3 class="h5 mb-1"><?php echo number_format($stats['total_days'] ?? 0); ?></h3>
                            <p class="small text-muted mb-0">Total Days</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 border rounded bg-light">
                            <h3 class="h5 mb-1"><?php echo number_format($stats['days_present'] ?? 0); ?></h3>
                            <p class="small text-muted mb-0">Days Present</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 border rounded bg-light">
                            <h3 class="h5 mb-1"><?php echo number_format($stats['days_absent'] ?? 0); ?></h3>
                            <p class="small text-muted mb-0">Days Absent</p>
                        </div>
                    </div>
                </div>

                <p class="text-muted mb-4">
                    Your account has been marked as graduated. You can no longer mark attendance,<br>
                    but you can still view your attendance history and statistics.
                </p>

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