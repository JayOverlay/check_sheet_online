<?php
require_once '../config/database.php';
include '../includes/header.php';

// Fetch Check History (Latest 50 records)
$sql = "SELECT cs.*, 
        CASE 
            WHEN cs.target_id LIKE 'm_%' THEN (SELECT machine_code FROM machines WHERE id = SUBSTRING(cs.target_id, 3))
            ELSE 'Unknown'
        END as machine_code,
        CASE 
            WHEN cs.target_id LIKE 'm_%' THEN (SELECT machine_name FROM machines WHERE id = SUBSTRING(cs.target_id, 3))
            ELSE 'Unknown'
        END as machine_name
        FROM check_sheets cs 
        ORDER BY cs.created_at DESC 
        LIMIT 50";
$history = $pdo->query($sql)->fetchAll();
?>

<div class="row mb-4 animate__animated animate__fadeInDown">
    <div class="col-md-8">
        <h2 class="fw-bold text-primary"><i class="fas fa-history me-2"></i>History Report</h2>
        <p class="text-muted">History of machine inspections</p>
    </div>
    <div class="col-md-4 text-md-end">
        <a href="machines.php" class="btn btn-primary rounded-pill shadow-sm">
            <i class="fas fa-plus me-2"></i> New Check
        </a>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <strong>Success!</strong> Inspection recorded successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-lg rounded-4 overflow-hidden animate__animated animate__fadeInUp">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-secondary">
                    <tr>
                        <th class="ps-4">Date/Time</th>
                        <th>Machine</th>
                        <th>Inspector</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">No history found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($history as $h): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-secondary">
                                    <?php echo date('d M Y, H:i', strtotime($h['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">
                                        <?php echo $h['machine_code']; ?>
                                    </div>
                                    <div class="small text-muted">
                                        <?php echo $h['machine_name']; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <i class="fas fa-user-circle me-1"></i>
                                        <?php echo $h['inspector_name']; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($h['overall_status'] == 'Pass'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">
                                            <i class="fas fa-check-circle me-1"></i> Pass
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill">
                                            <i class="fas fa-times-circle me-1"></i> Fail
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-outline-primary btn-sm rounded-pill px-3"
                                        onclick="viewDetails(<?php echo $h['id']; ?>)">
                                        <i class="fas fa-eye me-1"></i> View Details
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

<!-- Details Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-light p-4">
                <div>
                    <h5 class="fw-bold mb-1" id="modalMachineName">Machine Name</h5>
                    <div class="small text-muted">
                        Checked on <span id="modalDate" class="fw-bold"></span> by <span id="modalInspector"></span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="bg-white text-secondary small text-uppercase">
                            <tr>
                                <th class="ps-4">Code</th>
                                <th>Check Item</th>
                                <th class="text-center">Result</th>
                                <th>Comment</th>
                            </tr>
                        </thead>
                        <tbody id="detailBody">
                            <!-- Content loaded via AJAX -->
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
    const detailModal = new bootstrap.Modal(document.getElementById('detailModal'));

    function viewDetails(sheetId) {
        // Show loading state
        document.getElementById('detailBody').innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Loading details...</td></tr>';
        detailModal.show();

        fetch(`<?php echo BASE_URL; ?>actions/get_check_sheet_details.php?id=${sheetId}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    return;
                }

                // Update Header
                const info = data.header;
                document.getElementById('modalMachineName').textContent = info.machine_name + ' (' + info.machine_code + ')';
                document.getElementById('modalDate').textContent = info.created_at;
                document.getElementById('modalInspector').textContent = info.inspector_name;

                // Update Body
                const tbody = document.getElementById('detailBody');
                tbody.innerHTML = '';

                if (data.details.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No details recorded.</td></tr>';
                    return;
                }

                data.details.forEach(item => {
                    const isNG = item.result === 'NG';
                    const badgeClass = isNG ? 'bg-danger text-white' : 'bg-success bg-opacity-10 text-success';
                    const icon = isNG ? 'times-circle' : 'check-circle';

                    const row = `
                        <tr class="${isNG ? 'bg-danger bg-opacity-10' : ''}">
                            <td class="ps-4 fw-bold text-secondary">${item.item_code}</td>
                            <td>
                                <div class="fw-bold">${item.name_en}</div>
                                <div class="small text-muted">${item.name_th}</div>
                            </td>
                            <td class="text-center">
                                <span class="badge ${badgeClass} rounded-pill px-3">
                                    <i class="fas fa-${icon} me-1"></i> ${item.result}
                                </span>
                            </td>
                            <td class="text-muted small">${item.comment || '-'}</td>
                        </tr>
                    `;
                    tbody.innerHTML += row;
                });
            })
            .catch(err => {
                console.error(err);
                document.getElementById('detailBody').innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger">Failed to load details.</td></tr>';
            });
    }
</script>

<?php include '../includes/footer.php'; ?>