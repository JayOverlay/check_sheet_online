<?php
require_once '../config/database.php';
include '../includes/header.php';

// Fetch Machines for dropdown
$machines = $pdo->query("SELECT * FROM machines WHERE status = 'Active'")->fetchAll();

// Check if form is loaded
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

// DEBUG RAW
/*
echo "<pre style='background: white; padding: 20px; border: 2px solid red; position: relative; z-index: 9999;'>";
echo "Machine ID: $machineId <br>";
echo "Employee ID: $employeeId <br>";
echo "Form Loaded: " . ($formLoaded ? 'YES' : 'NO') . "<br>";
echo "</pre>";
*/

if ($formLoaded && $machineId) {
    // Get machine info
    $stmt = $pdo->prepare("SELECT * FROM machines WHERE id = ?");
    $stmt->execute([$machineId]);
    $machineInfo = $stmt->fetch();

    // Get check items assigned to this machine
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
    $isDefaultList = false;
    if (empty($checkItems)) {
        $stmt = $pdo->query("SELECT * FROM check_items ORDER BY category, item_code ASC");
        $checkItems = $stmt->fetchAll();
        $isDefaultList = true;
    }

    // Dynamic Grouping
    $groupedItems = [
        'Machine' => [],
        'Safety' => [],
        'Customer' => [],
        // Other categories will be added dynamically
    ];

    foreach ($checkItems as $item) {
        $cat = $item['category'] ?? 'Other';
        if (trim($cat) === '')
            $cat = 'Other'; // Handle empty string

        // Capitalize first letter just in case
        $cat = ucfirst($cat);

        if (!isset($groupedItems[$cat])) {
            $groupedItems[$cat] = [];
        }
        $groupedItems[$cat][] = $item;
    }

    // Uncomment for debugging
    /*
    echo "<div class='alert alert-info'>DEBUG: Found " . count($checkItems) . " items. <br>";
    foreach($groupedItems as $k => $v) echo "$k: " . count($v) . " items<br>";
    echo "</div>";
    */

    // Sort keys to ensure priority categories come first if needed.

    // Proactive Duplicate Check
    $isDuplicate = false;
    $duplicateMsg = "";

    $currentTime = date('H:i:s');
    $currentDate = date('Y-m-d');
    $shiftStart = '';
    $shiftEnd = '';
    $shiftLabel = '';

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

<?php if (!$formLoaded): ?>
    <!-- Step 1: Selection Form -->
    <div class="row justify-content-center">
        <div class="col-xl-6 col-lg-8">
            <div class="card card-premium">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                            style="width:80px; height:80px;">
                            <i class="fas fa-clipboard-check text-primary fa-2x"></i>
                        </div>
                        <h4 class="fw-bold">เริ่มต้นการตรวจสอบ</h4>
                        <p class="text-muted">เลือกเครื่องจักรและระบุรหัสพนักงาน</p>
                    </div>

                    <?php if (isset($isDuplicate) && $isDuplicate): ?>
                        <div class="alert alert-warning border-0 shadow-sm rounded-4 p-4 mb-4">
                            <div class="d-flex">
                                <i class="fas fa-exclamation-triangle fa-2x mt-1 me-3 text-warning"></i>
                                <div>
                                    <h5 class="fw-bold mb-1">ลงข้อมูลซ้ำ</h5>
                                    <p class="mb-0 text-muted"><?php echo $duplicateMsg; ?></p>
                                    <div class="mt-3">
                                        <a href="check_form.php" class="btn btn-sm btn-outline-warning rounded-pill px-3 me-2">
                                            <i class="fas fa-sync me-1"></i> เปลี่ยนเครื่อง
                                        </a>
                                        <a href="history.php" class="btn btn-sm btn-light rounded-pill px-3">
                                            <i class="fas fa-history me-1"></i> ดูประวัติ
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="GET" action="">
                        <?php if ($machineId): ?>
                            <!-- Scanned Mode: Machine is pre-identified -->
                            <input type="hidden" name="machine_id" value="<?php echo $machineId; ?>">
                            <div class="alert alert-primary d-flex align-items-center mb-4 rounded-4 border-0 shadow-sm p-4">
                                <?php
                                $scannedM = array_filter($machines, function ($m) use ($machineId) {
                                    return $m['id'] == $machineId;
                                });
                                $scannedM = reset($scannedM);
                                ?>

                                <?php if ($scannedM && !empty($scannedM['image_path'])): ?>
                                    <div class="me-3">
                                        <img src="<?php echo BASE_URL . $scannedM['image_path']; ?>" alt="Machine"
                                            class="rounded-circle shadow-sm object-fit-cover"
                                            style="width: 80px; height: 80px; border: 3px solid white;">
                                    </div>
                                <?php else: ?>
                                    <div class="bg-white rounded-circle p-3 me-3 text-primary shadow-sm"
                                        style="width: 80px; height: 80px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-industry fa-2x"></i>
                                    </div>
                                <?php endif; ?>

                                <div class="overflow-hidden">
                                    <div class="small fw-bold text-uppercase opacity-75">กำลังลงข้อมูลสำหรับ</div>
                                    <h4 class="fw-bold mb-0 text-truncate">
                                        <?php echo $scannedM ? ($scannedM['machine_code'] . ' - ' . $scannedM['machine_name']) : 'Machine ID: ' . $machineId; ?>
                                    </h4>
                                </div>
                            </div>

                            <?php if ($isInactive): ?>
                                <div class="alert alert-danger border-0 shadow-sm rounded-4 p-4 text-center my-4">
                                    <div class="bg-danger bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                        style="width:80px; height:80px;">
                                        <i class="fas fa-ban fa-3x text-danger"></i>
                                    </div>
                                    <h4 class="fw-bold text-danger">Machine Inactive</h4>
                                    <p class="text-muted">เครื่องจักรนี้สถานะ Non-Active ไม่สามารถทำการตรวจสอบหรือแจ้งซ่อมได้</p>
                                    <p class="small text-muted">กรุณาติดต่อผู้ดูแลระบบหากต้องการเปิดใช้งาน</p>
                                    <div class="mt-4">
                                        <a href="check_form.php" class="btn btn-outline-danger rounded-pill px-4">
                                            <i class="fas fa-arrow-left me-2"></i> สแกน/เลือกเครื่องใหม่
                                        </a>
                                    </div>
                                </div>
                            <?php elseif (!isset($_GET['action'])): ?>
                                <!-- MODE SELECTION CARDS -->
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <a href="?machine_id=<?php echo $machineId; ?>&action=check" class="text-decoration-none">
                                            <div class="card h-100 border-0 shadow-sm hover-shadow transition-all"
                                                style="background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);">
                                                <div class="card-body p-4 text-center text-white">
                                                    <div class="bg-white bg-opacity-25 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                                        style="width: 60px; height: 60px;">
                                                        <i class="fas fa-clipboard-check fa-2x"></i>
                                                    </div>
                                                    <h5 class="fw-bold mb-1">Check Sheet</h5>
                                                    <p class="small mb-0 opacity-75">บันทึกการตรวจสอบเครื่องจักร</p>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="downtime.php?report=1&machine_id=<?php echo $machineId; ?>"
                                            class="text-decoration-none">
                                            <div class="card h-100 border-0 shadow-sm hover-shadow transition-all"
                                                style="background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);">
                                                <div class="card-body p-4 text-center text-white">
                                                    <div class="bg-white bg-opacity-25 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                                        style="width: 60px; height: 60px;">
                                                        <i class="fas fa-tools fa-2x"></i>
                                                    </div>
                                                    <h5 class="fw-bold mb-1">แจ้งซ่อม</h5>
                                                    <p class="small mb-0 opacity-75">Report Downtime / Repair</p>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                                <div class="text-center">
                                    <a href="check_form.php" class="btn btn-link text-muted">ยกเลิก / สแกนใหม่</a>
                                </div>
                            <?php else: ?>
                                <!-- ACTION: CHECK - Show Employee Input -->
                                <input type="hidden" name="action" value="check">
                                <?php if (!$isDuplicate): ?>
                                    <div class="mb-4">
                                        <label class="form-label fw-bold"><i
                                                class="fas fa-id-badge me-2 text-primary"></i>รหัสพนักงาน</label>
                                        <input type="text" class="form-control form-control-lg rounded-3 shadow-sm border-0 bg-light"
                                            name="employee_id" placeholder="กรอกรหัสพนักงาน (EN Number)" required autofocus>
                                    </div>

                                    <div class="d-grid mt-5">
                                        <button type="submit" class="btn btn-primary btn-lg rounded-3 shadow-sm py-3 fw-bold">
                                            ถัดไป <i class="fas fa-chevron-right ms-2 small"></i>
                                        </button>
                                        <a href="?machine_id=<?php echo $machineId; ?>"
                                            class="btn btn-link mt-3 text-muted">ย้อนกลับ</a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                        <?php else: ?>
                            <!-- Standard Mode: Select Machine -->
                            <div class="mb-4">
                                <label class="form-label fw-bold"><i
                                        class="fas fa-industry me-2 text-primary"></i>เลือกเครื่องจักร</label>
                                <select class="form-select form-select-lg rounded-3 shadow-sm border-0 bg-light"
                                    name="machine_id" id="machineSelect" required>
                                    <option value="">-- เลือกเครื่องจักร --</option>
                                    <?php foreach ($machines as $m): ?>
                                        <option value="<?php echo $m['id']; ?>">
                                            <?php echo $m['machine_code'] . ' - ' . $m['machine_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Standard mode proceeds directly to check -->
                            <input type="hidden" name="action" value="check">
                            <div class="mb-4">
                                <label class="form-label fw-bold"><i
                                        class="fas fa-id-badge me-2 text-primary"></i>รหัสพนักงาน</label>
                                <input type="text" class="form-control form-control-lg rounded-3 shadow-sm border-0 bg-light"
                                    name="employee_id" placeholder="กรอกรหัสพนักงาน (EN Number)" required>
                            </div>

                            <div class="d-grid mt-5">
                                <button type="submit" class="btn btn-primary btn-lg rounded-3 shadow-sm py-3 fw-bold">
                                    ถัดไป <i class="fas fa-chevron-right ms-2 small"></i>
                                </button>
                            </div>
                        <?php endif; ?>

                        <?php // REMOVED PREVIOUS INPUT LOGIC HERE, MOVED INTO IF BLOCKS ABOVE ?>
                </div>
                </form>
            </div>
        </div>
    </div>
    </div>

<?php else: ?>
    <!-- Step 2: Check Form with Tabs -->
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <!-- Header Info -->
            <div class="card card-premium mb-4">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="fw-bold mb-1">
                                <i class="fas fa-industry text-primary me-2"></i>
                                <?php echo htmlspecialchars($machineInfo['machine_code'] ?? 'N/A'); ?> -
                                <?php echo htmlspecialchars($machineInfo['machine_name'] ?? 'Unknown Machine'); ?>
                            </h5>
                            <p class="text-muted mb-0">
                                <i class="fas fa-user me-1"></i> ผู้ตรวจสอบ:
                                <strong><?php echo htmlspecialchars($employeeId); ?></strong>
                                <span class="mx-2">|</span>
                                <i class="fas fa-calendar me-1"></i> <?php echo date('d/m/Y H:i'); ?>
                            </p>
                        </div>
                        <div class="col-md-6 text-md-end mt-3 mt-md-0">
                            <a href="check_form.php" class="btn btn-outline-secondary rounded-pill px-4">
                                <i class="fas fa-arrow-left me-2"></i> เปลี่ยนเครื่อง
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (empty($checkItems)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ไม่พบรายการตรวจสอบสำหรับเครื่องจักรนี้ กรุณาเพิ่มรายการตรวจสอบในหน้า <a href="machines.php">Machines</a>
                </div>
            <?php else: ?>

                <?php if ($isDuplicate): ?>
                    <div class="alert alert-warning border-0 shadow-sm rounded-4 p-4 mb-4">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                            <div>
                                <h5 class="fw-bold mb-1">กะนี้ลงข้อมูลไปแล้ว</h5>
                                <p class="mb-0 opacity-75"><?php echo $duplicateMsg; ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Check Form (DEBUG MODE: Single Table) -->
                <?php if (!$isDuplicate): ?>
                    <form action="<?php echo BASE_URL; ?>actions/save_check.php" method="POST" id="checkSheetForm">
                        <input type="hidden" name="machine_id" value="<?php echo $machineId; ?>">
                        <input type="hidden" name="employee_id" value="<?php echo $employeeId; ?>">

                        <div class="card card-premium">
                            <div class="card-body p-4">
                                <h5 class="fw-bold mb-3">All Check Items (<?php echo count($checkItems); ?> items)</h5>

                                <div class="table-responsive">
                                    <table class="table table-custom align-middle">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th>Code</th>
                                                <th style="width: 40%;">Check Item</th>
                                                <th class="text-center">Status</th>
                                                <th>Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($checkItems as $item): ?>
                                                <tr>
                                                    <td><span class="badge bg-secondary"><?php echo $item['category']; ?></span></td>
                                                    <td class="fw-bold text-primary"><?php echo $item['item_code']; ?></td>
                                                    <td>
                                                        <div class="fw-semibold"><?php echo $item['name_en']; ?></div>
                                                        <small class="text-muted"><?php echo $item['name_th']; ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex justify-content-center gap-3">
                                                            <div class="form-check form-check-inline">
                                                                <input class="form-check-input" type="radio"
                                                                    name="result[<?php echo $item['id']; ?>]"
                                                                    id="ok_<?php echo $item['id']; ?>" value="OK" required>
                                                                <label class="form-check-label text-success fw-bold"
                                                                    for="ok_<?php echo $item['id']; ?>">OK</label>
                                                            </div>
                                                            <div class="form-check form-check-inline">
                                                                <input class="form-check-input" type="radio"
                                                                    name="result[<?php echo $item['id']; ?>]"
                                                                    id="ng_<?php echo $item['id']; ?>" value="NG">
                                                                <label class="form-check-label text-danger fw-bold"
                                                                    for="ng_<?php echo $item['id']; ?>">NG</label>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm"
                                                            name="comment[<?php echo $item['id']; ?>]">
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex justify-content-end gap-3 mt-4">
                                    <button type="submit" class="btn btn-success px-5 py-2 rounded-pill fw-bold shadow">
                                        Save Check Sheet
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>



<?php include '../includes/footer.php'; ?>