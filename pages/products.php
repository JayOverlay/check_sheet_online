<?php
require_once '../config/database.php';
include '../includes/header.php';

// Handle addition of new product type
if (isset($_POST['add_product'])) {
    $name = $_POST['name'] ?? '';
    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO product_types (name) VALUES (?)");
            $stmt->execute([$name]);
        } catch (Exception $e) {
        }
    }
}

// Handle deletion
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM product_types WHERE id = ?");
    $stmt->execute([$id]);
}

$products = $pdo->query("SELECT * FROM product_types ORDER BY name ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Manage Products</h4>
    <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal"
        data-bs-target="#addProductModal">
        <i class="fas fa-plus me-2"></i> Add Product
    </button>
</div>

<div class="card card-premium">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product Name</th>
                        <th style="width: 100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="3" class="text-center py-5 text-muted">No products found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td>
                                    <?php echo $p['id']; ?>
                                </td>
                                <td>
                                    <?php echo $p['name']; ?>
                                </td>
                                <td>
                                    <a href="?delete=<?php echo $p['id']; ?>" class="btn btn-sm btn-light rounded-circle"
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
    </div>
</div>

<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-bold mb-0">Add New Product Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Product Name</label>
                        <input type="text" class="form-control" name="name" required placeholder="e.g. SMT, Injection">
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_product" class="btn btn-primary rounded-pill px-4 shadow">Save
                        Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>