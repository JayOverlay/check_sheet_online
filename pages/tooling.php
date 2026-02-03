<?php
require_once '../config/database.php';
include '../includes/header.php';

// Pagination Setup
$items_per_page = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $items_per_page;

$tooling = [];
$total_items = 0;
$productTypes = [];
$familyTypes = [];

try {
    // Get total count for pagination
    $total_items = $pdo->query("SELECT COUNT(*) FROM tooling")->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);

    // Fetch tooling with limit
    $stmt = $pdo->prepare("SELECT * FROM tooling ORDER BY tool_code ASC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $items_per_page, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $tooling = $stmt->fetchAll();

    $productTypes = $pdo->query("SELECT name FROM product_types ORDER BY name ASC")->fetchAll();
    $familyTypes = $pdo->query("SELECT name FROM family_types ORDER BY name ASC")->fetchAll();
    $masterChecks = $pdo->query("SELECT * FROM check_items 
        WHERE category IN ('Tooling', 'Common', 'Customer', 'Parameter', 'Inspection')
        ORDER BY 
        category ASC,
        CAST(SUBSTRING_INDEX(item_code, '.', 1) AS UNSIGNED) ASC, 
        CAST(SUBSTRING_INDEX(item_code, '.', -1) AS UNSIGNED) ASC")->fetchAll();
} catch (Exception $e) {
    // Table might not exist yet
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Tooling Inventory</h4>
    <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addToolModal">
        <i class="fas fa-plus me-2"></i> Add Tool
    </button>
</div>

<div class="card card-premium">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Tool Code</th>
                        <th>Name</th>
                        <th>Serial Number</th>
                        <th>Product</th>
                        <th>Family</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tooling)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="text-muted mb-3"><i class="fas fa-tools fa-3x opacity-25"></i></div>
                                <h6>No tools found in database.</h6>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tooling as $t): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($t['image_path'])): ?>
                                        <img src="<?php echo $t['image_path']; ?>" alt="Tool" class="rounded shadow-sm"
                                            style="width: 50px; height: 50px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center shadow-sm"
                                            style="width: 50px; height: 50px;">
                                            <i class="fas fa-tools text-muted opacity-50"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="fw-bold">
                                        <?php echo $t['tool_code']; ?>
                                    </span></td>
                                <td>
                                    <?php echo $t['tool_name']; ?>
                                </td>
                                <td>
                                    <?php echo $t['serial_number']; ?>
                                </td>
                                <td>
                                    <?php echo $t['product']; ?>
                                </td>
                                <td>
                                    <?php echo $t['family']; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = $t['status'] == 'Active' ? 'success' : ($t['status'] == 'Maintenance' ? 'warning' : 'danger');
                                    ?>
                                    <span
                                        class="badge bg-<?php echo $statusClass; ?>-subtle text-<?php echo $statusClass; ?> border border-<?php echo $statusClass; ?>-subtle px-3">
                                        <?php echo $t['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-light rounded-circle me-1"><i
                                            class="fas fa-edit text-primary"></i></button>
                                    <button class="btn btn-sm btn-light rounded-circle"
                                        onclick="confirmDelete('delete_tooling?id=<?php echo $t['id']; ?>')">
                                        <i class="fas fa-trash text-danger"></i>
                                    </button>
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
                    of <span class="fw-bold"><?php echo $total_items; ?></span> tools
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

<!-- Add Tool Modal -->
<div class="modal fade" id="addToolModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="save_tooling" method="POST" enctype="multipart/form-data">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-bold mb-0">Register New Tool</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-uppercase">Tool Code</label>
                            <input type="text" class="form-control" name="tool_code" placeholder="e.g. TOOL-A5"
                                required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-uppercase">Tool Name</label>
                            <input type="text" class="form-control" name="tool_name" placeholder="Name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-uppercase">Serial Number</label>
                            <input type="text" class="form-control" name="serial_number" placeholder="S/N" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Product</label>
                            <select class="form-select" name="product">
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
                            <select class="form-select" name="family">
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
                            <select class="form-select" name="status">
                                <option value="Active">Active</option>
                                <option value="Maintenance">Maintenance</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Tool Image</label>
                            <input type="file" class="form-control" name="tool_image" accept="image/*">
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
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow">Save Tool</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const masterItems = <?php echo json_encode($masterChecks); ?>;

    function addCheckItemFromSelect() {
        const select = document.getElementById('checkItemSearch');
        const id = select.value;
        if (!id) return;

        const option = select.options[select.selectedIndex];
        addCheckItemRow(id, option.dataset.code, option.dataset.en, option.dataset.th);

        select.value = ""; // Reset dropdown
    }

    function addCheckItemRow(id, code, en, th) {
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
                    <option value="shift">Shift</option>
                    <option value="daily" selected>Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="6_months">6 Months</option>
                    <option value="yearly">Yearly</option>
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
            tbody.innerHTML = `<tr id="noItemsNote"><td colspan="4" class="text-center py-3 text-muted small">No items selected. Add from dropdown above.</td></tr>`;
        }
    }

    function addAllMasterItems() {
        masterItems.forEach(item => {
            addCheckItemRow(item.id, item.item_code, item.name_en, item.name_th);
        });
    }
</script>

<?php include '../includes/footer.php'; ?>