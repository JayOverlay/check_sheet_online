<?php
/**
 * Admin Guard - Include AFTER header.php
 * Redirects non-admin users back to dashboard with an access denied message.
 */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo '<div class="container-fluid py-5">
            <div class="card card-premium text-center p-5">
                <div class="text-danger mb-3"><i class="fas fa-shield-alt fa-4x opacity-50"></i></div>
                <h4 class="fw-bold text-danger">Access Denied</h4>
                <p class="text-muted">คุณไม่มีสิทธิ์เข้าถึงหน้านี้ — เฉพาะ Admin เท่านั้น</p>
                <p class="text-muted small">You do not have permission to access this page. Admin only.</p>
                <a href="' . BASE_URL . 'index.php" class="btn btn-primary rounded-pill px-4 mt-3">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
          </div>';
    include __DIR__ . '/footer.php';
    exit();
}
?>