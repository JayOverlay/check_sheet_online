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
    // Get summary counts (Only active/pending for this page)
    $total_items = $pdo->query("SELECT COUNT(*) FROM downtime WHERE status NOT IN ('Ready', 'Rejected')")->fetchColumn();
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
                    u_tech.full_name as tech_name, u_tech.username as tech_username,
                    u_lead.full_name as leader_name, u_lead.username as lead_username
            FROM downtime dt
            LEFT JOIN machines m ON dt.ref_id = m.id AND dt.ref_type = 'machine'
            LEFT JOIN tooling t ON dt.ref_id = t.id AND dt.ref_type = 'tooling'
            LEFT JOIN users u_tech ON dt.technician_id = u_tech.id
            LEFT JOIN users u_lead ON dt.leader_id = u_lead.id
            WHERE dt.status NOT IN ('Ready', 'Rejected')
            ORDER BY FIELD(dt.status, 'Reported', 'Waiting for Technician', 'In Progress', 'Technician Finished') ASC, dt.reported_at DESC
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
    <div class="d-flex align-items-center gap-3">
        <div class="btn-group shadow-sm rounded-pill p-1 bg-white">
            <a href="downtime.php" class="btn btn-sm rounded-pill px-3 btn-primary">
                <i class="fas fa-tools me-1"></i> Active Repairs
            </a>
            <a href="downtime_history.php" class="btn btn-sm rounded-pill px-3 btn-light">
                <i class="fas fa-history me-1"></i> Full History
            </a>
        </div>
        <button class="btn btn-danger rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#reportModal">
            <i class="fas fa-exclamation-circle me-2"></i> Report Issue
        </button>
    </div>
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
                        <th class="col-code" style="width: 15%;">Asset Info</th>
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
                                    <div class="small mt-1 d-flex align-items-center flex-wrap gap-2">
                                        <?php
                                        $catColor = 'secondary';
                                        $catIcon = 'tag';
                                        switch ($r['category']) {
                                            case 'SET_UP':
                                                $catColor = 'primary';
                                                $catIcon = 'cog';
                                                break;
                                            case 'DOWN':
                                                $catColor = 'danger';
                                                $catIcon = 'exclamation-circle';
                                                break;
                                            case 'PM':
                                                $catColor = 'success';
                                                $catIcon = 'tools';
                                                break;
                                            case 'PD_IDEL':
                                                $catColor = 'warning';
                                                $catIcon = 'clock';
                                                break;
                                            case 'ENG':
                                                $catColor = 'info';
                                                $catIcon = 'microchip';
                                                break;
                                            case 'CUSTOMER_DOWN':
                                                $catColor = 'dark';
                                                $catIcon = 'user-slash';
                                                break;
                                        }
                                        ?>
                                        <span
                                            class="badge bg-<?php echo $catColor; ?>-subtle text-<?php echo $catColor; ?> border border-<?php echo $catColor; ?>-subtle d-inline-flex align-items-center"
                                            style="font-size: 0.65rem;">
                                            <i class="fas fa-<?php echo $catIcon; ?> me-1"></i>
                                            <?php echo $r['category'] ?: 'General'; ?>
                                        </span>
                                        <span class="text-muted small">•</span>
                                        <span class="text-danger small fw-bold">ID: <?php echo $r['reported_by']; ?></span>
                                        <span class="text-muted small">•</span>
                                        <span
                                            class="text-muted small"><?php echo date('d/m/y H:i', strtotime($r['reported_at'])); ?></span>
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
                                                style="width: 24px; height: 24px; font-size: 0.7rem; font-weight: bold;">
                                                <?php echo $t_initial; ?>
                                            </div>
                                            <div>
                                                <div class="small fw-bold lh-1"><?php echo $r['tech_name']; ?></div>
                                                <div class="text-muted" style="font-size: 0.65rem;">ID:
                                                    <?php echo $r['tech_username']; ?>
                                                </div>
                                            </div>
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
                                        <div class="small text-dark text-truncate" title="Solution: <?php echo $r['solution']; ?>">
                                            <i class="fas fa-wrench me-1"></i> <?php echo $r['solution']; ?>
                                        </div>
                                        <?php if (!empty($r['remarks'])): ?>
                                            <div class="small text-muted text-truncate" title="Remark: <?php echo $r['remarks']; ?>">
                                                <i class="fas fa-comment-alt me-1"></i> <?php echo $r['remarks']; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif ($r['status'] == 'In Progress'): ?>
                                        <div class="text-info fw-bold"><i class="fas fa-play-circle me-1"></i> Starting Work</div>
                                        <div class="text-muted small">
                                            <i class="far fa-clock me-1"></i>
                                            <?php echo date('H:i', strtotime($r['accepted_at'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">--</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex align-items-center justify-content-end gap-1">
                                        <button class="btn btn-sm btn-light rounded-circle border shadow-xs"
                                            onclick="openDetailModal(<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8'); ?>)"
                                            title="View Details">
                                            <i class="fas fa-search-plus text-primary"></i>
                                        </button>
                                        <?php if (($r['status'] == 'Reported' || $r['status'] == 'Waiting for Technician') && ($_SESSION['role'] == 'Technicien' || $_SESSION['role'] == 'admin')): ?>
                                            <form action="../actions/update_downtime.php" method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="accept">
                                                <input type="hidden" name="downtime_id" value="<?php echo $r['id']; ?>">
                                                <button type="submit"
                                                    class="btn btn-sm btn-outline-info rounded-pill px-3 shadow-xs">
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
                                                onclick="openVerifyModal(<?php echo $r['id']; ?>, '<?php echo htmlspecialchars($r['solution']); ?>', '<?php echo htmlspecialchars($r['remarks']); ?>')">
                                                Verify
                                            </button>
                                        <?php endif; ?>
                                    </div>
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
            <form action="../actions/save_downtime.php" method="POST">
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
                            <option value="SET_UP">SET_UP</option>
                            <option value="DOWN" selected>DOWN</option>
                            <option value="PM">PM</option>
                            <option value="PD_IDEL">PD_IDEL</option>
                            <option value="ENG">ENG</option>
                            <option value="CUSTOMER_DOWN">CUSTOMER_DOWN</option>
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
            <form action="../actions/update_downtime.php" method="POST">
                <input type="hidden" name="action" value="finish">
                <input type="hidden" name="downtime_id" id="finish_id">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-bold mb-0 text-primary">Finish Job / ส่งงานซ่อม</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Repair Details / วิธีแก้ไข</label>
                        <textarea class="form-control" name="solution" rows="3" placeholder="ช่างดำเนินการแก้ไขอย่างไร?"
                            required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Remark / บันทึกเพิ่มเติม</label>
                        <textarea class="form-control" name="remarks" rows="2"
                            placeholder="หมายเหตุเพิ่มเติม (ถ้ามี)"></textarea>
                    </div>
                    <div class="alert alert-info small mb-0">
                        <i class="fas fa-info-circle me-1"></i> งานจะถูกส่งไปให้ Leader ตรวจสอบต่อ (Wait Verification)
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
            <form action="../actions/update_downtime.php" method="POST">
                <input type="hidden" name="downtime_id" id="verify_id">
                <!-- Action will be set by button -->

                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-bold mb-0 text-success">Verify Repair / ตรวจสอบงาน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3 p-3 bg-light rounded border">
                        <label class="small fw-bold text-uppercase text-muted">Technician's Solution</label>
                        <div id="tech_solution" class="mt-1 fw-bold text-dark"></div>
                        <div id="tech_remarks_area" class="mt-2 pt-2 border-top" style="display:none;">
                            <label class="small fw-bold text-uppercase text-muted"
                                style="font-size: 0.65rem;">Remarks</label>
                            <div id="tech_remarks" class="small italic text-muted"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Leader Comment</label>
                        <textarea class="form-control" name="leader_comment" id="verify_comment" rows="2"
                            placeholder="บันทึกเพิ่มเติม (จำเป็นต้องระบุหากกด Reject)"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0 justify-content-between">
                    <button type="submit" name="action" value="reject" class="btn btn-outline-danger rounded-pill px-4"
                        onclick="return validateReject()">
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

<!-- 4. Detail Modal -->
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
                    <!-- Left Column: Asset & Reported Info -->
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

                    <!-- Right Column: Status & Technical Info -->
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

                        <!-- Leader Verification Part -->
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
    function openFinishModal(id) {
        document.getElementById('finish_id').value = id;
        new bootstrap.Modal(document.getElementById('finishModal')).show();
    }

    function openVerifyModal(id, solution, remarks) {
        document.getElementById('verify_id').value = id;
        document.getElementById('verify_comment').value = ''; // Clear previous comment
        document.getElementById('tech_solution').innerText = solution;
        const remarksArea = document.getElementById('tech_remarks_area');
        if (remarks && remarks !== '') {
            document.getElementById('tech_remarks').innerText = remarks;
            remarksArea.style.display = 'block';
        } else {
            remarksArea.style.display = 'none';
        }
        new bootstrap.Modal(document.getElementById('verifyModal')).show();
    }

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

    function validateReject() {
        const comment = document.getElementById('verify_comment').value.trim();
        if (comment === '') {
            Swal.fire({
                icon: 'error',
                title: 'ต้องระบุเหตุผล',
                text: 'กรุณากรอกเหตุผลในช่อง Leader Comment ก่อนกด Reject',
                confirmButtonColor: '#ef4444'
            });
            return false;
        }
        return true;
    }

    window.addEventListener('load', function () {
        // Auto-open Report Modal if params exist
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('report')) {
            const reportModalEl = document.getElementById('reportModal');

            // If coming from check form with NG, make modal static (can't dismiss)
            if (urlParams.has('from_check')) {
                reportModalEl.setAttribute('data-bs-backdrop', 'static');
                reportModalEl.setAttribute('data-bs-keyboard', 'false');
            }

            const reportModal = new bootstrap.Modal(reportModalEl);
            reportModal.show();

            // Pre-select machine
            if (urlParams.has('machine_id')) {
                const mid = 'm_' + urlParams.get('machine_id');
                const select = document.querySelector('select[name="target_id"]');
                if (select) {
                    select.value = mid;
                }
            }

            // Pre-fill problem description from NG items
            if (urlParams.has('auto_problem')) {
                const problemTextarea = document.querySelector('textarea[name="problem"]');
                if (problemTextarea) {
                    problemTextarea.value = urlParams.get('auto_problem');
                }
            }

            // Show alert if redirected from check form
            if (urlParams.has('from_check')) {
                Swal.fire({
                    icon: 'warning',
                    title: 'พบรายการ NG!',
                    html: '<p>ระบบตรวจพบปัญหาจากการเช็คเครื่อง</p><p class="fw-bold text-danger">กรุณากรอกรายละเอียดและส่งแจ้งซ่อม</p>',
                    confirmButtonText: 'เข้าใจแล้ว',
                    confirmButtonColor: '#ef4444',
                    allowOutsideClick: false
                });
            }
        }
    });
</script>

<?php include '../includes/footer.php'; ?>