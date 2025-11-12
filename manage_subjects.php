<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/avatar_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

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

$flash = '';
$flashType = 'success';

// Handle Add Subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subject'])) {
    $name = trim($_POST['subject_name'] ?? '');
    $description = trim($_POST['subject_description'] ?? '');
    
    if ($name) {
        try {
            $stmt = $pdo->prepare('INSERT INTO subjects (name, description) VALUES (?, ?)');
            $stmt->execute([$name, $description]);
            $flash = 'Subject added successfully.';
        } catch (PDOException $e) {
            $flash = 'Subject already exists or error occurred.';
            $flashType = 'danger';
        }
    } else {
        $flash = 'Please provide a subject name.';
        $flashType = 'danger';
    }
}

// Handle Edit Subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_subject'])) {
    $id = (int)($_POST['subject_id'] ?? 0);
    $name = trim($_POST['subject_name'] ?? '');
    $description = trim($_POST['subject_description'] ?? '');
    
    if ($id && $name) {
        try {
            $stmt = $pdo->prepare('UPDATE subjects SET name = ?, description = ? WHERE id = ?');
            $stmt->execute([$name, $description, $id]);
            $flash = 'Subject updated successfully.';
        } catch (PDOException $e) {
            $flash = 'Error updating subject.';
            $flashType = 'danger';
        }
    }
}

// Handle Delete Subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_subject'])) {
    $id = (int)($_POST['subject_id'] ?? 0);
    
    if ($id) {
        try {
            // First delete related grades
            $pdo->prepare('DELETE FROM grades WHERE subject = (SELECT name FROM subjects WHERE id = ?)')->execute([$id]);
            // Then delete subject
            $pdo->prepare('DELETE FROM subjects WHERE id = ?')->execute([$id]);
            $flash = 'Subject and related grades deleted.';
        } catch (PDOException $e) {
            $flash = 'Error deleting subject.';
            $flashType = 'danger';
        }
    }
}

// Fetch all subjects
$stmt = $pdo->query("SELECT * FROM subjects ORDER BY created_at DESC");
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/partials/header.php';
?>
<div class="dashboard-container">
    <div class="mb-4">
        <h1 class="display-6 mb-0">Manage Subjects</h1>
        <p class="text-muted">Create, edit, and manage course subjects</p>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flashType; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($flash); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Add Subject Card -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Add New Subject</h5>
        </div>
        <div class="card-body">
            <form method="post">
                <div class="row g-2">
                    <div class="col-md-5">
                        <label class="form-label">Subject Name</label>
                        <input name="subject_name" class="form-control" placeholder="e.g., Anatomy & Physiology" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Description (optional)</label>
                        <input name="subject_description" class="form-control" placeholder="Brief description">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" name="add_subject" class="btn btn-primary w-100">
                            <i class="bi bi-plus-lg"></i> Add
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Subjects List -->
    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0">All Subjects</h5>
        </div>
        <div class="card-body">
            <?php if (empty($subjects)): ?>
                <p class="text-muted mb-0">No subjects yet. Add one above.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Subject Name</th>
                                <th>Description</th>
                                <th>Created</th>
                                <th style="width: 150px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjects as $sub): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($sub['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($sub['description'] ?? '-'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($sub['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" 
                                                data-bs-target="#editSubjectModal" 
                                                data-id="<?php echo $sub['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($sub['name']); ?>"
                                                data-description="<?php echo htmlspecialchars($sub['description'] ?? ''); ?>">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this subject and all related grades?');">
                                            <input type="hidden" name="subject_id" value="<?php echo $sub['id']; ?>">
                                            <button type="submit" name="delete_subject" class="btn btn-sm btn-danger">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Subject Modal -->
<div class="modal fade" id="editSubjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="subject_id" id="editSubjectId">
                    <div class="mb-3">
                        <label class="form-label">Subject Name</label>
                        <input name="subject_name" id="editSubjectName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input name="subject_description" id="editSubjectDescription" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_subject" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const editModal = document.getElementById('editSubjectModal');
if (editModal) {
    editModal.addEventListener('show.bs.modal', function(e) {
        const btn = e.relatedTarget;
        document.getElementById('editSubjectId').value = btn.dataset.id;
        document.getElementById('editSubjectName').value = btn.dataset.name;
        document.getElementById('editSubjectDescription').value = btn.dataset.description;
    });
}
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
