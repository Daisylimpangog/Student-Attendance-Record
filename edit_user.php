<?php
session_start();
require_once __DIR__ . '/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$errors = [];
$success = '';

$userId = (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: admin.php');
    exit;
}

// ensure schedule column exists
try {
    $pdo->query("SHOW COLUMNS FROM users LIKE 'schedule' ")->fetch();
} catch (Exception $e) {
    // ignore
}

// load user
$stmt = $pdo->prepare('SELECT id, email, name, role, kind, schedule FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    header('Location: admin.php');
    exit;
}

// fetch available teachers for assignment
$teachers = $pdo->query("SELECT id, name, email FROM users WHERE kind = 'teacher' ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $kind = ($_POST['kind'] === 'teacher') ? 'teacher' : 'student';
    $role = ($_POST['role'] === 'admin') ? 'admin' : 'user';
    $schedule = in_array($_POST['schedule'] ?? '', ['Day','Night','Weekend']) ? $_POST['schedule'] : null;
    $teacher_id = null;
    if (!empty($_POST['teacher_id'])) {
        $teacher_id = (int)$_POST['teacher_id'];
    }
    $password = $_POST['password'] ?? '';

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if (!$name) {
        $errors[] = 'Name is required.';
    }

    if (empty($errors)) {
        // check if email used by someone else
        $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $chk->execute([$email, $userId]);
        if ($chk->fetch()) {
            $errors[] = 'Email is already used by another user.';
        } else {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $up = $pdo->prepare('UPDATE users SET email = ?, name = ?, role = ?, kind = ?, schedule = ?, teacher_id = ?, password = ? WHERE id = ?');
                $up->execute([$email, $name, $role, $kind, $schedule, $teacher_id, $hash, $userId]);
            } else {
                $up = $pdo->prepare('UPDATE users SET email = ?, name = ?, role = ?, kind = ?, schedule = ?, teacher_id = ? WHERE id = ?');
                $up->execute([$email, $name, $role, $kind, $schedule, $teacher_id, $userId]);
            }
            $success = 'User updated successfully.';
            // reload data
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            // Update session if editing the currently logged-in user
            if ($userId === $_SESSION['user_id']) {
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_role'] = $role;
            }
        }
    }
}

?>
<?php include __DIR__ . '/partials/header.php'; ?>

<div class="wrap">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1>Edit User</h1>
            <div class="userbar">Admin: <?php echo htmlspecialchars($_SESSION['user_email']); ?></div>
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
        <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
        <div class="col-md-6">
            <label class="form-label">Email</label>
            <input class="form-control" type="email" name="email" required value="<?php echo htmlspecialchars($user['email']); ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Name</label>
            <input class="form-control" type="text" name="name" required value="<?php echo htmlspecialchars($user['name']); ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Kind</label>
            <select class="form-select" name="kind">
                <option value="student" <?php echo ($user['kind'] === 'student') ? 'selected' : ''; ?>>Student</option>
                <option value="teacher" <?php echo ($user['kind'] === 'teacher') ? 'selected' : ''; ?>>Teacher</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Role</label>
            <select class="form-select" name="role">
                <option value="user" <?php echo ($user['role'] === 'user') ? 'selected' : ''; ?>>User</option>
                <option value="admin" <?php echo ($user['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Schedule</label>
            <select class="form-select" name="schedule">
                <option value="Day" <?php echo ($user['schedule'] === 'Day') ? 'selected' : ''; ?>>Day</option>
                <option value="Night" <?php echo ($user['schedule'] === 'Night') ? 'selected' : ''; ?>>Night</option>
                <option value="Weekend" <?php echo ($user['schedule'] === 'Weekend') ? 'selected' : ''; ?>>Weekend</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Assigned teacher</label>
            <select class="form-select" name="teacher_id">
                <option value="">-- none --</option>
                <?php foreach ($teachers as $t): ?>
                    <option value="<?php echo (int)$t['id']; ?>" <?php echo (($user['teacher_id'] ?? '') == $t['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['name'] . ' <' . $t['email'] . '>'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">New password (leave blank to keep unchanged)</label>
            <input class="form-control" type="text" name="password" placeholder="Enter new password">
        </div>
        <div class="col-12">
            <button class="btn btn-primary" type="submit">Update user</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>

