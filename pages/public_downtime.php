<?php
require_once '../config/database.php';

$machineId = $_GET['machine_id'] ?? '';
$machineInfo = null;
$errorMsg = $_GET['error'] ?? '';

if ($machineId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM machines WHERE id = ?");
        $stmt->execute([$machineId]);
        $machineInfo = $stmt->fetch();
    } catch (Exception $e) {
        $errorMsg = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Repair - Hana Check Sheet</title>
    <link href="<?php echo BASE_URL; ?>assets/libs/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/libs/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
            min-height: 100vh;
        }
    </style>
</head>

<body>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-xl-5">

                <?php if ($machineInfo): ?>
                    <form action="../actions/public_save_downtime.php" method="POST">
                        <input type="hidden" name="target_id" value="m_<?php echo $machineId; ?>">

                        <div class="card border-0 shadow-sm rounded-4 mb-3">
                            <div class="card-body p-4 text-center">
                                <div class="bg-danger bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                    style="width: 60px; height: 60px;">
                                    <i class="fas fa-tools fa-2x text-danger"></i>
                                </div>
                                <h4 class="fw-bold mb-1">Report Issue</h4>
                                <p class="text-muted small">
                                    <?php echo htmlspecialchars($machineInfo['machine_code'] . ' - ' . $machineInfo['machine_name']); ?>
                                </p>
                            </div>
                        </div>

                        <?php if ($errorMsg): ?>
                            <div class="alert alert-danger rounded-4 shadow-sm border-0 mb-3">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($errorMsg); ?>
                            </div>
                        <?php endif; ?>

                        <div class="card border-0 shadow-sm rounded-4">
                            <div class="card-body p-4">
                                <div class="mb-3">
                                    <label class="form-label fw-bold small text-uppercase">Reported By (ชื่อผู้แจ้ง)</label>
                                    <input type="text" class="form-control form-control-lg bg-light border-0"
                                        name="reported_by" placeholder="Enter your name / ID" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold small text-uppercase">Category (หมวดหมู่)</label>
                                    <select class="form-select form-select-lg bg-light border-0" name="category" required>
                                        <option value="Mechanical">Mechanical</option>
                                        <option value="Electrical">Electrical</option>
                                        <option value="Software">Software</option>
                                        <option value="Process">Process</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-bold small text-uppercase">Problem Description
                                        (รายละเอียดปัญหา)</label>
                                    <textarea class="form-control form-control-lg bg-light border-0" name="problem" rows="4"
                                        placeholder="Describe what happened..." required></textarea>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit"
                                        class="btn btn-danger btn-lg rounded-pill shadow-sm fw-bold">Submit Report</button>
                                    <a href="scan.php?machine_id=<?php echo $machineId; ?>"
                                        class="btn btn-light btn-lg rounded-pill text-muted">Cancel</a>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h4>Machine Not Found</h4>
                        <a href="scan.php" class="btn btn-primary mt-3">Back to Scan</a>
                    </div>
                <?php endif; ?>

                <div class="text-center mt-4">
                    <p class="text-muted small">&copy;
                        <?php echo date('Y'); ?> Hana Project
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>assets/libs/bootstrap/bootstrap.bundle.min.js"></script>
</body>

</html>