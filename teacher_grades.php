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

// Fetch user kind from database
$userStmt = $pdo->prepare('SELECT kind FROM users WHERE id = ? LIMIT 1');
$userStmt->execute([$userId]);
$user = $userStmt->fetch();
$userKind = $user['kind'] ?? null;

// Only teachers (or admin acting as teacher) can access
if ($userKind !== 'teacher' && $userRole !== 'admin') {
    http_response_code(403);
    echo "Access denied";
    exit;
}

// Ensure tables exist
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

// Fetch subjects from database
$subjectsStmt = $pdo->query("SELECT name FROM subjects ORDER BY name ASC");
$subjects = $subjectsStmt->fetchAll(PDO::FETCH_COLUMN);

// Fallback to default subjects if none exist
if (empty($subjects)) {
    $subjects = [
        'Anatomy & Physiology','Basic Emergency Care','Child Diseases','Child Care','Personal Care',
        'Mobilization','Nursing Procedure','Infection Control/Incontinence','Elderly Care','Hospice Care',
        'Mental Health Issues','Diet & Nutrition','Home Management','Going Abroad','Employment & Interview',
        'Medical Terminologies','Medical Math & Pharmacology','Legal Ethics','Personality Development'
    ];
}

$schedules = ['Day','Night','Weekend'];

// Fetch students list (only those assigned to this teacher unless admin)
if ($userRole === 'admin') {
    $studentsStmt = $pdo->query("SELECT id, name, email FROM users WHERE kind = 'student' ORDER BY name ASC");
    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $studentsStmt = $pdo->prepare("SELECT id, name, email FROM users WHERE kind = 'student' AND teacher_id = ? ORDER BY name ASC");
    $studentsStmt->execute([$userId]);
    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle flash messages
$flash = '';
$flashType = 'success';

// Determine selected student (teacher can filter to one student)
$selectedStudentId = (int)($_GET['student_id'] ?? $_POST['student_id'] ?? 0);
// If teacher and selected student is not assigned, reset
if ($selectedStudentId && $userRole !== 'admin') {
    $chk = $pdo->prepare('SELECT id FROM users WHERE id = ? AND teacher_id = ? AND kind = "student" LIMIT 1');
    $chk->execute([$selectedStudentId, $userId]);
    if (!$chk->fetch()) {
        $selectedStudentId = 0;
    }
}

// Handle Save Grade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grade'])) {
    $studentId = (int)($_POST['student_id'] ?? $selectedStudentId ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $schedule = trim($_POST['schedule'] ?? 'Day');
    $grade = trim($_POST['grade'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    // Permission: teacher can only grade own students (admin can grade anyone)
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
        // keep selected student
        $selectedStudentId = $studentId;
    } else {
        $flash = 'Please provide student, subject and grade.';
        $flashType = 'danger';
    }
}

// Handle Delete Grade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_grade'])) {
    $gradeId = (int)($_POST['grade_id'] ?? 0);
    if ($gradeId) {
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

// Handle Edit Grade
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

// Fetch grades for this teacher (or all if admin)
// Fetch grades: if a student is selected, show only that student's grades; otherwise show all grades by this teacher
if ($selectedStudentId) {
    if ($userRole === 'admin') {
        $gstmt = $pdo->prepare("SELECT g.*, u.name as student_name, t.name as teacher_name FROM grades g LEFT JOIN users u ON u.id = g.student_id LEFT JOIN users t ON t.id = g.teacher_id WHERE g.student_id = ? ORDER BY g.created_at DESC");
        $gstmt->execute([$selectedStudentId]);
    } else {
        $gstmt = $pdo->prepare("SELECT g.*, u.name as student_name, t.name as teacher_name FROM grades g LEFT JOIN users u ON u.id = g.student_id LEFT JOIN users t ON t.id = g.teacher_id WHERE g.student_id = ? AND g.teacher_id = ? ORDER BY g.created_at DESC");
        $gstmt->execute([$selectedStudentId, $userId]);
    }
} else {
    if ($userRole === 'admin') {
        $gstmt = $pdo->query("SELECT g.*, u.name as student_name, t.name as teacher_name FROM grades g LEFT JOIN users u ON u.id = g.student_id LEFT JOIN users t ON t.id = g.teacher_id ORDER BY g.created_at DESC");
    } else {
        $gstmt = $pdo->prepare("SELECT g.*, u.name as student_name, t.name as teacher_name FROM grades g LEFT JOIN users u ON u.id = g.student_id LEFT JOIN users t ON t.id = g.teacher_id WHERE g.teacher_id = ? ORDER BY g.created_at DESC");
        $gstmt->execute([$userId]);
    }
}
$grades = $gstmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/partials/header.php';
?>
<div class="dashboard-container">
    <div class="mb-4">
        <h1 class="display-6 mb-0">Manage Grades</h1>
        <p class="text-muted">Create, edit, and delete student grades</p>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Create / Update Grade</h5>
        </div>
        <div class="card-body">
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flashType; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($flash); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <!-- Student filter -->
            <form method="get" class="row g-2 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Filter by Student</label>
                    <select name="student_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- All Students --</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo ($s['id'] == $selectedStudentId) ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name']); ?> (<?php echo htmlspecialchars($s['email']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <a href="teacher_grades.php" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
            <form method="post">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Student</label>
                        <select name="student_id" class="form-select" required>
                            <option value="">Select student</option>
                            <?php foreach ($students as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo ($s['id'] == $selectedStudentId) ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name']) . ' (' . htmlspecialchars($s['email']) . ')'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
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
                    <div class="col-md-1">
                        <label class="form-label">Grade</label>
                        <input name="grade" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button class="btn btn-primary w-100" name="save_grade">Save Grade</button>
                    </div>
                </div>
                <div class="mt-2">
                    <label class="form-label">Remarks (optional)</label>
                    <input name="remarks" class="form-control">
                </div>
            </form>
        </div>
    </div>

    <!-- Grades Table -->
    <div class="card">
        <div class="card-header">
            <?php if ($selectedStudentId): ?>
                <h5 class="mb-0">Grades for <?php
                    $sname = '';
                    foreach ($students as $ss) { if ($ss['id'] == $selectedStudentId) { $sname = $ss['name']; break; } }
                    echo htmlspecialchars($sname ?: 'Selected Student');
                ?></h5>
            <?php else: ?>
                <h5 class="mb-0">Your Grades</h5>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead class="table-light"><tr>
                        <?php if (!$selectedStudentId): ?><th>Student</th><?php endif; ?>
                        <th>Subject</th><th>Schedule</th><th>Grade</th><th>Teacher</th><th>When</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($grades as $g): ?>
                        <tr>
                            <?php if (!$selectedStudentId): ?><td><?php echo htmlspecialchars($g['student_name']); ?></td><?php endif; ?>
                            <td><?php echo htmlspecialchars($g['subject']); ?></td>
                            <td><?php echo htmlspecialchars($g['schedule']); ?></td>
                            <td><?php echo htmlspecialchars($g['grade']); ?></td>
                            <td><?php echo htmlspecialchars($g['teacher_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($g['updated_at'] ?? $g['created_at']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editGradeModal" 
                                    onclick="setEditGrade(<?php echo $g['id']; ?>, '<?php echo htmlspecialchars($g['grade']); ?>', '<?php echo htmlspecialchars($g['remarks']); ?>')">Edit</button>
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

<!-- Edit Grade Modal -->
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
function setEditGrade(id, grade, remarks) {
    document.getElementById('gradeId').value = id;
    document.getElementById('gradeValue').value = grade;
    document.getElementById('remarksValue').value = remarks;
}
</script>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
