<?php
require_once '../config/database.php';
include '../includes/header.php';

// Fetch Machines for dropdown
$machines = $pdo->query("SELECT * FROM machines WHERE status = 'Active'")->fetchAll();

// Check if form is loaded
$formLoaded = isset($_GET['machine_id']) && isset($_GET['employee_id']);
$machineId = $_GET['machine_id'] ?? '';
$employeeId = $_GET['employee_id'] ?? '';
$type = $_GET['type'] ?? '';
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

// Validate Employee ID if provided
$userError = '';
if ($formLoaded && $employeeId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND status = 'Active'");
    $stmt->execute([$employeeId]);
    if ($stmt->fetchColumn() == 0) {
        $formLoaded = false; // Prevent loading the form
        $userError = "รหัสพนักงานนี้ ($employeeId) ไม่มีในระบบ หรือสถานะไม่ Active";
        // Do not clear $employeeId so the user can see what they typed, or you can clear it.
        // Let's keep it but maybe it's better to clear it from the form loaded state logic
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
    // $type defined at top level now
    if ($type) {
        $stmt = $pdo->prepare("
            SELECT ci.*, mci.frequency 
            FROM check_items ci 
            INNER JOIN machine_check_items mci ON ci.id = mci.check_item_id 
            WHERE mci.machine_id = ? AND ci.category = ?
            ORDER BY ci.item_code ASC
        ");
        $stmt->execute([$machineId, $type]);
    } else {
        $stmt = $pdo->prepare("
            SELECT ci.*, mci.frequency 
            FROM check_items ci 
            INNER JOIN machine_check_items mci ON ci.id = mci.check_item_id 
            WHERE mci.machine_id = ?
            ORDER BY ci.category, ci.item_code ASC
        ");
        $stmt->execute([$machineId]);
    }
    $checkItems = $stmt->fetchAll();

    // FALLBACK: If no specific items mapped, fetch ALL active items
    $isDefaultList = false;
    if (empty($checkItems)) {
        $stmt = $pdo->query("SELECT *, 'daily' as frequency FROM check_items ORDER BY category, item_code ASC");
        $checkItems = $stmt->fetchAll();
        $isDefaultList = true;
    }

    // Collect available frequencies for this machine
    $availableFreqs = [];
    foreach ($checkItems as $ci) {
        $f = $ci['frequency'] ?: 'daily';
        $availableFreqs[$f] = true;
    }

    // Dynamic Grouping
    $groupedItems = [
        'Machine' => [],
        'Safety' => [],
        'Customer' => [],
        // Other categories will be added dynamically
    ];

    foreach ($checkItems as $item) {
        $catRaw = trim($item['category'] ?? 'Other');
        if ($catRaw === '')
            $catRaw = 'Other';

        // STRICT FILTER: If a type is requested, only group items for that specific type
        if ($type && strcasecmp($catRaw, trim($type)) !== 0) {
            continue;
        }

        $cat = ucfirst($catRaw);

        if (!isset($groupedItems[$cat])) {
            $groupedItems[$cat] = [];
        }
        $groupedItems[$cat][] = $item;
    }

    // Remove empty pre-defined categories if they are not the requested type
    foreach (['Machine', 'Safety', 'Customer'] as $pre) {
        if (empty($groupedItems[$pre]))
            unset($groupedItems[$pre]);
    }

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
    $dupSql = "SELECT COUNT(*) FROM check_sheets WHERE target_id = ? AND created_at BETWEEN ? AND ?";
    $dupParams = [$target_id, $shiftStart, $shiftEnd];
    if ($type) {
        $dupSql .= " AND check_type = ?";
        $dupParams[] = $type;
    } else {
        $dupSql .= " AND (check_type = 'Daily' OR check_type IS NULL)";
    }

    $dupCheck = $pdo->prepare($dupSql);
    $dupCheck->execute($dupParams);
    if ($dupCheck->fetchColumn() > 0) {
        $isDuplicate = true;
        $typeLabel = $type ? "หัวข้อ $type" : "ข้อมูลทั้งหมด";
        $duplicateMsg = "เครื่องจักรนี้ถูกลงข้อมูลสำหรับ $typeLabel ใน $shiftLabel เรียบร้อยแล้ว ไม่สามารถลงซ้ำได้ หากต้องการแก้ไขกรุณาติดต่อ Leader";
    }
}
?>
<style>
    /* Frequency Filter Styles */
    .freq-filter-bar {
        position: sticky;
        top: 0;
        z-index: 100;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(0, 0, 0, 0.05);
        margin-bottom: 20px;
    }

    .freq-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 50px;
        border: 2px solid #dee2e6;
        background: #fff;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.8rem;
        font-weight: 600;
        color: #6c757d;
        user-select: none;
    }

    .freq-chip:hover {
        border-color: #0d6efd;
        color: #0d6efd;
        background: rgba(13, 110, 253, 0.04);
    }

    .freq-chip input[type="checkbox"] {
        display: none;
    }

    .freq-chip.active {
        background: linear-gradient(135deg, #0d6efd, #0b5ed7);
        color: #fff;
        border-color: #0d6efd;
        box-shadow: 0 2px 8px rgba(13, 110, 253, 0.3);
    }

    .freq-chip .chip-icon {
        font-size: 0.7rem;
    }

    .check-row-hidden {
        display: none !important;
    }

    .freq-counter {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        border-radius: 50px;
        font-size: 0.65rem;
        font-weight: 700;
        background: rgba(0, 0, 0, 0.1);
        padding: 0 5px;
    }

    .freq-chip.active .freq-counter {
        background: rgba(255, 255, 255, 0.3);
    }
</style>

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
                                <!-- MODE SELECTION CARDS - Now Categorized -->
                                <div class="row g-3 mb-4">
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
                                            <a href="?machine_id=<?php echo $machineId; ?>&action=check&type=<?php echo urlencode($cat); ?>"
                                                class="text-decoration-none">
                                                <div class="card h-100 border-0 shadow-sm hover-shadow transition-all"
                                                    style="background: <?php echo $display['color']; ?>;">
                                                    <div class="card-body p-4 text-center text-white">
                                                        <div class="bg-white bg-opacity-25 rounded-circle d-inline-flex align-items-center justify-content-center mb-2"
                                                            style="width: 50px; height: 50px;">
                                                            <i class="fas <?php echo $display['icon']; ?> fa-lg"></i>
                                                        </div>
                                                        <h6 class="fw-bold mb-0"><?php echo $display['name']; ?></h6>
                                                        <p class="small mb-0 opacity-75" style="font-size: 0.7rem;">Check</p>
                                                    </div>
                                                </div>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>

                                    <?php if (empty($categories)): ?>
                                        <div class="col-12">
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
                                    <?php endif; ?>

                                    <div class="col-12 mt-2">
                                        <a href="downtime.php?report=1&machine_id=<?php echo $machineId; ?>"
                                            class="text-decoration-none">
                                            <div class="card h-100 border-0 shadow-sm hover-shadow transition-all"
                                                style="background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);">
                                                <div class="card-body p-3 text-center text-white">
                                                    <i class="fas fa-tools me-2"></i>
                                                    <span class="fw-bold">Report Repair / แจ้งซ่อม</span>
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
                                <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                                <?php if (!$isDuplicate): ?>
                                    <?php if (!empty($userError)): ?>
                                        <div class="alert alert-danger rounded-4 shadow-sm border-0 mb-4">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-user-times fa-2x me-3"></i>
                                                <div>
                                                    <h6 class="fw-bold mb-0">Validation Error</h6>
                                                    <p class="mb-0 small"><?php echo htmlspecialchars($userError); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
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
                            <!-- Standard Mode: Select Machine Only First -->
                            <div class="mb-4">
                                <label class="form-label fw-bold"><i
                                        class="fas fa-industry me-2 text-primary"></i>เลือกเครื่องจักรเพื่อเริ่มการตรวจสอบ</label>
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

                            <div class="d-grid mt-5">
                                <button type="submit" class="btn btn-primary btn-lg rounded-3 shadow-sm py-3 fw-bold">
                                    ถัดไป (Next) <i class="fas fa-chevron-right ms-2 small"></i>
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
                        <input type="hidden" name="check_type" value="<?php echo $type ?: 'Daily'; ?>">

                        <!-- Frequency Filter Checkboxes -->
                        <?php if (!empty($availableFreqs)): ?>
                            <div class="freq-filter-bar p-3 mb-4">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-filter text-primary me-2"></i>
                                    <span class="fw-bold small text-uppercase text-dark">Frequency Filter</span>
                                    <span class="ms-auto small text-muted" id="freqItemCount"></span>
                                </div>
                                <div class="d-flex flex-wrap gap-2" id="freqFilterGroup">
                                    <?php
                                    $allFreqs = [
                                        'shift' => ['label' => 'Shift', 'icon' => 'fas fa-sync-alt'],
                                        'daily' => ['label' => 'Daily', 'icon' => 'fas fa-calendar-day'],
                                        'weekly' => ['label' => 'Weekly', 'icon' => 'fas fa-calendar-week'],
                                        'monthly' => ['label' => 'Monthly', 'icon' => 'fas fa-calendar-alt'],
                                        '3_months' => ['label' => '3 Months', 'icon' => 'fas fa-calendar'],
                                        '6_months' => ['label' => '6 Months', 'icon' => 'fas fa-calendar'],
                                        'yearly' => ['label' => 'Yearly', 'icon' => 'fas fa-calendar-check'],
                                    ];
                                    foreach ($allFreqs as $fVal => $fInfo):
                                        $hasItems = isset($availableFreqs[$fVal]);
                                        if (!$hasItems)
                                            continue;
                                        $isDefault = ($fVal === 'daily');
                                        ?>
                                        <label class="freq-chip <?php echo $isDefault ? 'active' : ''; ?>" data-freq="<?php echo $fVal; ?>">
                                            <input type="checkbox" class="freq-checkbox" value="<?php echo $fVal; ?>" <?php echo $isDefault ? 'checked' : ''; ?>>
                                            <i class="chip-icon <?php echo $fInfo['icon']; ?>"></i>
                                            <?php echo $fInfo['label']; ?>
                                            <span class="freq-counter" data-freq-count="<?php echo $fVal; ?>">0</span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="card card-premium">
                            <div class="card-body p-4">
                                <h5 class="fw-bold mb-3"><?php echo $type ?: 'Daily'; ?> Check Items
                                    (<?php echo count($checkItems); ?> items)</h5>

                                <div class="table-responsive">
                                    <table class="table table-custom align-middle">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th class="col-code">Code</th>
                                                <th style="width: 40%;">Check Item</th>
                                                <th class="text-center">Status</th>
                                                <th>Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($checkItems as $item):
                                                $itemFreq = $item['frequency'] ?: 'daily';
                                                ?>
                                                <tr class="check-item-row" data-frequency="<?php echo $itemFreq; ?>">
                                                    <td><span class="badge bg-secondary"><?php echo $item['category']; ?></span></td>
                                                    <td class="fw-bold text-primary">
                                                        <?php echo $item['item_code']; ?>
                                                        <span class="badge bg-light text-dark border ms-1"
                                                            style="font-size:0.6rem;"><?php echo str_replace('_', ' ', $itemFreq); ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="fw-semibold"><?php echo $item['name_en']; ?></div>
                                                        <small class="text-muted"><?php echo $item['name_th']; ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex justify-content-center gap-3">
                                                            <div class="form-check form-check-inline">
                                                                <input class="form-check-input item-radio" type="radio"
                                                                    name="result[<?php echo $item['id']; ?>]"
                                                                    id="ok_<?php echo $item['id']; ?>" value="OK" required>
                                                                <label class="form-check-label text-success fw-bold"
                                                                    for="ok_<?php echo $item['id']; ?>">OK</label>
                                                            </div>
                                                            <div class="form-check form-check-inline">
                                                                <input class="form-check-input item-radio" type="radio"
                                                                    name="result[<?php echo $item['id']; ?>]"
                                                                    id="ng_<?php echo $item['id']; ?>" value="NG">
                                                                <label class="form-check-label text-danger fw-bold"
                                                                    for="ng_<?php echo $item['id']; ?>">NG</label>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm remark-input"
                                                            name="comment[<?php echo $item['id']; ?>]"
                                                            id="remark_<?php echo $item['id']; ?>"
                                                            data-item-id="<?php echo $item['id']; ?>"
                                                            data-item-code="<?php echo htmlspecialchars($item['item_code']); ?>"
                                                            placeholder="กรอก Remark...">
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

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('checkSheetForm');
        if (!form) return;

        // --- FILTER LOGIC ---
        const chips = document.querySelectorAll('.freq-chip');
        const checkboxes = document.querySelectorAll('.freq-checkbox');
        const rows = document.querySelectorAll('.check-item-row');

        if (chips.length > 0) {
            function getSelectedFreqs() {
                const selected = [];
                checkboxes.forEach(cb => { if (cb.checked) selected.push(cb.value); });
                return selected;
            }

            function applyFilter() {
                const selected = getSelectedFreqs();
                let totalVisible = 0;
                const freqCounts = {};

                // Count items per frequency
                rows.forEach(row => {
                    const freq = row.getAttribute('data-frequency');
                    freqCounts[freq] = (freqCounts[freq] || 0) + 1;
                });

                // Update counters
                document.querySelectorAll('[data-freq-count]').forEach(el => {
                    const f = el.getAttribute('data-freq-count');
                    el.textContent = freqCounts[f] || 0;
                });

                rows.forEach(row => {
                    const freq = row.getAttribute('data-frequency');
                    const isVisible = selected.includes(freq);

                    if (isVisible) {
                        row.classList.remove('check-row-hidden');
                        totalVisible++;
                        // Re-enable inputs
                        row.querySelectorAll('.item-radio').forEach(r => {
                            if (r.value === 'OK') r.setAttribute('required', 'required');
                            r.removeAttribute('disabled');
                        });
                        row.querySelectorAll('.remark-input').forEach(c => c.removeAttribute('disabled'));
                    } else {
                        row.classList.add('check-row-hidden');
                        // Disable inputs so they don't submit
                        row.querySelectorAll('.item-radio').forEach(r => {
                            r.removeAttribute('required');
                            r.setAttribute('disabled', 'disabled');
                        });
                        row.querySelectorAll('.remark-input').forEach(c => c.setAttribute('disabled', 'disabled'));
                    }
                });

                // Update item count display
                const countEl = document.getElementById('freqItemCount');
                if (countEl) {
                    countEl.innerHTML = '<i class="fas fa-list-ol me-1"></i> ' + totalVisible + ' items';
                }
            }

            // Chip click handler
            chips.forEach(chip => {
                chip.addEventListener('click', function (e) {
                    e.preventDefault();
                    const cb = this.querySelector('input[type="checkbox"]');
                    cb.checked = !cb.checked;
                    this.classList.toggle('active', cb.checked);
                    applyFilter();
                });
            });

            // Initial filter
            applyFilter();
        }

        // --- VALIDATION AND INTERACTION LOGIC ---

        // Listen to all radio button changes
        form.querySelectorAll('input[type="radio"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                // Extract item id from name: result[123] -> 123
                const match = this.name.match(/result\[(\d+)\]/);
                if (!match) return;
                const itemId = match[1];
                const remarkInput = document.getElementById('remark_' + itemId);
                if (!remarkInput) return;

                if (this.value === 'NG') {
                    // Make remark required
                    remarkInput.setAttribute('required', 'required');
                    remarkInput.classList.add('border-danger', 'bg-danger-subtle');
                    remarkInput.placeholder = '⚠️ กรุณาระบุปัญหาที่พบ (บังคับ)';
                    remarkInput.focus();
                } else {
                    // Remove required
                    remarkInput.removeAttribute('required');
                    remarkInput.classList.remove('border-danger', 'bg-danger-subtle');
                    remarkInput.placeholder = 'กรอก Remark...';
                }
            });
        });

        // Form submit validation
        form.addEventListener('submit', function (e) {
            const ngItems = form.querySelectorAll('input[type="radio"][value="NG"]:checked');
            const missingRemarks = [];

            ngItems.forEach(function (ngRadio) {
                if (ngRadio.disabled) return; // Skip disabled items
                const match = ngRadio.name.match(/result\[(\d+)\]/);
                if (!match) return;
                const itemId = match[1];
                const remarkInput = document.getElementById('remark_' + itemId);
                if (remarkInput && remarkInput.value.trim() === '') {
                    const itemCode = remarkInput.getAttribute('data-item-code') || itemId;
                    missingRemarks.push(itemCode);
                    remarkInput.classList.add('border-danger', 'bg-danger-subtle');
                }
            });

            if (missingRemarks.length > 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณากรอก Remark สำหรับข้อที่ NG',
                    html: '<div class="text-start"><p>รายการที่ยังไม่ได้กรอก Remark:</p><ul>' +
                        missingRemarks.map(code => '<li class="text-danger fw-bold">' + code + '</li>').join('') +
                        '</ul></div>',
                    confirmButtonText: 'ตกลง',
                    confirmButtonColor: '#4f46e5'
                });
                return false;
            }
        });
    });
</script>

<?php include '../includes/footer.php'; ?>