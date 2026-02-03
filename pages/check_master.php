<?php
require_once '../config/database.php';
include '../includes/header.php';

// Handle Add / Edit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_item'])) {
    $id = $_POST['id'] ?? '';
    $item_code = $_POST['item_code'] ?? '';
    $name_en = $_POST['name_en'] ?? '';
    $name_th = $_POST['name_th'] ?? '';
    $category = $_POST['category'] ?? 'Machine';

    try {
        if (empty($id)) {
            // Add
            $stmt = $pdo->prepare("INSERT INTO check_items (item_code, name_en, name_th, category) VALUES (?, ?, ?, ?)");
            $stmt->execute([$item_code, $name_en, $name_th, $category]);
        } else {
            // Edit
            $stmt = $pdo->prepare("UPDATE check_items SET item_code = ?, name_en = ?, name_th = ?, category = ? WHERE id = ?");
            $stmt->execute([$item_code, $name_en, $name_th, $category, $id]);
        }
        header("Location: check_master?success=1");
        exit();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        // Also remove from mapping table to maintain integrity
        $pdo->prepare("DELETE FROM machine_check_items WHERE check_item_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM check_items WHERE id = ?")->execute([$id]);
        header("Location: check_master?deleted=1");
        exit();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch all check items with natural sort (treating 43.10 as greater than 43.2)
$checkItems = $pdo->query("SELECT * FROM check_items 
    ORDER BY 
    CAST(SUBSTRING_INDEX(item_code, '.', 1) AS UNSIGNED) ASC, 
    CAST(SUBSTRING_INDEX(item_code, '.', -1) AS UNSIGNED) ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Master Check Items</h4>
    <button class="btn btn-primary rounded-pill px-4 shadow-sm" onclick="openAddModal()">
        <i class="fas fa-plus me-2"></i> Add Item
    </button>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card card-premium">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th style="width: 80px;">Code</th>
                        <th>Description (EN/TH)</th>
                        <th style="width: 120px;">Category</th>
                        <th style="width: 120px;" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($checkItems)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">No check items found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($checkItems as $item): ?>
                            <tr>
                                <td class="fw-bold"><?php echo $item['item_code']; ?></td>
                                <td>
                                    <div class="mb-1 text-primary fw-semibold"><?php echo $item['name_en']; ?></div>
                                    <div class="text-muted small"><?php echo $item['name_th']; ?></div>
                                </td>
                                <td>
                                    <?php
                                    $catClass = 'primary';
                                    if ($item['category'] == 'Tooling')
                                        $catClass = 'success';
                                    else if ($item['category'] == 'Common')
                                        $catClass = 'info';
                                    else if ($item['category'] == 'Customer')
                                        $catClass = 'warning';
                                    else if ($item['category'] == 'Parameter')
                                        $catClass = 'danger';
                                    else if ($item['category'] == 'Inspection')
                                        $catClass = 'dark';
                                    ?>
                                    <span
                                        class="badge bg-<?php echo $catClass; ?>-subtle text-<?php echo $catClass; ?> border border-<?php echo $catClass; ?>-subtle">
                                        <?php echo $item['category']; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-light rounded-circle me-1"
                                        onclick='openEditModal(<?php echo htmlspecialchars(json_encode($item), ENT_QUOTES, "UTF-8"); ?>)'>
                                        <i class="fas fa-edit text-primary"></i>
                                    </button>
                                    <a href="?delete=<?php echo $item['id']; ?>" class="btn btn-sm btn-light rounded-circle"
                                        onclick="return confirm('Are you sure you want to delete this item?')">
                                        <i class="fas fa-trash text-danger"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST">
                <input type="hidden" name="id" id="item_id">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-bold mb-0" id="modalTitle">Add Check Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Item Code</label>
                        <input type="text" class="form-control" name="item_code" id="item_code" required
                            placeholder="e.g. 43.1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Description (English)</label>
                        <textarea class="form-control" name="name_en" id="name_en" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Description (Thai)</label>
                        <textarea class="form-control" name="name_th" id="name_th" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Category</label>
                        <select class="form-select" name="category" id="category" required>
                            <option value="Machine">Machine (General)</option>
                            <option value="Customer">Customer Requirement</option>
                            <option value="Parameter">Parameter Check</option>
                            <option value="Inspection">Visual Inspection</option>
                            <option value="Tooling">Tooling Specific</option>
                            <option value="Common">Common (All Types)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="save_item" class="btn btn-primary rounded-pill px-4 shadow">Save
                        Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let itemModal;

    document.addEventListener('DOMContentLoaded', function () {
        itemModal = new bootstrap.Modal(document.getElementById('itemModal'));
    });

    function openAddModal() {
        document.getElementById('modalTitle').textContent = 'Add Check Item';
        document.getElementById('item_id').value = '';
        document.getElementById('item_code').value = '';
        document.getElementById('name_en').value = '';
        document.getElementById('name_th').value = '';
        document.getElementById('category').value = 'Machine';
        itemModal.show();
    }

    function openEditModal(item) {
        document.getElementById('modalTitle').textContent = 'Edit Check Item';
        document.getElementById('item_id').value = item.id;
        document.getElementById('item_code').value = item.item_code;
        document.getElementById('name_en').value = item.name_en;
        document.getElementById('name_th').value = item.name_th;
        document.getElementById('category').value = item.category;
        itemModal.show();
    }
</script>

<?php include '../includes/footer.php'; ?>