<?php
require_once 'config/database.php';
include 'includes/header.php';

// Fetching actual counts
try {
    $machineCount = $pdo->query("SELECT COUNT(*) FROM machines")->fetchColumn();
    $toolingCount = $pdo->query("SELECT COUNT(*) FROM tooling")->fetchColumn();
    $pendingChecks = 0; // Replace with actual logic when history table is ready
    $failChecks = 0;    // Replace with actual logic when history table is ready
} catch (Exception $e) {
    $machineCount = 0;
    $toolingCount = 0;
    $pendingChecks = 0;
    $failChecks = 0;
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
        <div class="card card-premium p-4 h-100">
            <div class="stat-icon stat-warning">
                <i class="fas fa-clock"></i>
            </div>
            <h3 class="fw-bold">
                <?php echo $pendingChecks; ?>
            </h3>
            <p class="text-muted mb-0">Pending Checks</p>
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
                            <tr>
                                <td>2024-05-20 08:30</td>
                                <td>MCH-001</td>
                                <td><span class="badge bg-light text-primary border">Machine</span></td>
                                <td>John Doe</td>
                                <td><span class="badge bg-success">Pass</span></td>
                                <td><button class="btn btn-sm btn-light"><i
                                            class="fas fa-eye text-primary"></i></button></td>
                            </tr>
                            <tr>
                                <td>2024-05-20 09:15</td>
                                <td>TOOL-A5</td>
                                <td><span class="badge bg-light text-secondary border">Tooling</span></td>
                                <td>Sarah W.</td>
                                <td><span class="badge bg-danger">Fail</span></td>
                                <td><button class="btn btn-sm btn-light"><i
                                            class="fas fa-eye text-primary"></i></button></td>
                            </tr>
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

<?php include 'includes/footer.php'; ?>