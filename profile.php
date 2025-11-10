<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/avatar_helper.php';

// Ensure optional columns exist (safe, idempotent). This avoids fatal errors when older DB
// schemas don't have phone/address fields. We run this before selecting/updating the user.
try {
    $optionalCols = [
        'phone' => "VARCHAR(50) NULL",
        'address' => "TEXT NULL"
    ];
    foreach ($optionalCols as $col => $type) {
        try {
            $check = $pdo->query("SHOW COLUMNS FROM users LIKE '$col'");
            if ($check && $check->rowCount() === 0) {
                // Add the column if it doesn't exist. Use a simple ALTER TABLE; older MySQL
                // versions may not support IF NOT EXISTS, so we check first.
                $pdo->exec("ALTER TABLE users ADD COLUMN `$col` $type");
            }
        } catch (PDOException $inner) {
            // If adding a column fails (permissions / engine), continue — the profile
            // page will gracefully degrade and won't try to write those fields.
        }
    }
} catch (PDOException $e) {
    // If the DB check itself fails for any reason, continue — we will avoid fatal errors
    // by guarding updates later.
}

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Fetch user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: index.php');
    exit;
}

// Fetch attendance statistics
$attendanceStats = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT DATE(recorded_at)) as total_days,
        COUNT(CASE WHEN type = 'in' THEN 1 END) as total_present,
        COUNT(CASE WHEN type = 'absent' THEN 1 END) as total_absent
    FROM attendance 
    WHERE user_id = ? 
    AND recorded_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
$attendanceStats->execute([$_SESSION['user_id']]);
$stats = $attendanceStats->fetch();

// Fetch recent attendance
$recentAttendance = $pdo->prepare("
    SELECT type, note, recorded_at 
    FROM attendance 
    WHERE user_id = ? 
    ORDER BY recorded_at DESC 
    LIMIT 10
");
$recentAttendance->execute([$_SESSION['user_id']]);
$attendance = $recentAttendance->fetchAll();

// Handle profile update
// Handle profile update — build SQL dynamically depending on which columns actually exist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (!empty($name) && !empty($email)) {
        // Determine which optional columns are present
        $colsToUpdate = ['name' => $name, 'email' => $email];
        try {
            $checkPhone = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone'");
            if ($checkPhone && $checkPhone->rowCount() > 0) {
                $colsToUpdate['phone'] = $phone;
            }
        } catch (PDOException $e) {
            // ignore
        }
        try {
            $checkAddress = $pdo->query("SHOW COLUMNS FROM users LIKE 'address'");
            if ($checkAddress && $checkAddress->rowCount() > 0) {
                $colsToUpdate['address'] = $address;
            }
        } catch (PDOException $e) {
            // ignore
        }

        // Build SQL dynamically
        $setParts = [];
        $params = [];
        foreach ($colsToUpdate as $col => $val) {
            $setParts[] = "$col = ?";
            $params[] = $val;
        }
        $params[] = $_SESSION['user_id'];

        $sql = "UPDATE users SET " . implode(', ', $setParts) . " WHERE id = ?";

        try {
            $updateStmt = $pdo->prepare($sql);
            $updateStmt->execute($params);
            $_SESSION['flash'] = 'Profile updated successfully!';
        } catch (PDOException $e) {
            // If update fails (e.g. permissions), show a friendly message and don't fatal.
            $_SESSION['flash'] = 'Profile update failed (database).';
        }

        header('Location: profile.php');
        exit;
    }
}

// Helper function for avatar style
function avatar_style($seed) {
    $hash = md5($seed);
    $h = hexdec(substr($hash, 0, 6)) % 360;
    $h2 = ($h + 30) % 360;
    return "linear-gradient(135deg, hsl($h,70%,52%), hsl($h2,65%,45%))";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/dashboard.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-modern">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center" href="<?php echo $user['role'] === 'admin' ? 'admin.php' : 'attendance.php'; ?>">
                <i class="bi bi-calendar-check fs-4 me-2"></i>
                Attendance System
            </a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $user['role'] === 'admin' ? 'admin.php' : 'attendance.php'; ?>">
                            <i class="bi bi-speedometer2 me-2"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="profile.php">
                            <i class="bi bi-person me-2"></i>
                            Profile
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <a href="logout.php" class="btn btn-light btn-sm">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="dashboard-container">
        <?php if (!empty($_SESSION['flash'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Profile Information -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <?php echo get_user_avatar($user, 'mx-auto mb-3 avatar-xl', 'xl'); ?>
                        <h4 class="mb-1"><?php echo htmlspecialchars($user['name']); ?></h4>
                        <p class="text-muted mb-3">
                            <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'warning' : ($user['kind'] === 'teacher' ? 'success' : 'primary'); ?>">
                                <?php echo ucfirst($user['kind'] ?? $user['role']); ?>
                            </span>
                            <?php if (!empty($user['schedule'])): ?>
                                <span class="badge bg-info"><?php echo htmlspecialchars($user['schedule']); ?></span>
                            <?php endif; ?>
                        </p>
                        <div class="d-flex justify-content-around mb-3">
                            <div class="text-center">
                                <h5 class="mb-0"><?php echo $stats['total_days'] ?? 0; ?></h5>
                                <small class="text-muted">Days Present</small>
                            </div>
                            <div class="text-center">
                                <h5 class="mb-0"><?php echo $stats['total_absent'] ?? 0; ?></h5>
                                <small class="text-muted">Days Absent</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Info -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-4">Contact Information</h5>
                        <div class="mb-3">
                            <label class="text-muted d-block">Email</label>
                            <div class="fw-medium"><?php echo htmlspecialchars($user['email']); ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted d-block">Phone</label>
                            <div class="fw-medium"><?php echo htmlspecialchars($user['phone'] ?? 'Not set'); ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted d-block">Address</label>
                            <div class="fw-medium"><?php echo htmlspecialchars($user['address'] ?? 'Not set'); ?></div>
                        </div>
                        <?php if ($user['kind'] === 'student'): ?>
                            <div class="mb-3">
                                <label class="text-muted d-block">Status</label>
                                <div class="fw-medium">
                                    <span class="badge bg-<?php 
                                        echo ($user['status'] ?? 'Ongoing') === 'Ongoing' ? 'success' : 
                                            (($user['status'] ?? '') === 'Graduated' ? 'info' : 'warning'); 
                                    ?>">
                                        <?php echo htmlspecialchars($user['status'] ?? 'Ongoing'); ?>
                                    </span>
                                </div>
                            </div>
                            <?php if (($user['status'] ?? '') === 'Graduated' && !empty($user['graduated_date'])): ?>
                                <div class="mb-3">
                                    <label class="text-muted d-block">Graduated Date</label>
                                    <div class="fw-medium">
                                        <?php echo date('F j, Y', strtotime($user['graduated_date'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Edit Profile -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-4">Edit Profile</h5>
                        <form action="profile.php" method="post">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" name="name" 
                                           value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" name="phone" 
                                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" name="address" rows="2"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        Update Profile
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Recent Attendance -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-4">Recent Attendance</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Type</th>
                                        <th>Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance as $record): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y', strtotime($record['recorded_at'])); ?></td>
                                            <td><?php echo date('g:i A', strtotime($record['recorded_at'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $record['type'] === 'in' ? 'success' : 
                                                        ($record['type'] === 'out' ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo strtoupper($record['type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($record['note'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($attendance)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">
                                                No attendance records found
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php if ($user['role'] === 'admin'): ?>
                <!-- Admin Statistics -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-4">System Statistics</h5>
                        <div class="row g-4">
                            <?php
                            $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                            $totalAttendance = $pdo->query("SELECT COUNT(*) FROM attendance WHERE DATE(recorded_at) = CURDATE()")->fetchColumn();
                            ?>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <div class="stats-icon bg-primary bg-opacity-10">
                                            <i class="bi bi-people text-primary"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-0">Total Users</h6>
                                        <h4 class="mb-0"><?php echo number_format($totalUsers); ?></h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <div class="stats-icon bg-success bg-opacity-10">
                                            <i class="bi bi-check-circle text-success"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-0">Today's Attendance</h6>
                                        <h4 class="mb-0"><?php echo number_format($totalAttendance); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($user['kind'] === 'teacher'): ?>
                <!-- Teacher Schedule -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-4">Teaching Schedule</h5>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Your current schedule: <strong><?php echo htmlspecialchars($user['schedule'] ?? 'Not set'); ?></strong>
                        </div>
                        <!-- Additional teacher-specific information can be added here -->
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($user['kind'] === 'student'): ?>
                <!-- Student Progress -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-4">Academic Progress</h5>
                        <div class="alert alert-<?php 
                            echo ($user['status'] ?? 'Ongoing') === 'Ongoing' ? 'success' : 
                                (($user['status'] ?? '') === 'Graduated' ? 'info' : 'warning'); 
                        ?>">
                            <i class="bi bi-info-circle me-2"></i>
                            Current Status: <strong><?php echo htmlspecialchars($user['status'] ?? 'Ongoing'); ?></strong>
                            <?php if (($user['status'] ?? '') === 'Graduated'): ?>
                                <br>
                                Graduated on: <strong><?php echo date('F j, Y', strtotime($user['graduated_date'])); ?></strong>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>