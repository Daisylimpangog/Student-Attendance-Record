<?php
session_start();
require_once __DIR__ . '/db.php';
// Avatar helper for consistent avatar rendering (profile pictures + fallbacks)
require_once __DIR__ . '/includes/avatar_helper.php';
require_once __DIR__ . '/includes/avatar_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// Get total number of students
$totalStudentsStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE kind = 'student'");
$totalStudents = $totalStudentsStmt->fetchColumn();

// Get number of new students (registered in last 30 days)
$newStudentsStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE kind = 'student' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$newStudents = $newStudentsStmt->fetchColumn();

// Get number of inactive/graduated students (temporary until status column is added)
$graduatedStudents = 0; // We'll implement this properly once the status column is added

// Get total number of teachers
$totalTeachersStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE kind = 'teacher'");
$totalTeachers = $totalTeachersStmt->fetchColumn();

// Get average daily attendance for last 30 days
$avgAttendanceStmt = $pdo->query("
    SELECT COUNT(DISTINCT user_id) as daily_avg 
    FROM attendance 
    WHERE recorded_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    AND type = 'in'
    GROUP BY DATE(recorded_at)
");
$attendanceCounts = $avgAttendanceStmt->fetchAll(PDO::FETCH_COLUMN);
$avgDailyAttendance = count($attendanceCounts) > 0 ? round(array_sum($attendanceCounts) / count($attendanceCounts)) : 0;

// First ensure status column exists
try {
    $pdo->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT "Ongoing"');
} catch (PDOException $e) {
    // Column might already exist, continue
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $userId = (int)$_POST['user_id'];
    $newStatus = in_array($_POST['status'], ['Ongoing', 'Graduated', 'Freeze']) ? $_POST['status'] : 'Ongoing';
    $updateStmt = $pdo->prepare('UPDATE users SET status = ? WHERE id = ?');
    $updateStmt->execute([$newStatus, $userId]);
    if ($newStatus === 'Graduated') {
        $u = $pdo->prepare('UPDATE users SET status = ?, graduated_date = CURDATE() WHERE id = ? LIMIT 1');
        $u->execute([$newStatus, $userId]);
    } else {
        // If changing from Graduated to something else, clear graduated_date
        $u = $pdo->prepare('UPDATE users SET status = ?, graduated_date = NULL WHERE id = ? LIMIT 1');
        $u->execute([$newStatus, $userId]);
    }
    header('Location: admin.php');
    exit;
}

// Check if graduated_date column exists
$hasGraduatedDate = false;
try {
    $check = $pdo->query("SHOW COLUMNS FROM users LIKE 'graduated_date'");
    $hasGraduatedDate = ($check->rowCount() > 0);
} catch (PDOException $e) {
    // Column doesn't exist yet
}

// Add graduated_date column if it doesn't exist
if (!$hasGraduatedDate) {
    try {
        $pdo->exec('ALTER TABLE users ADD COLUMN graduated_date DATE NULL DEFAULT NULL');
        $pdo->exec("UPDATE users SET graduated_date = CURDATE() WHERE status = 'Graduated' AND graduated_date IS NULL");
        $hasGraduatedDate = true;
    } catch (PDOException $e) {
        // Failed to add column, we'll skip it in the query
    }
}

$hasProfilePicture = false;
try {
    $check = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
    $hasProfilePicture = ($check->rowCount() > 0);
} catch (PDOException $e) {
    // ignore
}

// Fetch all users with conditional graduated_date and profile_picture fields
$fields = 'id, email, name, role, kind, schedule, status, created_at' .
          ($hasGraduatedDate ? ', graduated_date' : '') .
          ($hasProfilePicture ? ', profile_picture' : '');
$stmt = $pdo->query("SELECT $fields FROM users ORDER BY id DESC");
$users = $stmt->fetchAll();

// Prepare statement to get latest attendance for today per user
$latestStmt = $pdo->prepare("SELECT type, recorded_at FROM attendance WHERE user_id = ? AND DATE(recorded_at) = CURDATE() ORDER BY recorded_at DESC LIMIT 1");

// classify today's status
$inUsers = [];
$outUsers = [];
$absentUsers = [];
$absentRecorded = [];

// Group teachers by schedule
$dayTeachers = [];
$nightTeachers = [];
$weekendTeachers = [];


foreach ($users as $u) {
    $latestStmt->execute([$u['id']]);
    $latest = $latestStmt->fetch();
    if (!$latest) {
        $absentUsers[] = $u;
    } else {
        if ($latest['type'] === 'in') {
            $inUsers[] = array_merge($u, ['last_type' => 'in', 'last_at' => $latest['recorded_at']]);
        } elseif ($latest['type'] === 'out') {
            $outUsers[] = array_merge($u, ['last_type' => 'out', 'last_at' => $latest['recorded_at']]);
        } elseif ($latest['type'] === 'absent') {
            $absentRecorded[] = array_merge($u, ['last_type' => 'absent', 'last_at' => $latest['recorded_at']]);
        } else {
            // unknown type, classify as out for now
            $outUsers[] = array_merge($u, ['last_type' => $latest['type'], 'last_at' => $latest['recorded_at']]);
        }
    }
    // group teachers by schedule
    if (($u['kind'] ?? '') === 'teacher') {
        $s = $u['schedule'] ?? 'Day';
        if ($s === 'Day') $dayTeachers[] = $u;
        elseif ($s === 'Night') $nightTeachers[] = $u;
        elseif ($s === 'Weekend') $weekendTeachers[] = $u;
        else $dayTeachers[] = $u;
    }
}

// Totals
$totalIn = count($inUsers);
$totalAbsent = count($absentUsers) + count($absentRecorded);

// Fetch recent attendance globally for admin view (include user name for avatar)
$att = $pdo->query("SELECT a.id, u.email, COALESCE(u.name, u.email) as name, a.type, a.note, a.recorded_at FROM attendance a JOIN users u ON u.id = a.user_id ORDER BY a.recorded_at DESC LIMIT 200");
$records = $att->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/dashboard.css" rel="stylesheet">
    <style>
            /* Search Styles */
            .search-container {
                position: relative;
            }
            .search-container .input-group {
                background: white;
                border-radius: 0.5rem;
                box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            }
            .search-container .form-control:focus {
                box-shadow: none;
            }
            .search-box {
                position: relative;
                margin-bottom: 1rem;
            }
            .search-box .form-control {
                padding-left: 2.5rem;
            }
            .search-box .bi-search {
                position: absolute;
                left: 1rem;
                top: 50%;
                transform: translateY(-50%);
                color: #6c757d;
            }
        .role-badge {
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
        }
        .role-admin { background: #ffc107; color: #000; }
        .role-student { background: #0dcaf0; color: #000; }
        /* Avatar styles (circular, consistent size) */
        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #fff;
            text-transform: uppercase;
            box-shadow: 0 4px 10px rgba(18, 38, 63, 0.08);
            flex-shrink: 0;
            font-size: 1.05rem;
            overflow: hidden;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <i class="bi bi-calendar-check fs-4 me-2"></i>
                Attendance System
            </a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active d-flex align-items-center" href="admin.php">
                            <i class="bi bi-speedometer2 me-2"></i>
                            Dashboard
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <div class="dropdown d-none d-md-block">
                        <button class="btn btn-link text-light text-decoration-none p-0" type="button" data-bs-toggle="dropdown">
                            <div class="d-flex align-items-center">
                                <?php
                                    // Fetch freshest user info so profile_picture changes are reflected immediately
                                    $meStmt = $pdo->prepare('SELECT id, email, name, profile_picture FROM users WHERE id = ? LIMIT 1');
                                    $meStmt->execute([$_SESSION['user_id']]);
                                    $currentUser = $meStmt->fetch() ?: [
                                        'email' => $_SESSION['user_email'] ?? '',
                                        'name' => $_SESSION['user_name'] ?? '',
                                        'profile_picture' => $_SESSION['profile_picture'] ?? ''
                                    ];
                                    echo get_user_avatar($currentUser, 'me-2');
                                ?>
                                <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email']); ?></span>
                                <i class="bi bi-chevron-down ms-2 opacity-75"></i>
                            </div>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                    <a href="logout.php" class="btn btn-light btn-sm d-md-none">Logout</a>
                </div>
            </div>
        </div>
    </nav>

<div class="dashboard-container">
    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="flash"><?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
    <?php endif; ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="display-6 mb-0">Admin Dashboard</h1>
            <p class="text-muted mb-0">Manage users and view statistics</p>
        </div>
        <div>
            <a class="btn btn-outline-secondary me-2" href="attendance.php">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <a class="btn btn-primary" href="add_user.php">
                <i class="bi bi-plus-lg"></i> Add User
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <!-- Theme Settings -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="card-title mb-1">Theme Settings</h5>
                    <p class="text-muted small mb-0">Customize your dashboard appearance</p>
                </div>
                <div class="theme-switch-wrapper">
                    <i class="bi bi-sun"></i>
                    <label class="theme-switch ms-2 me-2">
                        <input type="checkbox" id="themeSwitch" class="theme-input">
                        <div class="theme-slider round"></div>
                    </label>
                    <i class="bi bi-moon"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="stats-grid mb-4">
        <!-- Student Statistics -->
        <div class="stats-card">
            <div class="card h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-4">
                        <div class="stats-icon bg-primary bg-opacity-10 me-3">
                            <i class="bi bi-mortarboard text-primary"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <p class="text-muted text-sm mb-1">Total Students</p>
                            <h3 class="mb-0 fw-bold"><?php echo number_format($totalStudents); ?></h3>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php
                            $ongoingCount = $pdo->query("SELECT COUNT(*) FROM users WHERE kind = 'student' AND (status = 'Ongoing' OR status IS NULL)")->fetchColumn();
                            $graduatedCount = $pdo->query("SELECT COUNT(*) FROM users WHERE kind = 'student' AND status = 'Graduated'")->fetchColumn();
                            $freezeCount = $pdo->query("SELECT COUNT(*) FROM users WHERE kind = 'student' AND status = 'Freeze'")->fetchColumn();
                        ?>
                        <div class="stat-item">
                            <span class="d-flex align-items-center">
                                <i class="bi bi-circle-fill text-success me-2"></i>
                                <span class="text-sm fw-semibold">Ongoing</span>
                            </span>
                            <h5 class="mb-0 mt-1"><?php echo number_format($ongoingCount); ?></h5>
                        </div>
                        <div class="stat-item ms-4">
                            <span class="d-flex align-items-center">
                                <i class="bi bi-circle-fill text-info me-2"></i>
                                <span class="text-sm fw-semibold">Graduated</span>
                            </span>
                            <h5 class="mb-0 mt-1"><?php echo number_format($graduatedCount); ?></h5>
                        </div>
                        <div class="stat-item ms-4">
                            <span class="d-flex align-items-center">
                                <i class="bi bi-circle-fill text-warning me-2"></i>
                                <span class="text-sm fw-semibold">Freeze</span>
                            </span>
                            <h5 class="mb-0 mt-1"><?php echo number_format($freezeCount); ?></h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Teacher Count -->
        <div class="stats-card">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                            <i class="bi bi-person-workspace text-success fs-4"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <h6 class="card-title mb-0 text-muted text-truncate">Total Teachers</h6>
                            <h2 class="mb-0 fs-3"><?php echo number_format($totalTeachers); ?></h2>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 text-muted small-stats">
                        <span class="badge bg-primary bg-opacity-10 text-primary">Day: <?php echo count($dayTeachers); ?></span>
                        <span class="badge bg-info bg-opacity-10 text-info">Night: <?php echo count($nightTeachers); ?></span>
                        <span class="badge bg-warning bg-opacity-10 text-warning">Weekend: <?php echo count($weekendTeachers); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Attendance -->
        <div class="stats-card">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3">
                            <i class="bi bi-calendar-check text-info fs-4"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <h6 class="card-title mb-0 text-muted text-truncate">Today's Attendance</h6>
                            <h2 class="mb-0 fs-3"><?php echo $totalIn; ?></h2>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 text-muted small-stats">
                        <span class="badge bg-success bg-opacity-10 text-success">Present: <?php echo $totalIn; ?></span>
                        <span class="badge bg-warning bg-opacity-10 text-warning">Out: <?php echo count($outUsers); ?></span>
                        <span class="badge bg-danger bg-opacity-10 text-danger">Absent: <?php echo $totalAbsent; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Average Attendance -->
        <div class="stats-card">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                            <i class="bi bi-graph-up text-warning fs-4"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <h6 class="card-title mb-0 text-muted text-truncate">Avg. Daily Attendance</h6>
                            <h2 class="mb-0 fs-3"><?php echo $avgDailyAttendance; ?></h2>
                        </div>
                    </div>
                    <div class="d-flex text-muted small-stats">
                        <span class="badge bg-secondary bg-opacity-10 text-secondary">Based on last 30 days</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
            <h2 class="mb-0">Users</h2>
            <div class="d-flex gap-3 flex-grow-1 flex-md-grow-0">
                <div class="search-container flex-grow-1" style="max-width: 360px;">
                    <div class="input-group">
                        <span class="input-group-text border-0 bg-transparent">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" class="form-control border-0" id="userSearch" 
                               placeholder="Search users...">
                        <button type="button" class="btn btn-outline-secondary" id="clearUserSearch" title="Clear">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                <select class="form-select" id="scheduleFilter" style="width: auto;">
                    <option value="all">All Schedules</option>
                    <option value="Day">Day</option>
                    <option value="Night">Night</option>
                    <option value="Weekend">Weekend</option>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <h4>Day teachers</h4>
                <div class="mb-2">
                    <input class="form-control form-control-sm schedule-search" id="dayTeachersSearch" name="dayTeachersSearch" placeholder="Search day teachers...">
                </div>
                <ul class="list-group mb-3">
                    <?php foreach ($dayTeachers as $t): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div class="user-info">
                                <?php echo get_user_avatar($t, 'me-2'); // Add any classes you need ?>
                                <div class="user-meta">
                                    <div class="name"><?php echo htmlspecialchars($t['name']); ?></div>
                                    <div class="email"><?php echo htmlspecialchars($t['email']); ?></div>
                                </div>
                            </div>
                            <span class="badge bg-secondary">Day</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="col-md-4">
                <h4>Night teachers</h4>
                <div class="mb-2">
                    <input class="form-control form-control-sm schedule-search" id="nightTeachersSearch" name="nightTeachersSearch" placeholder="Search night teachers...">
                </div>
                <ul class="list-group mb-3">
                    <?php foreach ($nightTeachers as $t): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div class="user-info">
                                <?php echo get_user_avatar($t, 'me-2'); // Add any classes you need ?>
                                <div class="user-meta">
                                    <div class="name"><?php echo htmlspecialchars($t['name']); ?></div>
                                    <div class="email"><?php echo htmlspecialchars($t['email']); ?></div>
                                </div>
                            </div>
                            <span class="badge bg-dark">Night</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="col-md-4">
                <h4>Weekend teachers</h4>
                <div class="mb-2">
                    <input class="form-control form-control-sm schedule-search" id="weekendTeachersSearch" name="weekendTeachersSearch" placeholder="Search weekend teachers...">
                </div>
                <ul class="list-group mb-3">
                    <?php foreach ($weekendTeachers as $t): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div class="user-info">
                                <?php echo get_user_avatar($t, 'me-2'); // Add any classes you need ?>
                                <div class="user-meta">
                                    <div class="name"><?php echo htmlspecialchars($t['name']); ?></div>
                                    <div class="email"><?php echo htmlspecialchars($t['email']); ?></div>
                                </div>
                            </div>
                            <span class="badge bg-info text-dark">Weekend</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width: 60px">ID</th>
                        <th>User Information</th>
                        <th style="width: 120px">Kind</th>
                        <th style="width: 120px">Schedule</th>
                        <th style="width: 120px">Status</th>
                        <?php if ($hasGraduatedDate): ?>
                        <th style="width: 140px">Graduated Date</th>
                        <?php endif; ?>
                        <th style="width: 100px">Role</th>
                        <th style="width: 140px">Created</th>
                        <th class="text-end" style="width: 200px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td data-label="ID" class="text-center fw-bold text-muted"><?php echo $u['id']; ?></td>
                        <td data-label="User Information">
                            <div class="user-info">
                                <?php echo get_user_avatar($u, ($u['status'] ?? '') === 'Graduated' ? 'graduated' : (($u['status'] ?? '') === 'Freeze' ? 'frozen' : '')); ?>
                                <div class="user-meta">
                                    <div class="name"><?php echo htmlspecialchars($u['name']); ?></div>
                                    <div class="email"><?php echo htmlspecialchars($u['email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td data-label="Kind">
                            <?php if (($u['kind'] ?? '') === 'teacher'): ?>
                                <span class="badge bg-success">Teacher</span>
                            <?php else: ?>
                                <span class="badge bg-primary">Student</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Schedule">
                            <?php if (!empty($u['schedule'])): ?>
                                <span class="badge bg-info text-dark"><?php echo htmlspecialchars($u['schedule']); ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Status">
                            <?php if ($u['kind'] === 'student'): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="width:120px">
                                        <option value="Ongoing" <?php echo ($u['status'] ?? 'Ongoing') === 'Ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                        <option value="Graduated" <?php echo ($u['status'] ?? '') === 'Graduated' ? 'selected' : ''; ?>>Graduated</option>
                                        <option value="Freeze" <?php echo ($u['status'] ?? '') === 'Freeze' ? 'selected' : ''; ?>>Freeze</option>
                                    </select>
                                </form>
                            <?php endif; ?>
                        </td>
                        <?php if ($hasGraduatedDate): ?>
                        <td data-label="Graduated Date">
                            <?php if (($u['status'] ?? '') === 'Graduated' && isset($u['graduated_date']) && $u['graduated_date']): ?>
                                <?php echo date('F j, Y', strtotime($u['graduated_date'])); ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td data-label="Role">
                            <?php $r = $u['role']; ?>
                            <?php if ($r === 'admin'): ?>
                                <span class="role-badge role-admin">Admin</span>
                            <?php elseif ($r === 'user'): ?>
                                <span class="role-badge role-student">User</span>
                            <?php else: ?>
                                <span class="role-badge"><?php echo htmlspecialchars($r); ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Created">
                            <div class="d-flex flex-column">
                                <span><?php echo date('M d, Y', strtotime($u['created_at'])); ?></span>
                                <small class="text-muted"><?php echo date('h:i A', strtotime($u['created_at'])); ?></small>
                            </div>
                        </td>
                        <td data-label="Actions" class="text-end">
                            <div class="d-flex justify-content-end gap-2 actions-inline">
                                <div class="btn-group btn-group-sm" role="group">
                                    <a class="btn btn-outline-primary" href="edit_user.php?user_id=<?php echo $u['id']; ?>" title="Edit user">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a class="btn btn-outline-primary" href="enroll_face.php?user_id=<?php echo $u['id']; ?>" title="Enroll face">
                                        <i class="bi bi-camera"></i>
                                    </a>
                                    <a class="btn btn-outline-primary" href="change_password.php?user_id=<?php echo $u['id']; ?>" title="Change password">
                                        <i class="bi bi-key"></i>
                                    </a>
                                </div>
                                <form method="post" action="delete_user.php" class="d-inline" onsubmit="return confirm('Delete this user? This cannot be undone.');">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <button class="btn btn-sm btn-danger" type="submit" title="Delete user">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section>
        <h2>Recent Attendance</h2>
        <table class="table table-hover">
            <thead class="table-light"><tr><th>ID</th><th>User</th><th>Type</th><th>Note</th><th>When</th></tr></thead>
            <tbody>
            <?php foreach ($records as $r): ?>
                <tr>
                    <td><?php echo $r['id']; ?></td>
                    <td>
                        <div class="d-flex align-items-center">
                            <?php echo get_user_avatar($r, 'me-2 avatar-sm', 'sm'); ?>
                            <div>
                                <div class="fw-bold small mb-0"><?php echo htmlspecialchars($r['name']); ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars($r['email']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php
                            if ($r['type'] === 'in') {
                                echo '<span class="badge bg-success">IN</span>';
                            } elseif ($r['type'] === 'out') {
                                echo '<span class="badge bg-warning">OUT</span>';
                            } elseif ($r['type'] === 'absent') {
                                echo '<span class="badge bg-danger">ABSENT</span>';
                            } else {
                                echo '<span class="badge bg-secondary">' . htmlspecialchars($r['type']) . '</span>';
                            }
                        ?>
                    </td>
                    <td>
                        <?php
                        $note = trim($r['note'] ?? '');
                        if ($note === '') {
                            echo '<span class="text-muted">-</span>';
                        } else {
                            $filePattern = '/(?:\|\|IMG::)?(\S+\.(?:pdf|docx?|xlsx?|pptx?|zip|png|jpe?g|gif))/i';
                            if (preg_match($filePattern, $note, $m)) {
                                $found = $m[0];
                                $filename = $m[1]; // Just the filename without ||IMG:: prefix
                                
                                // Check if it's in our uploads directory
                                $uploadsPath = "assets/uploads/attendance_images/";
                                $path = $filename;
                                
                                // If it starts with ||IMG::, it's in our uploads
                                if (stripos($found, '||IMG::') === 0) {
                                    $path = $uploadsPath . $filename;
                                }
                                
                                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                                $isImage = in_array($ext, ['png','jpg','jpeg','gif']);
                                
                                if ($isImage) {
                                    $safe = htmlspecialchars($path);
                                    echo '<a href="' . $safe . '" target="_blank" rel="noopener"><img src="' . $safe . '" class="attachment-thumb" alt="attachment"></a>';
                                    $rest = trim(str_replace($found, '', $note));
                                    if ($rest !== '') echo '<div class="small text-muted mt-1">' . htmlspecialchars($rest) . '</div>';
                                } else {
                                    $safe = htmlspecialchars($path);
                                    echo '<div class="d-flex align-items-center">';
                                    echo '<i class="bi bi-paperclip me-2"></i>';
                                    echo "<a href=\"$safe\" target=\"_blank\" rel=\"noopener\">View attachment</a>";
                                    echo '</div>';
                                    $rest = trim(str_replace($found, '', $note));
                                    if ($rest !== '') echo '<div class="small text-muted mt-1">' . htmlspecialchars($rest) . '</div>';
                                }
                            } else {
                                echo nl2br(htmlspecialchars($note));
                            }
                        }
                        ?>
                    </td>
                    <td><?php echo $r['recorded_at']; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>

        </table>
    </div>

    <!-- Charts Section -->
    <div class="row g-4 mb-4">
        <!-- Attendance Trends -->
        <div class="col-md-8">
            <div class="card chart-card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h5 class="chart-title mb-4">
                        <i class="bi bi-graph-up-arrow me-2"></i>Attendance Trends
                        <small class="text-muted ms-2 fw-normal">Last 7 days</small>
                    </h5>
                    <div class="chart-container" style="position: relative; min-height: 300px;">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Distribution -->
        <div class="col-md-4">
            <div class="card chart-card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h5 class="chart-title mb-4">
                        <i class="bi bi-pie-chart-fill me-2"></i>Schedule Distribution
                    </h5>
                    <div class="chart-container" style="position: relative; min-height: 300px;">
                        <canvas id="distributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Activity Timeline -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card chart-card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h5 class="chart-title mb-4">
                        <i class="bi bi-activity me-2"></i>Today's Activity
                        <small class="text-muted ms-2 fw-normal"><?php echo date('F j, Y'); ?></small>
                    </h5>
                    <div class="chart-container" style="position: relative; min-height: 120px;">
                        <canvas id="timelineChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* Responsive container padding */
        .container {
            padding-left: max(15px, 4vw);
            padding-right: max(15px, 4vw);
        }

        /* Card styles */
        .chart-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6 !important;
            height: 100%;
            transition: transform 0.2s ease;
        }
        
        .chart-card:hover {
            transform: translateY(-5px);
        }

        .chart-title {
            color: #344767;
            font-weight: 600;
            font-size: clamp(1rem, 2.5vw, 1.25rem);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Stats cards responsiveness */
        .stats-card {
            min-width: 240px;
            flex: 1;
        }

        /* Chart containers */
        .chart-container {
            margin: 0 -10px;
        }

        /* Responsive text */
        .small-stats {
            font-size: clamp(0.75rem, 2vw, 0.875rem);
        }

        /* Card grids */
        .stats-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        }

        /* Responsive margins and paddings */
        @media (max-width: 768px) {
            .row {
                margin-left: -10px;
                margin-right: -10px;
            }
            .col-md-3, .col-md-4, .col-md-8, .col-12 {
                padding-left: 10px;
                padding-right: 10px;
            }
            .chart-card .card-body {
                padding: 1rem !important;
            }
            .chart-title small {
                display: block;
                margin-top: 0.5rem;
                margin-left: 0 !important;
            }
        }

        /* Table responsiveness */
        .table-responsive {
            margin: 0;
            padding: 0;
            border-radius: 0.5rem;
            overflow: hidden;
        }

        @media (max-width: 576px) {
            .btn-group-sm > .btn {
                padding: 0.25rem 0.5rem;
            }
            .table > :not(caption) > * > * {
                padding: 0.5rem;
            }
        }

        /* Improved scrollbar for responsive tables */
        .table-responsive::-webkit-scrollbar {
            height: 6px;
        }
        
        .table-responsive::-webkit-scrollbar-thumb {
            background-color: rgba(52, 71, 103, 0.2);
            border-radius: 3px;
        }
        
        .table-responsive::-webkit-scrollbar-track {
            background: rgba(52, 71, 103, 0.05);
        }
    </style>

    <script>
    // Helper function to get gradient
    function createGradient(ctx, startColor, endColor) {
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, startColor);
        gradient.addColorStop(1, endColor);
        return gradient;
    }

    // Common chart options
    const commonOptions = {
        plugins: {
            legend: {
                labels: {
                    color: '#344767',
                    font: {
                        weight: '600'
                    }
                }
            }
        }
    };

    // Attendance Trends Chart
    const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
    const attendanceGradient = createGradient(attendanceCtx, 'rgba(45, 206, 137, 0.4)', 'rgba(45, 206, 137, 0.1)');
    
    new Chart(attendanceCtx, {
        type: 'line',
        data: {
            labels: <?php 
                $days = [];
                $counts = [];
                $stmt = $pdo->query("
                    SELECT DATE(recorded_at) as date, COUNT(DISTINCT user_id) as count 
                    FROM attendance 
                    WHERE recorded_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    AND type = 'in'
                    GROUP BY DATE(recorded_at)
                    ORDER BY date ASC
                ");
                while($row = $stmt->fetch()) {
                    $days[] = date('M d', strtotime($row['date']));
                    $counts[] = $row['count'];
                }
                echo json_encode($days);
            ?>,
            datasets: [{
                label: 'Daily Attendance',
                data: <?php echo json_encode($counts); ?>,
                borderColor: '#2dce89',
                backgroundColor: attendanceGradient,
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#2dce89',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: {
            ...commonOptions,
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 2000,
                easing: 'easeInOutQuart'
            },
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#e9ecef',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#344767',
                        font: {
                            weight: '500'
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#344767',
                        font: {
                            weight: '500'
                        }
                    }
                }
            }
        }
    });

    // Schedule Distribution Chart
    const distributionCtx = document.getElementById('distributionChart').getContext('2d');
    new Chart(distributionCtx, {
        type: 'doughnut',
        data: {
            labels: ['Day', 'Night', 'Weekend'],
            datasets: [{
                data: [
                    <?php echo count($dayTeachers); ?>,
                    <?php echo count($nightTeachers); ?>,
                    <?php echo count($weekendTeachers); ?>
                ],
                backgroundColor: [
                    '#11cdef',
                    '#5e72e4',
                    '#fb6340'
                ],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            ...commonOptions,
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                animateRotate: true,
                animateScale: true,
                duration: 2000
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        color: '#344767',
                        font: {
                            size: 13,
                            weight: '600'
                        }
                    }
                }
            },
            cutout: '75%'
        }
    });

    // Activity Timeline
    const timelineCtx = document.getElementById('timelineChart').getContext('2d');
    const timelineGradient = createGradient(timelineCtx, 'rgba(94, 114, 228, 0.4)', 'rgba(94, 114, 228, 0.1)');
    
    new Chart(timelineCtx, {
        type: 'bar',
        data: {
            labels: <?php 
                $hours = [];
                $activity = [];
                $stmt = $pdo->query("
                    SELECT HOUR(recorded_at) as hour, COUNT(*) as count 
                    FROM attendance 
                    WHERE DATE(recorded_at) = CURDATE()
                    GROUP BY HOUR(recorded_at)
                    ORDER BY hour ASC
                ");
                while($row = $stmt->fetch()) {
                    $hours[] = date('ga', strtotime($row['hour'] . ':00'));
                    $activity[] = $row['count'];
                }
                echo json_encode($hours);
            ?>,
            datasets: [{
                label: 'Activity',
                data: <?php echo json_encode($activity); ?>,
                backgroundColor: timelineGradient,
                borderColor: '#5e72e4',
                borderWidth: 2,
                borderRadius: 6,
                maxBarThickness: 40
            }]
        },
        options: {
            ...commonOptions,
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 2000,
                easing: 'easeInOutQuart'
            },
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#e9ecef',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#344767',
                        font: {
                            weight: '500'
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#344767',
                        font: {
                            weight: '500'
                        }
                    }
                }
            }
        }
    });
    </script>

    <script>
    // Attach search listeners for the user table and schedule lists
    document.addEventListener('DOMContentLoaded', function() {
        const userSearch = document.getElementById('userSearch');
        const scheduleFilter = document.getElementById('scheduleFilter');
        const tableRows = document.querySelectorAll('table tbody tr');

        function filterUsers() {
            const term = userSearch ? userSearch.value.toLowerCase() : '';
            const sched = scheduleFilter ? scheduleFilter.value : 'all';

            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const scheduleCell = row.querySelector('td:nth-child(4)');
                const scheduleText = scheduleCell ? scheduleCell.textContent.trim() : '';

                const matchesTerm = term === '' || text.includes(term);
                const matchesSched = sched === 'all' || scheduleText === sched;

                row.style.display = (matchesTerm && matchesSched) ? '' : 'none';
            });
        }

        if (userSearch) userSearch.addEventListener('input', filterUsers);
        if (scheduleFilter) scheduleFilter.addEventListener('change', filterUsers);
        // clear button
        const clearUserSearch = document.getElementById('clearUserSearch');
        if (clearUserSearch) {
            clearUserSearch.addEventListener('click', function() {
                if (userSearch) userSearch.value = '';
                if (scheduleFilter) scheduleFilter.value = 'all';
                filterUsers();
            });
        }
        // Clear search button
        const clearBtn = document.getElementById('clearUserSearch');
        if (clearBtn && userSearch) {
            clearBtn.addEventListener('click', function() {
                userSearch.value = '';
                filterUsers();
            });
        }

        // Schedule section local searches (day/night/weekend lists)
        document.querySelectorAll('.schedule-search').forEach(input => {
            const wrapperCol = input.closest('.col-md-4');
            if (!wrapperCol) return;
            const list = wrapperCol.querySelector('.list-group');
            if (!list) return;
            const items = list.querySelectorAll('.list-group-item');

            input.addEventListener('input', function() {
                const q = this.value.toLowerCase();
                items.forEach(it => {
                    it.style.display = it.textContent.toLowerCase().includes(q) ? '' : 'none';
                });
            });
        });
    });
            input.addEventListener('input', function() {
                const q = this.value.toLowerCase();
                items.forEach(it => {
                    it.style.display = it.textContent.toLowerCase().includes(q) ? '' : 'none';                });            });        });    });    </script></body></html>