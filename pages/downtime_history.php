<?php
require_once '../config/database.php';
include '../includes/header.php';

// Pagination & Filters
$items_per_page = 20;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $items_per_page;

$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';

try {
    $where_clauses = [];
    $params = [];

    if (!empty($start_date)) {
        $where_clauses[] = "DATE(dt.reported_at) >= ?";
        $params[] = $start_date;
    }
    if (!empty($end_date)) {
        $where_clauses[] = "DATE(dt.reported_at) <= ?";
        $params[] = $end_date;
    }
    if (!empty($category_filter)) {
        $where_clauses[] = "dt.category = ?";
        $params[] = $category_filter;
    }
    if (!empty($search)) {
        $where_clauses[] = "(m.machine_code LIKE ? OR t.tool_code LIKE ? OR dt.problem LIKE ? OR dt.reported_by LIKE ?)";
        $s = "%$search%";
        $params[] = $s;
        $params[] = $s;
        $params[] = $s;
        $params[] = $s;
    }

    $where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

    // Total Count for Pagination
    $count_sql = "SELECT COUNT(*) FROM downtime dt 
                  LEFT JOIN machines m ON dt.ref_id = m.id AND dt.ref_type = 'machine'
                  LEFT JOIN tooling t ON dt.ref_id = t.id AND dt.ref_type = 'tooling'
                  $where_sql";
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($params);
    $total_items = $stmt_count->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);

    // Fetch Records with all User Details and Timestamps
    $sql = "SELECT dt.*, 
                   CASE WHEN dt.ref_type = 'machine' THEN m.machine_code ELSE t.tool_code END as code,
                   CASE WHEN dt.ref_type = 'machine' THEN m.machine_name ELSE t.tool_name END as name,
                   u_tech.full_name as tech_name, u_tech.username as tech_username,
                   u_lead.full_name as leader_name, u_lead.username as lead_username
            FROM downtime dt
            LEFT JOIN machines m ON dt.ref_id = m.id AND dt.ref_type = 'machine'
            LEFT JOIN tooling t ON dt.ref_id = t.id AND dt.ref_type = 'tooling'
            LEFT JOIN users u_tech ON dt.technician_id = u_tech.id
            LEFT JOIN users u_lead ON dt.leader_id = u_lead.id
            $where_sql
            ORDER BY dt.reported_at DESC
            LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v)
        $stmt->bindValue($k + 1, $v);
    $stmt->bindValue(count($params) + 1, (int) $items_per_page, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, (int) $offset, PDO::PARAM_INT);
    $stmt->execute();
    $history = $stmt->fetchAll();

} catch (Exception $e) {
    $error = $e->getMessage();
}

function format_dt($dt)
{
    return $dt ? date('d/m/y H:i', strtotime($dt)) : '-';
}

function get_time_only($dt)
{
    return $dt ? date('H:i', strtotime($dt)) : '';
}

function format_smart_dt($dt, $base_dt)
{
    if (!$dt)
        return '-';
    $target = strtotime($dt);
    $base = strtotime($base_dt);

    // If same day as reported at, just show time. Otherwise show date + time
    if (date('Y-m-d', $target) === date('Y-m-d', $base)) {
        return date('H:i', $target);
    } else {
        return date('d/m H:i', $target);
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Downtime & Repair History</h4>
        <div class="text-muted small">ประวัติการแจ้งซ่อมและรหัสพนักงานที่เกี่ยวข้องในทุกขั้นตอน</div>
    </div>
    <div class="btn-group shadow-sm rounded-pill p-1 bg-white">
        <a href="downtime.php" class="btn btn-sm rounded-pill px-3 btn-light">
            <i class="fas fa-tools me-1"></i> Active Repairs
        </a>
        <a href="downtime_history.php" class="btn btn-sm rounded-pill px-3 btn-primary">
            <i class="fas fa-history me-1"></i> Full History
        </a>
    </div>
</div>

<div class="card card-premium mb-4 border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label small fw-bold text-uppercase text-muted">Date Range</label>
                <div class="input-group input-group-sm">
                    <input type="date" name="start_date" class="form-control rounded-start-pill border-light bg-light"
                        value="<?php echo $start_date; ?>">
                    <input type="date" name="end_date" class="form-control rounded-end-pill border-light bg-light"
                        value="<?php echo $end_date; ?>">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-uppercase text-muted">Category</label>
                <select name="category" class="form-select form-select-sm rounded-pill border-light bg-light">
                    <option value="">All Categories</option>
                    <option value="SET_UP" <?php echo $category_filter == 'SET_UP' ? 'selected' : ''; ?>>SET_UP</option>
                    <option value="DOWN" <?php echo $category_filter == 'DOWN' ? 'selected' : ''; ?>>DOWN</option>
                    <option value="PM" <?php echo $category_filter == 'PM' ? 'selected' : ''; ?>>PM</option>
                    <option value="PD_IDEL" <?php echo $category_filter == 'PD_IDEL' ? 'selected' : ''; ?>>PD_IDEL
                    </option>
                    <option value="ENG" <?php echo $category_filter == 'ENG' ? 'selected' : ''; ?>>ENG</option>
                    <option value="CUSTOMER_DOWN" <?php echo $category_filter == 'CUSTOMER_DOWN' ? 'selected' : ''; ?>>
                        CUSTOMER_DOWN</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-uppercase text-muted">Search Asset/Problem</label>
                <input type="text" name="search" class="form-control form-control-sm rounded-pill border-light bg-light"
                    placeholder="Machine code, name, or problem..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 flex-grow-1">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
                <a href="../actions/export_downtime.php?<?php echo $_SERVER['QUERY_STRING']; ?>&t=<?php echo time(); ?>"
                    class="btn btn-success btn-sm rounded-pill px-3">
                    <i class="fas fa-file-csv me-1"></i> Export to CSV
                </a>
                <a href="downtime_history.php" class="btn btn-light btn-sm rounded-pill px-3 border"
                    title="Clear Search">
                    <i class="fas fa-sync-alt"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card card-premium shadow-lg border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.75rem;">
                <thead class="bg-light text-muted text-uppercase small">
                    <tr>
                        <th class="ps-4 col-code">Asset / Problem</th>
                        <th>Category</th>
                        <th>1. Reported<br><small>(ผู้แจ้งซ่อม)</small></th>
                        <th>2. Accepted<br><small>(ช่างรับงาน)</small></th>
                        <th>3. Finished<br><small>(ช่างซ่อมเสร็จ)</small></th>
                        <th>4. Verified<br><small>(หลีดตรวจสอบ)</small></th>
                        <th>MTTR<br><small>(ซ่อมกี่นาที)</small></th>
                        <th class="text-center pe-4">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">No history found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($history as $r): ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <button
                                            class="btn btn-sm btn-light rounded-circle border p-0 d-flex align-items-center justify-content-center shadow-xs"
                                            style="width:28px; height:28px;"
                                            onclick="openDetailModal(<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8'); ?>)"
                                            title="View Details">
                                            <i class="fas fa-search-plus text-primary small"></i>
                                        </button>
                                        <div>
                                            <div class="fw-bold text-primary">
                                                <?php echo $r['code']; ?>
                                            </div>
                                            <div class="text-muted text-truncate" style="max-width: 120px;"
                                                title="<?php echo $r['problem']; ?>">
                                                <?php echo $r['problem']; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $catColor = 'secondary';
                                    switch ($r['category']) {
                                        case 'SET_UP':
                                            $catColor = 'primary';
                                            break;
                                        case 'DOWN':
                                            $catColor = 'danger';
                                            break;
                                        case 'PM':
                                            $catColor = 'success';
                                            break;
                                        case 'PD_IDEL':
                                            $catColor = 'warning';
                                            break;
                                        case 'ENG':
                                            $catColor = 'info';
                                            break;
                                        case 'CUSTOMER_DOWN':
                                            $catColor = 'dark';
                                            break;
                                    }
                                    ?>
                                    <span
                                        class="badge bg-<?php echo $catColor; ?>-subtle text-<?php echo $catColor; ?> border border-<?php echo $catColor; ?>-subtle"
                                        style="font-size: 0.65rem;">
                                        <?php echo $r['category'] ?: 'General'; ?>
                                    </span>
                                </td>
                                <!-- 1. Reported -->
                                <td>
                                    <div class="fw-bold text-danger">ID: <?php echo $r['reported_by']; ?></div>
                                    <div class="text-muted small"><?php echo format_dt($r['reported_at']); ?></div>
                                </td>

                                <!-- 3. Accepted -->
                                <td>
                                    <?php if ($r['accepted_at']): ?>
                                        <div class="fw-bold text-info"><?php echo $r['tech_username'] ?: 'System'; ?></div>
                                        <div class="text-muted small">
                                            <?php echo format_smart_dt($r['accepted_at'], $r['reported_at']); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted italic">--:--</span>
                                    <?php endif; ?>
                                </td>
                                <!-- 3. Finished -->
                                <td>
                                    <?php if ($r['fixed_at']): ?>
                                        <div class="fw-bold text-success"><?php echo $r['tech_username'] ?: 'System'; ?></div>
                                        <div class="text-muted small mb-1">
                                            <?php echo format_smart_dt($r['fixed_at'], $r['reported_at']); ?>
                                        </div>
                                        <div class="small text-dark border-top pt-1" style="max-width: 150px; font-size: 0.65rem;">
                                            <i class="fas fa-wrench me-1 text-muted"></i> <?php echo $r['solution']; ?>
                                        </div>
                                        <?php if ($r['remarks']): ?>
                                            <div class="small text-muted italic" style="max-width: 150px; font-size: 0.6rem;">
                                                <i class="fas fa-comment me-1"></i> <?php echo $r['remarks']; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted italic">--:--</span>
                                    <?php endif; ?>
                                </td>
                                <!-- 5. Verified -->
                                <td>
                                    <?php if ($r['verified_at']): ?>
                                        <div class="fw-bold text-dark"><?php echo $r['lead_username'] ?: 'Admin'; ?></div>
                                        <div class="text-muted small"><?php echo get_time_only($r['verified_at']); ?></div>
                                    <?php else: ?>
                                        <span class="text-muted italic">--:--</span>
                                    <?php endif; ?>
                                </td>
                                <!-- MTTR (Repair Time: Fixed - Accepted) -->
                                <td>
                                    <?php
                                    if ($r['accepted_at'] && $r['fixed_at']) {
                                        $repair_diff = strtotime($r['fixed_at']) - strtotime($r['accepted_at']);
                                        $mins = floor($repair_diff / 60);
                                        $color = ($mins > 60) ? 'danger' : (($mins > 30) ? 'warning' : 'success');
                                        echo "<span class='badge bg-{$color}-subtle text-{$color} border border-{$color}-subtle'>{$mins} min</span>";
                                    } else {
                                        echo '<span class="text-muted small">N/A</span>';
                                    }
                                    ?>
                                </td>
                                <td class="text-center pe-4">
                                    <?php
                                    $stColor = 'secondary';
                                    $stName = $r['status'];
                                    if ($r['status'] == 'Ready') {
                                        $stColor = 'success';
                                        $stName = 'Closed';
                                    } elseif ($r['status'] == 'Reported') {
                                        $stColor = 'danger';
                                        $stName = 'New';
                                    } elseif ($r['status'] == 'In Progress') {
                                        $stColor = 'info';
                                    } elseif ($r['status'] == 'Technician Finished') {
                                        $stColor = 'primary';
                                        $stName = 'Wait Verification';
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $stColor; ?> rounded-pill px-3" style="font-size: 0.7rem;">
                                        <?php echo $stName; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="p-4 border-top d-flex justify-content-between align-items-center">
                <div class="text-muted small">Showing
                    <?php echo $offset + 1; ?>-<?php echo min($offset + $items_per_page, $total_items); ?> of
                    <?php echo $total_items; ?> records
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link shadow-none"
                                    href="?page=<?php echo $i; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-light border-0 p-4">
                <div>
                    <h5 class="fw-bold mb-0">Ticket Detail / รายละเอียดงาน</h5>
                    <div id="detail_ticket_id" class="text-muted small"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-white">
                <div class="row g-4">
                    <!-- Left Column -->
                    <div class="col-md-6 border-end">
                        <h6 class="fw-bold text-primary mb-3 border-bottom pb-2"><i
                                class="fas fa-info-circle me-2"></i>Reported Info</h6>
                        <div class="mb-3">
                            <label class="small text-muted text-uppercase fw-bold">Asset</label>
                            <div id="det_asset" class="fw-bold h5 text-dark mb-0"></div>
                            <div id="det_asset_name" class="small text-muted"></div>
                        </div>
                        <div class="mb-3">
                            <label class="small text-muted text-uppercase fw-bold">Category</label>
                            <div id="det_category"></div>
                        </div>
                        <div class="mb-3">
                            <label class="small text-muted text-uppercase fw-bold">Problem Description</label>
                            <div id="det_problem"
                                class="p-3 bg-danger-subtle rounded border border-danger-subtle text-danger fw-medium">
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="small text-muted text-uppercase fw-bold">Reporter</label>
                            <div class="d-flex align-items-center">
                                <div class="bg-danger text-white rounded-circle p-2 me-2 d-flex align-items-center justify-content-center"
                                    style="width:32px; height:32px; font-size: 0.8rem;">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div>
                                    <div id="det_reported_by" class="fw-bold lh-1 text-dark"></div>
                                    <div id="det_reported_at" class="small text-muted mt-1"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <h6 class="fw-bold text-primary mb-3 border-bottom pb-2"><i class="fas fa-tools me-2"></i>Repair
                            Process</h6>
                        <div class="mb-3">
                            <label class="small text-muted text-uppercase fw-bold">Status</label>
                            <div id="det_status"></div>
                        </div>

                        <!-- Technician Part -->
                        <div id="tech_detail_section" class="mb-3" style="display:none;">
                            <label class="small text-muted text-uppercase fw-bold">Technician</label>
                            <div class="d-flex align-items-center mb-2">
                                <div class="bg-primary text-white rounded-circle p-2 me-2 d-flex align-items-center justify-content-center"
                                    style="width:32px; height:32px; font-size: 0.8rem;">
                                    <i class="fas fa-wrench"></i>
                                </div>
                                <div id="det_tech_name" class="fw-bold text-dark"></div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <div class="small text-muted">Accepted At:</div>
                                    <div id="det_accepted_at" class="small fw-bold"></div>
                                </div>
                                <div class="col-6">
                                    <div class="small text-muted">Finished At:</div>
                                    <div id="det_fixed_at" class="small fw-bold"></div>
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="small text-muted text-uppercase fw-bold"
                                    style="font-size: 0.65rem;">Solution</label>
                                <div id="det_solution"
                                    class="p-2 bg-success-subtle rounded border border-success-subtle text-success small fw-medium">
                                </div>
                            </div>
                            <div id="det_remarks_box" class="mb-2" style="display:none;">
                                <label class="small text-muted text-uppercase fw-bold"
                                    style="font-size: 0.65rem;">Remarks</label>
                                <div id="det_remarks" class="small italic text-muted p-2 border rounded bg-light"></div>
                            </div>
                        </div>

                        <!-- Leader Part -->
                        <div id="lead_detail_section" class="p-3 bg-light rounded border" style="display:none;">
                            <label class="small text-muted text-uppercase fw-bold">Leader Verification</label>
                            <div class="d-flex align-items-center mb-2">
                                <div class="bg-success text-white rounded-circle p-2 me-2 d-flex align-items-center justify-content-center"
                                    style="width:32px; height:32px; font-size: 0.8rem;">
                                    <i class="fas fa-check-double"></i>
                                </div>
                                <div id="det_lead_name" class="fw-bold text-dark"></div>
                            </div>
                            <div class="small text-muted">Verified At:</div>
                            <div id="det_verified_at" class="small fw-bold mb-2"></div>
                            <label class="small text-muted text-uppercase fw-bold" style="font-size: 0.65rem;">Leader
                                Comment</label>
                            <div id="det_lead_comment" class="small text-dark p-2 border rounded bg-white"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-3 bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    function openDetailModal(data) {
        document.getElementById('detail_ticket_id').innerText = 'ID: #' + data.id;
        document.getElementById('det_asset').innerText = data.code;
        document.getElementById('det_asset_name').innerText = data.name;
        document.getElementById('det_category').innerText = data.category || 'General';
        document.getElementById('det_problem').innerText = data.problem;
        document.getElementById('det_reported_by').innerText = 'ID: ' + data.reported_by;
        document.getElementById('det_reported_at').innerText = data.reported_at;

        // Status Badge
        const statusEl = document.getElementById('det_status');
        let statusClass = 'secondary';
        if (data.status === 'Reported') statusClass = 'danger';
        else if (data.status === 'In Progress') statusClass = 'info';
        else if (data.status === 'Technician Finished') statusClass = 'primary';
        else if (data.status === 'Ready') statusClass = 'success';
        statusEl.innerHTML = `<span class="badge bg-${statusClass} rounded-pill px-3">${data.status}</span>`;

        // Technician Section
        if (data.accepted_at || data.tech_name) {
            document.getElementById('tech_detail_section').style.display = 'block';
            document.getElementById('det_tech_name').innerText = (data.tech_name || 'System') + (data.tech_username ? ' (' + data.tech_username + ')' : '');
            document.getElementById('det_accepted_at').innerText = data.accepted_at || '-';
            document.getElementById('det_fixed_at').innerText = data.fixed_at || '-';
            document.getElementById('det_solution').innerText = data.solution || '-';

            const remarksBox = document.getElementById('det_remarks_box');
            if (data.remarks) {
                document.getElementById('det_remarks').innerText = data.remarks;
                remarksBox.style.display = 'block';
            } else {
                remarksBox.style.display = 'none';
            }
        } else {
            document.getElementById('tech_detail_section').style.display = 'none';
        }

        // Leader Section
        if (data.verified_at || data.leader_name) {
            document.getElementById('lead_detail_section').style.display = 'block';
            document.getElementById('det_lead_name').innerText = (data.leader_name || 'Admin') + (data.lead_username ? ' (' + data.lead_username + ')' : '');
            document.getElementById('det_verified_at').innerText = data.verified_at || '-';
            document.getElementById('det_lead_comment').innerText = data.leader_comment || '-';
        } else {
            document.getElementById('lead_detail_section').style.display = 'none';
        }

        new bootstrap.Modal(document.getElementById('detailModal')).show();
    }
</script>

<?php include '../includes/footer.php'; ?>