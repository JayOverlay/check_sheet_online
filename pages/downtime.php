<?php
require_once '../config/database.php';
include '../includes/header.php';

// Pagination Setup
$items_per_page = 15;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $items_per_page;

// Fetch Filters for Report Modal
$machines = $pdo->query("SELECT * FROM machines WHERE status != 'Inactive'")->fetchAll();
$tools = $pdo->query("SELECT * FROM tooling WHERE status != 'Inactive'")->fetchAll();

$records = [];
try {
    // Get summary counts
    $total_items = $pdo->query("SELECT COUNT(*) FROM downtime")->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);

    $total_reported = $pdo->query("SELECT COUNT(*) FROM downtime WHERE status = 'Reported'")->fetchColumn();
    $total_waiting = $pdo->query("SELECT COUNT(*) FROM downtime WHERE status = 'Waiting for Technician'")->fetchColumn();
    $total_progress = $pdo->query("SELECT COUNT(*) FROM downtime WHERE status = 'In Progress'")->fetchColumn();
    $total_finished = $pdo->query("SELECT COUNT(*) FROM downtime WHERE status = 'Technician Finished'")->fetchColumn();
    $total_ready = $pdo->query("SELECT COUNT(*) FROM downtime WHERE status = 'Ready'")->fetchColumn();

    // Fetch records with joins for names
    $sql = "SELECT dt.*, 
                   CASE WHEN dt.ref_type = 'machine' THEN m.machine_code ELSE t.tool_code END as code,
                   CASE WHEN dt.ref_type = 'machine' THEN m.machine_name ELSE t.tool_name END as name,
                   u_tech.full_name as tech_name,
                   u_lead.full_name as leader_name
            FROM downtime dt
            LEFT JOIN machines m ON dt.ref_id = m.id AND dt.ref_type = 'machine'
            LEFT JOIN tooling t ON dt.ref_id = t.id AND dt.ref_type = 'tooling'
            LEFT JOIN users u_tech ON dt.technician_id = u_tech.id
            LEFT JOIN users u_lead ON dt.leader_id = u_lead.id
            ORDER BY FIELD(dt.status, 'Reported', 'Waiting for Technician', 'In Progress', 'Technician Finished', 'Ready') ASC, dt.reported_at DESC
            LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, (int) $items_per_page, PDO::PARAM_INT);
    $stmt->bindValue(2, (int) $offset, PDO::PARAM_INT);
    $stmt->execute();
    $records = $stmt->fetchAll();
} catch (Exception $e) {
    echo $e->getMessage();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Downtime & Repair Tracking</h4>
        <div class="text-muted small">Manage machine breakdown and maintenance requests</div>
    </div>
    <button class="btn btn-danger rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#reportModal">
        <i class="fas fa-exclamation-circle me-2"></i> Report Issue
    </button>
</div>

<!-- Status Cards -->
<!-- Status Cards -->
<div class="row g-2 mb-4 row-cols-2 row-cols-md-4">
    <div class="col">
        <div class="card card-premium p-3 border-start border-4 border-danger h-100">
            <div class="d-flex justify-content-between align-items-center h-100">
                <div>
                    <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">1. แจ้งซ่อม</div>
                    <div class="h4 mb-0 fw-bold text-danger"><?php echo $total_reported; ?></div>
                </div>
                <i class="fas fa-exclamation-circle fa-2x text-danger opacity-25"></i>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card card-premium p-3 border-start border-4 border-warning h-100">
            <div class="d-flex justify-content-between align-items-center h-100">
                <div>
                    <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">2. รอซ่อม /
                        กำลังซ่อม</div>
                    <div class="h4 mb-0 fw-bold text-warning"><?php echo $total_waiting + $total_progress; ?></div>
                </div>
                <i class="fas fa-tools fa-2x text-warning opacity-25"></i>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card card-premium p-3 border-start border-4 border-primary h-100">
            <div class="d-flex justify-content-between align-items-center h-100">
                <div>
                    <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">3. รอตรวจสอบ</div>
                    <div class="h4 mb-0 fw-bold text-primary"><?php echo $total_finished; ?></div>
                </div>
                <i class="fas fa-clipboard-check fa-2x text-primary opacity-25"></i>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card card-premium p-3 border-start border-4 border-success h-100">
            <div class="d-flex justify-content-between align-items-center h-100">
                <div>
                    <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">4. พร้อมใช้งาน</div>
                    <div class="h4 mb-0 fw-bold text-success"><?php echo $total_ready; ?></div>
                </div>
                <i class="fas fa-check-circle fa-2x text-success opacity-25"></i>
            </div>
        </div>
    </div>
</div>

<div class="card card-premium">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th style="width: 15%;">Asset Info</th>
                        <th style="width: 25%;">Problem</th>
                        <th style="width: 15%;">Technician</th>
                        <th style="width: 10%;">Status</th>
                        <th style="width: 20%;">Solution / Verify</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">No downtime records found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $r): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-primary"><?php echo $r['code']; ?></div>
                                    <div class="small text-muted mb-1"><?php echo $r['name']; ?></div>
                                    <span class="badge bg-light text-dark border"><?php echo ucfirst($r['ref_type']); ?></span>
                                </td>
                                <td>
                                    <div class="text-truncate-2" title="<?php echo $r['problem']; ?>">
                                        <?php echo $r['problem']; ?>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        <i class="fas fa-tag me-1"></i> <?php echo $r['category'] ?: 'General'; ?>
                                        <span class="mx-1">•</span>
                                        <?php echo $r['reported_by']; ?>
                                        <span class="mx-1">•</span>
                                        <?php echo date('d/m/y H:i', strtotime($r['reported_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($r['tech_name']): ?>
                                        <div class="d-flex align-items-center">
                                            <?php
                                            $t_initial = strtoupper(substr($r['tech_name'], 0, 1));
                                            $t_colors = ['primary', 'success', 'danger', 'warning', 'info', 'dark'];
                                            $t_bg = $t_colors[ord($t_initial) % count($t_colors)];
                                            ?>
                                            <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-<?php echo $t_bg; ?> text-white me-2"
                                                style="width: 24px; height: 24px; font-size: 0.8rem; font-weight: bold;">
                                                <?php echo $t_initial; ?>
                                            </div>
                                            <span class="small fw-bold"><?php echo $r['tech_name']; ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small italic">-- Unassigned --</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusName = $r['status'];
                                    $statusColor = 'secondary';

                                    if ($r['status'] == 'Reported') {
                                        $statusColor = 'danger';
                                        $statusName = 'แจ้งซ่อม';
                                    } elseif ($r['status'] == 'Waiting for Technician') {
                                        $statusColor = 'warning';
                                        $statusName = 'รอช่างรับงาน';
                                    } elseif ($r['status'] == 'In Progress') {
                                        $statusColor = 'info';
                                        $statusName = 'กำลังซ่อม';
                                    } elseif ($r['status'] == 'Technician Finished') {
                                        $statusColor = 'primary';
                                        $statusName = 'รอตรวจสอบ';
                                    } elseif ($r['status'] == 'Ready') {
                                        $statusColor = 'success';
                                        $statusName = 'พร้อมใช้งาน';
                                    }
                                    ?>
                                    <span
                                        class="badge bg-<?php echo $statusColor; ?>-subtle text-<?php echo $statusColor; ?> border border-<?php echo $statusColor; ?>-subtle px-2 py-1">
                                        <?php echo $statusName; ?>
                                    </span>
                                </td>
                                <td class="small">
                                    <?php if ($r['status'] == 'Ready'): ?>
                                        <div class="text-success fw-bold"><i class="fas fa-check-double me-1"></i> Ready</div>
                                        <div class="text-muted"><?php echo $r['leader_comment']; ?></div>
                                    <?php elseif ($r['status'] == 'Technician Finished'): ?>
                                        <div class="text-primary fw-bold">Wait Verification</div>
                                        <div class="text-muted text-truncate" title="<?php echo $r['solution']; ?>">
                                            <?php echo $r['solution']; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">--</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($r['status'] == 'Reported' && ($_SESSION['role'] == 'leader' || $_SESSION['role'] == 'admin' || $_SESSION['role'] == 'Technicien')): ?>
                                        <form action="update_downtime.php" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="call_tech">
                                            <input type="hidden" name="downtime_id" value="<?php echo $r['id']; ?>">
                                            <button type="submit"
                                                class="btn btn-sm btn-outline-warning rounded-pill px-3 shadow-xs">
                                                Call Tech
                                            </button>
                                        </form>
                                    <?php elseif ($r['status'] == 'Waiting for Technician' && ($_SESSION['role'] == 'Technicien' || $_SESSION['role'] == 'admin')): ?>
                                        <form action="update_downtime.php" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="accept">
                                            <input type="hidden" name="downtime_id" value="<?php echo $r['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-info rounded-pill px-3 shadow-xs">
                                                Take Job
                                            </button>
                                        </form>
                                    <?php elseif ($r['status'] == 'In Progress' && ($_SESSION['role'] == 'Technicien' || $_SESSION['role'] == 'admin')): ?>
                                        <button class="btn btn-sm btn-primary rounded-pill px-3 shadow-xs"
                                            onclick="openFinishModal(<?php echo $r['id']; ?>)">
                                            Finish Job
                                        </button>
                                    <?php elseif ($r['status'] == 'Technician Finished' && ($_SESSION['role'] == 'leader' || $_SESSION['role'] == 'admin')): ?>
                                        <button class="btn btn-sm btn-success rounded-pill px-3 shadow-xs"
                                            onclick="openVerifyModal(<?php echo $r['id']; ?>, '<?php echo htmlspecialchars($r['solution']); ?>')">
                                            Verify
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-light rounded-circle border" disabled title="View Only">
                                            <i class="fas fa-eye text-muted"></i>
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

<!-- 1. Report Issue Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="save_downtime.php" method="POST">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-bold mb-0">Report Issue / แจ้งซ่อม</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Target Asset</label>
                        <select class="form-select" name="target_id" required>
                            <option value="">-- Select Machine or Tool --</option>
                            <optgroup label="Machines">
                                <?php foreach ($machines as $m): ?>
                                    <option value="m_<?php echo $m['id']; ?>">
                                        <?php echo $m['machine_code'] . ' - ' . $m['machine_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Tooling">
                                <?php foreach ($tools as $t): ?>
                                    <option value="t_<?php echo $t['id']; ?>">
                                        <?php echo $t['tool_code'] . ' - ' . $t['tool_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Category</label>
                        <select class="form-select" name="category" required>
                            <option value="Mechanical">Mechanical</option>
                            <option value="Electrical">Electrical</option>
                            <option value="Software">Software</option>
                            <option value="Process">Process</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Problem Description</label>
                        <textarea class="form-control" name="problem" rows="3" placeholder="Describe the issue..."
                            required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 shadow">Report Issue</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. Finish Job Modal -->
<div class="modal fade" id="finishModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="update_downtime.php" method="POST">
                <input type="hidden" name="action" value="finish">
                <input type="hidden" name="downtime_id" id="finish_id">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-bold mb-0 text-primary">Finish Job / ส่งงานซ่อม</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Solution / วิธีแก้ไข</label>
                        <textarea class="form-control" name="solution" rows="4" placeholder="How did you fix it?"
                            required></textarea>
                    </div>
                    <div class="alert alert-info small mb-0">
                        <i class="fas fa-info-circle me-1"></i> Job will be sent to Leader for verification.
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 3. Verify Modal -->
<div class="modal fade" id="verifyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="update_downtime.php" method="POST">
                <input type="hidden" name="downtime_id" id="verify_id">
                <!-- Action will be set by button -->

                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-bold mb-0 text-success">Verify Repair / ตรวจสอบงาน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3 p-3 bg-light rounded border">
                        <label class="small fw-bold text-uppercase text-muted">Technician's Solution</label>
                        <div id="tech_solution" class="mt-1"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Leader Comment</label>
                        <textarea class="form-control" name="leader_comment" rows="2" placeholder="Optional comments..."
                            required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0 justify-content-between">
                    <button type="submit" name="action" value="reject" class="btn btn-outline-danger rounded-pill px-4">
                        <i class="fas fa-times me-1"></i> Reject & Re-open
                    </button>
                    <button type="submit" name="action" value="verify" class="btn btn-success rounded-pill px-4 shadow">
                        <i class="fas fa-check me-1"></i> Verify & Close
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openFinishModal(id) {
        document.getElementById('finish_id').value = id;
        new bootstrap.Modal(document.getElementById('finishModal')).show();
    }

    function openVerifyModal(id, solution) {
        document.getElementById('verify_id').value = id;
        document.getElementById('tech_solution').innerText = solution;
        new bootstrap.Modal(document.getElementById('verifyModal')).show();
    }
</script>

<?php include '../includes/footer.php'; ?>