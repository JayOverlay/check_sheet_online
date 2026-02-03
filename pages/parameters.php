<?php
require_once '../config/database.php';
include '../includes/header.php';

// Handle Add / Edit Master Parameter
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_param'])) {
    $id = $_POST['id'] ?? '';
    $name_en = $_POST['name_en'] ?? '';
    $name_th = $_POST['name_th'] ?? '';
    $unit = $_POST['unit'] ?? '';
    $default_target = $_POST['default_target'] ?? '';
    $default_plus = $_POST['default_plus'] ?? 0;
    $default_minus = $_POST['default_minus'] ?? 0;

    try {
        if (empty($id)) {
            $stmt = $pdo->prepare("INSERT INTO parameters_master (name_en, name_th, unit, default_target, default_plus, default_minus) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name_en, $name_th, $unit, $default_target, $default_plus, $default_minus]);
        } else {
            $stmt = $pdo->prepare("UPDATE parameters_master SET name_en = ?, name_th = ?, unit = ?, default_target = ?, default_plus = ?, default_minus = ? WHERE id = ?");
            $stmt->execute([$name_en, $name_th, $unit, $default_target, $default_plus, $default_minus, $id]);
        }
        header("Location: parameters?success=1");
        exit();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM parameters_master WHERE id = ?")->execute([$id]);
        header("Location: parameters?deleted=1");
        exit();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$params = $pdo->query("SELECT * FROM parameters_master ORDER BY name_en ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Parameter Master</h4>
    <button class="btn btn-primary rounded-pill px-4 shadow-sm" onclick="openAddModal()">
        <i class="fas fa-plus me-2"></i> Add Parameter
    </button>
</div>

<div class="card card-premium">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th>Parameter Name (EN/TH)</th>
                        <th>Unit</th>
                        <th class="text-center">Target</th>
                        <th class="text-center text-success">(+) Plus</th>
                        <th class="text-center text-danger">(-) Minus</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($params)): ?>
                        <tr>
                            <td colspan="3" class="text-center py-5 text-muted">No parameters defined.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($params as $p): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-primary">
                                        <?php echo $p['name_en']; ?>
                                    </div>
                                    <div class="small text-muted">
                                        <?php echo $p['name_th']; ?>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border">
                                        <?php echo $p['unit'] ?: '-'; ?>
                                    </span></td>
                                <td class="text-center fw-bold text-primary"><?php echo $p['default_target'] ?: '-'; ?></td>
                                <td class="text-center text-success fw-semibold"><?php echo ($p['default_plus'] != 0) ? '+' . $p['default_plus'] : '0'; ?></td>
                                <td class="text-center text-danger fw-semibold"><?php echo ($p['default_minus'] != 0) ? '-' . $p['default_minus'] : '0'; ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-light rounded-circle me-1"
                                        onclick='openEditModal(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES, "UTF-8"); ?>)'>
                                        <i class="fas fa-edit text-primary"></i>
                                    </button>
                                    <a href="?delete=<?php echo $p['id']; ?>" class="btn btn-sm btn-light rounded-circle"
                                        onclick="return confirm('Delete this parameter?')">
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

<!-- Modal -->
<div class="modal fade" id="paramModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST">
                <input type="hidden" name="id" id="param_id">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-bold mb-0" id="modalTitle">Add Parameter</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Name (English)</label>
                        <input type="text" class="form-control" name="name_en" id="name_en" required
                            placeholder="e.g. Temperature">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Name (Thai)</label>
                        <input type="text" class="form-control" name="name_th" id="name_th" placeholder="เช่น อุณหภูมิ">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Unit</label>
                        <input type="text" class="form-control" name="unit" id="unit" placeholder="e.g. °C, bar, mm">
                    </div>
                    <hr>
                    <h6 class="fw-bold small text-muted text-uppercase mb-3">Default Values (Pre-fill in Machines)</h6>
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
                    <button type="submit" name="save_param" class="btn btn-primary rounded-pill px-4 shadow">Save
                        Parameter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let paramModal;
    document.addEventListener('DOMContentLoaded', function () {
        paramModal = new bootstrap.Modal(document.getElementById('paramModal'));
    });

    function openAddModal() {
        document.getElementById('modalTitle').textContent = 'Add Parameter';
        document.getElementById('param_id').value = '';
        document.getElementById('name_en').value = '';
        document.getElementById('name_th').value = '';
        document.getElementById('unit').value = '';
        document.getElementById('default_target').value = '';
        document.getElementById('default_plus').value = '';
        document.getElementById('default_minus').value = '';
        paramModal.show();
    }

    function openEditModal(p) {
        document.getElementById('modalTitle').textContent = 'Edit Parameter';
        document.getElementById('param_id').value = p.id;
        document.getElementById('name_en').value = p.name_en;
        document.getElementById('name_th').value = p.name_th;
        document.getElementById('unit').value = p.unit;
        document.getElementById('default_target').value = p.default_target;
        document.getElementById('default_plus').value = p.default_plus;
        document.getElementById('default_minus').value = p.default_minus;
        paramModal.show();
    }
</script>

<?php include '../includes/footer.php'; ?>