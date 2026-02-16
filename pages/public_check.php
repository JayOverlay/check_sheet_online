<?php
require_once '../config/database.php';

// Fetch Machines for dropdown (only Active)
$machines = $pdo->query("SELECT * FROM machines WHERE status = 'Active'")->fetchAll();

// Check if form is loaded with both IDs
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
        $userError = "Invalid Employee ID: $employeeId not found or inactive.";
        // We set $_GET['error'] so it displays on the input form
        $_GET['error'] = $userError;
    }
}

if ($formLoaded && $machineId) {
    // Get machine info
    $stmt = $pdo->prepare("SELECT * FROM machines WHERE id = ?");
    $stmt->execute([$machineId]);
    $machineInfo = $stmt->fetch();

    // Get check items 
    // $type defined at top level now

    // Define Category Names for UI
    $categoryNames = [
        'Safety' => 'Safety Check',
        'Customer' => 'Customer Requirement',
        'Machine' => 'Machine Check',
        'Parameter' => 'Parameter Check',
        'Inspection' => 'Visual Inspection',
        'Tooling' => 'Tooling Specific',
        'Common' => 'General Check'
    ];
    $displayType = $categoryNames[$type] ?? ($type ?: 'Daily Check');

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

    // Collect available frequencies for this machine
    $availableFreqs = [];
    foreach ($checkItems as $ci) {
        $f = $ci['frequency'] ?: 'daily';
        $availableFreqs[$f] = true;
    }

    // Group items
    $groupedItems = [];
    foreach ($checkItems as $item) {
        $catRaw = trim($item['category'] ?: 'Other');
        $cat = ucfirst($catRaw);

        // STRICT FILTER: If a type is requested, only group items for that specific type
        if ($type && strcasecmp($catRaw, trim($type)) !== 0) {
            continue;
        }

        if (!isset($groupedItems[$cat]))
            $groupedItems[$cat] = [];
        $groupedItems[$cat][] = $item;
    }

    // Duplicate Check (Specific to Category if type is present)
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
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Check Sheet - Hana Project</title>
    <link href="<?php echo BASE_URL; ?>assets/libs/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/libs/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/inter.css" rel="stylesheet">
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

        /* Frequency Filter Styles */
        .freq-filter-bar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
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
                                <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
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
                            <input type="hidden" name="check_type" value="<?php echo $type ?: 'Daily'; ?>">

                            <div class="card card-premium mb-4 bg-primary text-white border-0">
                                <div class="card-body p-3 text-center">
                                    <h5 class="fw-bold mb-1 text-uppercase">
                                        <i class="fas fa-check-circle me-2"></i>
                                        <?php echo $displayType; ?>
                                    </h5>
                                    <p class="small mb-0 opacity-75">Hana Microelectronics Public Co., Ltd.</p>
                                </div>
                            </div>

                            <!-- Frequency Filter Checkboxes -->
                            <?php if (!empty($groupedItems)): ?>
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
                                            <label class="freq-chip <?php echo $isDefault ? 'active' : ''; ?>"
                                                data-freq="<?php echo $fVal; ?>">
                                                <input type="checkbox" class="freq-checkbox" value="<?php echo $fVal; ?>" <?php echo $isDefault ? 'checked' : ''; ?>>
                                                <i class="chip-icon <?php echo $fInfo['icon']; ?>"></i>
                                                <?php echo $fInfo['label']; ?>
                                                <span class="freq-counter" data-freq-count="<?php echo $fVal; ?>">0</span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (empty($groupedItems)): ?>
                                <div class="card card-premium text-center p-5 mb-4">
                                    <div class="text-muted mb-3"><i class="fas fa-clipboard-list fa-3x opacity-25"></i></div>
                                    <h5 class="fw-bold text-muted">No items found for this category.</h5>
                                    <p class="text-muted small">Please contact administrator to map check items for this machine.
                                    </p>
                                    <a href="scan.php?machine_id=<?php echo $machineId; ?>"
                                        class="btn btn-primary rounded-pill px-4 mt-3">Back</a>
                                </div>
                            <?php else: ?>
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
                                                            <th class="ps-4 col-code">Item</th>
                                                            <th class="text-center" style="width: 150px;">Status</th>
                                                            <th style="width: 250px;" class="pe-4">Memo</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($items as $item):
                                                            $itemFreq = $item['frequency'] ?: 'daily';
                                                            ?>
                                                            <tr class="check-item-row" data-frequency="<?php echo $itemFreq; ?>">
                                                                <td class="ps-4">
                                                                    <div class="fw-bold text-dark">
                                                                        <?php echo $item['item_code']; ?>
                                                                        <span class="badge bg-light text-dark border ms-1"
                                                                            style="font-size:0.6rem;"><?php echo str_replace('_', ' ', $itemFreq); ?></span>
                                                                    </div>
                                                                    <div class="small text-muted"><?php echo $item['name_en']; ?></div>
                                                                    <div class="small text-secondary"><?php echo $item['name_th']; ?></div>
                                                                </td>
                                                                <td class="text-center">
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
                                                                <td class="pe-4">
                                                                    <input type="text"
                                                                        class="form-control form-control-sm bg-light border-0 item-comment remark-input"
                                                                        name="comment[<?php echo $item['id']; ?>]"
                                                                        id="remark_<?php echo $item['id']; ?>"
                                                                        data-item-id="<?php echo $item['id']; ?>"
                                                                        data-item-code="<?php echo htmlspecialchars($item['item_code']); ?>"
                                                                        placeholder="Remarks...">
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
                                        <i class="fas fa-save me-2"></i> Submit <?php echo $displayType; ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
    <script src="<?php echo BASE_URL; ?>assets/libs/bootstrap/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const chips = document.querySelectorAll('.freq-chip');
            const checkboxes = document.querySelectorAll('.freq-checkbox');
            const rows = document.querySelectorAll('.check-item-row');

            if (!chips.length) return;

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
                        row.querySelectorAll('.item-comment').forEach(c => c.removeAttribute('disabled'));
                    } else {
                        row.classList.add('check-row-hidden');
                        // Disable inputs so they don't submit
                        row.querySelectorAll('.item-radio').forEach(r => {
                            r.removeAttribute('required');
                            r.setAttribute('disabled', 'disabled');
                        });
                        row.querySelectorAll('.item-comment').forEach(c => c.setAttribute('disabled', 'disabled'));
                    }
                });

                // Update item count display
                const countEl = document.getElementById('freqItemCount');
                if (countEl) {
                    countEl.innerHTML = '<i class="fas fa-list-ol me-1"></i> ' + totalVisible + ' items';
                }

                // Hide/show category cards if all items inside are hidden
                document.querySelectorAll('.card-premium.mb-4').forEach(card => {
                    const tbody = card.querySelector('tbody');
                    if (!tbody) return;
                    const visibleRows = tbody.querySelectorAll('.check-item-row:not(.check-row-hidden)');
                    if (visibleRows.length === 0) {
                        card.style.display = 'none';
                    } else {
                        card.style.display = '';
                    }
                });
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
        });
    </script>
    <script src="<?php echo BASE_URL; ?>assets/js/sweetalert2.all.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('checkSheetForm');
            if (!form) return;

            // Listen to all radio button changes for NG validation
            form.querySelectorAll('input[type="radio"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    const match = this.name.match(/result\[(\d+)\]/);
                    if (!match) return;
                    const itemId = match[1];
                    const remarkInput = document.getElementById('remark_' + itemId);
                    if (!remarkInput) return;

                    if (this.value === 'NG') {
                        remarkInput.setAttribute('required', 'required');
                        remarkInput.classList.remove('bg-light', 'border-0');
                        remarkInput.classList.add('border-danger', 'bg-danger-subtle');
                        remarkInput.placeholder = '⚠️ กรุณาระบุปัญหาที่พบ (บังคับ)';
                        remarkInput.focus();
                    } else {
                        remarkInput.removeAttribute('required');
                        remarkInput.classList.remove('border-danger', 'bg-danger-subtle');
                        remarkInput.classList.add('bg-light', 'border-0');
                        remarkInput.placeholder = 'Remarks...';
                    }
                });
            });

            // Form submit validation
            form.addEventListener('submit', function (e) {
                const ngItems = form.querySelectorAll('input[type="radio"][value="NG"]:checked');
                const missingRemarks = [];

                ngItems.forEach(function (ngRadio) {
                    if (ngRadio.disabled) return; // skip hidden frequency items
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
</body>

</html>