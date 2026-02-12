<?php
require_once '../config/database.php';

// Handle Add / Edit - MUST be before any HTML output (header.php)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_item'])) {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . BASE_URL . "login.php");
        exit();
    }

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
        header("Location: " . BASE_URL . "pages/check_master.php?success=1");
        exit();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle Delete - MUST be before any HTML output
if (isset($_GET['delete'])) {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . BASE_URL . "login.php");
        exit();
    }

    $id = $_GET['delete'];
    try {
        // Also remove from mapping table to maintain integrity
        $pdo->prepare("DELETE FROM machine_check_items WHERE check_item_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM check_items WHERE id = ?")->execute([$id]);
        header("Location: " . BASE_URL . "pages/check_master.php?deleted=1");
        exit();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Now include header (outputs HTML)
include '../includes/header.php';

// Get unique categories for filter
$categories = $pdo->query("SELECT DISTINCT category FROM check_items ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Pagination Setup
$items_per_page = isset($_GET['limit']) ? (int) $_GET['limit'] : 15;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $items_per_page;

$category_filter = $_GET['category'] ?? 'all';
$search = $_GET['search'] ?? '';

try {
    $where_clauses = [];
    $params = [];

    if ($category_filter != 'all') {
        $where_clauses[] = "category = ?";
        $params[] = $category_filter;
    }

    if (!empty($search)) {
        $where_clauses[] = "(item_code LIKE ? OR name_en LIKE ? OR name_th LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

    // Get total count
    $total_items = $pdo->prepare("SELECT COUNT(*) FROM check_items $where_sql");
    $total_items->execute($params);
    $total_items = $total_items->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);

    // Fetch check items with natural sort logic
    $sql = "SELECT * FROM check_items $where_sql 
            ORDER BY 
            CAST(SUBSTRING_INDEX(item_code, '.', 1) AS UNSIGNED) ASC, 
            CAST(SUBSTRING_INDEX(item_code, '.', -1) AS UNSIGNED) ASC
            LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key + 1, $val);
    }
    $stmt->bindValue(count($params) + 1, (int) $items_per_page, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, (int) $offset, PDO::PARAM_INT);
    $stmt->execute();
    $checkItems = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Master Check Items</h4>
    <div class="d-flex gap-2 align-items-center">
        <div class="input-group input-group-sm rounded-pill bg-white shadow-sm px-2 me-2" style="width: 250px;">
            <span class="input-group-text bg-transparent border-0"><i class="fas fa-search text-muted"></i></span>
            <input type="text" id="searchInput" class="form-control border-0 shadow-none ps-0" placeholder="Search code/name..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <!-- Category Filter Dropdown -->
        <select class="form-select form-select-sm rounded-pill px-3 shadow-sm" id="categoryFilter"
            style="width: auto; min-width: 150px;">
            <option value="all">ทุก Category</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category_filter == $cat ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary rounded-pill px-4 shadow-sm" onclick="openAddModal()">
            <i class="fas fa-plus me-2"></i> Add Item
        </button>
    </div>
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
                        <th style="width: 100px; white-space: nowrap;">Code</th>
                        <th style="max-width: 400px;">Description (EN/TH)</th>
                        <th style="width: 120px;">Category</th>
                        <th style="width: 100px;" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($checkItems)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">No check items found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($checkItems as $item): ?>
                            <tr data-category="<?php echo htmlspecialchars($item['category']); ?>">
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
                                    else if ($item['category'] == 'Safety')
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
                                    <button class="btn btn-sm btn-light rounded-circle"
                                        onclick="confirmDelete('check_master?delete=<?php echo $item['id']; ?>')">
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
            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                <div class="text-muted small">
                    Showing <span class="fw-bold"><?php echo $offset + 1; ?></span> to 
                    <span class="fw-bold"><?php echo min($offset + $items_per_page, $total_items); ?></span> 
                    of <span class="fw-bold"><?php echo $total_items; ?></span> items
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link rounded-start-pill" href="<?php echo getPaginatedUrl($page - 1); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo getPaginatedUrl($i); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link rounded-end-pill" href="<?php echo getPaginatedUrl($page + 1); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <div>
                    <select class="form-select form-select-sm" id="itemsPerPage" style="width: auto;" onchange="changeLimit(this.value)">
                        <option value="15" <?php echo $items_per_page == 15 ? 'selected' : ''; ?>>15 ต่อหน้า</option>
                        <option value="25" <?php echo $items_per_page == 25 ? 'selected' : ''; ?>>25 ต่อหน้า</option>
                        <option value="50" <?php echo $items_per_page == 50 ? 'selected' : ''; ?>>50 ต่อหน้า</option>
                        <option value="100" <?php echo $items_per_page == 100 ? 'selected' : ''; ?>>100 ต่อหน้า</option>
                    </select>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
function getPaginatedUrl($p) {
    $params = $_GET;
    $params['page'] = $p;
    return '?' . http_build_query($params);
}
?>

<!-- Add/Edit Modal -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST" action="<?php echo BASE_URL; ?>pages/check_master.php">
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
                            <option value="Safety">Safety Check</option>
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
        
        // Category Filter Event Listener
        document.getElementById('categoryFilter').addEventListener('change', function () {
            updateFilters();
        });

        // Search Input Event (with debounce)
        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                updateFilters();
            }, 500);
        });
    });

    function updateFilters() {
        const cat = document.getElementById('categoryFilter').value;
        const search = document.getElementById('searchInput').value;
        const limit = document.getElementById('itemsPerPage') ? document.getElementById('itemsPerPage').value : 15;
        
        window.location.href = `?category=${cat}&search=${encodeURIComponent(search)}&limit=${limit}&page=1`;
    }

    function changeLimit(limit) {
        const cat = document.getElementById('categoryFilter').value;
        const search = document.getElementById('searchInput').value;
        window.location.href = `?category=${cat}&search=${encodeURIComponent(search)}&limit=${limit}&page=1`;
    }

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