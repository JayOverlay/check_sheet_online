<?php
require_once '../config/database.php';
include '../includes/header.php';
include '../includes/admin_guard.php';

// Handle Add / Edit Master Inspection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_inspection'])) {
    $id = $_POST['id'] ?? '';
    $name_en = $_POST['name_en'] ?? '';
    $name_th = $_POST['name_th'] ?? '';
    $unit = $_POST['unit'] ?? '';
    $default_target = $_POST['default_target'] ?? '';
    $default_plus = $_POST['default_plus'] ?? 0;
    $default_minus = $_POST['default_minus'] ?? 0;

    // Image Upload Handling
    $image_filename = null;
    if (isset($_FILES['spec_image']) && $_FILES['spec_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['spec_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $upload_dir = 'uploads/specs/';
            if (!is_dir($upload_dir))
                mkdir($upload_dir, 0777, true);
            $new_name = uniqid('spec_') . '.' . $ext;
            if (move_uploaded_file($_FILES['spec_image']['tmp_name'], $upload_dir . $new_name)) {
                $image_filename = $new_name;
            }
        }
    }

    try {
        if (empty($id)) {
            $stmt = $pdo->prepare("INSERT INTO inspections_master (name_en, name_th, unit, default_target, default_plus, default_minus, spec_image) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name_en, $name_th, $unit, $default_target, $default_plus, $default_minus, $image_filename]);
        } else {
            if ($image_filename) {
                $stmt = $pdo->prepare("UPDATE inspections_master SET name_en = ?, name_th = ?, unit = ?, default_target = ?, default_plus = ?, default_minus = ?, spec_image = ? WHERE id = ?");
                $stmt->execute([$name_en, $name_th, $unit, $default_target, $default_plus, $default_minus, $image_filename, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE inspections_master SET name_en = ?, name_th = ?, unit = ?, default_target = ?, default_plus = ?, default_minus = ? WHERE id = ?");
                $stmt->execute([$name_en, $name_th, $unit, $default_target, $default_plus, $default_minus, $id]);
            }
        }
        header("Location: inspections.php?success=1");
        exit();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM inspections_master WHERE id = ?")->execute([$id]);
        header("Location: inspections.php?deleted=1");
        exit();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Pagination Setup
$items_per_page = 15;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $items_per_page;

$inspections = [];
$total_items = 0;

try {
    // Get total count
    $total_items = $pdo->query("SELECT COUNT(*) FROM inspections_master")->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);

    // Fetch inspections with limit
    $stmt = $pdo->prepare("SELECT * FROM inspections_master ORDER BY name_en ASC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, (int) $items_per_page, PDO::PARAM_INT);
    $stmt->bindValue(2, (int) $offset, PDO::PARAM_INT);
    $stmt->execute();
    $inspections = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Inspection Master (Buy-off)</h4>
    <button class="btn btn-primary rounded-pill px-4 shadow-sm" onclick="openAddModal()">
        <i class="fas fa-plus me-2"></i> Add Inspection Item
    </button>
</div>

<div class="card card-premium">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th>Item Name (EN/TH)</th>
                        <th>Ref. Image</th>
                        <th>Unit</th>
                        <th class="text-center">Set Point</th>
                        <th class="text-center text-success">(+) Tol</th>
                        <th class="text-center text-danger">(-) Tol</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inspections)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No inspection items defined.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($inspections as $i): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-primary">
                                        <?php echo $i['name_en']; ?>
                                    </div>
                                    <div class="small text-muted">
                                        <?php echo $i['name_th']; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($i['spec_image'])): ?>
                                        <a href="uploads/specs/<?php echo $i['spec_image']; ?>" target="_blank">
                                            <img src="uploads/specs/<?php echo $i['spec_image']; ?>" class="rounded border"
                                                style="width: 50px; height: 50px; object-fit: cover;" alt="Spec">
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-light text-dark border">
                                        <?php echo $i['unit'] ?: '-'; ?>
                                    </span></td>
                                <td class="text-center fw-bold text-primary">
                                    <?php echo $i['default_target'] ?: '-'; ?>
                                </td>
                                <td class="text-center text-success fw-semibold">
                                    <?php echo ($i['default_plus'] != 0) ? '+' . $i['default_plus'] : '0'; ?>
                                </td>
                                <td class="text-center text-danger fw-semibold">
                                    <?php echo ($i['default_minus'] != 0) ? '-' . $i['default_minus'] : '0'; ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-light rounded-circle me-1"
                                        onclick='openEditModal(<?php echo htmlspecialchars(json_encode($i), ENT_QUOTES, "UTF-8"); ?>)'>
                                        <i class="fas fa-edit text-primary"></i>
                                    </button>
                                    <a href="?delete=<?php echo $i['id']; ?>" class="btn btn-sm btn-light rounded-circle"
                                        onclick="return confirm('Delete this inspection item?')">
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
                    of <span class="fw-bold"><?php echo $total_items; ?></span> inspection items
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

<!-- Modal -->
<div class="modal fade" id="inspectionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" id="inspection_id">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-bold mb-0" id="modalTitle">Add Inspection Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Name (English)</label>
                        <input type="text" class="form-control" name="name_en" id="name_en" required
                            placeholder="e.g. Dimensions">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Name (Thai)</label>
                        <input type="text" class="form-control" name="name_th" id="name_th"
                            placeholder="เช่น ขนาดชิ้นงาน">
                    </div>
                    <div class="mb-3">
                        <input type="text" class="form-control" name="unit" id="unit" placeholder="e.g. mm, g, pcs">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Reference Image</label>
                        <input type="file" class="form-control" name="spec_image" id="spec_image" accept="image/*">
                        <div id="current_image_preview" class="mt-2 d-none">
                            <small class="text-muted d-block mb-1">Current Image:</small>
                            <img src="" id="preview_img" class="rounded border" style="height: 100px;">
                        </div>
                    </div>
                    <hr>
                    <h6 class="fw-bold small text-muted text-uppercase mb-3">Spec (Default Values)</h6>
                    <div class="row g-2">
                        <div class="col-4">
                            <label class="form-label small">Target</label>
                            <input type="number" step="any" class="form-control" name="default_target"
                                id="default_target" placeholder="0.00">
                        </div>
                        <div class="col-4">
                            <label class="form-label small text-success">(+) Plus</label>
                            <input type="number" step="any" class="form-control" name="default_plus" id="default_plus"
                                placeholder="+">
                        </div>
                        <div class="col-4">
                            <label class="form-label small text-danger">(-) Minus</label>
                            <input type="number" step="any" class="form-control" name="default_minus" id="default_minus"
                                placeholder="-">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="save_inspection" class="btn btn-primary rounded-pill px-4 shadow">Save
                        Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let inspectionModal;
    document.addEventListener('DOMContentLoaded', function () {
        inspectionModal = new bootstrap.Modal(document.getElementById('inspectionModal'));
    });

    function openAddModal() {
        document.getElementById('modalTitle').textContent = 'Add Inspection Item';
        document.getElementById('inspection_id').value = '';
        document.getElementById('name_en').value = '';
        document.getElementById('name_th').value = '';
        document.getElementById('default_target').value = '';
        document.getElementById('default_plus').value = '';
        document.getElementById('default_minus').value = '';
        document.getElementById('spec_image').value = '';
        document.getElementById('current_image_preview').classList.add('d-none');
        inspectionModal.show();
    }

    function openEditModal(i) {
        document.getElementById('modalTitle').textContent = 'Edit Inspection Item';
        document.getElementById('inspection_id').value = i.id;
        document.getElementById('name_en').value = i.name_en;
        document.getElementById('name_th').value = i.name_th;
        document.getElementById('unit').value = i.unit;
        document.getElementById('default_target').value = i.default_target;
        document.getElementById('default_plus').value = i.default_plus;
        document.getElementById('default_minus').value = i.default_minus;
        document.getElementById('spec_image').value = '';

        const previewContainer = document.getElementById('current_image_preview');
        const previewImg = document.getElementById('preview_img');

        if (i.spec_image) {
            previewImg.src = 'uploads/specs/' + i.spec_image;
            previewContainer.classList.remove('d-none');
        } else {
            previewContainer.classList.add('d-none');
        }

        inspectionModal.show();
    }
</script>

<?php include '../includes/footer.php'; ?>