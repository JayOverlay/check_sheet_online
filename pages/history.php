<?php
require_once '../config/database.php';
include '../includes/header.php';

$tab = $_GET['tab'] ?? 'machine';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$search_code = $_GET['search_code'] ?? '';
$status_filter = $_GET['status'] ?? '';
$inspector_filter = $_GET['inspector'] ?? '';

// Pagination Setup
$items_per_page = 15;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $items_per_page;

$history = [];
$total_items = 0;
$total_pages = 0;

try {
    $where_clauses = [];
    $params = [];

    // Base filters
    if ($tab == 'machine') {
        $where_clauses[] = "cs.target_id LIKE 'm_%' AND cs.check_type = 'Daily'";
        $join = "JOIN machines m ON SUBSTRING_INDEX(cs.target_id, '_', -1) = m.id";
        $select_fields = "m.machine_code as code, m.machine_name as name";
    } elseif ($tab == 'tooling') {
        $where_clauses[] = "cs.target_id LIKE 't_%' AND cs.check_type = 'Daily'";
        $join = "JOIN tooling t ON SUBSTRING_INDEX(cs.target_id, '_', -1) = t.id";
        $select_fields = "t.tool_code as code, t.tool_name as name";
    } elseif ($tab == 'parameter') {
        $where_clauses[] = "cs.check_type = 'Parameter'";
        $join = "JOIN machines m ON SUBSTRING_INDEX(cs.target_id, '_', -1) = m.id";
        $select_fields = "m.machine_code as code, m.machine_name as name";
    } elseif ($tab == 'inspection') {
        $where_clauses[] = "cs.check_type = 'Inspection'";
        $join = "JOIN machines m ON SUBSTRING_INDEX(cs.target_id, '_', -1) = m.id";
        $select_fields = "m.machine_code as code, m.machine_name as name";
    } elseif ($tab == 'customer') {
        $where_clauses[] = "cs.check_type = 'Customer'";
        $join = "JOIN machines m ON SUBSTRING_INDEX(cs.target_id, '_', -1) = m.id";
        $select_fields = "m.machine_code as code, m.machine_name as name";
    }

    // Advanced filters
    if (!empty($start_date)) {
        $where_clauses[] = "DATE(cs.created_at) >= ?";
        $params[] = $start_date;
    }
    if (!empty($end_date)) {
        $where_clauses[] = "DATE(cs.created_at) <= ?";
        $params[] = $end_date;
    }
    if (!empty($status_filter)) {
        $where_clauses[] = "cs.overall_status = ?";
        $params[] = $status_filter;
    }
    if (!empty($inspector_filter)) {
        $where_clauses[] = "cs.inspector_name LIKE ?";
        $params[] = "%$inspector_filter%";
    }
    if (!empty($search_code)) {
        if ($tab == 'tooling') {
            $where_clauses[] = "(t.tool_code LIKE ? OR t.tool_name LIKE ?)";
        } else {
            $where_clauses[] = "(m.machine_code LIKE ? OR m.machine_name LIKE ?)";
        }
        $params[] = "%$search_code%";
        $params[] = "%$search_code%";
    }

    $where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

    // Total Count
    $countSql = "SELECT COUNT(*) FROM check_sheets cs $join $where_sql";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total_items = $countStmt->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);

    // Paginated Data
    $sql = "SELECT cs.*, $select_fields 
            FROM check_sheets cs 
            $join 
            $where_sql 
            ORDER BY cs.created_at DESC
            LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key + 1, $val);
    }
    $stmt->bindValue(count($params) + 1, (int) $items_per_page, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, (int) $offset, PDO::PARAM_INT);
    $stmt->execute();
    $history = $stmt->fetchAll();
} catch (Exception $e) {
    // Handle error
}

// Function to generate pagination URL
function getPaginatedUrl($pageNo)
{
    $params = $_GET;
    $params['page'] = $pageNo;
    return '?' . http_build_query($params);
}

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Check Sheet History</h4>
    <div class="btn-group shadow-sm rounded-pill p-1 bg-white">
        <a href="?tab=machine"
            class="btn btn-sm rounded-pill px-3 <?php echo $tab == 'machine' ? 'btn-primary' : 'btn-light'; ?>">
            <i class="fas fa-industry me-1"></i> Machine
        </a>
        <a href="?tab=tooling"
            class="btn btn-sm rounded-pill px-3 <?php echo $tab == 'tooling' ? 'btn-primary' : 'btn-light'; ?>">
            <i class="fas fa-tools me-1"></i> Tooling
        </a>
        <a href="?tab=parameter"
            class="btn btn-sm rounded-pill px-3 <?php echo $tab == 'parameter' ? 'btn-primary' : 'btn-light'; ?>">
            <i class="fas fa-sliders-h me-1"></i> Parameter
        </a>
        <a href="?tab=inspection"
            class="btn btn-sm rounded-pill px-3 <?php echo $tab == 'inspection' ? 'btn-primary' : 'btn-light'; ?>">
            <i class="fas fa-microscope me-1"></i> Inspection
        </a>
        <a href="?tab=customer"
            class="btn btn-sm rounded-pill px-3 <?php echo $tab == 'customer' ? 'btn-primary' : 'btn-light'; ?>">
            <i class="fas fa-user-check me-1"></i> Customer
        </a>
    </div>
</div>

<!-- Advanced Filter Card -->
<div class="card border-0 shadow-sm rounded-4 mb-4 bg-light">
    <div class="card-body p-4">
        <form method="GET" class="row g-3">
            <input type="hidden" name="tab" value="<?php echo $tab; ?>">
            <div class="col-md-2">
                <label class="form-label small fw-bold text-uppercase text-muted">Start Date</label>
                <input type="date" name="start_date" class="form-control form-control-sm border-0 shadow-sm"
                    value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-uppercase text-muted">End Date</label>
                <input type="date" name="end_date" class="form-control form-control-sm border-0 shadow-sm"
                    value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-uppercase text-muted">Search Code/Name</label>
                <input type="text" name="search_code" class="form-control form-control-sm border-0 shadow-sm"
                    placeholder="e.g. MCH-001" value="<?php echo $search_code; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-uppercase text-muted">Status</label>
                <select name="status" class="form-select form-select-sm border-0 shadow-sm">
                    <option value="">-- All Status --</option>
                    <option value="Pass" <?php echo $status_filter == 'Pass' ? 'selected' : ''; ?>>Pass</option>
                    <option value="Fail" <?php echo $status_filter == 'Fail' ? 'selected' : ''; ?>>Fail</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-uppercase text-muted">Inspector</label>
                <input type="text" name="inspector" class="form-control form-control-sm border-0 shadow-sm"
                    placeholder="Name" value="<?php echo $inspector_filter; ?>">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-sm w-100 rounded-pill shadow">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
        <?php if (!empty($start_date) || !empty($end_date) || !empty($search_code) || !empty($status_filter) || !empty($inspector_filter)): ?>
            <div class="mt-2 text-end">
                <a href="?tab=<?php echo $tab; ?>" class="text-decoration-none small text-danger fw-bold">
                    <i class="fas fa-times-circle"></i> Clear Filters
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card card-premium">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th>Date / Time</th>
                        <?php if ($tab == 'machine' || $tab == 'parameter' || $tab == 'inspection'): ?>
                            <th>Machine Code</th>
                        <?php else: ?>
                            <th>Tool Code</th>
                        <?php endif; ?>
                        <th>Name</th>
                        <th>Inspector</th>
                        <th>Status</th>
                        <th>Remarks</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted mb-3"><i class="fas fa-history fa-3x opacity-25"></i></div>
                                <h6 class="text-secondary">No <?php echo ucfirst($tab); ?> records found.</h6>
                                <p class="small text-muted">Recent inspections and checks will appear here.</p>
                                <a href="check_form" class="btn btn-sm btn-primary rounded-pill mt-2">Fill Check Sheet</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($history as $h): ?>
                            <tr class="align-middle border-bottom">
                                <td class="small">
                                    <div class="fw-bold"><?php echo date('d/m/Y', strtotime($h['created_at'])); ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;">
                                        <?php echo date('H:i', strtotime($h['created_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        <?php echo $h['code']; ?>
                                    </span>
                                </td>
                                <td class="small fw-medium"><?php echo $h['name']; ?></td>
                                <td class="small"><?php echo $h['inspector_name']; ?></td>
                                <td>
                                    <?php
                                    $statusClass = $h['overall_status'] == 'Pass' ? 'success' : 'danger';
                                    $icon = $h['overall_status'] == 'Pass' ? 'check-circle' : 'times-circle';
                                    ?>
                                    <span
                                        class="badge bg-<?php echo $statusClass; ?>-subtle text-<?php echo $statusClass; ?> border border-<?php echo $statusClass; ?>-subtle px-3 fw-bold">
                                        <i class="fas fa-<?php echo $icon; ?> me-1"></i> <?php echo $h['overall_status']; ?>
                                    </span>
                                </td>
                                <td class="small text-muted" style="max-width: 200px;"><?php echo $h['remarks']; ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-light rounded-pill px-3 shadow-xs border"
                                        onclick="viewDetails(<?php echo $h['id']; ?>)">
                                        <i class="fas fa-eye me-1 text-primary"></i> View
                                    </button>
                                    <?php if ($_SESSION['role'] == 'admin'): ?>
                                        <button class="btn btn-sm btn-light rounded-circle ms-1 text-danger"
                                            onclick="confirmDelete('delete_history?id=<?php echo $h['id']; ?>')">
                                            <i class="fas fa-trash"></i>
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
                    of <span class="fw-bold"><?php echo $total_items; ?></span> records
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link shadow-none" href="<?php echo getPaginatedUrl($page - 1); ?>"><i
                                    class="fas fa-chevron-left"></i></a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link shadow-none" href="<?php echo getPaginatedUrl($i); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link shadow-none" href="<?php echo getPaginatedUrl($page + 1); ?>"><i
                                    class="fas fa-chevron-right"></i></a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function viewDetails(id) {
        alert("Viewing details for Check Sheet #" + id);
    }
</script>

<?php include '../includes/footer.php'; ?>