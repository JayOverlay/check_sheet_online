<?php
require_once '../config/database.php';
include '../includes/header.php';

// Handle addition of new family type
if (isset($_POST['add_family'])) {
    $name = $_POST['name'] ?? '';
    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO family_types (name) VALUES (?)");
            $stmt->execute([$name]);
        } catch (Exception $e) {
        }
    }
}

// Handle deletion
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM family_types WHERE id = ?");
    $stmt->execute([$id]);
}

// Pagination Setup
$items_per_page = 15;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $items_per_page;

$families = [];
$total_items = 0;

try {
    // Get total count
    $total_items = $pdo->query("SELECT COUNT(*) FROM family_types")->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);

    // Fetch families with limit
    $stmt = $pdo->prepare("SELECT * FROM family_types ORDER BY name ASC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, (int) $items_per_page, PDO::PARAM_INT);
    $stmt->bindValue(2, (int) $offset, PDO::PARAM_INT);
    $stmt->execute();
    $families = $stmt->fetchAll();
} catch (Exception $e) {
    // Handle error
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Manage Family</h4>
    <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addFamilyModal">
        <i class="fas fa-plus me-2"></i> Add Family
    </button>
</div>

<div class="card card-premium">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Family Name</th>
                        <th style="width: 100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($families)): ?>
                        <tr>
                            <td colspan="3" class="text-center py-5 text-muted">No family found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($families as $f): ?>
                            <tr>
                                <td>
                                    <?php echo $f['id']; ?>
                                </td>
                                <td>
                                    <?php echo $f['name']; ?>
                                </td>
                                <td>
                                    <a href="?delete=<?php echo $f['id']; ?>" class="btn btn-sm btn-light rounded-circle"
                                        onclick="return confirm('Are you sure?')">
                                        <i class="fas fa-trash text-danger"></i>
                                    </a>
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
                    of <span class="fw-bold"><?php echo $total_items; ?></span> families
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

<div class="modal fade" id="addFamilyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-bold mb-0">Add New Family Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Family Name</label>
                        <input type="text" class="form-control" name="name" required
                            placeholder="e.g. Model A, Series 100">
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_family" class="btn btn-primary rounded-pill px-4 shadow">Save
                        Family</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>