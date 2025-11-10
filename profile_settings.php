<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/avatar_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$flash = '';

// Fetch current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updates = [];
    $params = [];
    
    // Basic information updates
    if (!empty($_POST['name'])) {
        $updates[] = "name = ?";
        $params[] = trim($_POST['name']);
    }
    
    if (!empty($_POST['email'])) {
        // Check if email is already taken by another user
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $checkStmt->execute([trim($_POST['email']), $userId]);
        if ($checkStmt->rowCount() > 0) {
            $flash = '<div class="alert alert-danger">Email is already taken by another user.</div>';
        } else {
            $updates[] = "email = ?";
            $params[] = trim($_POST['email']);
        }
    }

    // Handle profile picture upload
    if (!empty($_FILES['profile_picture']['name'])) {
        $file = $_FILES['profile_picture'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg', 'image/png', 'image/gif'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            if (in_array($file['type'], $allowed) && $file['size'] <= $maxSize) {
                $uploadsDir = __DIR__ . '/assets/uploads/profile_pictures';
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0755, true);
                }

                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $newName = 'profile_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $uploadsDir . '/' . $newName;

                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    // Remove old profile picture if exists
                    if (!empty($user['profile_picture'])) {
                        $oldFile = $uploadsDir . '/' . $user['profile_picture'];
                        if (file_exists($oldFile)) {
                            unlink($oldFile);
                        }
                    }
                    $updates[] = "profile_picture = ?";
                    $params[] = $newName;
                }
            } else {
                $flash = '<div class="alert alert-danger">Invalid file type or size. Please upload a JPG, PNG, or GIF file under 5MB.</div>';
            }
        }
    }

    // Update phone if provided
    if (!empty($_POST['phone'])) {
        $updates[] = "phone = ?";
        $params[] = trim($_POST['phone']);
    }

    // Update user record if we have changes
    if (!empty($updates) && empty($flash)) {
        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
        try {
            $updateStmt = $pdo->prepare($sql);
            $updateStmt->execute($params);
            $flash = '<div class="alert alert-success">Profile updated successfully!</div>';
            
            // Refresh user data
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            $flash = '<div class="alert alert-danger">Error updating profile. Please try again.</div>';
        }
    }
}

// Check if profile_picture column exists
try {
    $check = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
    if ($check->rowCount() === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) NULL");
    }
} catch (PDOException $e) {
    // Column might already exist
}

// Ensure phone column exists
try {
    $check = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone'");
    if ($check->rowCount() === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL");
    }
} catch (PDOException $e) {
    // Column might already exist
}

include __DIR__ . '/partials/header.php';
?>

<div class="dashboard-container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h4 class="card-title mb-0">Profile Settings</h4>
                    <p class="text-muted small mb-0">Update your personal information and profile picture</p>
                </div>
                
                <div class="card-body">
                    <?php echo $flash; ?>
                    
                    <form method="post" enctype="multipart/form-data" class="profile-form">
                        <div class="profile-picture-section mb-4 text-center">
                            <div class="profile-picture-preview mx-auto mb-3">
                                <?php echo get_user_avatar($user, 'profile-avatar mx-auto', 'lg'); ?>
                            </div>
                            <div class="mb-3">
                                <label class="btn btn-outline-primary">
                                    <i class="bi bi-camera me-2"></i>Change Picture
                                    <input type="file" name="profile_picture" class="d-none" accept="image/jpeg,image/png,image/gif">
                                </label>
                                <div class="form-text">Supported formats: JPG, PNG, GIF (max. 5MB)</div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="name" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" name="phone" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                           placeholder="Enter phone number">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Role</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo htmlspecialchars(ucfirst($user['role'])); ?>" 
                                           readonly disabled>
                                </div>
                            </div>

                            <?php if ($user['kind'] === 'teacher'): ?>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Schedule</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['schedule'] ?? 'Not set'); ?>" 
                                           readonly disabled>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2 me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const profilePictureInput = document.querySelector('input[name="profile_picture"]');
    const previewContainer = document.querySelector('.profile-picture-preview');
    
    profilePictureInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                previewContainer.innerHTML = `
                    <img src="${e.target.result}" alt="Profile Picture Preview" class="profile-image">
                `;
            };
            
            reader.readAsDataURL(this.files[0]);
        }
    });
});
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>