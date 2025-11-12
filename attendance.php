<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/avatar_helper.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$userId = (int)$_SESSION['user_id'];
$userEmail = $_SESSION['user_email'];
$userRole = $_SESSION['user_role'];

// load current user kind, schedule and status
$me = $pdo->prepare('SELECT kind, schedule, status FROM users WHERE id = ? LIMIT 1');
$me->execute([$userId]);
$me = $me->fetch();
$myKind = $me['kind'] ?? '';
$mySchedule = $me['schedule'] ?? 'Day';
$myStatus = $me['status'] ?? 'Ongoing';

// Handle account status restrictions
if ($myStatus === 'Graduated') {
    $_SESSION['flash'] = 'Your account has been marked as graduated. Attendance marking is no longer available.';
    header('Location: graduated.php');
    exit;
} elseif ($myStatus === 'Freeze') {
    $_SESSION['flash'] = 'Your account is currently frozen. Please contact your administrator.';
    header('Location: frozen.php');
    exit;
}

$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // accept in, out, or absent
    $type = in_array($_POST['type'] ?? '', ['in','out','absent']) ? $_POST['type'] : 'in';
    $note = trim(substr($_POST['note'] ?? '', 0, 255));
        $schedule = in_array($_POST['schedule'] ?? '', ['Day','Night','Weekend']) ? $_POST['schedule'] : 'Day';
    // handle optional attachment for 'absent' type
        $attachmentName = null;
        if (($type === 'absent' || isset($_FILES['attachment'])) && !empty($_FILES['attachment']['name'])) {
            $file = $_FILES['attachment'];
            if ($file['error'] === UPLOAD_ERR_OK) {
                // basic validation: allow only PDF up to 6MB
                $allowed = ['application/pdf'];
                if (in_array($file['type'], $allowed) && $file['size'] <= 6 * 1024 * 1024) {
                    $uploadsDir = __DIR__ . '/assets/uploads/absences';
                    if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $base = preg_replace('/[^a-z0-9\-_]/i', '_', pathinfo($file['name'], PATHINFO_FILENAME));
                    $saved = $base . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $dest = $uploadsDir . '/' . $saved;
                    if (move_uploaded_file($file['tmp_name'], $dest)) {
                        $attachmentName = $saved;
                    }
                }
            }
        }

        // handle optional image sent from face-capture (data URL)
        if (!empty($_POST['image'])) {
            $imgDataUrl = $_POST['image'];
            if (preg_match('/^data:image\/(png|jpeg);base64,/', $imgDataUrl)) {
                $parts = explode(',', $imgDataUrl, 2);
                $b64 = $parts[1] ?? '';
                $imgData = base64_decode($b64);
                if ($imgData !== false) {
                    $imgDir = __DIR__ . '/assets/uploads/attendance_images';
                    if (!is_dir($imgDir)) {
                        mkdir($imgDir, 0755, true);
                        // Create index.html to prevent directory listing
                        file_put_contents($imgDir . '/index.html', '');
                    }
                    $imgName = 'img_' . time() . '_' . bin2hex(random_bytes(4)) . '.png';
                    $imgPath = $imgDir . '/' . $imgName;
                    if (file_put_contents($imgPath, $imgData)) {
                        chmod($imgPath, 0644); // Ensure file is readable by web server
                        // append marker to note so we can render it later
                        $note = trim(substr($note, 0, 200)) . ' ||IMG::' . $imgName . '||';
                    }
                }
            }
        }

        // If we have an attachment, append a marker to the note so we can render a link later
        if ($attachmentName) {
            // keep reason length modest and append marker
            $note = trim(substr($note, 0, 220)) . ' ||ATTACH::' . $attachmentName . '||';
        }

        $stmt = $pdo->prepare('INSERT INTO attendance (user_id, type, note, schedule) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $type, $note, $schedule]);
    $flash = 'Attendance recorded: ' . htmlspecialchars($type);
}

// Fetch last 20 records for this user
$stmt = $pdo->prepare('SELECT * FROM attendance WHERE user_id = ? ORDER BY recorded_at DESC LIMIT 20');
$stmt->execute([$userId]);
$records = $stmt->fetchAll();

// If teacher, fetch their students for a quick view (filter attendance by teacher's schedule)
$myStudents = [];
if ($myKind === 'teacher') {
    // exclude graduated students from the active teacher list
    $s = $pdo->prepare("SELECT id, name, email FROM users WHERE teacher_id = ? AND kind = 'student' AND (status IS NULL OR status != 'Graduated') ORDER BY name");
    $s->execute([$userId]);
    $myStudents = $s->fetchAll();
    // for each student, fetch today's last IN, OUT, and ABSENT (and any attachment marker) for this schedule
    foreach ($myStudents as &$st) {
        $st['last_in'] = null;
        $st['last_out'] = null;
        $st['last_absent'] = null;
        $st['absent_note'] = null;
        $st['absent_attachment'] = null;
        $st['last_in_image'] = null;
        $st['last_out_image'] = null;
    // Include absences even if they were recorded under a different schedule
    $attS = $pdo->prepare("SELECT type, recorded_at, note FROM attendance WHERE user_id = ? AND DATE(recorded_at) = CURDATE() AND (schedule = ? OR type = 'absent') ORDER BY recorded_at DESC");
    $attS->execute([$st['id'], $mySchedule]);
        $rows = $attS->fetchAll();
        foreach ($rows as $row) {
            if ($st['last_in'] === null && $row['type'] === 'in') {
                $st['last_in'] = $row['recorded_at'];
                // parse image marker if present
                if (preg_match('/\|\|IMG::([^|]+)\|\|/', $row['note'] ?? '', $mimg)) {
                    $st['last_in_image'] = $mimg[1];
                }
            }
            if ($st['last_out'] === null && $row['type'] === 'out') {
                $st['last_out'] = $row['recorded_at'];
                if (preg_match('/\|\|IMG::([^|]+)\|\|/', $row['note'] ?? '', $mimg)) {
                    $st['last_out_image'] = $mimg[1];
                }
            }
            if ($st['last_absent'] === null && $row['type'] === 'absent') {
                $st['last_absent'] = $row['recorded_at'];
                $st['absent_note'] = $row['note'];
                // parse attachment marker if present
                if (preg_match('/\|\|ATTACH::([^|]+)\|\|/', $row['note'] ?? '', $m)) {
                    $st['absent_attachment'] = $m[1];
                }
            }
            if ($st['last_in'] !== null && $st['last_out'] !== null && $st['last_absent'] !== null) break;
        }
    }
    unset($st);
}
?>
<?php include __DIR__ . '/partials/header.php'; ?>

<div class="dashboard-container">
    <style>
        /* General Responsive Styles */
        .wrap {
            padding: clamp(1rem, 3vw, 2rem);
        }
        
        /* User Avatar & Info */
        .user-avatar { 
            width: clamp(40px, 5vw, 48px); 
            height: clamp(40px, 5vw, 48px); 
            border-radius: 50%; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            color: #fff; 
            font-weight: 700; 
            box-shadow: 0 3px 8px rgba(0,0,0,0.08); 
            text-transform: uppercase;
            flex-shrink: 0;
        }
        
        .userbar {
            flex-wrap: wrap;
            gap: 0.5rem !important;
        }
        
        /* Card & Stats Layouts */
        .stats-row { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); 
            gap: 1rem; 
            margin-bottom: 1rem; 
        }
        
        .stat-card { 
            background: #fff; 
            border: 1px solid #e9ecef; 
            padding: clamp(0.75rem, 2vw, 1.25rem); 
            border-radius: 8px; 
            box-shadow: 0 1px 4px rgba(0,0,0,0.02);
            height: 100%;
        } 

        /* Attendance Form */
        .attendance-form {
            background: #fff;
            padding: clamp(1rem, 2vw, 1.5rem);
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
        }

        .attendance-form .row {
            margin: -0.5rem;
        }

        .attendance-form [class*="col-"] {
            padding: 0.5rem;
        }

        .attendance-form .btn {
            width: 100%;
            margin-bottom: 0.5rem;
        }

        /* Table Responsiveness */
        .table-responsive {
            margin: 0 -1rem;
            padding: 0 1rem;
            width: calc(100% + 2rem);
        }

        .table {
            min-width: 800px;
        }

        .table th {
            white-space: nowrap;
            padding: 1rem;
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
        }

        /* Student List */
        .list-group-item {
            padding: 1rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
        }

        .list-group-item .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex: 1;
            min-width: 200px;
        }

        .list-group-item .text-end {
            flex: 0 0 auto;
            min-width: 160px;
        }

        /* Role Badges */
        .role-badge {
            white-space: nowrap;
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }

        /* Search Bars */
        .search-container {
            position: relative;
            margin-bottom: 1rem;
        }

        .form-control-sm {
            height: calc(1.5em + 0.75rem);
            padding: 0.25rem 0.5rem;
        }

        /* Face Authentication Section */
        .face-authentication {
            margin-top: 2rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 0.5rem;
        }

        #faceVideo {
            max-width: 100%;
            height: auto;
            border-radius: 0.5rem;
        }

        /* Responsive Breakpoints */
        @media (max-width: 768px) {
            .d-flex {
                flex-wrap: wrap;
            }
            
            .table-responsive {
                margin: 0 -0.5rem;
                padding: 0 0.5rem;
                width: calc(100% + 1rem);
            }

            .attendance-form .btn {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }

            .list-group-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .list-group-item .text-end {
                width: 100%;
                text-align: left !important;
            }

            .user-meta {
                font-size: 0.875rem;
            }
        }

        @media (max-width: 576px) {
            .attendance-form .col-auto { 
                width: 100%; 
            }
            
            .wrap {
                padding: 0.75rem;
            }

            h1, h2, h4 {
                font-size: 1.25rem;
                margin-bottom: 0.75rem;
            }

            .table td {
                padding: 0.75rem 0.5rem;
                font-size: 0.875rem;
            }
        }

        /* Print Styles */
        @media print {
            /* Print styles for long bond paper (8.5" x 13") */
            @page {
                size: 8.5in 13in;
                margin: 0.5in;
            }
            body {
                width: 100%;
                margin: 0;
                padding: 0;
                font-size: 10pt;
            }
            .wrap {
                width: 100%;
                padding: 0;
                margin: 0;
            }
            .table {
                font-size: 9pt;
                width: 100%;
                margin-bottom: 0.5in;
            }
            .table td, .table th {
                padding: 4px 8px;
            }
            /* Hide non-printable elements */
            .attendance-form, .face-authentication, #faceVideo, #faceStatus,
            .btn-outline-light, .btn-secondary, #studentSearch, #recordSearch,
            .face-authentication, video, .mt-3 h5, .mt-3 p {
                display: none !important;
            }
            /* Optimize student list for printing */
            .list-group-item {
                padding: 4px 8px;
                page-break-inside: avoid;
            }
            .user-avatar {
                width: 24px;
                height: 24px;
                box-shadow: none;
            }
            /* Ensure table headers repeat on each page */
            thead {
                display: table-header-group;
            }
            tr {
                page-break-inside: avoid;
            }
            /* Compress vertical spacing */
            .mb-3, .mb-2 {
                margin-bottom: 0.25in !important;
            }
            .card {
                border: none;
                padding: 0 !important;
                margin-bottom: 0.25in !important;
            }
            /* Make text more compact but still readable */
            .small {
                font-size: 8pt;
            }
            h1 { font-size: 16pt; margin-bottom: 0.15in; }
            h2 { font-size: 14pt; margin-bottom: 0.1in; }
            h4 { font-size: 12pt; margin-bottom: 0.1in; }
        }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1>Attendance</h1>
            <div class="d-flex align-items-center gap-2">
                <div class="userbar d-flex align-items-center gap-2">
                    <?php echo get_user_avatar([
                        'email' => $userEmail ?? $userId,
                        'name' => $_SESSION['user_name'] ?? $userEmail,
                        'profile_picture' => $_SESSION['profile_picture'] ?? ''
                    ], '', 'md'); ?>
                    <div>Logged in as <?php echo htmlspecialchars($userEmail); ?></div>
                </div>
                <?php if (!empty($userRole)): ?>
                    <?php if ($userRole === 'admin'): ?>
                        <span class="role-badge role-admin">Admin</span>
                    <?php elseif ($myKind === 'teacher'): ?>
                        <span class="role-badge role-teacher">Teacher</span>
                    <?php else: ?>
                        <span class="role-badge role-student">Student</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex gap-2">
            <?php if ($userRole === 'admin'): ?>
                <a class="btn btn-outline-light btn-sm" href="admin.php">Admin</a>
            <?php endif; ?>
            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#howToUseModal" aria-label="How to Use">
                <i class="bi bi-question-circle"></i>
            </button>
            <a class="btn btn-secondary btn-sm" href="logout.php">Logout</a>
        </div>
    </div>

    <!-- How to Use Modal -->
    <div class="modal fade" id="howToUseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-info-circle me-2"></i>
                        How to Use the Attendance System
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <!-- Login Card -->
                        <div class="col-md-12">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                                            <i class="bi bi-box-arrow-in-right text-primary fs-3"></i>
                                        </div>
                                        <h5 class="card-title mb-0">1. Log in to your account</h5>
                                    </div>
                                    <p class="card-text text-muted mb-0">
                                        Use your provided email and password to access the system.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Present Card -->
                        <div class="col-md-6">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                                            <i class="bi bi-clock-history text-success fs-3"></i>
                                        </div>
                                        <h5 class="card-title mb-0">2. For Present</h5>
                                    </div>
                                    <ul class="card-text text-muted mb-0 list-unstyled">
                                        <li class="mb-2">
                                            <i class="bi bi-check-circle text-success me-2"></i>
                                            Click "Clock In" at the start of your class session
                                        </li>
                                        <li class="mb-2">
                                            <i class="bi bi-check-circle text-success me-2"></i>
                                            After class, click "Time Out"
                                        </li>
                                        <li class="mb-2">
                                            <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                                            Failure to log out may result in half-day attendance
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Absent Card -->
                        <div class="col-md-6">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                                            <i class="bi bi-file-earmark-text text-warning fs-3"></i>
                                        </div>
                                        <h5 class="card-title mb-0">3. For Absent</h5>
                                    </div>
                                    <ul class="card-text text-muted mb-0 list-unstyled">
                                        <li class="mb-2">
                                            <i class="bi bi-file-pdf text-danger me-2"></i>
                                            Prepare your excuse letter in PDF format
                                        </li>
                                        <li class="mb-2">
                                            <i class="bi bi-upload text-primary me-2"></i>
                                            Click "Mark Absent" and upload your PDF file
                                        </li>
                                        <li class="mb-2">
                                            <i class="bi bi-info-circle text-info me-2"></i>
                                            Your teacher can view your uploaded document
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="flash"><?php echo $flash; ?></div>
    <?php endif; ?>

        <?php if ($myKind === 'teacher'): ?>
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-people-fill fs-4 text-primary"></i>
                            <div>
                                <h4 class="mb-0">My Students</h4>
                                <div class="text-muted small">Schedule: <?php echo htmlspecialchars($mySchedule); ?></div>
                            </div>
                        </div>
                        <div class="search-container">
                            <div class="input-group">
                                <span class="input-group-text border-0 bg-light">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input class="form-control border-0 bg-light" id="studentSearch" placeholder="Search students...">
                            </div>
                        </div>
                    </div>
                <?php if (empty($myStudents)): ?>
                    <p class="muted">No staudents assigned to you yet.</p>
                <?php else: ?>
                    <ul class="list-group">
                    <?php foreach ($myStudents as $st): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center student-item">
                            <div class="d-flex align-items-center gap-3">
                                <?php echo get_user_avatar($st, '', 'md'); ?>
                                <div>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($st['name']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($st['email']); ?></div>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="small text-muted">IN: <?php echo htmlspecialchars($st['last_in'] ?? '-'); ?></div>
                                <div class="small text-muted">OUT: <?php echo htmlspecialchars($st['last_out'] ?? '-'); ?></div>
                                    <?php if (!empty($st['last_in_image'])): ?>
                                        <?php $iurl = '/CHPCEBU-Attendance/assets/uploads/attendance_images/' . rawurlencode($st['last_in_image']); ?>
                                        <?php if (file_exists(__DIR__ . '/assets/uploads/attendance_images/' . $st['last_in_image'])): ?>
                                            <div class="small mt-1"><a href="<?php echo htmlspecialchars($iurl); ?>" target="_blank"><img src="<?php echo htmlspecialchars($iurl); ?>" style="height:40px;width:40px;border-radius:6px;object-fit:cover;border:1px solid #e9ecef"></a> <small class="text-muted">(Last IN photo)</small></div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($st['last_out_image'])): ?>
                                        <?php $ourl = '/CHPCEBU-Attendance/assets/uploads/attendance_images/' . rawurlencode($st['last_out_image']); ?>
                                        <?php if (file_exists(__DIR__ . '/assets/uploads/attendance_images/' . $st['last_out_image'])): ?>
                                            <div class="small mt-1"><a href="<?php echo htmlspecialchars($ourl); ?>" target="_blank"><img src="<?php echo htmlspecialchars($ourl); ?>" style="height:40px;width:40px;border-radius:6px;object-fit:cover;border:1px solid #e9ecef"></a> <small class="text-muted">(Last OUT photo)</small></div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php if (!empty($st['last_absent'])): ?>
                                    <div class="small text-warning">ABSENT: <?php echo htmlspecialchars($st['last_absent']); ?>
                                        <?php if (!empty($st['absent_attachment'])): ?>
                                            <?php $aurl = '/CHPCEBU-Attendance/assets/uploads/absences/' . rawurlencode($st['absent_attachment']); ?>
                                            <?php if (file_exists(__DIR__ . '/assets/uploads/absences/' . $st['absent_attachment'])): ?>
                                                <a href="<?php echo htmlspecialchars($aurl); ?>" download>(Download PDF)</a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-1"><a class="btn btn-sm btn-outline-primary" href="teacher_monthly.php?student_id=<?php echo $st['id']; ?>">Monthly</a></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="attendance-form">
                <!-- Alert for missing PDF -->
                <div class="alert alert-warning alert-dismissible fade d-none" role="alert" id="pdfAlert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Please upload your excuse letter (PDF file) before marking absent
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-md-auto">
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" name="type" value="absent" class="btn btn-warning flex-grow-1 attendance-btn" aria-label="Mark Absent">
                                <i class="bi bi-calendar-x"></i>
                            </button>
                        </div>
                        <input type="hidden" name="type" id="attendanceType">
                    </div>
                    <div class="col-12 col-md-auto">
                        <select name="schedule" class="form-select">
                            <option value="Day">Day Shift</option>
                            <option value="Night">Night Shift</option>
                            <option value="Weekend">Weekend Shift</option>
                        </select>
                    </div>
                    <div class="col-12 col-md">
                        <input class="form-control" type="text" name="note" placeholder="Note (optional)">
                    </div>
                    <div class="col-12">
                        <div class="input-group">
                            <span class="input-group-text bg-light">
                                <i class="bi bi-file-earmark-pdf text-danger"></i>
                            </span>
                            <div class="form-floating flex-grow-1">
                                <input type="file" name="attachment" accept="application/pdf" class="form-control" id="attachmentInput">
                                <label for="attachmentInput">Attach excuse letter (PDF only)</label>
                            </div>
                        </div>
                        <div class="form-text">Upload first before Mark Absent</div>
                    </div>
                </div>
            </form>
        <div class="mt-3">
            <h5>Face authentication</h5>
            <p class="muted">Use your camera to verify identity and clock in/out automatically.</p>
            <video id="faceVideo" width="320" height="240" autoplay muted style="border:1px solid #ddd;border-radius:6px"></video>
            <div id="faceStatus" class="mt-2 muted"></div>
            <div class="mt-2">
                <button id="faceInBtn" class="btn btn-success btn-sm" aria-label="Face Clock In"><i class="bi bi-person-check"></i></button>
                <button id="faceOutBtn" class="btn btn-danger btn-sm" aria-label="Face Clock Out"><i class="bi bi-person-x"></i></button>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="mb-0">Recent Records</h2>
        <div style="max-width:360px;width:100%;">
            <input id="recordSearch" class="form-control form-control-sm" placeholder="Search recent records...">
        </div>
    </div>
    <div class="table-responsive">
    <table class="table table-striped table-hover records">
        <thead class="table-light"><tr><th>#</th><th>Type</th><th>Schedule</th><th>Note</th><th>When</th></tr></thead>
        <tbody>
        <?php foreach ($records as $r): ?>
            <tr class="record-row">
                <td data-label="#"><?php echo htmlspecialchars($r['id']); ?></td>
                <td data-label="Type"><?php if ($r['type'] === 'in') { echo '<span class="badge-in">IN</span>'; } elseif ($r['type'] === 'out') { echo '<span class="badge-out">OUT</span>'; } else { echo '<span class="badge-absent">ABSENT</span>'; } ?></td>
                <td data-label="Schedule"><?php echo htmlspecialchars($r['schedule']); ?></td>
                <td data-label="Note">
                    <?php
                                $note = $r['note'] ?? '';
                                $attachmentHtml = '';
                                $imageHtml = '';
                                // attachments (PDF)
                                if (preg_match('/\|\|ATTACH::([^|]+)\|\|/', $note, $m)) {
                                    $fname = $m[1];
                                    $note = trim(str_replace($m[0], '', $note));
                                    $url = '/CHPCEBU-Attendance/assets/uploads/absences/' . rawurlencode($fname);
                                    if (file_exists(__DIR__ . '/assets/uploads/absences/' . $fname)) {
                                        $attachmentHtml = ' <a class="attachment-link" href="' . htmlspecialchars($url) . '" download><i class="bi bi-file-earmark-pdf-fill"></i> Download PDF</a>';
                                    }
                                }
                                // inline images saved during face capture
                                if (preg_match('/\|\|IMG::([^|]+)\|\|/', $note, $mi)) {
                                    $imgFile = $mi[1];
                                    $note = trim(str_replace($mi[0], '', $note));
                                    // Clean the filename and ensure it's safe
                                    $safeImgFile = basename(preg_replace('/[^a-zA-Z0-9_.-]/', '', $imgFile));
                                    $imgPath = __DIR__ . '/assets/uploads/attendance_images/' . $safeImgFile;
                                    if (file_exists($imgPath)) {
                                        $imgUrl = 'assets/uploads/attendance_images/' . rawurlencode($safeImgFile);
                                        $imageHtml = '<div class="mt-2"><a href="' . htmlspecialchars($imgUrl) . '" target="_blank" rel="noopener"><img src="' . htmlspecialchars($imgUrl) . '" class="attachment-thumb" alt="attendance image"></a></div>';
                                    }
                                }
                                echo nl2br(htmlspecialchars($note));
                                echo $attachmentHtml;
                                echo $imageHtml;
                    ?>
                </td>
                <td data-label="When"><?php echo htmlspecialchars($r['recorded_at']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script src="assets/js/face.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
    // Student search
    const studentSearch = document.getElementById('studentSearch');
    if (studentSearch) {
        const items = document.querySelectorAll('.student-item');
        studentSearch.addEventListener('input', function(){
            const q = this.value.toLowerCase();
            items.forEach(it => {
                it.style.display = it.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    // Records search
    const recordSearch = document.getElementById('recordSearch');
    if (recordSearch) {
        const rows = document.querySelectorAll('.record-row');
        recordSearch.addEventListener('input', function(){
            const q = this.value.toLowerCase();
            rows.forEach(r => {
                r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const status = document.getElementById('faceStatus');
    const videoSel = '#faceVideo';
    const faceInBtn = document.getElementById('faceInBtn');
    const faceOutBtn = document.getElementById('faceOutBtn');

    if (faceInBtn) {
        faceInBtn.addEventListener('click', function(){
            if (typeof FACE === 'undefined') {
                if (status) status.innerText = 'Face module not loaded.';
                return;
            }
            FACE.verifyCurrentUser(videoSel, '#faceStatus', function(){
                const schedSelect = document.querySelector('select[name="schedule"]');
                const schedVal = schedSelect ? schedSelect.value : 'Day';
                // capture snapshot from video
                try {
                    const video = document.querySelector(videoSel);
                    const canvas = document.createElement('canvas');
                    canvas.width = video.videoWidth || 320;
                    canvas.height = video.videoHeight || 240;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    const dataUrl = canvas.toDataURL('image/png');
                    const body = 'type=in&schedule=' + encodeURIComponent(schedVal) + '&image=' + encodeURIComponent(dataUrl);
                    fetch(window.location.href, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body})
                    .then(()=> location.reload());
                } catch (e) {
                    // fallback to sending without image
                    const body = 'type=in&schedule=' + encodeURIComponent(schedVal);
                    fetch(window.location.href, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body})
                    .then(()=> location.reload());
                }
            }, function(err){ if (status) status.innerText = 'Verification failed: ' + err; });
        });
    }

    if (faceOutBtn) {
        faceOutBtn.addEventListener('click', function(){
            if (typeof FACE === 'undefined') {
                if (status) status.innerText = 'Face module not loaded.';
                return;
            }
            FACE.verifyCurrentUser(videoSel, '#faceStatus', function(){
                const schedSelect = document.querySelector('select[name="schedule"]');
                const schedVal = schedSelect ? schedSelect.value : 'Day';
                try {
                    const video = document.querySelector(videoSel);
                    const canvas = document.createElement('canvas');
                    canvas.width = video.videoWidth || 320;
                    canvas.height = video.videoHeight || 240;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    const dataUrl = canvas.toDataURL('image/png');
                    const body = 'type=out&schedule=' + encodeURIComponent(schedVal) + '&image=' + encodeURIComponent(dataUrl);
                    fetch(window.location.href, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body})
                    .then(()=> location.reload());
                } catch (e) {
                    const body = 'type=out&schedule=' + encodeURIComponent(schedVal);
                    fetch(window.location.href, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body})
                    .then(()=> location.reload());
                }
            }, function(err){ if (status) status.innerText = 'Verification failed: ' + err; });
        });
    }
});
</script>

<script>
Array.from(document.querySelectorAll('iframe')).filter(f => {
  const s = f.getAttribute('sandbox') || '';
  return s.includes('allow-scripts') && s.includes('allow-same-origin');
}).forEach(f => console.log('Danger iframe:', f.src, f));
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.attendance-form');
    const fileInput = form.querySelector('input[name="attachment"]');
    const attendanceTypeInput = document.getElementById('attendanceType');
    const alertDiv = document.getElementById('pdfAlert');

    // Handle attendance button clicks
    document.querySelectorAll('.attendance-btn').forEach(button => {
        button.addEventListener('click', function() {
            const type = this.value;
            attendanceTypeInput.value = type;

            if (type === 'absent') {
                // Check for PDF file when marking absent
                if (!fileInput.files.length) {
                    alertDiv.classList.remove('d-none');
                    alertDiv.classList.add('show');
                    // Scroll to the alert
                    alertDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return; // Don't submit form
                }
                // Check if file is PDF
                if (fileInput.files[0].type !== 'application/pdf') {
                    alertDiv.classList.remove('d-none');
                    alertDiv.classList.add('show');
                    alertDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i> Please select a PDF file only <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                    return;
                }
            }
            // Submit the form
            form.submit();
        });
    });

    // Handle file selection
    fileInput.addEventListener('change', function() {
        const fileLabel = document.querySelector('label[for="attachmentInput"]');
        if (this.files.length) {
            if (this.files[0].type === 'application/pdf') {
                fileLabel.style.color = '#198754'; // Bootstrap success color
                fileLabel.innerHTML = '<i class="bi bi-check-circle me-1"></i>PDF ready to submit';
                // Hide alert if it was showing
                alertDiv.classList.remove('show');
                setTimeout(() => alertDiv.classList.add('d-none'), 150);
            } else {
                fileLabel.style.color = '#dc3545'; // Bootstrap danger color
                fileLabel.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>Please select a PDF file';
                this.value = ''; // Clear invalid file
            }
        } else {
            fileLabel.style.color = ''; // Reset color
            fileLabel.innerHTML = 'Attach excuse letter (PDF only)';
        }
    });

    // Hide alert when dismissed
    form.querySelector('.btn-close')?.addEventListener('click', () => {
        alertDiv.classList.remove('show');
        setTimeout(() => alertDiv.classList.add('d-none'), 150);
    });
});
</script>
