<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/avatar_helper.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'user';

// Only teachers (and admins viewing as teacher) can view this
$me = $pdo->prepare('SELECT id, kind, schedule FROM users WHERE id = ? LIMIT 1');
$me->execute([$userId]);
$me = $me->fetch();
if (!$me || ($me['kind'] ?? '') !== 'teacher') {
    // not a teacher
    header('Location: attendance.php');
    exit;
}
$mySchedule = $me['schedule'] ?? 'Day';

$studentId = (int)($_GET['student_id'] ?? 0);
if ($studentId <= 0) {
    // nothing selected
    header('Location: attendance.php');
    exit;
}

// verify student belongs to this teacher and get their status
$chk = $pdo->prepare('SELECT id, name, email, status FROM users WHERE id = ? AND teacher_id = ? AND kind = "student" LIMIT 1');
$chk->execute([$studentId, $userId]);
$student = $chk->fetch();
if (!$student) {
    header('Location: attendance.php');
    exit;
}

// month/year selection
$month = (int)($_GET['month'] ?? date('n'));
$year = (int)($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) $month = (int)date('n');
if ($year < 2000 || $year > 2100) $year = (int)date('Y');

$start = sprintf('%04d-%02d-01 00:00:00', $year, $month);
$end = date('Y-m-d H:i:s', strtotime('+1 month', strtotime($start)) - 1);

// Aggregate per-date first IN and last OUT for that student filtered by teacher schedule
$sql = "SELECT DATE(recorded_at) as day,
               MIN(CASE WHEN type='in' THEN recorded_at END) as first_in,
               MAX(CASE WHEN type='out' THEN recorded_at END) as last_out
        FROM attendance
        WHERE user_id = :uid
          AND schedule = :sched
          AND recorded_at BETWEEN :start AND :end
        GROUP BY day
        ORDER BY day ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':uid' => $studentId, ':sched' => $mySchedule, ':start' => $start, ':end' => $end]);
$rows = $stmt->fetchAll();

// produce a simple map day->row for days with records
$byDay = [];
foreach ($rows as $r) {
    $byDay[$r['day']] = $r;
}

// Fetch absences for the month (include regardless of schedule so teacher sees absent marks)
// Be forgiving: include rows where type='absent' OR the note contains the attachment marker (in case type wasn't set)
$absStmt = $pdo->prepare('SELECT DATE(recorded_at) as day, note FROM attendance WHERE user_id = :uid AND (type = "absent" OR note LIKE "%ATTACH::%") AND recorded_at BETWEEN :start AND :end ORDER BY recorded_at ASC');
$absStmt->execute([':uid' => $studentId, ':start' => $start, ':end' => $end]);
$absRows = $absStmt->fetchAll();
$absentByDay = [];
foreach ($absRows as $ar) {
    $aNote = $ar['note'] ?? '';
    $attachment = null;
    if (preg_match('/\|\|ATTACH::([^|]+)\|\|/', $aNote, $m)) {
        $attachment = $m[1];
        // remove marker from note for display
        $aNote = trim(str_replace($m[0], '', $aNote));
    }
    $absentByDay[$ar['day']] = ['note' => $aNote, 'attachment' => $attachment];
}

// prepare counts for summary (full days, half days, absent)
$days = (int)date('t', strtotime($start));
$fullDays = 0;
$halfDays = 0;
$absentCount = 0;
for ($d = 1; $d <= $days; $d++) {
    $dayStrCalc = sprintf('%04d-%02d-%02d', $year, $month, $d);
    $rowCalc = $byDay[$dayStrCalc] ?? null;
    $hasIn = $rowCalc && !empty($rowCalc['first_in']);
    $hasOut = $rowCalc && !empty($rowCalc['last_out']);
    $isAbsent = !empty($absentByDay[$dayStrCalc]);
    if ($isAbsent) {
        $absentCount++;
    } else {
        if ($hasIn && $hasOut) $fullDays++;
        elseif ($hasIn || $hasOut) $halfDays++;
    }
}
// present total counts
$presentTotal = $fullDays + $halfDays;

include __DIR__ . '/partials/header.php';
?>
<div class="wrap">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1>Monthly Attendance — <?php echo htmlspecialchars($student['name']); ?>
                <?php if (($student['status'] ?? '') === 'Graduated'): ?>
                    <span class="badge bg-success">Graduated</span>
                <?php elseif (($student['status'] ?? '') === 'Freeze'): ?>
                    <span class="badge bg-warning">Freeze</span>
                <?php endif; ?>
            </h1>
            <?php if (($student['status'] ?? '') === 'Graduated'): ?>
                <?php
                    // Calculate total attendance stats for graduated student
                    $statsStmt = $pdo->prepare("
                        SELECT 
                            COUNT(DISTINCT DATE(recorded_at)) as total_days,
                            COUNT(DISTINCT CASE WHEN type = 'in' THEN DATE(recorded_at) END) as days_present,
                            COUNT(DISTINCT CASE WHEN type = 'absent' THEN DATE(recorded_at) END) as days_absent
                        FROM attendance 
                        WHERE user_id = ?
                    ");
                    $statsStmt->execute([$studentId]);
                    $stats = $statsStmt->fetch();
                ?>
                <div class="alert alert-info mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <?php echo get_user_avatar($student, 'graduated me-3 avatar-lg', 'lg'); ?>
                        <h5 class="mb-0">Graduated Student Record</h5>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="card border-0 bg-light">
                                <div class="card-body p-3 text-center">
                                    <h3 class="mb-0"><?php echo number_format($stats['total_days'] ?? 0); ?></h3>
                                    <div class="text-muted small">Total Days Recorded</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 bg-success bg-opacity-10">
                                <div class="card-body p-3 text-center">
                                    <h3 class="mb-0 text-success"><?php echo number_format($stats['days_present'] ?? 0); ?></h3>
                                    <div class="text-muted small">Days Present</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 bg-warning bg-opacity-10">
                                <div class="card-body p-3 text-center">
                                    <h3 class="mb-0 text-warning"><?php echo number_format($stats['days_absent'] ?? 0); ?></h3>
                                    <div class="text-muted small">Days Absent</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="text-muted mt-3 mb-0">
                        <i class="bi bi-info-circle"></i> This is a final attendance record. No further modifications can be made.
                    </div>
                </div>
            <?php elseif (($student['status'] ?? '') === 'Freeze'): ?>
                <div class="alert alert-warning">
                    This student's account is currently frozen. Attendance marking is suspended.
                </div>
            <?php endif; ?>
            <div class="userbar">Schedule: <?php echo htmlspecialchars($mySchedule); ?></div>
        </div>
        <div>
            <a class="btn btn-outline-secondary btn-sm" href="attendance.php">Back</a>
            <?php
            // print/export button: open the same view in print mode
            $printUrl = htmlspecialchars('teacher_monthly.php?student_id=' . (int)$studentId . '&month=' . (int)$month . '&year=' . (int)$year . '&print=1');
            ?>
            <a class="btn btn-primary btn-sm ms-2" href="<?php echo $printUrl; ?>" target="_blank">Print / Export PDF</a>
        </div>
    </div>

    <form method="get" class="row g-2 align-items-center mb-3">
        <input type="hidden" name="student_id" value="<?php echo (int)$studentId; ?>">
        <div class="col-auto">
            <select name="month" class="form-select">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo ($m === $month) ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-auto">
            <input type="number" name="year" class="form-control" value="<?php echo (int)$year; ?>" min="2000" max="2100">
        </div>
        <div class="col-auto">
            <button class="btn btn-primary">Show</button>
        </div>
    </form>

    <h4><?php echo htmlspecialchars(date('F Y', strtotime($start))); ?></h4>
    <div class="mb-2">
        <strong>Summary:</strong>
        <span class="ms-2">Full days: <?php echo $fullDays; ?></span>
        <span class="ms-2">Half days: <?php echo $halfDays; ?></span>
        <span class="ms-2">Present (total): <?php echo $presentTotal; ?></span>
        <span class="ms-2">Absent: <?php echo $absentCount; ?></span>
    </div>
    <table class="table table-striped">
        <thead><tr><th>Date</th><th>First IN</th><th>Last OUT</th><th>Absent</th></tr></thead>
        <tbody>
        <?php
        // iterate through month days
        $days = (int)date('t', strtotime($start));
        for ($d = 1; $d <= $days; $d++):
            $dayStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $row = $byDay[$dayStr] ?? null;
        ?>
            <tr>
                <td><?php echo htmlspecialchars($dayStr); ?></td>
                <td><?php echo $row && $row['first_in'] ? htmlspecialchars($row['first_in']) : '-'; ?></td>
                <td><?php echo $row && $row['last_out'] ? htmlspecialchars($row['last_out']) : '-'; ?></td>
                <td>
                    <?php if (!empty($absentByDay[$dayStr])): ?>
                        <span class="badge-absent">ABSENT</span>
                        <?php if (!empty($absentByDay[$dayStr]['note'])): ?>
                            <div class="small text-muted"><?php echo nl2br(htmlspecialchars($absentByDay[$dayStr]['note'])); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($absentByDay[$dayStr]['attachment'])):
                            $fname = $absentByDay[$dayStr]['attachment'];
                            $aurl = '/CHPCEBU-Attendance/assets/uploads/absences/' . rawurlencode($fname);
                            if (file_exists(__DIR__ . '/assets/uploads/absences/' . $fname)): ?>
                                <div class="mt-1"><a href="<?php echo htmlspecialchars($aurl); ?>" target="_blank">(View letter)</a></div>
                        <?php endif; endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endfor; ?>
        </tbody>
    </table>
</div>

<?php if (!empty($_GET['print'])): ?>
    <script>
        // auto-print in the popup window
        window.addEventListener('load', function(){
            setTimeout(function(){ window.print(); }, 300);
        });
    </script>
    <style>
        /* Tidy printable output */
        @media print {
            body { font-size: 12px; }
            .btn { display: none !important; }
        }
    </style>
<?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>

