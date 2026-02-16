<?php
require_once '../config/database.php';

$machineId = $_GET['machine_id'] ?? '';
$machineInfo = null;

if ($machineId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM machines WHERE id = ?");
        $stmt->execute([$machineId]);
        $machineInfo = $stmt->fetch();
    } catch (Exception $e) {
        $error = "Error fetching machine: " . $e->getMessage();
    }
}

// Handle success/error messages
$successMsg = '';
$errorMsg = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'check_completed')
        $successMsg = "บันทึกการตรวจสอบเรียบร้อยแล้ว";
    if ($_GET['success'] == 'downtime_reported')
        $successMsg = "แจ้งซ่อมเรียบร้อยแล้ว";
}
if (isset($_GET['error'])) {
    $errorMsg = htmlspecialchars($_GET['error']);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Machine - Hana Check Sheet</title>
    <!-- Bootstrap 5 CSS -->
    <link href="<?php echo BASE_URL; ?>assets/libs/bootstrap/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="<?php echo BASE_URL; ?>assets/libs/fontawesome/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="<?php echo BASE_URL; ?>assets/css/inter.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
            min-height: 100vh;
        }

        .card-option {
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .card-option:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1) !important;
        }

        .text-shadow {
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-xl-5">

                <?php if ($machineInfo): ?>

                    <!-- Machine Header -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                        <div class="card-body p-4 text-center">
                            <?php if (!empty($machineInfo['image_path'])): ?>
                                <img src="<?php echo BASE_URL . $machineInfo['image_path']; ?>"
                                    class="rounded-circle shadow mb-3 object-fit-cover"
                                    style="width: 100px; height: 100px; border: 4px solid white;">
                            <?php else: ?>
                                <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                    style="width: 100px; height: 100px;">
                                    <i class="fas fa-industry fa-3x text-primary"></i>
                                </div>
                            <?php endif; ?>

                            <h4 class="fw-bold mb-1">
                                <?php echo htmlspecialchars($machineInfo['machine_name']); ?>
                            </h4>
                            <div class="badge bg-light text-dark border px-3 py-2 rounded-pill mb-2">
                                <?php echo htmlspecialchars($machineInfo['machine_code']); ?>
                            </div>

                            <?php if ($machineInfo['status'] == 'Inactive'): ?>
                                <div
                                    class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger mt-3 mb-0 rounded-3">
                                    <i class="fas fa-ban me-1"></i> Machine Inactive
                                </div>
                            <?php elseif ($machineInfo['status'] == 'Maintenance'): ?>
                                <div
                                    class="alert alert-warning border-0 bg-warning bg-opacity-10 text-warning mt-3 mb-0 rounded-3">
                                    <i class="fas fa-tools me-1"></i> Under Maintenance
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Messages -->
                    <?php if ($successMsg): ?>
                        <div class="alert alert-success rounded-4 shadow-sm border-0 mb-4 text-center">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $successMsg; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($errorMsg): ?>
                        <div class="alert alert-danger rounded-4 shadow-sm border-0 mb-4 text-center">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo $errorMsg; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($machineInfo['status'] != 'Inactive'): ?>
                        <!-- Action Cards -->
                        <div class="row g-3">
                            <?php
                            // Fetch distinct categories for this machine
                            $stmt = $pdo->prepare("
                                SELECT DISTINCT ci.category 
                                FROM check_items ci 
                                INNER JOIN machine_check_items mci ON ci.id = mci.check_item_id 
                                WHERE mci.machine_id = ?
                            ");
                            $stmt->execute([$machineId]);
                            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

                            // Define Display Names and Icons for Categories
                            $categoryMap = [
                                'Safety' => ['name' => 'Safety', 'icon' => 'fa-shield-alt', 'color' => '#10b981'],
                                'Customer' => ['name' => 'Customer Requirement', 'icon' => 'fa-user-check', 'color' => '#f59e0b'],
                                'Machine' => ['name' => 'Machine', 'icon' => 'fa-cogs', 'color' => '#3b82f6'],
                                'Parameter' => ['name' => 'Parameter Check', 'icon' => 'fa-sliders-h', 'color' => '#8b5cf6'],
                                'Inspection' => ['name' => 'Visual Inspec', 'icon' => 'fa-search', 'color' => '#ec4899'],
                                'Tooling' => ['name' => 'Tooling Specific', 'icon' => 'fa-tools', 'color' => '#64748b'],
                                'Common' => ['name' => 'General Check', 'icon' => 'fa-clipboard-list', 'color' => '#6b7280']
                            ];

                            foreach ($categories as $cat):
                                $display = $categoryMap[$cat] ?? ['name' => $cat, 'icon' => 'fa-tasks', 'color' => '#4f46e5'];
                                ?>
                                <div class="col-6">
                                    <a href="public_check.php?machine_id=<?php echo $machineId; ?>&type=<?php echo urlencode($cat); ?>"
                                        class="text-decoration-none">
                                        <div class="card h-100 border-0 shadow-sm card-option"
                                            style="background: <?php echo $display['color']; ?>;">
                                            <div class="card-body p-4 text-center text-white">
                                                <div class="bg-white bg-opacity-25 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                                    style="width: 50px; height: 50px;">
                                                    <i class="fas <?php echo $display['icon']; ?> fa-lg"></i>
                                                </div>
                                                <h6 class="fw-bold mb-1" style="font-size: 0.9rem;">
                                                    <?php echo $display['name']; ?>
                                                </h6>
                                                <p class="small mb-0 opacity-75" style="font-size: 0.7rem;">ตรวจสอบหัวข้อนี้</p>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>

                            <!-- If no categories found, show default check -->
                            <?php if (empty($categories)): ?>
                                <div class="col-6">
                                    <a href="public_check.php?machine_id=<?php echo $machineId; ?>" class="text-decoration-none">
                                        <div class="card h-100 border-0 shadow-sm card-option"
                                            style="background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);">
                                            <div class="card-body p-4 text-center text-white">
                                                <div class="bg-white bg-opacity-25 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                                    style="width: 50px; height: 50px;">
                                                    <i class="fas fa-clipboard-check fa-lg"></i>
                                                </div>
                                                <h6 class="fw-bold mb-1">Check Machine</h6>
                                                <p class="small mb-0 opacity-75">ลงบันทึกประจำวัน</p>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php endif; ?>

                            <!-- Repair Notification (Always show) -->
                            <div class="col-6">
                                <a href="public_downtime.php?machine_id=<?php echo $machineId; ?>" class="text-decoration-none">
                                    <div class="card h-100 border-0 shadow-sm card-option"
                                        style="background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);">
                                        <div class="card-body p-4 text-center text-white">
                                            <div class="bg-white bg-opacity-25 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                                style="width: 50px; height: 50px;">
                                                <i class="fas fa-tools fa-lg"></i>
                                            </div>
                                            <h6 class="fw-bold mb-1">Repair</h6>
                                            <p class="small mb-0 opacity-75">แจ้งซ่อม/ปัญหา</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-qrcode fa-4x text-muted mb-3"></i>
                        <h4 class="fw-bold text-muted">Machine Not Found</h4>
                        <p class="text-muted">Please scan a valid machine QR code.</p>
                    </div>
                <?php endif; ?>

                <div class="text-center mt-5">
                    <p class="text-muted small">&copy;
                        <?php echo date('Y'); ?> Hana Project
                    </p>
                </div>

            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="<?php echo BASE_URL; ?>assets/libs/bootstrap/bootstrap.bundle.min.js"></script>
</body>

</html>