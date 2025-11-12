<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Small helper: deterministic gradient avatar background from a seed
if (!function_exists('avatar_style')) {
  function avatar_style($seed) {
    $h = hexdec(substr(md5((string)$seed), 0, 6)) % 360;
    $h2 = ($h + 30) % 360;
    return "linear-gradient(135deg, hsl($h,70%,52%), hsl($h2,65%,45%))";
  }
}

// fetch current user's kind, graduated count, and unread announcements when possible
$myKind = '';
$graduatedCount = 0;
$unreadAnnouncements = [];
$totalUnread = 0;

if (!empty($_SESSION['user_id'])) {
  // ensure $pdo is available
  if (!isset($pdo)) {
    @include_once __DIR__ . '/../db.php';
  }
  if (isset($pdo)) {
    $uS = $pdo->prepare('SELECT kind FROM users WHERE id = ? LIMIT 1');
    $uS->execute([$_SESSION['user_id']]);
    $uR = $uS->fetch();
    $myKind = $uR['kind'] ?? '';
    
    // Get graduated count for admin/teacher
    if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
      $c = $pdo->query("SELECT COUNT(*) FROM users WHERE kind = 'student' AND status = 'Graduated'");
      $graduatedCount = (int)$c->fetchColumn();
    } elseif ($myKind === 'teacher') {
      $c = $pdo->prepare("SELECT COUNT(*) FROM users WHERE kind = 'student' AND teacher_id = ? AND status = 'Graduated'");
      $c->execute([$_SESSION['user_id']]);
      $graduatedCount = (int)$c->fetchColumn();
    }

  // Get unread announcements if the table exists
  $unreadAnnouncements = [];
  $totalUnread = 0;

  try {
    // Check if announcements table exists
    $tableExists = $pdo->query("SHOW TABLES LIKE 'announcements'")->rowCount() > 0;
    if ($tableExists) {
      $stmt = $pdo->prepare("SELECT a.*
        FROM announcements a
        LEFT JOIN announcements_read ar ON ar.announcement_id = a.id AND ar.user_id = ?
        WHERE a.is_active = 1
        AND ar.read_at IS NULL
        AND (
          a.target_role = 'all'
          OR (a.target_role = ? AND ? IN ('student', 'teacher'))
        )
        ORDER BY a.created_at DESC
        LIMIT 5");
      $stmt->execute([$_SESSION['user_id'], $myKind, $myKind]);
      $unreadAnnouncements = $stmt->fetchAll();
      $totalUnread = count($unreadAnnouncements);
    }
  } catch (PDOException $e) {
    // Silently fail - worst case the announcements won't show
    $unreadAnnouncements = [];
    $totalUnread = 0;
  }
  }
}

// Mark announcement as read if requested
if (!empty($_POST['mark_read']) && !empty($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO announcements_read (announcement_id, user_id) VALUES (?, ?)");
        $stmt->execute([(int)$_POST['mark_read'], $_SESSION['user_id']]);
    } catch (PDOException $e) {
        // Silently fail - worst case the announcement stays unread
    }
}
?>
<!doctype html>
<html data-theme="light">
<head>
    <meta name="theme-color" content="#ffffff" id="theme-color">
    <script>
        // Prevent Flash Of Incorrect Theme (FOIT)
        (function() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
            const color = theme === 'dark' ? '#1a1f2e' : '#ffffff';
            document.getElementById('theme-color').setAttribute('content', color);
        })();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>CHP CEBU ATTENDANCE SYSTEM</title>
    <!-- Bootstrap CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="/CHPCEBU-Attendance/assets/css/style.css">
    <link rel="stylesheet" href="/CHPCEBU-Attendance/assets/css/avatars.css">
    <link rel="stylesheet" href="/CHPCEBU-Attendance/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="/CHPCEBU-Attendance/assets/js/theme.js"></script>
    <style>
  /* Minimal overrides to work with dashboard.css variables */
  body { font-family: 'Inter', sans-serif; background: var(--bg); }
  .navbar-modern { background: linear-gradient(135deg, var(--primary), var(--primary-600)); padding: .75rem 0; }
  .navbar-modern .navbar-brand { font-weight:600; color: #fff !important; }
.navbar-modern .nav-link { color: rgba(255,255,255,0.8) !important; }
.navbar-modern .nav-link:hover { color: #fff !important; }
.dropdown-item.active, .dropdown-item:active { background-color: var(--primary); }
  .navbar-modern .nav-link { color: rgba(255,255,255,.95) !important; font-weight:500; padding:.45rem .85rem; border-radius:.45rem; }
  .navbar-modern .nav-link:hover { background: rgba(255,255,255,0.06); }
  .dropdown-modern { border-radius: .5rem; box-shadow: var(--shadow-sm); }
  .notification-dropdown { min-width:320px !important; }
  .notification-header { background: transparent; padding:.85rem; border-bottom:1px solid #eef4ff; }
  .notification-body { max-height: 300px; overflow-y:auto; }
  .notification-item { padding:.85rem; border-bottom:1px solid #f1f6ff; }
  .notification-item:hover { background:#fbfdff; }
  .notification-dot { position:absolute; top:8px; right:8px; transform:translate(25%,-25%); }
  .announcement-content { max-height:300px; overflow-y:auto; }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-modern shadow-sm">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="/CHPCEBU-Attendance/">
      <i class="bi bi-calendar-check me-2"></i>
      CHP CEBU ATTENDANCE PORTAL
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navmenu" aria-controls="navmenu" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navmenu">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
        <?php if (!empty($_SESSION['user_email'])): ?>
          <?php if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
            <li class="nav-item me-2">
              <a class="nav-link" href="admin.php">Admin</a>
            </li>
            <li class="nav-item me-2">
              <a class="nav-link" href="announcements.php">Manage Announcements</a>
            </li>
            <li class="nav-item me-2">
              <a class="nav-link" href="admin_grades.php">Manage Grades</a>
            </li>
            <li class="nav-item me-2">
              <a class="nav-link" href="manage_subjects.php">Manage Subjects</a>
            </li>
          <?php endif; ?>
          <?php if (!empty($myKind) && $myKind === 'teacher'): ?>
            <li class="nav-item me-2">
              <a class="nav-link" href="attendance.php">Dashboard</a>
            </li>
            <li class="nav-item me-2">
              <a class="nav-link" href="teacher_grades.php">Manage Grades</a>
            </li>
          <?php elseif (!empty($myKind) && $myKind === 'student'): ?>
            <!-- Teachers show Manage Grades instead, so this is only for students -->
            <?php $isStudent = isset($myKind) && $myKind === 'student' ? true : false; ?>
            <?php if ($isStudent): ?>
            <li class="nav-item me-2">
              <a class="nav-link" href="student_grades.php">My Grades</a>
            </li>
            <?php endif; ?>
          <?php endif; ?>

          <!-- Notifications Dropdown -->
          <li class="nav-item dropdown me-2">
            <a class="nav-link position-relative" href="#" id="notificationsMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-bell-fill"></i>
              <?php if ($totalUnread > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-dot">
                  <?php echo $totalUnread; ?>
                </span>
              <?php endif; ?>
            </a>
            <div class="dropdown-menu dropdown-menu-end dropdown-modern notification-dropdown" aria-labelledby="notificationsMenu">
              <div class="notification-header">
                <h6 class="mb-0 fw-bold">Announcements</h6>
              </div>
              <div class="notification-body">
                <?php if (empty($unreadAnnouncements)): ?>
                  <p class="dropdown-item text-muted">No new announcements</p>
                <?php else: ?>
                  <?php foreach ($unreadAnnouncements as $announcement): ?>
                    <div class="dropdown-item">
                      <div class="d-flex justify-content-between align-items-center">
                        <strong class="text-truncate" style="max-width: 200px;">
                          <a href="#" class="text-decoration-none" data-bs-toggle="modal" 
                             data-bs-target="#announcementModal" 
                             data-title="<?php echo htmlspecialchars($announcement['title']); ?>"
                             data-content="<?php echo htmlspecialchars($announcement['content']); ?>"
                             data-date="<?php echo date('F j, Y g:i A', strtotime($announcement['created_at'])); ?>"
                             onclick="event.stopPropagation();">
                            <?php echo htmlspecialchars($announcement['title']); ?>
                          </a>
                        </strong>
                        <small class="text-muted ms-2"><?php echo date('M j', strtotime($announcement['created_at'])); ?></small>
                      </div>
                      <p class="mb-0 small text-muted"><?php echo htmlspecialchars(substr($announcement['content'], 0, 100)) . (strlen($announcement['content']) > 100 ? '...' : ''); ?></p>
                      <form method="post" class="mt-1">
                        <input type="hidden" name="mark_read" value="<?php echo $announcement['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-light w-100">Mark as Read</button>
                      </form>
                      <hr class="my-2">
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </li>

          <!-- User Menu Dropdown -->
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="me-2 small text-white-50"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Guest'); ?></span>
              <?php if (!empty($_SESSION['user_role'])): ?>
                <span class="badge bg-light text-primary small me-1"><?php echo htmlspecialchars(ucfirst($_SESSION['user_role'])); ?></span>
              <?php endif; ?>
            </a>
      <ul class="dropdown-menu dropdown-menu-end dropdown-modern" aria-labelledby="userMenu">
        <li class="px-3 py-2">
          <small class="text-muted">Signed in as</small><br>
          <strong class="text-dark"><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></strong>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
          <a class="dropdown-item d-flex align-items-center" href="profile_settings.php">
            <i class="bi bi-person-gear me-2"></i>
            Profile Settings
          </a>
        </li>
        <li><hr class="dropdown-divider"></li>
        <!-- Graduated Students visible to admin and teachers -->
      <?php if ((!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') || $myKind === 'teacher'): ?>
        <li>
          <a class="dropdown-item d-flex justify-content-between align-items-center" href="graduated_students.php">
            <span>Graduated Students</span>
            <?php if (!empty($graduatedCount)): ?>
              <span class="badge bg-warning text-dark ms-2"><?= htmlspecialchars($graduatedCount) ?></span>
            <?php endif; ?>
          </a>
        </li>
      <?php endif; ?>
      <li><a class="dropdown-item" href="change_password.php">Change Password</a></li>
      <li><a class="dropdown-item" href="logout.php">Logout</a></li>
      </ul>
        </li>
  <?php else: ?>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<main class="dashboard-container">

<!-- Announcement Modal -->
<div class="modal fade" id="announcementModal" tabindex="-1" aria-labelledby="announcementModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="announcementModalLabel"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="announcementContent"></p>
        <small class="text-muted" id="announcementDate"></small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Add Modal handling script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var announcementModal = document.getElementById('announcementModal');
    if (announcementModal) {
        announcementModal.addEventListener('show.bs.modal', function(event) {
            var trigger = event.relatedTarget;
            var title = trigger.getAttribute('data-title');
            var content = trigger.getAttribute('data-content');
            var date = trigger.getAttribute('data-date');
            
            var modalTitle = this.querySelector('.modal-title');
            var modalContent = this.querySelector('#announcementContent');
            var modalDate = this.querySelector('#announcementDate');
            
            modalTitle.textContent = title;
            modalContent.innerHTML = content.replace(/\n/g, '<br>');
            modalDate.textContent = 'Posted on ' + date;
        });
    }
});
</script>
