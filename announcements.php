<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$success = '';
$error = '';

// Check if announcements table exists
$hasAnnouncementsTable = $pdo->query("SHOW TABLES LIKE 'announcements'")->rowCount() > 0;
if (!$hasAnnouncementsTable) {
    // Redirect to setup script
    header('Location: setup_announcements.php');
    exit;
}

// Handle form submission for new/edit announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $targetRole = in_array($_POST['target_role'], ['student', 'teacher', 'all']) ? $_POST['target_role'] : 'all';

        if ($_POST['action'] === 'create') {
            if ($title && $content) {
                try {
                    $stmt = $pdo->prepare('INSERT INTO announcements (title, content, created_by, target_role) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$title, $content, $userId, $targetRole]);
                    $success = 'Announcement created successfully!';
                } catch (PDOException $e) {
                    $error = 'Failed to create announcement.';
                }
            } else {
                $error = 'Title and content are required.';
            }
        } elseif ($_POST['action'] === 'delete' && !empty($_POST['id'])) {
            try {
                $stmt = $pdo->prepare('DELETE FROM announcements WHERE id = ? AND created_by = ?');
                $stmt->execute([(int)$_POST['id'], $userId]);
                $success = 'Announcement deleted successfully!';
            } catch (PDOException $e) {
                $error = 'Failed to delete announcement.';
            }
        } elseif ($_POST['action'] === 'toggle' && !empty($_POST['id'])) {
            try {
                $stmt = $pdo->prepare('UPDATE announcements SET is_active = NOT is_active WHERE id = ? AND created_by = ?');
                $stmt->execute([(int)$_POST['id'], $userId]);
                $success = 'Announcement status updated successfully!';
            } catch (PDOException $e) {
                $error = 'Failed to update announcement status.';
            }
        }
    }
}

// Fetch all announcements
try {
    $announcements = $pdo->query("
        SELECT a.*, u.name as creator_name 
        FROM announcements a 
        JOIN users u ON u.id = a.created_by 
        ORDER BY a.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $error = "Database error. Please ensure you've run the setup script first.";
    $announcements = [];
}

include __DIR__ . '/partials/header.php';
?>

<div class="wrap">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1>Manage Announcements</h1>
            <div class="userbar">Admin: <?php echo htmlspecialchars($_SESSION['user_email']); ?></div>
        </div>
        <div>
            <a class="btn btn-outline-secondary btn-sm" href="admin.php">Back to Admin</a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Create Announcement Form -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Create New Announcement</h5>
            <form method="post" class="row g-3">
                <input type="hidden" name="action" value="create">
                <div class="col-md-8">
                    <label class="form-label">Title</label>
                    <input type="text" class="form-control" name="title" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Target Audience</label>
                    <select class="form-select" name="target_role">
                        <option value="all">Everyone</option>
                        <option value="student">Students Only</option>
                        <option value="teacher">Teachers Only</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Content</label>
                    <textarea class="form-control" name="content" rows="3" required></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Create Announcement</button>
                </div>
            </form>
        </div>
    </div>

    <!-- List of Announcements -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">All Announcements</h5>
            <?php if (empty($announcements)): ?>
                <p class="text-muted">No announcements found.</p>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($announcements as $a): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-1"><?php echo htmlspecialchars($a['title']); ?></h6>
                                <small class="text-muted">
                                    <?php echo date('M j, Y g:i A', strtotime($a['created_at'])); ?>
                                </small>
                            </div>
                            <p class="mb-1"><?php echo nl2br(htmlspecialchars($a['content'])); ?></p>
                            <small class="text-muted">
                                By <?php echo htmlspecialchars($a['creator_name']); ?> |
                                For: <?php echo ucfirst(htmlspecialchars($a['target_role'])); ?> |
                                Status: <?php echo $a['is_active'] ? 'Active' : 'Inactive'; ?>
                            </small>
                            <div class="mt-2">
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-<?php echo $a['is_active'] ? 'warning' : 'success'; ?>">
                                        <?php echo $a['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                                <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>