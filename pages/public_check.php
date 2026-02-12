<?php
require_once '../config/database.php';

// Fetch Machines for dropdown (only Active)
$machines = $pdo->query("SELECT * FROM machines WHERE status = 'Active'")->fetchAll();

// Check if form is loaded with both IDs
$formLoaded = isset($_GET['machine_id']) && isset($_GET['employee_id']);
$machineId = $_GET['machine_id'] ?? '';
$employeeId = $_GET['employee_id'] ?? '';
$machineInfo = null;
$checkItems = [];

// Check Machine Status immediately if scanned
$isInactive = false;
if ($machineId) {
    $stmt = $pdo->prepare("SELECT status FROM machines WHERE id = ?");
    $stmt->execute([$machineId]);
    $mStatus = $stmt->fetchColumn();
    if ($mStatus === 'Inactive') {
        $isInactive = true;
    }
}

if ($formLoaded && $machineId) {
    // Get machine info
    $stmt = $pdo->prepare("SELECT * FROM machines WHERE id = ?");
    $stmt->execute([$machineId]);
    $machineInfo = $stmt->fetch();

    // Get check items 
    $stmt = $pdo->prepare("
        SELECT ci.* 
        FROM check_items ci 
        INNER JOIN machine_check_items mci ON ci.id = mci.check_item_id 
        WHERE mci.machine_id = ?
        ORDER BY ci.category, ci.item_code ASC
    ");
    $stmt->execute([$machineId]);
    $checkItems = $stmt->fetchAll();

    // FALLBACK: If no specific items mapped, fetch ALL active items
    if (empty($checkItems)) {
        $stmt = $pdo->query("SELECT * FROM check_items ORDER BY category, item_code ASC");
        $checkItems = $stmt->fetchAll();
    }

    // Group items
    $groupedItems = [];
    foreach ($checkItems as $item) {
        $cat = ucfirst($item['category'] ?: 'Other');
        if (!isset($groupedItems[$cat]))
            $groupedItems[$cat] = [];
        $groupedItems[$cat][] = $item;
    }

    // Duplicate Check
    $isDuplicate = false;
    $duplicateMsg = "";
    $currentTime = date('H:i:s');
    $currentDate = date('Y-m-d');

    if ($currentTime >= '07:00:00' && $currentTime < '19:00:00') {
        $shiftLabel = "กะเช้า (Day Shift)";
        $shiftStart = $currentDate . ' 07:00:00';
        $shiftEnd = $currentDate . ' 18:59:59';
    } else {
        $shiftLabel = "กะกลางคืน (Night Shift)";
        if ($currentTime >= '19:00:00') {
            $shiftStart = $currentDate . ' 19:00:00';
            $shiftEnd = date('Y-m-d', strtotime('+1 day')) . ' 06:59:59';
        } else {
            $shiftStart = date('Y-m-d', strtotime('-1 day')) . ' 19:00:00';
            $shiftEnd = $currentDate . ' 06:59:59';
        }
    }

    $target_id = "m_" . $machineId;
    $dupCheck = $pdo->prepare("SELECT COUNT(*) FROM check_sheets WHERE target_id = ? AND created_at BETWEEN ? AND ?");
    $dupCheck->execute([$target_id, $shiftStart, $shiftEnd]);
    if ($dupCheck->fetchColumn() > 0) {
        $isDuplicate = true;
        $duplicateMsg = "เครื่องจักรนี้ถูกลงข้อมูลสำหรับ $shiftLabel เรียบร้อยแล้ว ไม่สามารถลงซ้ำได้ หากต้องการแก้ไขกรุณาติดต่อ Leader";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Check Sheet - Hana Project</title>
    <link href="<?php echo BASE_URL; ?>assets/libs/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/libs/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
            min-height: 100vh;
        }

        .card-premium {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>

<body class="py-5">
    <div class="container save-check-form">

        <?php if ($isInactive): ?>
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="alert alert-danger border-0 shadow-sm rounded-4 p-4 text-center my-4">
                        <div class="bg-danger bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                            style="width:80px; height:80px;">
                            <i class="fas fa-ban fa-3x text-danger"></i>
                        </div>
                        <h4 class="fw-bold text-danger">Machine Inactive</h4>
                        <p class="text-muted">เครื่องจักรนี้สถานะ Inactive ไม่สามารถทำการตรวจสอบได้</p>
                        <div class="mt-4">
                            <a href="scan.php" class="btn btn-outline-danger rounded-pill px-4">
                                <i class="fas fa-arrow-left me-2"></i> สแกน/เลือกเครื่องใหม่
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif (!$formLoaded): ?>
            <!-- Step 1: Input Employee ID -->
            <div class="row justify-content-center">
                <div class="col-xl-5 col-lg-6">
                    <div class="card card-premium">
                        <div class="card-body p-5">
                            <div class="text-center mb-4">
                                <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                    style="width:80px; height:80px;">
                                    <i class="fas fa-user-clock text-primary fa-2x"></i>
                                </div>
                                <h4 class="fw-bold">Login to Check</h4>
                                <p class="text-muted">Machine ID: <?php echo htmlspecialchars($machineId); ?></p>
                            </div>

                            <?php if (isset($_GET['error'])): ?>
                                <div class="alert alert-danger rounded-4 shadow-sm border-0 mb-4 text-center">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <?php echo htmlspecialchars($_GET['error']); ?>
                                </div>
                            <?php endif; ?>

                            <form method="GET" action="">
                                <input type="hidden" name="machine_id" value="<?php echo htmlspecialchars($machineId); ?>">
                                <div class="mb-4">
                                    <label class="form-label fw-bold small text-uppercase">Employee ID (รหัสพนักงาน)</label>
                                    <input type="text" class="form-control form-control-lg rounded-3 border-0 bg-light"
                                        name="employee_id" placeholder="Enter ID (e.g. EN1234)" required autofocus>
                                </div>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg rounded-pill fw-bold shadow-sm">
                                        Start Check
                                    </button>
                                    <a href="scan.php?machine_id=<?php echo htmlspecialchars($machineId); ?>"
                                        class="btn btn-light btn-lg rounded-pill text-muted">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Step 2: The Check Form -->
            <div class="row justify-content-center">
                <div class="col-xl-10">
                    <!-- Header Info -->
                    <div class="card card-premium mb-4">
                        <div class="card-body p-4">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h5 class="fw-bold mb-1">
                                        <i class="fas fa-industry text-primary me-2"></i>
                                        <?php echo htmlspecialchars($machineInfo['machine_code'] ?? 'N/A'); ?> -
                                        <?php echo htmlspecialchars($machineInfo['machine_name'] ?? 'Unknown Machine'); ?>
                                    </h5>
                                    <p class="text-muted mb-0 small">
                                        <i class="fas fa-user me-1"></i> Inspector:
                                        <strong><?php echo htmlspecialchars($employeeId); ?></strong>
                                        <span class="mx-2">|</span>
                                        <i class="fas fa-calendar me-1"></i> <?php echo date('d/m/Y H:i'); ?>
                                    </p>
                                </div>
                                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                    <a href="scan.php?machine_id=<?php echo $machineId; ?>"
                                        class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($isDuplicate): ?>
                        <div class="alert alert-warning border-0 shadow-sm rounded-4 p-4 mb-4 text-center">
                            <i class="fas fa-check-circle fa-3x text-warning mb-3"></i>
                            <h4 class="fw-bold text-warning">Already Checked</h4>
                            <p class="mb-0 text-muted"><?php echo $duplicateMsg; ?></p>
                            <a href="scan.php" class="btn btn-warning mt-3 rounded-pill text-white fw-bold">Back to Scan</a>
                        </div>
                    <?php else: ?>
                        <form action="../actions/public_save_check.php" method="POST" id="checkSheetForm">
                            <input type="hidden" name="machine_id" value="<?php echo $machineId; ?>">
                            <input type="hidden" name="employee_id" value="<?php echo $employeeId; ?>">

                            <?php foreach ($groupedItems as $category => $items): ?>
                                <div class="card card-premium mb-4">
                                    <div class="card-header bg-white border-0 py-3">
                                        <h6 class="fw-bold text-primary mb-0 text-uppercase"><i class="fas fa-tasks me-2"></i>
                                            <?php echo $category; ?> Checks</h6>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table align-middle mb-0">
                                                <thead class="bg-light">
                                                    <tr>
                                                        <th class="ps-4">Item</th>
                                                        <th class="text-center" style="width: 150px;">Status</th>
                                                        <th style="width: 250px;" class="pe-4">Memo</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($items as $item): ?>
                                                        <tr>
                                                            <td class="ps-4">
                                                                <div class="fw-bold text-dark"><?php echo $item['item_code']; ?></div>
                                                                <div class="small text-muted"><?php echo $item['name_en']; ?></div>
                                                                <div class="small text-secondary"><?php echo $item['name_th']; ?></div>
                                                            </td>
                                                            <td class="text-center">
                                                                <div class="btn-group w-100" role="group">
                                                                    <input type="radio" class="btn-check"
                                                                        name="result[<?php echo $item['id']; ?>]"
                                                                        id="ok_<?php echo $item['id']; ?>" value="OK" required checked>
                                                                    <label class="btn btn-outline-success btn-sm"
                                                                        for="ok_<?php echo $item['id']; ?>">OK</label>

                                                                    <input type="radio" class="btn-check"
                                                                        name="result[<?php echo $item['id']; ?>]"
                                                                        id="ng_<?php echo $item['id']; ?>" value="NG">
                                                                    <label class="btn btn-outline-danger btn-sm"
                                                                        for="ng_<?php echo $item['id']; ?>">NG</label>
                                                                </div>
                                                            </td>
                                                            <td class="pe-4">
                                                                <input type="text"
                                                                    class="form-control form-control-sm bg-light border-0"
                                                                    name="comment[<?php echo $item['id']; ?>]" placeholder="Remarks...">
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="bg-white rounded-4 shadow-sm p-4 mb-5">
                                <label class="form-label fw-bold">Overall Remarks (ความเห็นเพิ่มเติม)</label>
                                <textarea class="form-control bg-light border-0 rounded-3 mb-4" name="overall_remarks"
                                    rows="3"></textarea>

                                <button type="submit" class="btn btn-primary w-100 btn-lg rounded-pill fw-bold shadow-sm py-3">
                                    <i class="fas fa-save me-2"></i> Submit Check Sheet
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
    <script src="<?php echo BASE_URL; ?>assets/libs/bootstrap/bootstrap.bundle.min.js"></script>
</body>

</html>