<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

try {
    $stmt = $pdo->query("SELECT pr.*, u.name as user_name FROM password_reset_requests pr LEFT JOIN users u ON pr.user_id = u.id ORDER BY pr.created_at DESC");
    $requests = $stmt->fetchAll();
} catch (Exception $e) {
    $requests = [];
}

?>
<?php include __DIR__ . '/partials/header.php'; ?>
<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h3>Password Reset Requests</h3>
            <?php if ($flash_success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($flash_success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($flash_error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($flash_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Email</th>
                            <th>Name</th>
                            <th>Requested</th>
                            <th>Face Verified</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $r): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($r['id']); ?></td>
                                <td><?php echo htmlspecialchars($r['email']); ?></td>
                                <td><?php echo htmlspecialchars($r['user_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                                <td><?php echo $r['face_verified'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'; ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($r['status'])); ?></td>
                                <td>
                                    <?php if ($r['status'] === 'pending'): ?>
                                        <form method="post" action="approve_password_request.php" style="display:inline-block;margin-right:6px;">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($r['id']); ?>">
                                            <button class="btn btn-sm btn-success">Approve</button>
                                        </form>
                                        <form method="post" action="reject_password_request.php" style="display:inline-block;">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($r['id']); ?>">
                                            <button class="btn btn-sm btn-danger">Reject</button>
                                        </form>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
