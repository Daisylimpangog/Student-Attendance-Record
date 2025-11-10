<?php
session_start();
require_once __DIR__ . '/db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? '';

// handle admin action to revert status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userRole === 'admin') {
    $sid = (int)($_POST['student_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($sid > 0 && $action === 'revert') {
        $u = $pdo->prepare('UPDATE users SET status = "Ongoing", graduated_date = NULL WHERE id = ? LIMIT 1');
        $u->execute([$sid]);
        $_SESSION['flash'] = 'Student status reverted to Ongoing.';
        header('Location: graduated_students.php');
        exit;
    }
}

// fetch graduates depending on role
// First check if graduated_date column exists
$hasGraduatedDate = false;
try {
    $check = $pdo->query("SHOW COLUMNS FROM users LIKE 'graduated_date'");
    $hasGraduatedDate = ($check->rowCount() > 0);
} catch (PDOException $e) {
    // Column doesn't exist, we'll handle this
}

if ($userRole === 'admin') {
    $fields = "u.id, u.name, u.email, u.teacher_id, t.name as teacher_name" . 
              ($hasGraduatedDate ? ", u.graduated_date" : "");
    $stmt = $pdo->prepare("SELECT $fields FROM users u LEFT JOIN users t ON t.id = u.teacher_id WHERE u.kind = 'student' AND u.status = 'Graduated' ORDER BY u.name");
    $stmt->execute();
    $rows = $stmt->fetchAll();
} else {
    // for teachers, show only their graduated students
    $fields = "id, name, email" . ($hasGraduatedDate ? ", graduated_date" : "");
    $stmt = $pdo->prepare("SELECT $fields FROM users WHERE kind = 'student' AND teacher_id = ? AND status = 'Graduated' ORDER BY name");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
}

include __DIR__ . '/partials/header.php';
?>

<div class="wrap">
    <h1>Graduated Students</h1>
    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
    <?php endif; ?>

    <?php if (empty($rows)): ?>
        <p class="text-muted">No graduated students found.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead><tr><th>#</th><th>Name</th><th>Email</th><?php if ($userRole === 'admin'): ?><th>Teacher</th><?php endif; ?><?php if ($hasGraduatedDate): ?><th>Graduated Date</th><?php endif; ?><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['id']); ?></td>
                        <td><?php echo htmlspecialchars($r['name']); ?></td>
                        <td><?php echo htmlspecialchars($r['email']); ?></td>
                        <?php if ($userRole === 'admin'): ?><td><?php echo htmlspecialchars($r['teacher_name'] ?? ''); ?></td><?php endif; ?>
                        <?php if ($hasGraduatedDate): ?>
                        <td><?php echo isset($r['graduated_date']) && $r['graduated_date'] ? date('F j, Y', strtotime($r['graduated_date'])) : '-'; ?></td>
                        <?php endif; ?>
                        <td>
                            <?php if ($userRole === 'admin'): ?>
                                <form method="post" style="display:inline-block" onsubmit="return confirm('Revert student to Ongoing?');">
                                    <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($r['id']); ?>">
                                    <input type="hidden" name="action" value="revert">
                                    <button class="btn btn-sm btn-outline-primary">Revert</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">(graduated)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
