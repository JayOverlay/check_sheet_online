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

    // Sort keys to ensure priority categories come first if needed, 
    // but the initialization above already helps with order for Machine, Safety, Customer.
    // We can keep 'Other' or unknown categories at the end if we want, but simple iteration is fine.
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

                    <form method="GET" action="">
                        <div class="mb-4">
                            <label class="form-label fw-bold"><i
                                    class="fas fa-industry me-2 text-primary"></i>เลือกเครื่องจักร</label>
                            <select class="form-select form-select-lg rounded-3" name="machine_id" id="machineSelect"
                                required>
                                <option value="">-- เลือกเครื่องจักร --</option>
                                <?php foreach ($machines as $m): ?>
                                    <option value="<?php echo $m['id']; ?>">
                                        <?php echo $m['machine_code'] . ' - ' . $m['machine_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold"><i
                                    class="fas fa-id-badge me-2 text-primary"></i>รหัสพนักงาน</label>
                            <input type="text" class="form-control form-control-lg rounded-3" name="employee_id"
                                placeholder="กรอกรหัสพนักงาน (EN Number)" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg rounded-pill shadow">
                                <i class="fas fa-file-alt me-2"></i> โหลด Form ตรวจสอบ
                            </button>
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
                            <a href="check_form" class="btn btn-outline-secondary rounded-pill px-4">
                                <i class="fas fa-arrow-left me-2"></i> เปลี่ยนเครื่อง
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (empty($checkItems)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ไม่พบรายการตรวจสอบสำหรับเครื่องจักรนี้ กรุณาเพิ่มรายการตรวจสอบในหน้า <a href="machines">Machines</a>
                </div>
            <?php else: ?>

                <!-- Check Form (DEBUG MODE: Single Table) -->
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
        </div>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>