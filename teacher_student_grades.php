<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/avatar_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? '';

// determine kind
$userStmt = $pdo->prepare('SELECT kind FROM users WHERE id = ? LIMIT 1');
$userStmt->execute([$userId]);
$user = $userStmt->fetch();
$userKind = $user['kind'] ?? null;

if ($userKind !== 'teacher' && $userRole !== 'admin') {
    http_response_code(403);
    echo "Access denied";
    exit;
}

// Ensure tables exist (subjects, grades)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(191) NOT NULL UNIQUE,
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $pdo->exec("CREATE TABLE IF NOT EXISTS grades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        teacher_id INT DEFAULT NULL,
        subject VARCHAR(191) NOT NULL,
        schedule VARCHAR(20) NOT NULL,
        grade VARCHAR(16) NOT NULL,
        remarks VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_student_subject_schedule (student_id, subject, schedule)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {}

// Fetch subjects
$subjectsStmt = $pdo->query("SELECT name FROM subjects ORDER BY name ASC");
$subjects = $subjectsStmt->fetchAll(PDO::FETCH_COLUMN);
if (empty($subjects)) {
    $subjects = ['Anatomy & Physiology', 'Pharmacology', 'Pathology', 'Microbiology', 'Biochemistry', 'Physiology'];
}

$schedules = ['Day', 'Night', 'Weekend'];

// Fetch teacher's students
$studentsStmt = $pdo->prepare("SELECT id, name, email FROM users WHERE teacher_id = ? AND kind = 'student' ORDER BY name");
$studentsStmt->execute([$userId]);
$students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

$flash = '';
$flashType = 'success';

// Handle create/update via save_grade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grade'])) {
    $studentId = (int)($_POST['student_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $schedule = trim($_POST['schedule'] ?? 'Day');
    $grade = trim($_POST['grade'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    // ensure teacher is assigned to this student (or admin)
    $canModify = false;
    if ($userRole === 'admin') {
        $canModify = true;
    } else {
        $chk = $pdo->prepare('SELECT id FROM users WHERE id = ? AND teacher_id = ? AND kind = "student" LIMIT 1');
        $chk->execute([$studentId, $userId]);
        if ($chk->fetch()) $canModify = true;
    }

    if (!$canModify) {
        $flash = 'Permission denied for this student.';
        $flashType = 'danger';
    } elseif ($studentId && $subject && $grade) {
        $up = $pdo->prepare("INSERT INTO grades (student_id, teacher_id, subject, schedule, grade, remarks)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE grade = VALUES(grade), remarks = VALUES(remarks), teacher_id = VALUES(teacher_id), updated_at = CURRENT_TIMESTAMP");
        $up->execute([$studentId, $userId, $subject, $schedule, $grade, $remarks]);
        $flash = 'Grade saved.';
    } else {
        $flash = 'Please provide student, subject and grade.';
        $flashType = 'danger';
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_grade'])) {
    $gradeId = (int)($_POST['grade_id'] ?? 0);
    if ($gradeId) {
        // restrict
        if ($userRole === 'admin') {
            $pdo->prepare('DELETE FROM grades WHERE id = ?')->execute([$gradeId]);
            $flash = 'Grade deleted.';
        } else {
            $d = $pdo->prepare('DELETE FROM grades WHERE id = ? AND teacher_id = ?');
            $d->execute([$gradeId, $userId]);
            if ($d->rowCount()) $flash = 'Grade deleted.'; else { $flash = 'Not allowed to delete this grade.'; $flashType = 'danger'; }
        }
    }
}

// Handle edit update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_grade'])) {
    $gradeId = (int)($_POST['grade_id'] ?? 0);
    $grade = trim($_POST['grade'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    if ($gradeId && $grade) {
        if ($userRole === 'admin') {
            $pdo->prepare('UPDATE grades SET grade = ?, remarks = ? WHERE id = ?')->execute([$grade, $remarks, $gradeId]);
            $flash = 'Grade updated.';
        } else {
            $u = $pdo->prepare('UPDATE grades SET grade = ?, remarks = ? WHERE id = ? AND teacher_id = ?');
            $u->execute([$grade, $remarks, $gradeId, $userId]);
            if ($u->rowCount()) $flash = 'Grade updated.'; else { $flash = 'Not allowed to update this grade.'; $flashType = 'danger'; }
        }
    }
}

// Selected student (via GET or POST)
$selectedStudentId = (int)($_GET['student_id'] ?? $_POST['student_id'] ?? ($students[0]['id'] ?? 0));

// If selected student isn't assigned to this teacher (and not admin), prevent
if ($selectedStudentId && $userRole !== 'admin') {
    $chk = $pdo->prepare('SELECT id FROM users WHERE id = ? AND teacher_id = ? AND kind = "student" LIMIT 1');
    $chk->execute([$selectedStudentId, $userId]);
    if (!$chk->fetch()) {
        $selectedStudentId = 0;
    }
}

// Fetch grades for selected student
$grades = [];
if ($selectedStudentId) {
    $gstmt = $pdo->prepare('SELECT g.*, t.name as teacher_name FROM grades g LEFT JOIN users t ON t.id = g.teacher_id WHERE g.student_id = ? ORDER BY g.created_at DESC');
    $gstmt->execute([$selectedStudentId]);
    $grades = $gstmt->fetchAll(PDO::FETCH_ASSOC);
}

include __DIR__ . '/partials/header.php';
?>
<div class="dashboard-container">
    <div class="mb-4">
        <h1 class="display-6 mb-0">My Student Grades</h1>
        <p class="text-muted">Create, edit, and delete grades for your assigned students</p>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flashType; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($flash); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="GET" class="row g-2 align-items-end mb-3">
                <div class="col-md-4">
                    <label class="form-label">Select Student</label>
                    <select name="student_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Select --</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo ($s['id']==$selectedStudentId)?'selected':''; ?>><?php echo htmlspecialchars($s['name']); ?> (<?php echo htmlspecialchars($s['email']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <a href="teacher_student_grades.php" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>

            <?php if (!$selectedStudentId): ?>
                <div class="alert alert-info">Please select one of your assigned students to manage grades.</div>
            <?php else: ?>
                <form method="POST" class="row g-2">
                    <input type="hidden" name="student_id" value="<?php echo $selectedStudentId; ?>">
                    <div class="col-md-4">
                        <label class="form-label">Subject</label>
                        <select name="subject" class="form-select" required>
                            <?php foreach ($subjects as $sub): ?>
                                <option value="<?php echo htmlspecialchars($sub); ?>"><?php echo htmlspecialchars($sub); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Schedule</label>
                        <select name="schedule" class="form-select">
                            <?php foreach ($schedules as $sch): ?>
                                <option value="<?php echo $sch; ?>"><?php echo $sch; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Grade</label>
                        <input name="grade" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button class="btn btn-primary w-100" name="save_grade">Save Grade</button>
                    </div>
                </form>
                <div class="mt-2">
                    <label class="form-label">Remarks (optional)</label>
                    <input name="remarks" class="form-control">
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($selectedStudentId): ?>
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Grades for <?php echo htmlspecialchars(($students[array_search($selectedStudentId, array_column($students,'id'))]['name'] ?? 'Selected Student')); ?></h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead class="table-light"><tr><th>Subject</th><th>Schedule</th><th>Grade</th><th>Remarks</th><th>When</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($grades as $g): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($g['subject']); ?></td>
                            <td><?php echo htmlspecialchars($g['schedule']); ?></td>
                            <td><?php echo htmlspecialchars($g['grade']); ?></td>
                            <td><?php echo htmlspecialchars($g['remarks'] ?? '-'); ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($g['updated_at'] ?? $g['created_at']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editGradeModal" onclick="setEdit(<?php echo $g['id']; ?>, '<?php echo htmlspecialchars($g['grade']); ?>', '<?php echo htmlspecialchars(addslashes($g['remarks'])); ?>')">Edit</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this grade?')">
                                    <input type="hidden" name="grade_id" value="<?php echo $g['id']; ?>">
                                    <button class="btn btn-sm btn-danger" name="delete_grade" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editGradeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Grade</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" id="gradeId" name="grade_id">
                        <div class="mb-2">
                            <label class="form-label">Grade</label>
                            <input type="text" id="gradeValue" name="grade" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Remarks</label>
                            <input type="text" id="remarksValue" name="remarks" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_grade" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function setEdit(id, grade, remarks) {
        document.getElementById('gradeId').value = id;
        document.getElementById('gradeValue').value = grade;
        document.getElementById('remarksValue').value = remarks;
    }
    </script>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/partials/footer.php'; ?>