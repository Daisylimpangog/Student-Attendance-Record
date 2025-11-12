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

// Ensure grades table exists
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

// For students: show their own grades
// For teachers/admin: show specific student grades (via query param)
$viewingStudentId = $userId;
$viewingStudent = null;

if ($userRole === 'admin' || $userRole === 'teacher') {
    $viewingStudentId = (int)($_GET['student_id'] ?? $userId);
}

// Fetch the viewing student's info
$stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$viewingStudentId]);
$viewingStudent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$viewingStudent) {
    http_response_code(404);
    echo "Student not found";
    exit;
}

// Fetch all grades for this student
$gstmt = $pdo->prepare("SELECT g.*, t.name as teacher_name FROM grades g LEFT JOIN users t ON t.id = g.teacher_id WHERE g.student_id = ? ORDER BY g.schedule, g.subject");
$gstmt->execute([$viewingStudentId]);
$grades = $gstmt->fetchAll(PDO::FETCH_ASSOC);

// Group grades by schedule
$gradesBySchedule = ['Day' => [], 'Night' => [], 'Weekend' => []];
$subjectGrades = [];
foreach ($grades as $g) {
    $sch = $g['schedule'] ?? 'Day';
    if (isset($gradesBySchedule[$sch])) {
        $gradesBySchedule[$sch][] = $g;
        $subjectGrades[$sch . '_' . $g['subject']] = $g['grade'];
    }
}

include __DIR__ . '/partials/header.php';
?>
<style>
@media print {
    .no-print { display: none !important; }
    .dashboard-container { padding: 0 !important; }
    .card { page-break-inside: avoid; }
    body { background: white !important; }
    .btn { display: none; }
    h1, h5 { page-break-after: avoid; }
}
</style>
<div class="dashboard-container">
    <div class="mb-4 no-print">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1 class="display-6 mb-0">Grades</h1>
                <p class="text-muted">
                    <?php if ($viewingStudent['id'] === $userId): ?>
                        My Grades
                    <?php else: ?>
                        Grades for <?php echo htmlspecialchars($viewingStudent['name']); ?>
                    <?php endif; ?>
                </p>
            </div>
            <button class="btn btn-primary" onclick="window.print()">
                <i class="bi bi-printer"></i> Print Grades
            </button>
        </div>
    </div>

    <!-- Student Info Card -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <?php echo get_user_avatar($viewingStudent, '', 'lg'); ?>
                <div class="ms-3">
                    <h5 class="mb-1"><?php echo htmlspecialchars($viewingStudent['name']); ?></h5>
                    <p class="mb-0 text-muted"><?php echo htmlspecialchars($viewingStudent['email']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($grades)): ?>
        <div class="alert alert-info">No grades recorded yet.</div>
    <?php else: ?>
        <!-- Grades by Schedule -->
        <?php foreach (['Day', 'Night', 'Weekend'] as $schedule): ?>
            <?php if (!empty($gradesBySchedule[$schedule])): ?>
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><?php echo htmlspecialchars($schedule); ?> Schedule</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Subject</th>
                                        <th style="width: 100px;">Grade</th>
                                        <th style="width: 150px;">Teacher</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($gradesBySchedule[$schedule] as $g): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($g['subject']); ?></td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($g['grade']); ?></td>
                                        <td><?php echo htmlspecialchars($g['teacher_name'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($g['remarks'] ?? '-'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="mt-4 no-print">
        <a href="<?php echo $userRole === 'student' ? 'attendance.php' : 'admin.php'; ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
