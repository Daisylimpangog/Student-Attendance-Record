<?php
// add_user.php - Admin page to add new users (student/teacher)
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$errors = [];
$success = '';

// Ensure `kind` column exists; add if missing
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'kind'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE users ADD COLUMN kind ENUM('student','teacher') DEFAULT 'student'");
    }
    // ensure schedule column exists (for teachers)
    $schedCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'schedule'")->fetch();
    if (!$schedCheck) {
        // default to Day for backwards compatibility
        $pdo->exec("ALTER TABLE users ADD COLUMN schedule ENUM('Day','Night','Weekend') DEFAULT 'Day'");
    }
    // ensure teacher_id column exists (to assign students to teachers)
    $tidCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'teacher_id'")->fetch();
    if (!$tidCheck) {
        $pdo->exec("ALTER TABLE users ADD COLUMN teacher_id INT NULL DEFAULT NULL");
    }
} catch (Exception $e) {
    $errors[] = 'Failed to ensure kind column: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $kind = ($_POST['kind'] === 'teacher') ? 'teacher' : 'student';
    $role = ($_POST['role'] === 'admin') ? 'admin' : 'user';
    $password = $_POST['password'] ?? '';

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if (!$name) {
        $errors[] = 'Name is required.';
    }

    if (empty($password)) {
        // generate a simple random password and show it to admin
        $password = bin2hex(random_bytes(4)); // 8 chars
        $generated = true;
    } else {
        $generated = false;
    }

    if (empty($errors)) {
        // Check for existing email
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            // Only set schedule when creating a teacher; default to NULL for students
            $schedule = null;
            if ($kind === 'teacher') {
                $schedule = in_array($_POST['schedule'] ?? '', ['Day','Night','Weekend']) ? $_POST['schedule'] : 'Day';
            }
            // assign teacher for students
            $teacher_id = null;
            if ($kind === 'student' && !empty($_POST['teacher_id'])) {
                $teacher_id = (int)$_POST['teacher_id'];
            }
            $ins = $pdo->prepare('INSERT INTO users (email, password, name, role, kind, schedule, teacher_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $ins->execute([$email, $hash, $name, $role, $kind, $schedule, $teacher_id]);
            $success = 'User created successfully. ' . ($generated ? "Generated password: $password" : '');
        }
    }
}

?>
<?php include __DIR__ . '/partials/header.php'; ?>

<div class="wrap">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1>Add User</h1>
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
        <div class="col-md-6">
            <label class="form-label">Email</label>
            <input class="form-control" type="email" name="email" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Name</label>
            <input class="form-control" type="text" name="name" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Kind</label>
            <select class="form-select" name="kind">
                <option value="student">Student</option>
                <option value="teacher">Teacher</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Assign teacher</label>
            <select class="form-select" name="teacher_id">
                <option value="">-- none --</option>
                <?php
                // fetch teachers
                $teachers = $pdo->query("SELECT id, name, email FROM users WHERE kind = 'teacher' ORDER BY name")->fetchAll();
                foreach ($teachers as $t) {
                    echo '<option value="' . (int)$t['id'] . '">' . htmlspecialchars($t['name'] . ' <' . $t['email'] . '>') . '</option>';
                }
                ?>
            </select>
            <div class="form-text">Assign this student to a teacher (optional).</div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Teacher schedule</label>
            <select class="form-select" name="schedule">
                <option value="Day">Day</option>
                <option value="Night">Night</option>
                <option value="Weekend">Weekend</option>
            </select>
            <div class="form-text">Only relevant for teachers; ignored for students.</div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Role</label>
            <select class="form-select" name="role">
                <option value="user">User</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Password (optional)</label>
            <input class="form-control" type="text" name="password" placeholder="Leave blank to auto-generate">
        </div>
        <div class="col-12">
            <button class="btn btn-primary" type="submit">Create user</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
