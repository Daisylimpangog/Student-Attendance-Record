<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/avatar_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Ensure subjects table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(191) NOT NULL UNIQUE,
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {}

// Ensure grades table exists (same DDL as teacher page)
try {
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

// Handle flash messages
$flash = '';
$flashType = 'success';

// Handle Create New Grade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_grade'])) {
    $studentId = (int)($_POST['student_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $schedule = trim($_POST['schedule'] ?? 'Day');
    $grade = trim($_POST['grade'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $teacherId = (int)($_POST['teacher_id'] ?? 0);

    if ($studentId && $subject && $grade) {
        $in = $pdo->prepare("INSERT INTO grades (student_id, teacher_id, subject, schedule, grade, remarks)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE grade = VALUES(grade), remarks = VALUES(remarks), teacher_id = VALUES(teacher_id)");
        $in->execute([$studentId, $teacherId, $subject, $schedule, $grade, $remarks]);
        $flash = 'Grade created.';
    } else {
        $flash = 'Please provide student, subject, schedule, and grade.';
        $flashType = 'danger';
    }
}

// Handle Delete Grade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_grade'])) {
    $gradeId = (int)($_POST['grade_id'] ?? 0);
    if ($gradeId) {
        $pdo->prepare('DELETE FROM grades WHERE id = ?')->execute([$gradeId]);
        $flash = 'Grade deleted.';
    }
}

// Handle Edit Grade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_grade'])) {
    $gradeId = (int)($_POST['grade_id'] ?? 0);
    $grade = trim($_POST['grade'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    
    if ($gradeId && $grade) {
        $pdo->prepare('UPDATE grades SET grade = ?, remarks = ? WHERE id = ?')->execute([$grade, $remarks, $gradeId]);
        $flash = 'Grade updated.';
    }
}

// Fetch all students and teachers for dropdowns
$students = $pdo->query("SELECT id, name FROM users WHERE kind = 'student' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$teachers = $pdo->query("SELECT id, name FROM users WHERE kind = 'teacher' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch subjects
$subjectsStmt = $pdo->query("SELECT name FROM subjects ORDER BY name ASC");
$subjects = $subjectsStmt->fetchAll(PDO::FETCH_COLUMN);
if (empty($subjects)) {
    $subjects = ['Anatomy & Physiology', 'Pharmacology', 'Pathology', 'Microbiology', 'Biochemistry', 'Physiology'];
}

$schedules = ['Day', 'Night', 'Weekend'];

// Fetch all grades
$stmt = $pdo->query("SELECT g.*, u.name as student_name, u.email as student_email, t.name as teacher_name FROM grades g LEFT JOIN users u ON u.id = g.student_id LEFT JOIN users t ON t.id = g.teacher_id ORDER BY g.created_at DESC");
$grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/partials/header.php';
?>
<div class="dashboard-container">
    <?php if ($flash): ?>
    <div class="alert alert-<?php echo $flashType; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($flash); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Create New Grade Form -->
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">Create New Grade</h5>
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Student</label>
                    <select name="student_id" class="form-select" required>
                        <option value="">-- Select Student --</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Subject</label>
                    <select name="subject" class="form-select" required>
                        <option value="">-- Select --</option>
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
                    <input type="text" name="grade" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Teacher</label>
                    <select name="teacher_id" class="form-select">
                        <option value="">-- None --</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button class="btn btn-primary w-100" name="create_grade" type="submit">Create Grade</button>
                </div>
            </form>
            <div class="mt-2">
                <label class="form-label">Remarks (optional)</label>
                <input type="text" name="remarks" class="form-control" form="createGradeForm">
            </div>
        </div>
    </div>

    <!-- Grades Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">All Grades</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Subject</th>
                            <th>Schedule</th>
                            <th>Grade</th>
                            <th>Teacher</th>
                            <th>Remarks</th>
                            <th>When</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grades as $g): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($g['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($g['subject']); ?></td>
                            <td><?php echo htmlspecialchars($g['schedule']); ?></td>
                            <td><?php echo htmlspecialchars($g['grade']); ?></td>
                            <td><?php echo htmlspecialchars($g['teacher_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($g['remarks'] ?? '-'); ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($g['updated_at'] ?? $g['created_at']); ?></td>
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

<?php include __DIR__ . '/partials/footer.php'; ?>
