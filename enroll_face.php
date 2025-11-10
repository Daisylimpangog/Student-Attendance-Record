<?php
// enroll_face.php - admin can enroll face for any user, users can enroll their own face
session_start();
require_once __DIR__ . '/db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (int)$_SESSION['user_id'];
// only admin or same user
if ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_id'] != $userId) {
    echo "Forbidden"; exit;
}

?>
<?php include __DIR__ . '/partials/header.php'; ?>

<div class="wrap">
    <h1>Enroll Face</h1>
    <p class="muted">Position your face in front of the camera and click <strong>Capture & Save</strong>. Models must be downloaded to <code>assets/models/</code>. See README for instructions.</p>

    <div class="row">
        <div class="col-md-6">
            <video id="video" width="480" height="360" autoplay muted></video>
            <canvas id="overlay" width="480" height="360" style="display:none"></canvas>
        </div>
        <div class="col-md-6">
            <div id="status" class="mb-2"></div>
            <button id="captureBtn" class="btn btn-primary">Capture & Save</button>
            <div id="result" class="mt-3"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <script src="assets/js/face.js"></script>
    <script>
        (function(){
            const userId = <?php echo json_encode($userId); ?>;
            FACE.initEnrollment(userId, '#video', '#status', '#captureBtn', '#result');
        })();
    </script>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
