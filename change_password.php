<?php
// change_password.php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];
$currentRole = $_SESSION['user_role'] ?? 'user';

$targetUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $currentUserId;

// only admin or the owner may change
if ($currentRole !== 'admin' && $currentUserId !== $targetUserId) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = trim($_POST['new_password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    if ($currentRole !== 'admin' || $currentUserId === $targetUserId) {
        // require current password for non-admins (and admins changing their own password)
        $current = $_POST['current_password'] ?? '';
        if (!$current) $errors[] = 'Current password is required.';
    }

    if (!$new || strlen($new) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }
    if ($new !== $confirm) {
        $errors[] = 'New password and confirmation do not match.';
    }

    if (empty($errors)) {
        // verify current password if needed
        if (($currentRole !== 'admin' || $currentUserId === $targetUserId)) {
            $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$currentUserId]);
            $row = $stmt->fetch();
            if (!$row || !password_verify($current, $row['password'])) {
                $errors[] = 'Current password is incorrect.';
            }
        }
    }

    if (empty($errors)) {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([$hash, $targetUserId]);
        $success = 'Password updated successfully.';
    }
}

// Fetch target user email for display
$stmt = $pdo->prepare('SELECT id, email, name FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$targetUserId]);
$target = $stmt->fetch();
if (!$target) {
    echo 'User not found.'; exit;
}

?>
<?php include __DIR__ . '/partials/header.php'; ?>
<div class="wrap">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1>Change Password</h1>
            <div class="userbar">For: <?php echo htmlspecialchars($target['email']); ?></div>
        </div>
        <div>
            <a class="btn btn-outline-secondary btn-sm" href="admin.php">Back</a>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="errors"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="flash"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3">
        <?php if ($currentRole !== 'admin' || $currentUserId === $targetUserId): ?>
            <div class="col-12">
                <label class="form-label">Current password</label>
                <input class="form-control" type="password" name="current_password" required>
            </div>
        <?php endif; ?>

        <div class="col-md-6">
            <label class="form-label">New password</label>
            <input class="form-control" type="password" name="new_password" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Confirm new password</label>
            <input class="form-control" type="password" name="confirm_password" required>
        </div>
        <div class="col-12">
            <button class="btn btn-primary" type="submit">Save password</button>
        </div>
    </form>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
