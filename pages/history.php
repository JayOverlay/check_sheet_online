<?php
require_once '../config/database.php';
include '../includes/header.php';

$tab = $_GET['tab'] ?? 'summary';
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
    if ($tab == 'summary') {
        $join = ""; // Handle separately below
    } elseif ($tab == 'machine') {
        $where_clauses[] = "cs.target_id LIKE 'm_%' AND (cs.check_type = 'Daily' OR cs.check_type IS NULL)";
        $join = "JOIN machines m ON SUBSTRING_INDEX(cs.target_id, '_', -1) = m.id";
        $select_fields = "m.machine_code as code, m.machine_name as name";
    } elseif ($tab == 'tooling') {
        $where_clauses[] = "cs.target_id LIKE 't_%' AND (cs.check_type = 'Daily' OR cs.check_type IS NULL)";
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

    if ($tab == 'summary') {
        // Machine Summary Logic
        $countSql = "SELECT COUNT(*) FROM machines WHERE status = 'Active'";
        if (!empty($search_code)) {
            $countSql .= " AND (machine_code LIKE :search OR machine_name LIKE :search)";
        }
        $countStmt = $pdo->prepare($countSql);
        if (!empty($search_code))
            $countStmt->bindValue(':search', "%$search_code%");
        $countStmt->execute();
        $total_items = $countStmt->fetchColumn();
        $total_pages = ceil($total_items / $items_per_page);

        $sql = "SELECT m.id, m.machine_code, m.machine_name, m.product, m.family,
                COUNT(cs.id) as total_checks,
                SUM(CASE WHEN cs.overall_status = 'Pass' THEN 1 ELSE 0 END) as pass_count,
                MAX(cs.created_at) as last_check_date,
                (SELECT overall_status FROM check_sheets WHERE target_id = CONCAT('m_', m.id) ORDER BY created_at DESC LIMIT 1) as last_status
                FROM machines m
                LEFT JOIN check_sheets cs ON CONCAT('m_', m.id) = cs.target_id
                WHERE m.status = 'Active'";

        if (!empty($search_code)) {
            $sql .= " AND (m.machine_code LIKE :search OR m.machine_name LIKE :search)";
        }

        $sql .= " GROUP BY m.id ORDER BY total_checks DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        if (!empty($search_code))
            $stmt->bindValue(':search', "%$search_code%");
        $stmt->bindValue(':limit', (int) $items_per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();
        $summaryData = $stmt->fetchAll();
        $history = $summaryData; // For compat
    } else {
        // Advanced filters for other tabs
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
    }
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
    <div class="d-flex gap-2">
        <button class="btn btn-warning btn-sm rounded-pill px-3 shadow-sm border-0" onclick="showMissingModal()">
            <i class="fas fa-exclamation-triangle me-1"></i> Missing Today
        </button>
        <div class="btn-group shadow-sm rounded-pill p-1 bg-white">
            <a href="?tab=summary"
                class="btn btn-sm rounded-pill px-3 <?php echo $tab == 'summary' ? 'btn-primary' : 'btn-light'; ?>">
                <i class="fas fa-chart-pie me-1"></i> Summary
            </a>
            <a href="?tab=machine"
                class="btn btn-sm rounded-pill px-3 <?php echo $tab == 'machine' ? 'btn-primary' : 'btn-light'; ?>">
                <i class="fas fa-industry me-1"></i> Logs (Machine)
            </a>
            <a href="?tab=tooling"
                class="btn btn-sm rounded-pill px-3 <?php echo $tab == 'tooling' ? 'btn-primary' : 'btn-light'; ?>">
                <i class="fas fa-tools me-1"></i> Logs (Tool)
            </a>
            <a href="?tab=parameter"
                class="btn btn-sm rounded-pill px-3 <?php echo $tab == 'parameter' ? 'btn-primary' : 'btn-light'; ?>">
                <i class="fas fa-sliders-h me-1"></i> Parameter
            </a>
        </div>
    </div>
</div>

<?php if ($tab != 'summary'): ?>
    <!-- Advanced Filter Card (For Logs Only) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-light">
        <div class="card-body p-4">
            <form method="GET" class="row g-3">
                <input type="hidden" name="tab" value="<?php echo $tab; ?>">
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-uppercase text-muted">Start Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm border-0 shadow-sm"
                        style="border-radius: 10px;" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-uppercase text-muted">End Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm border-0 shadow-sm"
                        style="border-radius: 10px;" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-uppercase text-muted">Search Code/Name</label>
                    <input type="text" name="search_code" class="form-control form-control-sm border-0 shadow-sm"
                        style="border-radius: 10px;" placeholder="e.g. MCH-001" value="<?php echo $search_code; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-uppercase text-muted">Status</label>
                    <select name="status" class="form-select form-select-sm border-0 shadow-sm"
                        style="border-radius: 10px;">
                        <option value="">-- All --</option>
                        <option value="Pass" <?php echo $status_filter == 'Pass' ? 'selected' : ''; ?>>Pass</option>
                        <option value="Fail" <?php echo $status_filter == 'Fail' ? 'selected' : ''; ?>>Fail</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-uppercase text-muted">Inspector</label>
                    <input type="text" name="inspector" class="form-control form-control-sm border-0 shadow-sm"
                        style="border-radius: 10px;" placeholder="Name" value="<?php echo $inspector_filter; ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm w-100 rounded-pill shadow">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <!-- Simple Search for Summary -->
    <div class="row mb-4">
        <div class="col-md-4">
            <form method="GET" class="input-group">
                <input type="hidden" name="tab" value="summary">
                <input type="text" name="search_code" class="form-control border-0 shadow-sm rounded-start-pill ps-4"
                    placeholder="Search Machine..." value="<?php echo $search_code; ?>">
                <button type="submit" class="btn btn-primary rounded-end-pill px-4 shadow-sm"><i
                        class="fas fa-search"></i></button>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="card card-premium">
    <div class="card-body p-4">
        <div class="table-responsive">
            <?php if ($tab == 'summary'): ?>
                <!-- SUMMARY VIEW -->
                <table class="table table-custom align-middle">
                    <thead>
                        <tr>
                            <th>Machine</th>
                            <th>Total Checks</th>
                            <th style="width: 250px;">Pass Rate</th>
                            <th>Latest Date</th>
                            <th>Latest Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">No machines found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($history as $s): ?>
                                <?php
                                $rate = $s['total_checks'] > 0 ? round(($s['pass_count'] / $s['total_checks']) * 100) : 0;
                                $rateColor = $rate >= 90 ? 'success' : ($rate >= 70 ? 'warning' : 'danger');
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo $s['machine_code']; ?></div>
                                        <div class="small text-muted"><?php echo $s['machine_name']; ?></div>
                                    </td>
                                    <td><span class="badge bg-light text-dark border px-3"><?php echo $s['total_checks']; ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                                <div class="progress-bar bg-<?php echo $rateColor; ?>"
                                                    style="width: <?php echo $rate; ?>%"></div>
                                            </div>
                                            <span class="small fw-bold text-<?php echo $rateColor; ?>"><?php echo $rate; ?>%</span>
                                        </div>
                                    </td>
                                    <td class="small text-muted">
                                        <?php echo $s['last_check_date'] ? date('d/m/y H:i', strtotime($s['last_check_date'])) : '--'; ?>
                                    </td>
                                    <td>
                                        <?php if ($s['last_status']): ?>
                                            <span
                                                class="badge bg-<?php echo $s['last_status'] == 'Pass' ? 'success' : 'danger'; ?>-subtle text-<?php echo $s['last_status'] == 'Pass' ? 'success' : 'danger'; ?> px-2 py-1 border border-<?php echo $s['last_status'] == 'Pass' ? 'success' : 'danger'; ?>-subtle">
                                                <?php echo $s['last_status']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted italic small">No history</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button onclick="viewMachineLogs(<?php echo $s['id']; ?>)"
                                            class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                            View Logs
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <!-- LOG VIEW -->
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
                                    <h6 class="text-secondary">No records found.</h6>
                                    <p class="small text-muted">Recent inspections will appear here.</p>
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
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
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

<!-- Unified History Modal -->
<div class="modal fade" id="unifiedHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-primary bg-opacity-10 p-4">
                <div class="flex-grow-1">
                    <h5 class="fw-bold mb-1 text-primary" id="unifiedMachineName">Machine Logs</h5>
                    <div class="small text-muted" id="unifiedMachineCode">MCH-XXX</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <!-- History Selector Bar (Only shown for Machine History view) -->
            <div id="historySelectorBar" class="px-4 py-3 bg-white border-bottom d-none">
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <label class="small text-muted fw-bold text-uppercase mb-1 d-block">Select Check Date:</label>
                        <select id="logDateSelector" class="form-select form-select-sm border-0 bg-light rounded-3"
                            onchange="loadLogDetails(this.value)">
                            <!-- Options populated via AJAX -->
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-body p-0">
                <!-- Loader -->
                <div id="unifiedLoading" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>

                <!-- Detail Content -->
                <div id="unifiedContent">
                    <div class="px-4 py-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                        <div class="small">
                            <i class="far fa-user me-1"></i> <span id="unifiedInspector" class="fw-bold">...</span>
                            <span class="mx-2 text-muted">|</span>
                            <i class="far fa-calendar-alt me-1"></i> <span id="unifiedDate"
                                class="text-muted">...</span>
                        </div>
                        <span class="badge bg-primary rounded-pill px-3" id="unifiedCount">0 items</span>
                    </div>

                    <div class="table-responsive" style="max-height: 50vh; overflow-y: auto;">
                        <table class="table align-middle mb-0">
                            <thead class="bg-light text-secondary small text-uppercase"
                                style="position: sticky; top: 0; z-index: 10;">
                                <tr>
                                    <th class="ps-4">Code</th>
                                    <th>Check Item</th>
                                    <th class="text-center">Result</th>
                                    <th>Comment</th>
                                </tr>
                            </thead>
                            <tbody id="unifiedBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-3 bg-light bg-opacity-50">
                <button type="button" class="btn btn-light rounded-pill px-4 shadow-sm border"
                    data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Pending Checks Modal (Missing Today) -->
<div class="modal fade" id="pendingModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-warning bg-opacity-10 p-4">
                <h5 class="fw-bold mb-0 text-warning-emphasis"><i class="fas fa-hourglass-half me-2"></i>Missing Today
                    (Not Checked)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="pendingLoading" class="text-center py-5 d-none">
                    <div class="spinner-border text-warning" role="status"></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-secondary small text-uppercase">
                            <tr>
                                <th class="ps-4">Code</th>
                                <th>Machine Name</th>
                                <th>PF / Family</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody id="pendingBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let unifiedModal, pendingModal;

    document.addEventListener('DOMContentLoaded', function () {
        unifiedModal = new bootstrap.Modal(document.getElementById('unifiedHistoryModal'));
        pendingModal = new bootstrap.Modal(document.getElementById('pendingModal'));
    });

    // View Machine History (Shows history selector + results of latest)
    function viewMachineLogs(machineId) {
        const selector = document.getElementById('logDateSelector');
        const selectorBar = document.getElementById('historySelectorBar');

        selector.innerHTML = '';
        selectorBar.classList.remove('d-none'); // Show selector for history mode

        document.getElementById('unifiedLoading').classList.remove('d-none');
        document.getElementById('unifiedContent').classList.add('d-none');
        unifiedModal.show();

        // 1. Fetch History List
        fetch(`<?php echo BASE_URL; ?>actions/get_machine_logs.php?id=${machineId}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) throw new Error(data.error);

                document.getElementById('unifiedMachineName').textContent = data.machine.machine_name;
                document.getElementById('unifiedMachineCode').textContent = data.machine.machine_code;

                if (!data.logs || data.logs.length === 0) {
                    selector.innerHTML = '<option>No history</option>';
                    document.getElementById('unifiedLoading').classList.add('d-none');
                    document.getElementById('unifiedBody').innerHTML = '<tr><td colspan="4" class="text-center py-5 text-muted">No records.</td></tr>';
                    document.getElementById('unifiedContent').classList.remove('d-none');
                    return;
                }

                data.logs.forEach(log => {
                    const dateStr = new Date(log.created_at).toLocaleString('th-TH');
                    const opt = document.createElement('option');
                    opt.value = log.id;
                    opt.textContent = `${dateStr} - ${log.inspector_name} (${log.overall_status})`;
                    selector.appendChild(opt);
                });

                // 2. Load latest record details automatically
                loadLogDetails(data.logs[0].id);
            })
            .catch(err => {
                alert(err.message || 'Error loading history');
                unifiedModal.hide();
            });
    }

    // Load Specific Check Details into Unified Modal
    function loadLogDetails(sheetId) {
        document.getElementById('unifiedLoading').classList.remove('d-none');
        document.getElementById('unifiedContent').classList.add('d-none');

        fetch(`<?php echo BASE_URL; ?>actions/get_check_sheet_details.php?id=${sheetId}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) throw new Error(data.error);

                document.getElementById('unifiedLoading').classList.add('d-none');
                document.getElementById('unifiedContent').classList.remove('d-none');

                const info = data.header;
                document.getElementById('unifiedInspector').textContent = info.inspector_name;
                document.getElementById('unifiedDate').textContent = new Date(info.created_at).toLocaleString('th-TH');
                document.getElementById('unifiedCount').textContent = data.details.length + ' items';
                document.getElementById('unifiedMachineName').textContent = info.machine_name || 'Details';
                document.getElementById('unifiedMachineCode').textContent = info.machine_code || info.target_id;

                const tbody = document.getElementById('unifiedBody');
                tbody.innerHTML = '';

                data.details.forEach(item => {
                    const isNG = item.result === 'NG';
                    const badgeClass = isNG ? 'bg-danger text-white' : 'bg-success-subtle text-success border border-success-subtle';
                    const row = `
                        <tr class="${isNG ? 'bg-danger bg-opacity-10' : ''}">
                            <td class="ps-4 fw-bold text-secondary small">${item.item_code || '-'}</td>
                            <td>
                                <div class="fw-bold small">${item.name_en || 'N/A'}</div>
                                <div class="text-muted" style="font-size:0.7rem">${item.name_th || ''}</div>
                            </td>
                            <td class="text-center">
                                <span class="badge ${badgeClass} rounded-pill px-3" style="font-size: 0.7rem;">
                                    ${item.result}
                                </span>
                            </td>
                            <td class="text-muted small">${item.comment || '-'}</td>
                        </tr>
                    `;
                    tbody.innerHTML += row;
                });
            });
    }

    // View specific log from "Logs" tab (Standard Detail View)
    function viewDetails(sheetId) {
        document.getElementById('historySelectorBar').classList.add('d-none'); // Hide selector for single view
        unifiedModal.show();
        loadLogDetails(sheetId);
    }

    function showMissingModal() {
        const tbody = document.getElementById('pendingBody');
        const loader = document.getElementById('pendingLoading');
        tbody.innerHTML = '';
        loader.classList.remove('d-none');
        pendingModal.show();

        fetch(`<?php echo BASE_URL; ?>actions/get_pending_checks.php`)
            .then(res => res.json())
            .then(data => {
                loader.classList.add('d-none');
                if (!data.success) return;
                if (data.machines.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-5 text-muted">All machines are checked today!</td></tr>';
                    return;
                }
                data.machines.forEach(m => {
                    tbody.innerHTML += `
                        <tr>
                            <td class="ps-4 fw-bold text-primary">${m.machine_code}</td>
                            <td>${m.machine_name}</td>
                            <td><span class="small text-muted">${m.product} / ${m.family}</span></td>
                            <td class="text-end pe-4">
                                <a href="check_form?machine_id=${m.id}" class="btn btn-sm btn-primary rounded-pill px-3">Check Now</a>
                            </td>
                        </tr>`;
                });
            });
    }

    function confirmDelete(url) {
        Swal.fire({
            title: 'Are you sure?',
            text: "This record will be permanently deleted!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        })
    }
</script>

<?php include '../includes/footer.php'; ?>