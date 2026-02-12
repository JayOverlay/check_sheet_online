<?php
require_once '../config/database.php';
include '../includes/header.php';

// Pagination Setup
$items_per_page = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $items_per_page;

$machines = [];
$total_items = 0;
$productTypes = [];
$familyTypes = [];
$masterChecks = [];

try {
    // Get total count for pagination
    $total_items = $pdo->query("SELECT COUNT(*) FROM machines")->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);

    // Fetch machines with limit
    $stmt = $pdo->prepare("SELECT * FROM machines ORDER BY machine_code ASC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $items_per_page, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $machines = $stmt->fetchAll();

    $productTypes = $pdo->query("SELECT name FROM product_types ORDER BY name ASC")->fetchAll();
    $familyTypes = $pdo->query("SELECT name FROM family_types ORDER BY name ASC")->fetchAll();
    $masterChecks = $pdo->query("SELECT * FROM check_items 
        WHERE category IN ('Machine', 'Common', 'Customer', 'Parameter', 'Inspection', 'Safety')
        ORDER BY 
        category ASC,
        CAST(SUBSTRING_INDEX(item_code, '.', 1) AS UNSIGNED) ASC, 
        CAST(SUBSTRING_INDEX(item_code, '.', -1) AS UNSIGNED) ASC")->fetchAll();
    $masterParams = $pdo->query("SELECT * FROM parameters_master ORDER BY name_en ASC")->fetchAll();
    $masterInspections = $pdo->query("SELECT * FROM inspections_master ORDER BY name_en ASC")->fetchAll();
} catch (Exception $e) {
    // Table might not exist yet
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Machine Inventory</h4>
    <button class="btn btn-primary rounded-pill px-4 shadow-sm" onclick="openAddModal()">
        <i class="fas fa-plus me-2"></i> Add Machine
    </button>
</div>

<div class="card card-premium">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Machine Code</th>
                        <th>Name</th>
                        <th>Serial Number</th>
                        <th>Product</th>
                        <th>Family</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($machines)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted mb-3"><i class="fas fa-industry fa-3x opacity-25"></i></div>
                                <h6>No machines found in database.</h6>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($machines as $m): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($m['image_path'])): ?>
                                        <img src="<?php echo BASE_URL . $m['image_path']; ?>" alt="Machine"
                                            class="rounded shadow-sm"
                                            style="width: 50px; height: 50px; object-fit: cover; cursor: pointer;"
                                            onclick="viewImage('<?php echo BASE_URL . $m['image_path']; ?>', '<?php echo htmlspecialchars($m['machine_name']); ?>')">
                                    <?php else: ?>
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center shadow-sm"
                                            style="width: 50px; height: 50px;">
                                            <i class="fas fa-industry text-muted opacity-50"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="fw-bold">
                                        <?php echo $m['machine_code']; ?>
                                    </span></td>
                                <td>
                                    <?php echo $m['machine_name']; ?>
                                </td>
                                <td>
                                    <?php echo $m['serial_number']; ?>
                                </td>
                                <td>
                                    <?php echo $m['product']; ?>
                                </td>
                                <td>
                                    <?php echo $m['family']; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = $m['status'] == 'Active' ? 'success' : ($m['status'] == 'Maintenance' ? 'warning' : 'danger');
                                    ?>
                                    <span
                                        class="badge bg-<?php echo $statusClass; ?>-subtle text-<?php echo $statusClass; ?> border border-<?php echo $statusClass; ?>-subtle px-3">
                                        <?php echo $m['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-light rounded-circle me-1"
                                        onclick="showQRCode('<?php echo htmlspecialchars($m['machine_code']); ?>', '<?php echo htmlspecialchars($m['machine_name']); ?>', <?php echo $m['id']; ?>)"
                                        title="Get QR Link">
                                        <i class="fas fa-qrcode text-dark"></i>
                                    </button>
                                    <button class="btn btn-sm btn-light rounded-circle me-1"
                                        onclick="viewMachine(<?php echo $m['id']; ?>)" title="View Details">
                                        <i class="fas fa-eye text-success"></i>
                                    </button>
                                    <button class="btn btn-sm btn-light rounded-circle me-1"
                                        onclick="editMachine(<?php echo $m['id']; ?>)">
                                        <i class="fas fa-edit text-primary"></i>
                                    </button>

                                    <?php if ($m['status'] === 'Inactive'): ?>
                                        <button class="btn btn-sm btn-light rounded-circle"
                                            onclick="confirmActivate('../actions/activate_machine.php?id=<?php echo $m['id']; ?>')"
                                            title="Activate Machine">
                                            <i class="fas fa-undo text-success"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-light rounded-circle"
                                            onclick="confirmDeactivate('../actions/delete_machine.php?id=<?php echo $m['id']; ?>')"
                                            title="Deactivate Machine">
                                            <i class="fas fa-trash text-danger"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="small text-muted">
                    Showing <span class="fw-bold"><?php echo $offset + 1; ?></span> to
                    <span class="fw-bold"><?php echo min($offset + $items_per_page, $total_items); ?></span>
                    of <span class="fw-bold"><?php echo $total_items; ?></span> machines
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link shadow-none" href="?page=<?php echo $page - 1; ?>"><i
                                    class="fas fa-chevron-left"></i></a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link shadow-none" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link shadow-none" href="?page=<?php echo $page + 1; ?>"><i
                                    class="fas fa-chevron-right"></i></a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Machine Modal -->
<div class="modal fade" id="addMachineModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="<?php echo BASE_URL; ?>actions/save_machine.php" method="POST" enctype="multipart/form-data"
                id="machineForm">
                <input type="hidden" name="machine_id" id="machine_id">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-bold mb-0" id="modalTitle">Register New Machine</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-uppercase">Machine Code</label>
                            <input type="text" class="form-control" name="machine_code" id="machine_code"
                                placeholder="e.g. MCH-001" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-uppercase">Machine Name</label>
                            <input type="text" class="form-control" name="machine_name" id="machine_name"
                                placeholder="Name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-uppercase">Serial Number</label>
                            <input type="text" class="form-control" name="serial_number" id="serial_number"
                                placeholder="S/N" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Product</label>
                            <select class="form-select" name="product" id="product">
                                <option value="">-- Select Product --</option>
                                <?php foreach ($productTypes as $pt): ?>
                                    <option value="<?php echo $pt['name']; ?>">
                                        <?php echo $pt['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Family</label>
                            <select class="form-select" name="family" id="family">
                                <option value="">-- Select Family --</option>
                                <?php foreach ($familyTypes as $ft): ?>
                                    <option value="<?php echo $ft['name']; ?>">
                                        <?php echo $ft['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Initial Status</label>
                            <select class="form-select" name="status" id="status">
                                <option value="Active">Active</option>
                                <option value="Maintenance">Maintenance</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Machine Image</label>
                            <input type="file" class="form-control" name="machine_image" accept="image/*">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase d-flex justify-content-between">
                            Check Items
                            <button type="button" class="btn btn-sm btn-outline-primary py-0" style="font-size: 0.7rem;"
                                onclick="addAllMasterItems()">Add All Items</button>
                        </label>

                        <!-- Search & Add Dropdown -->
                        <div class="input-group mb-2">
                            <span class="input-group-text bg-light text-muted"><i
                                    class="fas fa-search small"></i></span>
                            <select class="form-select form-select-sm" id="checkItemSearch"
                                onchange="addCheckItemFromSelect()">
                                <option value="">-- Search & Add Check Item --</option>
                                <?php
                                $currentCat = '';
                                foreach ($masterChecks as $check):
                                    if ($currentCat != $check['category']) {
                                        $currentCat = $check['category'];
                                        echo '<optgroup label="' . (($currentCat == 'Common') ? 'General / Common' : $currentCat) . '">';
                                    }
                                    ?>
                                    <option value="<?php echo $check['id']; ?>"
                                        data-code="<?php echo $check['item_code']; ?>"
                                        data-en="<?php echo $check['name_en']; ?>"
                                        data-th="<?php echo $check['name_th']; ?>">
                                        <?php echo $check['item_code']; ?>: <?php echo $check['name_en']; ?>
                                    </option>
                                    <?php
                                    if (!isset($masterChecks[array_search($check, $masterChecks) + 1]) || $masterChecks[array_search($check, $masterChecks) + 1]['category'] != $currentCat) {
                                        echo '</optgroup>';
                                    }
                                endforeach; ?>
                            </select>
                        </div>

                        <!-- Selected Items List -->
                        <div class="border rounded p-2" style="max-height: 300px; overflow-y: auto; background: #fff;">
                            <table class="table table-sm table-hover mb-0" id="selectedChecksTable">
                                <thead class="small text-muted bg-light">
                                    <tr>
                                        <th>Code</th>
                                        <th>Check Item</th>
                                        <th style="width: 120px;">Frequency</th>
                                        <th style="width: 40px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="selectedChecksBody">
                                    <tr id="noItemsNote">
                                        <td colspan="4" class="text-center py-3 text-muted small">No items selected. Add
                                            from dropdown above.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Parameter Setup Section -->
                    <div class="mb-3 pt-3 border-top">
                        <label class="form-label small fw-bold text-uppercase d-flex justify-content-between">
                            Parameter Setup
                            <span class="text-muted small">Target & Tolerances</span>
                        </label>

                        <!-- Search & Add Dropdown for Parameters -->
                        <div class="input-group mb-2">
                            <span class="input-group-text bg-light text-muted"><i
                                    class="fas fa-sliders-h small"></i></span>
                            <select class="form-select form-select-sm" id="parameterSearch"
                                onchange="addParameterRowFromSelect()">
                                <option value="">-- Add Parameter Check --</option>
                                <?php foreach ($masterParams as $mp): ?>
                                    <option value="<?php echo $mp['id']; ?>" data-name="<?php echo $mp['name_en']; ?>"
                                        data-unit="<?php echo $mp['unit']; ?>"
                                        data-target="<?php echo $mp['default_target']; ?>"
                                        data-plus="<?php echo $mp['default_plus']; ?>"
                                        data-minus="<?php echo $mp['default_minus']; ?>">
                                        <?php echo $mp['name_en']; ?> (<?php echo $mp['unit']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="border rounded p-2" style="background: #fff;">
                            <div class="table-responsive">
                                <table class="table table-sm table-borderless mb-0">
                                    <thead class="text-center small fw-bold text-muted bg-light">
                                        <tr>
                                            <th class="text-start">Parameter</th>
                                            <th style="width: 80px;">Target</th>
                                            <th style="width: 70px;">(+) Tol</th>
                                            <th style="width: 70px;">(-) Tol</th>
                                            <th style="width: 30px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="selectedParamsBody">
                                        <tr id="noParamsNote">
                                            <td colspan="5" class="text-center py-2 text-muted small">Optional: No
                                                parameters selected.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Inspection Setup Section (Buy-off) -->
                    <div class="mb-3 pt-3 border-top">
                        <label class="form-label small fw-bold text-uppercase d-flex justify-content-between">
                            Inspection Setup (Buy-off 5 pcs)
                            <span class="text-muted small">Spec & Tolerances</span>
                        </label>

                        <!-- Search & Add Dropdown for Inspections -->
                        <div class="input-group mb-2">
                            <span class="input-group-text bg-light text-muted"><i
                                    class="fas fa-microscope small"></i></span>
                            <select class="form-select form-select-sm" id="inspectionSearch"
                                onchange="addInspectionRowFromSelect()">
                                <option value="">-- Add Buy-off Inspection --</option>
                                <?php foreach ($masterInspections as $mi): ?>
                                    <option value="<?php echo $mi['id']; ?>" data-name="<?php echo $mi['name_en']; ?>"
                                        data-unit="<?php echo $mi['unit']; ?>"
                                        data-target="<?php echo $mi['default_target']; ?>"
                                        data-plus="<?php echo $mi['default_plus']; ?>"
                                        data-minus="<?php echo $mi['default_minus']; ?>">
                                        <?php echo $mi['name_en']; ?> (<?php echo $mi['unit']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="border rounded p-2" style="background: #fff;">
                            <div class="table-responsive">
                                <table class="table table-sm table-borderless mb-0">
                                    <thead class="text-center small fw-bold text-muted bg-light">
                                        <tr>
                                            <th class="text-start">Inspection Item</th>
                                            <th style="width: 80px;">Spec</th>
                                            <th style="width: 70px;">(+) Tol</th>
                                            <th style="width: 70px;">(-) Tol</th>
                                            <th style="width: 30px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="selectedInspectionsBody">
                                        <tr id="noInsNote">
                                            <td colspan="5" class="text-center py-2 text-muted small">Optional: No
                                                inspections selected.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow">Save Machine</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Machine Modal -->
<div class="modal fade" id="viewMachineModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-light p-4 pb-3">
                <h5 class="fw-bold mb-0 text-primary"><i class="fas fa-info-circle me-2"></i>Machine Specifications</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 pt-2">
                <div class="row mb-4 align-items-center bg-white p-3 rounded-4 border mx-0">
                    <div class="col-md-6">
                        <div class="fw-bold text-dark fs-5" id="v_machine_name">Name</div>
                        <div class="badge bg-primary-subtle text-primary border border-primary-subtle"
                            id="v_machine_code">Code</div>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div class="small text-muted fw-bold text-uppercase">Product / Family</div>
                        <div class="fw-bold text-secondary" id="v_pf">PF Name</div>
                    </div>
                </div>

                <ul class="nav nav-pills nav-fill bg-light p-1 rounded-pill mb-3" role="tablist">
                    <li class="nav-item"><button class="nav-link active rounded-pill px-4" data-bs-toggle="tab"
                            data-bs-target="#v-checks">Checks</button></li>
                    <li class="nav-item"><button class="nav-link rounded-pill px-4" data-bs-toggle="tab"
                            data-bs-target="#v-params">Params</button></li>
                    <li class="nav-item"><button class="nav-link rounded-pill px-4" data-bs-toggle="tab"
                            data-bs-target="#v-insps">Insps</button></li>
                </ul>

                <div class="tab-content" style="max-height: 400px; overflow-y: auto;">
                    <div class="tab-pane fade show active" id="v-checks">
                        <table class="table table-sm align-middle">
                            <thead class="small text-muted text-uppercase">
                                <tr>
                                    <th class="ps-3">Code</th>
                                    <th>Item Name</th>
                                    <th>Freq</th>
                                </tr>
                            </thead>
                            <tbody id="v_checks_body"></tbody>
                        </table>
                    </div>
                    <div class="tab-pane fade" id="v-params">
                        <table class="table table-sm align-middle">
                            <thead class="small text-muted text-uppercase">
                                <tr>
                                    <th class="ps-3">Parameter</th>
                                    <th class="text-center">Target</th>
                                    <th class="text-center">Tolerance</th>
                                </tr>
                            </thead>
                            <tbody id="v_params_body"></tbody>
                        </table>
                    </div>
                    <div class="tab-pane fade" id="v-insps">
                        <table class="table table-sm align-middle">
                            <thead class="small text-muted text-uppercase">
                                <tr>
                                    <th class="ps-3">Inspection</th>
                                    <th class="text-center">Target</th>
                                    <th class="text-center">Tolerance</th>
                                </tr>
                            </thead>
                            <tbody id="v_insps_body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-3">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Image View Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 bg-transparent shadow-none">
            <div class="modal-body p-0 text-center position-relative">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3"
                    style="z-index: 1056;" data-bs-dismiss="modal" aria-label="Close"></button>
                <img src="" id="fullImage" class="img-fluid rounded-4 shadow-lg" style="max-height: 85vh;">
                <div class="mt-2 text-white fw-bold text-shadow" id="imageCaption"></div>
            </div>
        </div>
    </div>
</div>

<!-- QR Link Modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="fw-bold mb-0">QR Code สำหรับเครื่องจักร</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="bg-light rounded-4 p-4 mb-3">
                    <i class="fas fa-qrcode fa-5x text-primary opacity-25 mb-3"></i>
                    <h5 class="fw-bold mb-1" id="qr_machine_code">MCH-001</h5>
                    <div class="text-primary fw-bold mb-3" id="qr_machine_name">Machine Name</div>
                    <p class="text-muted small">ใช้ Link ด้านล่างนี้เพื่อนำไปสร้าง QR Code ติดหน้าเครื่องจักร</p>
                </div>
                <div class="input-group mb-3">
                    <input type="text" id="qr_url_input" class="form-control bg-light border-0" readonly>
                    <button class="btn btn-primary" onclick="copyQRLink()">
                        <i class="fas fa-copy"></i> คัดลอก
                    </button>
                </div>
                <div class="small text-success d-none" id="copy_success_msg">
                    <i class="fas fa-check-circle me-1"></i> คัดลอกลงคลิปบอร์ดแล้ว!
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>

<script>
    const masterItems = <?php echo json_encode($masterChecks); ?>;

    let addModal;
    let viewSpecModal;
    let qrModal;
    let imageModal;

    document.addEventListener('DOMContentLoaded', function () {
        addModal = new bootstrap.Modal(document.getElementById('addMachineModal'));
        viewSpecModal = new bootstrap.Modal(document.getElementById('viewMachineModal'));
        qrModal = new bootstrap.Modal(document.getElementById('qrModal'));
        imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
    });

    function viewImage(url, title) {
        document.getElementById('fullImage').src = url;
        document.getElementById('imageCaption').textContent = title;
        imageModal.show();
    }

    function showQRCode(code, name, id) {
        const baseUrl = '<?php echo BASE_URL; ?>';
        const encodedCode = encodeURIComponent(code);
        const encodedName = encodeURIComponent(name);
        // Link format: pages/scan.php?machine_id=X
        const fullUrl = baseUrl + 'pages/scan.php?machine_id=' + id;

        document.getElementById('qr_machine_code').textContent = code;
        document.getElementById('qr_machine_name').textContent = name;
        document.getElementById('qr_url_input').value = fullUrl;
        document.getElementById('copy_success_msg').classList.add('d-none');
        qrModal.show();
    }

    function copyQRLink() {
        const input = document.getElementById('qr_url_input');
        input.select();
        input.setSelectionRange(0, 99999);

        try {
            // Priority: Clipboard API
            if (navigator.clipboard) {
                navigator.clipboard.writeText(input.value);
            } else {
                document.execCommand('copy');
            }

            // Show SweetAlert2 Success Toast
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });

            Toast.fire({
                icon: 'success',
                title: 'คัดลอก Link สำเร็จแล้ว!'
            });

            document.getElementById('copy_success_msg').classList.remove('d-none');
            setTimeout(() => {
                document.getElementById('copy_success_msg').classList.add('d-none');
            }, 2000);

        } catch (err) {
            console.error('Failed to copy: ', err);
            alert('ไม่สามารถคัดลอกได้ กรุณาคัดลอกด้วยตัวเองจากช่องข้อความ');
        }
    }

    function viewMachine(id) {
        document.getElementById('v_checks_body').innerHTML = '<tr><td colspan="3" class="text-center py-4">Loading...</td></tr>';
        viewSpecModal.show();

        fetch('<?php echo BASE_URL; ?>actions/get_machine_details.php?id=' + id)
            .then(r => r.text())
            .then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("View Machine Error:", text);
                    alert("Server Error: " + text.substring(0, 100));
                    throw new Error("Server Error");
                }
            })
            .then(data => {
                if (data.error) { alert(data.error); return; }
                const m = data.machine;
                document.getElementById('v_machine_name').textContent = m.machine_name;
                document.getElementById('v_machine_code').textContent = m.machine_code;
                document.getElementById('v_pf').textContent = `${m.product} / ${m.family}`;

                // Pop Checks
                const cb = document.getElementById('v_checks_body');
                cb.innerHTML = '';
                (data.checkItems || []).forEach(i => {
                    cb.innerHTML += `<tr>
    <td class="ps-3 small fw-bold text-primary">${i.item_code}</td>
    <td>
        <div class="small fw-bold">${i.name_en}</div>
        <div class="text-muted" style="font-size:0.7rem">${i.name_th}</div>
    </td>
    <td><span class="badge bg-light text-dark border">${i.frequency}</span></td>
</tr>`;
                });
                if (!data.checkItems.length) cb.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted small">No items mapped.</td></tr>';

                // Pop Params
                const pb = document.getElementById('v_params_body');
                pb.innerHTML = '';
                (data.parameters || []).forEach(p => {
                    pb.innerHTML += `<tr>
    <td class="ps-3"><strong>${p.name_en}</strong> <small class="text-muted">(${p.unit})</small></td>
    <td class="text-center">${p.target_value}</td>
    <td class="text-center">+${p.plus_tolerance}/-${p.minus_tolerance}</td>
</tr>`;
                });
                if (!data.parameters.length) pb.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted small">No parameters.</td></tr>';

                // Pop Insps
                const ib = document.getElementById('v_insps_body');
                ib.innerHTML = '';
                (data.inspections || []).forEach(i => {
                    ib.innerHTML += `<tr>
    <td class="ps-3"><strong>${i.name_en}</strong> <small class="text-muted">(${i.unit})</small></td>
    <td class="text-center">${i.target_value}</td>
    <td class="text-center">+${i.plus_tolerance}/-${i.minus_tolerance}</td>
</tr>`;
                });
                if (!data.inspections.length) ib.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted small">No inspections.</td></tr>';
            })
            .catch(err => {
                console.error(err);
                alert('View Machine Error: ' + err.message);
            });
    }

    function openAddModal() {
        document.getElementById('machineForm').reset();
        document.getElementById('machine_id').value = '';
        document.getElementById('modalTitle').textContent = 'Register New Machine';

        // Clear tables
        document.getElementById('selectedChecksBody').innerHTML = `<tr id="noItemsNote">
    <td colspan="4" class="text-center py-3 text-muted small">No items selected. Add from dropdown above.</td>
</tr>`;
        document.getElementById('selectedParamsBody').innerHTML = `<tr id="noParamsNote">
    <td colspan="5" class="text-center py-2 text-muted small">Optional: No parameters selected.</td>
</tr>`;
        document.getElementById('selectedInspectionsBody').innerHTML = `<tr id="noInsNote">
    <td colspan="5" class="text-center py-2 text-muted small">Optional: No inspections selected.</td>
</tr>`;

        addModal.show();
    }

    function editMachine(id) {
        document.getElementById('machineForm').reset();
        document.getElementById('machine_id').value = id;
        document.getElementById('modalTitle').textContent = 'Edit Machine';

        // Clear tables first
        document.getElementById('selectedChecksBody').innerHTML = '';
        document.getElementById('selectedParamsBody').innerHTML = '';
        document.getElementById('selectedInspectionsBody').innerHTML = '';

        // Fetch Data
        fetch('<?php echo BASE_URL; ?>actions/get_machine_details.php?id=' + id)
            .then(response => response.text())
            .then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("Edit Machine Error:", text);
                    throw new Error("Server returned: " + text.substring(0, 100));
                }
            })
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }

                // Set Basic Info
                const m = data.machine;
                document.getElementById('machine_code').value = m.machine_code;
                document.getElementById('machine_name').value = m.machine_name;
                document.getElementById('serial_number').value = m.serial_number;
                document.getElementById('product').value = m.product;
                document.getElementById('family').value = m.family;
                document.getElementById('status').value = m.status;

                // Set Check Items
                if (data.checkItems && data.checkItems.length > 0) {
                    data.checkItems.forEach(item => {
                        addCheckItemRow(item.check_item_id, item.item_code, item.name_en, item.name_th, item.frequency || 'daily');
                    });
                } else {
                    document.getElementById('selectedChecksBody').innerHTML = `<tr id="noItemsNote">
    <td colspan="4" class="text-center py-3 text-muted small">No items selected. Add from dropdown above.</td>
</tr>`;
                }

                // Set Parameters
                if (data.parameters && data.parameters.length > 0) {
                    data.parameters.forEach(p => {
                        addParameterRow(p.parameter_id, p.name_en, p.unit, p.target_value, p.plus_tolerance, p.minus_tolerance);
                    });
                } else {
                    document.getElementById('selectedParamsBody').innerHTML = `<tr id="noParamsNote">
    <td colspan="5" class="text-center py-2 text-muted small">Optional: No parameters selected.</td>
</tr>`;
                }

                // Set Inspections
                if (data.inspections && data.inspections.length > 0) {
                    data.inspections.forEach(i => {
                        addInspectionRow(i.inspection_id, i.name_en, i.unit, i.target_value, i.plus_tolerance, i.minus_tolerance);
                    });
                } else {
                    document.getElementById('selectedInspectionsBody').innerHTML = `<tr id="noInsNote">
    <td colspan="5" class="text-center py-2 text-muted small">Optional: No inspections selected.</td>
</tr>`;
                }

                addModal.show();
            })
            .catch(err => {
                console.error(err);
                alert('Failed to fetch machine details: ' + err.message);
            });
    }

    function addCheckItemFromSelect() {
        const select = document.getElementById('checkItemSearch');
        const id = select.value;
        if (!id) return;

        const option = select.options[select.selectedIndex];
        addCheckItemRow(id, option.dataset.code, option.dataset.en, option.dataset.th);

        select.value = ""; // Reset dropdown
    }

    function addCheckItemRow(id, code, en, th, frequency = 'daily') {
        // Check if already exists
        if (document.getElementById('row_check_' + id)) return;

        const note = document.getElementById('noItemsNote');
        if (note) note.remove();

        const tbody = document.getElementById('selectedChecksBody');
        const row = document.createElement('tr');
        row.id = 'row_check_' + id;
        row.className = 'align-middle';
        row.innerHTML = `
<td class="small fw-bold text-primary">${code}</td>
<td class="small">
    <div>${en}</div>
    <div class="text-muted" style="font-size: 0.7rem;">${th}</div>
    <input type="hidden" name="check_items[]" value="${id}">
</td>
<td>
    <select class="form-select form-select-sm" name="frequency[${id}]">
        <option value="shift" ${frequency === 'shift' ? 'selected' : ''}>Shift</option>
        <option value="daily" ${frequency === 'daily' ? 'selected' : ''}>Daily</option>
        <option value="weekly" ${frequency === 'weekly' ? 'selected' : ''}>Weekly</option>
        <option value="monthly" ${frequency === 'monthly' ? 'selected' : ''}>Monthly</option>
        <option value="quarterly" ${frequency === 'quarterly' ? 'selected' : ''}>Quarterly</option>
        <option value="6_months" ${frequency === '6_months' ? 'selected' : ''}>6 Months</option>
        <option value="yearly" ${frequency === 'yearly' ? 'selected' : ''}>Yearly</option>
    </select>
</td>
<td>
    <button type="button" class="btn btn-sm text-danger" onclick="removeCheckItem(${id})">
        <i class="fas fa-times"></i>
    </button>
</td>
`;
        tbody.appendChild(row);
    }

    function removeCheckItem(id) {
        document.getElementById('row_check_' + id).remove();
        if (document.getElementById('selectedChecksBody').children.length === 0) {
            const tbody = document.getElementById('selectedChecksBody');
            tbody.innerHTML = `<tr id="noItemsNote">
    <td colspan="4" class="text-center py-3 text-muted small">No items selected. Add from dropdown above.</td>
</tr>`;
        }
    }

    function addAllMasterItems() {
        masterItems.forEach(item => {
            addCheckItemRow(item.id, item.item_code, item.name_en, item.name_th);
        });
    }

    // New Parameter logic
    function addParameterRowFromSelect() {
        const select = document.getElementById('parameterSearch');
        if (!select.value) return;
        const opt = select.options[select.selectedIndex];
        addParameterRow(select.value, opt.dataset.name, opt.dataset.unit, opt.dataset.target, opt.dataset.plus,
            opt.dataset.minus);
        select.value = "";
    }

    function addParameterRow(id, name, unit, target, plus, minus) {
        if (document.getElementById('row_param_' + id)) return;
        const note = document.getElementById('noParamsNote');
        if (note) note.remove();

        const tbody = document.getElementById('selectedParamsBody');
        const row = document.createElement('tr');
        row.id = 'row_param_' + id;
        row.className = 'align-middle border-bottom pb-2';
        row.innerHTML = `
<td class="small">
    <div class="fw-bold">${name}</div>
    <div class="text-muted" style="font-size: 0.7rem;">${unit}</div>
    <input type="hidden" name="param_ids[]" value="${id}">
</td>
<td><input type="number" step="any" name="param_target[${id}]" class="form-control form-control-sm text-center"
        value="${target}"></td>
<td><input type="number" step="any" name="param_plus[${id}]"
        class="form-control form-control-sm text-center text-success" value="${plus}"></td>
<td><input type="number" step="any" name="param_minus[${id}]"
        class="form-control form-control-sm text-center text-danger" value="${minus}"></td>
<td><button type="button" class="btn btn-sm text-danger"
        onclick="document.getElementById('row_param_${id}').remove(); checkEmptyParam();"><i
            class="fas fa-times"></i></button></td>
`;
        tbody.appendChild(row);
    }

    function checkEmptyParam() {
        const tbody = document.getElementById('selectedParamsBody');
        if (tbody.children.length === 0) {
            tbody.innerHTML = `<tr id="noParamsNote">
    <td colspan="5" class="text-center py-2 text-muted small">Optional: No parameters selected.</td>
</tr>`;
        }
    }

    // New Inspection logic
    function addInspectionRowFromSelect() {
        const select = document.getElementById('inspectionSearch');
        if (!select.value) return;
        const opt = select.options[select.selectedIndex];
        addInspectionRow(select.value, opt.dataset.name, opt.dataset.unit, opt.dataset.target, opt.dataset.plus,
            opt.dataset.minus);
        select.value = "";
    }

    function addInspectionRow(id, name, unit, target, plus, minus) {
        if (document.getElementById('row_ins_' + id)) return;
        const note = document.getElementById('noInsNote');
        if (note) note.remove();

        const tbody = document.getElementById('selectedInspectionsBody');
        const row = document.createElement('tr');
        row.id = 'row_ins_' + id;
        row.className = 'align-middle border-bottom pb-2';
        row.innerHTML = `
<td class="small">
    <div class="fw-bold">${name}</div>
    <div class="text-muted" style="font-size: 0.7rem;">${unit}</div>
    <input type="hidden" name="inspection_ids[]" value="${id}">
</td>
<td><input type="number" step="any" name="inspection_target[${id}]" class="form-control form-control-sm text-center"
        value="${target}"></td>
<td><input type="number" step="any" name="inspection_plus[${id}]"
        class="form-control form-control-sm text-center text-success" value="${plus}"></td>
<td><input type="number" step="any" name="inspection_minus[${id}]"
        class="form-control form-control-sm text-center text-danger" value="${minus}"></td>
<td><button type="button" class="btn btn-sm text-danger"
        onclick="document.getElementById('row_ins_${id}').remove(); checkEmptyIns();"><i
            class="fas fa-times"></i></button></td>
`;
        tbody.appendChild(row);
    }

    function checkEmptyIns() {
        const tbody = document.getElementById('selectedInspectionsBody');
        if (tbody.children.length === 0) {
            tbody.innerHTML = `<tr id="noInsNote">
    <td colspan="5" class="text-center py-2 text-muted small">Optional: No inspections selected.</td>
</tr>`;
        }
    }
    function confirmDeactivate(url) {
        Swal.fire({
            title: 'ยืนยันการเปลี่ยนสถานะ?',
            text: "เครื่องจักรนี้จะถูกเปลี่ยนสถานะเป็น Inactive แทนการลบข้อมูล",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'ใช่, เปลี่ยนเป็น Inactive',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    }

    function confirmActivate(url) {
        Swal.fire({
            title: 'ยืนยันการเปิดใช้งาน?',
            text: "เครื่องจักรนี้จะถูกเปลี่ยนสถานะเป็น Active",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'ใช่, เปิดใช้งาน',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    }
</script>

<?php include '../includes/footer.php'; ?>