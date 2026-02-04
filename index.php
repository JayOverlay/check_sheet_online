<?php
require_once 'config/database.php';
include 'includes/header.php';

// Fetching actual counts
try {
    $machineCount = $pdo->query("SELECT COUNT(*) FROM machines WHERE status = 'Active'")->fetchColumn();
    $toolingCount = $pdo->query("SELECT COUNT(*) FROM tooling WHERE status = 'Active'")->fetchColumn();

    // Pending Checks: Machines that have NOT been checked today
    $pendingSql = "SELECT COUNT(*) FROM machines m 
                   WHERE m.status = 'Active' 
                   AND NOT EXISTS (
                       SELECT 1 FROM check_sheets cs 
                       WHERE cs.target_id = CONCAT('m_', m.id) 
                       AND DATE(cs.created_at) = CURDATE()
                   )";
    $pendingChecks = $pdo->query($pendingSql)->fetchColumn();

    // Fail Checks: Checks with 'Fail' status today
    $failChecks = $pdo->query("SELECT COUNT(*) FROM check_sheets WHERE overall_status = 'Fail' AND DATE(created_at) = CURDATE()")->fetchColumn();

    // Recent History
    $recentSql = "SELECT cs.*, 
                  CASE 
                    WHEN cs.target_id LIKE 'm_%' THEN (SELECT machine_code FROM machines WHERE id = SUBSTRING(cs.target_id, 3))
                    WHEN cs.target_id LIKE 't_%' THEN (SELECT tool_code FROM tooling WHERE id = SUBSTRING(cs.target_id, 3))
                    ELSE cs.target_id
                  END as target_code
                  FROM check_sheets cs 
                  ORDER BY cs.created_at DESC 
                  LIMIT 5";
    $recentHistory = $pdo->query($recentSql)->fetchAll();

} catch (Exception $e) {
    $machineCount = 0;
    $toolingCount = 0;
    $pendingChecks = 0;
    $failChecks = 0;
    $recentHistory = [];
}
?>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card card-premium p-4 h-100">
            <div class="stat-icon stat-primary">
                <i class="fas fa-industry"></i>
            </div>
            <h3 class="fw-bold">
                <?php echo $machineCount; ?>
            </h3>
            <p class="text-muted mb-0">Machines</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-premium p-4 h-100">
            <div class="stat-icon stat-success">
                <i class="fas fa-tools"></i>
            </div>
            <h3 class="fw-bold">
                <?php echo $toolingCount; ?>
            </h3>
            <p class="text-muted mb-0">Total Tooling</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-premium p-4 h-100 cursor-pointer hover-shadow" onclick="showPendingModal()">
            <div class="stat-icon stat-warning">
                <i class="fas fa-clock"></i>
            </div>
            <h3 class="fw-bold">
                <?php echo $pendingChecks; ?>
            </h3>
            <p class="text-muted mb-0">Pending Checks <i class="fas fa-external-link-alt ms-1 small opacity-50"></i></p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-premium p-4 h-100">
            <div class="stat-icon stat-danger">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3 class="fw-bold">
                <?php echo $failChecks; ?>
            </h3>
            <p class="text-muted mb-0">Fail/Critical Items</p>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card card-premium">
            <div class="card-header bg-transparent border-0 p-4 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">Recent Inspections</h5>
                <a href="history" class="btn btn-sm btn-outline-primary rounded-pill">View All</a>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Target</th>
                                <th>Type</th>
                                <th>Inspector</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentHistory)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No recent checks found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentHistory as $row): ?>
                                    <tr>
                                        <td class="small">
                                            <div class="fw-bold"><?php echo date('d/m/Y', strtotime($row['created_at'])); ?>
                                            </div>
                                            <div class="text-muted" style="font-size: 0.75rem;">
                                                <?php echo date('H:i', strtotime($row['created_at'])); ?>
                                            </div>
                                        </td>
                                        <td class="fw-bold text-primary"><?php echo $row['target_code']; ?></td>
                                        <td>
                                            <span class="badge bg-light text-secondary border px-2">
                                                <?php echo $row['check_type'] ?: 'General'; ?>
                                            </span>
                                        </td>
                                        <td class="small"><?php echo $row['inspector_name']; ?></td>
                                        <td>
                                            <?php $isPass = $row['overall_status'] == 'Pass'; ?>
                                            <span
                                                class="badge bg-<?php echo $isPass ? 'success' : 'danger'; ?> rounded-pill px-3">
                                                <i class="fas fa-<?php echo $isPass ? 'check' : 'times'; ?>-circle me-1"></i>
                                                <?php echo $row['overall_status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-light rounded-circle shadow-xs border"
                                                onclick="viewDetails(<?php echo $row['id']; ?>)">
                                                <i class="fas fa-eye text-primary"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-premium h-100">
            <div class="card-header bg-transparent border-0 p-4 pb-0">
                <h5 class="fw-bold mb-0">Quick Actions</h5>
            </div>
            <div class="card-body p-4">
                <button class="btn btn-primary w-100 mb-3 py-3 rounded-4 shadow-sm"
                    onclick="location.href='check_form'">
                    <i class="fas fa-plus me-2"></i> New Inspection
                </button>
                <div class="list-group list-group-flush">
                    <a href="machines" class="list-group-item list-group-item-action border-0 px-0 py-3">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-industry me-3 text-primary"></i>
                                <span>Manage Machines</span>
                            </div>
                            <i class="fas fa-chevron-right small text-muted"></i>
                        </div>
                    </a>
                    <a href="tooling" class="list-group-item list-group-item-action border-0 px-0 py-3">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-tools me-3 text-success"></i>
                                <span>Manage Tooling</span>
                            </div>
                            <i class="fas fa-chevron-right small text-muted"></i>
                        </div>
                    </a>
                    <a href="templates" class="list-group-item list-group-item-action border-0 px-0 py-3">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-list-check me-3 text-warning"></i>
                                <span>Checklist Templates</span>
                            </div>
                            <i class="fas fa-chevron-right small text-muted"></i>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Details Modal (Integrated from History) -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-light p-4">
                <div>
                    <h5 class="fw-bold mb-1" id="modalTargetName">Target Name</h5>
                    <div class="small text-muted">
                        Checked on <span id="modalDate" class="fw-bold"></span> by <span id="modalInspector"></span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="max-height: 60vh; overflow-y: auto;">
                <div id="detailLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
                <div id="detailContent" style="display: none;">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="bg-light text-secondary small text-uppercase">
                                <tr>
                                    <th class="ps-4">Code</th>
                                    <th>Check Item</th>
                                    <th class="text-center">Result</th>
                                    <th>Comment</th>
                                </tr>
                            </thead>
                            <tbody id="detailBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-3">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Pending Checks Modal -->
<div class="modal fade" id="pendingModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-warning bg-opacity-10 p-4">
                <h5 class="fw-bold mb-0 text-warning-emphasis"><i class="fas fa-hourglass-half me-2"></i>Pending
                    Machines (Today)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="px-4 py-3 border-bottom small text-muted bg-light">
                    The following active machines have <strong>not been checked</strong> yet for today
                    (<?php echo date('d/m/Y'); ?>).
                </div>
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
                        <tbody id="pendingBody">
                            <!-- Loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0 p-3">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    let detailModal, pendingModal;

    document.addEventListener('DOMContentLoaded', function () {
        detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
        pendingModal = new bootstrap.Modal(document.getElementById('pendingModal'));
    });

    function showPendingModal() {
        const tbody = document.getElementById('pendingBody');
        const loader = document.getElementById('pendingLoading');

        tbody.innerHTML = '';
        loader.classList.remove('d-none');
        pendingModal.show();

        fetch(`actions/get_pending_checks.php`)
            .then(res => res.json())
            .then(data => {
                loader.classList.add('d-none');
                if (!data.success) { alert(data.error); return; }

                if (data.machines.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-5 text-muted">All good! All active machines have been checked today.</td></tr>';
                    return;
                }

                data.machines.forEach(m => {
                    const row = `
                        <tr>
                            <td class="ps-4 fw-bold text-primary">${m.machine_code}</td>
                            <td>${m.machine_name}</td>
                            <td><span class="small text-muted">${m.product} / ${m.family}</span></td>
                            <td class="text-end pe-4">
                                <a href="check_form?machine_id=${m.id}" class="btn btn-sm btn-primary rounded-pill px-3 shadow-xs">
                                    <i class="fas fa-plus me-1"></i> Check Now
                                </a>
                            </td>
                        </tr>
                    `;
                    tbody.innerHTML += row;
                });
            })
            .catch(err => {
                loader.classList.add('d-none');
                console.error(err);
            });
    }

    function viewDetails(sheetId) {
        document.getElementById('detailLoading').style.display = 'block';
        document.getElementById('detailContent').style.display = 'none';
        document.getElementById('detailBody').innerHTML = '';
        detailModal.show();

        fetch(`actions/get_check_sheet_details.php?id=${sheetId}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) { alert(data.error); detailModal.hide(); return; }
                document.getElementById('detailLoading').style.display = 'none';
                document.getElementById('detailContent').style.display = 'block';

                const info = data.header;
                const targetName = info.machine_name ? (info.machine_name + ' (' + info.machine_code + ')') : info.target_id;
                document.getElementById('modalTargetName').textContent = targetName;
                document.getElementById('modalDate').textContent = new Date(info.created_at).toLocaleString('th-TH');
                document.getElementById('modalInspector').textContent = info.inspector_name;

                const tbody = document.getElementById('detailBody');
                data.details.forEach(item => {
                    const isNG = item.result === 'NG';
                    const badgeClass = isNG ? 'bg-danger text-white' : 'bg-success-subtle text-success border border-success-subtle';
                    const row = `
                        <tr class="${isNG ? 'bg-danger bg-opacity-10' : ''}">
                            <td class="ps-4 fw-bold text-secondary small">${item.item_code || '-'}</td>
                            <td><div class="fw-bold small">${item.name_en || 'N/A'}</div></td>
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
</script>

<?php include 'includes/footer.php'; ?>