<?php
require_once 'config/database.php';

// Auth check removed for Public TV Display

try {
    // 1. Fetch Summary Stats
    $machineCount = $pdo->query("SELECT COUNT(*) FROM machines")->fetchColumn();
    $activeCount = $pdo->query("SELECT COUNT(*) FROM machines WHERE status = 'Active'")->fetchColumn();
    $maintenanceCount = $pdo->query("SELECT COUNT(*) FROM machines WHERE status = 'Maintenance'")->fetchColumn();
    $downCount = $pdo->query("SELECT COUNT(*) FROM machines WHERE status = 'Inactive'")->fetchColumn();

    // 1.1 Specific Repair Stats for Header (Counting ONLY the latest active status per machine)
    // 1.1 Specific Repair Stats for Header (Counting ONLY the latest active status per machine)
    $reportedCount = $pdo->query("SELECT COUNT(*) FROM (
        SELECT ref_id FROM downtime t1 
        WHERE id IN (SELECT MAX(id) FROM downtime WHERE ref_type = 'Machine' AND status NOT IN ('Ready', 'Rejected') GROUP BY ref_id)
        AND status IN ('Reported', 'Waiting for Technician')
    ) as tmp")->fetchColumn();

    $acceptedCount = $pdo->query("SELECT COUNT(*) FROM (
        SELECT ref_id FROM downtime t1 
        WHERE id IN (SELECT MAX(id) FROM downtime WHERE ref_type = 'Machine' AND status NOT IN ('Ready', 'Rejected') GROUP BY ref_id)
        AND status = 'In Progress'
    ) as tmp")->fetchColumn();

    $finishedCount = $pdo->query("SELECT COUNT(*) FROM (
        SELECT ref_id FROM downtime t1 
        WHERE id IN (SELECT MAX(id) FROM downtime WHERE ref_type = 'Machine' AND status NOT IN ('Ready', 'Rejected') GROUP BY ref_id)
        AND status = 'Technician Finished'
    ) as tmp")->fetchColumn();

    // 2. Count Pending Daily Checks (Active machines not checked today)
    $pendingChecks = $pdo->query("SELECT COUNT(*) FROM machines m
WHERE m.status = 'Active'
AND NOT EXISTS (
SELECT 1 FROM check_sheets cs
WHERE cs.target_id = CONCAT('m_', m.id)
AND DATE(cs.created_at) = CURDATE()
)")->fetchColumn();

    // 3. Fetch Machines with Downtime Status and Custom Sorting
// Priority: 1. Reported (Down), 2. Accepted (In Repair), 3. Tech Finished (Pending Verification), 4. Others
    $sql = "SELECT m.*, d.status as dt_status
FROM machines m
LEFT JOIN (
SELECT t1.ref_id, t1.status
FROM downtime t1
INNER JOIN (
SELECT ref_id, MAX(id) as max_id
FROM downtime
WHERE ref_type = 'Machine' AND status != 'Verified'
GROUP BY ref_id
) t2 ON t1.id = t2.max_id
) d ON m.id = d.ref_id
ORDER BY
CASE
WHEN d.status IN ('Reported', 'Waiting for Technician') THEN 1
WHEN d.status = 'In Progress' THEN 2
WHEN d.status = 'Technician Finished' THEN 3
WHEN m.status = 'Maintenance' THEN 4
WHEN m.status = 'Inactive' THEN 5
ELSE 6
END ASC,
m.machine_code ASC";

    $stmt = $pdo->query($sql);
    $machines = $stmt->fetchAll();

    // 3. Last Check Status for each machine (Simplified for TV)
// We want to know if it has been checked today
    $checkStatusStmt = $pdo->query("SELECT target_id, overall_status, created_at
FROM check_sheets
WHERE target_id LIKE 'm_%'
AND DATE(created_at) = CURDATE()
ORDER BY created_at DESC");
    $dailyChecks = [];
    while ($row = $checkStatusStmt->fetch()) {
        $mid = str_replace('m_', '', $row['target_id']);
        if (!isset($dailyChecks[$mid])) {
            $dailyChecks[$mid] = $row;
        }
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TV Dashboard - Machine Status</title>
    <!-- Bootstrap 5 CSS -->
    <link href="<?php echo BASE_URL; ?>assets/libs/bootstrap/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/libs/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/inter.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/prompt.css">
    <style>
        /* Fonts loaded via local CSS files */

        :root {
            --bg-light: #f1f5f9;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --text-main: #1e293b;
            --text-dim: #64748b;
        }

        body {
            font-family: 'Inter', 'Prompt', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-main);
            margin: 0;
            padding: 20px;
            overflow-x: hidden;
        }

        .tv-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: var(--card-bg);
            border-radius: 20px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }

        .stat-group {
            display: flex;
            gap: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 0 10px;
            border-right: 1px solid var(--border-color);
        }

        .stat-item:last-child {
            border-right: none;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .machine-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 15px;
        }

        .machine-card {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            position: relative;
            transition: all 0.3s ease;
            height: 100%;
            box-shadow: 0 2px 4px -1px rgb(0 0 0 / 0.05);
        }

        .machine-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
            z-index: 10;
        }

        .status-Active::before {
            background: #10b981;
        }

        .status-Maintenance::before {
            background: #f59e0b;
        }

        .status-Inactive::before {
            background: #ef4444;
        }

        .status-Verify::before {
            background: #2563eb;
        }

        .image-container {
            width: 100%;
            height: 130px;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid var(--border-color);
            padding: 5px;
        }

        .machine-img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            /* Shows full image */
        }

        .machine-body {
            padding: 12px;
        }

        .machine-code {
            font-size: 1.1rem;
            font-weight: 700;
            color: #2563eb;
            margin-bottom: 0px;
        }

        .machine-name {
            font-size: 0.85rem;
            color: var(--text-main);
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 10px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 10px;
        }

        .detail-item {
            background: #f8fafc;
            padding: 6px 8px;
            border-radius: 6px;
            border: 1px solid #f1f5f9;
        }

        .detail-label {
            font-size: 0.6rem;
            color: var(--text-dim);
            text-transform: uppercase;
            font-weight: 600;
        }

        .detail-val {
            font-size: 0.8rem;
            font-weight: 700;
        }

        .check-status {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 8px;
            border-top: 1px solid var(--border-color);
        }

        .clock-container {
            font-family: 'Inter', sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            color: #1e293b;
        }

        /* Animations for "Down" or "Maintenance" items to catch eye on TV */
        @keyframes pulse-red {
            0% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }

        .status-Inactive {
            animation: pulse-red 2s infinite;
            border-color: rgba(239, 68, 68, 0.5);
        }

        .tag-badge {
            font-size: 0.65rem;
            padding: 3px 8px;
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-dim);
        }
    </style>
</head>

<body>
    <div class="tv-header">
        <div class="d-flex align-items-center gap-4">
            <h1 class="fw-bold mb-0 text-primary">MACHINE MONITORING</h1>
            <div id="liveClock" class="clock-container ms-4">00:00:00</div>
        </div>

        <div class="stat-group">
            <div class="stat-item text-primary">
                <div class="stat-value"><?php echo $activeCount; ?></div>
                <div class="stat-label">Running</div>
            </div>
            <div class="stat-item text-danger">
                <div class="stat-value"><?php echo $reportedCount; ?></div>
                <div class="stat-label">Down</div>
            </div>
            <div class="stat-item text-warning">
                <div class="stat-value"><?php echo $acceptedCount; ?></div>
                <div class="stat-label">Repairing</div>
            </div>
            <div class="stat-item text-info">
                <div class="stat-value"><?php echo $finishedCount; ?></div>
                <div class="stat-label">Verify</div>
            </div>
            <div class="stat-item text-secondary">
                <div class="stat-value"><?php echo $pendingChecks; ?></div>
                <div class="stat-label">Pending Check</div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <!-- Exit button removed -->
        </div>
    </div>

    <div class="machine-grid">
        <?php foreach ($machines as $m):
            // Default values
            $cardClass = 'status-' . $m['status'];
            $displayStatus = $m['status'];
            $statusColor = ($m['status'] == 'Active' ? 'success' : ($m['status'] == 'Maintenance' ? 'warning' : 'danger'));

            // Override display based on Repair Status (Near Real-time)
            if (in_array($m['dt_status'], ['Reported', 'Waiting for Technician'])) {
                $cardClass = 'status-Inactive'; // Red
                $displayStatus = 'DOWN';
                $statusColor = 'danger';
            } elseif ($m['dt_status'] == 'In Progress') {
                $cardClass = 'status-Maintenance'; // Yellow
                $displayStatus = 'IN REPAIR';
                $statusColor = 'warning';
            } elseif ($m['dt_status'] == 'Technician Finished') {
                $cardClass = 'status-Verify'; // Blue
                $displayStatus = 'PENDING VERIFY';
                $statusColor = 'primary';
            }

            $check = $dailyChecks[$m['id']] ?? null;
            $image = !empty($m['image_path']) ? BASE_URL . $m['image_path'] : BASE_URL . 'assets/img/no-image.svg';
            ?>
            <div class="machine-card <?php echo $cardClass; ?>">
                <div class="image-container">
                    <img src="<?php echo $image; ?>" class="machine-img" alt="Machine">
                </div>
                <div class="machine-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="machine-code">
                            <?php echo $m['machine_code']; ?>
                        </div>
                        <div class="tag-badge">
                            <?php echo $m['product']; ?>
                        </div>
                    </div>
                    <div class="machine-name">
                        <?php echo $m['machine_name']; ?>
                    </div>

                    <div class="details-grid">
                        <div class="detail-item">
                            <div class="detail-label">Status</div>
                            <div class="detail-val text-<?php echo $statusColor; ?>">
                                <i class="fas fa-circle me-1 small"></i>
                                <?php echo $displayStatus; ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Repair Status</div>
                            <div class="detail-val">
                                <?php
                                if (in_array($m['dt_status'], ['Reported', 'Waiting for Technician']))
                                    echo '<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i> แจ้งซ่อม</span>';
                                elseif ($m['dt_status'] == 'In Progress')
                                    echo '<span class="text-info"><i class="fas fa-wrench me-1"></i> ช่างรับงาน</span>';
                                elseif ($m['dt_status'] == 'Technician Finished')
                                    echo '<span class="text-primary"><i class="fas fa-check-double me-1"></i> รอ Verify</span>';
                                else
                                    echo '<span class="text-success"><i class="fas fa-check-circle me-1"></i> ปกติ</span>';
                                ?>
                            </div>
                        </div>
                    </div>

                    <div class="check-status">
                        <div class="small">
                            <span class="detail-label d-block">Check (Today)</span>
                            <?php if ($check): ?>
                                <span
                                    class="fw-bold <?php echo $check['overall_status'] == 'Pass' ? 'text-success' : 'text-danger'; ?>">
                                    <i
                                        class="fas fa-<?php echo $check['overall_status'] == 'Pass' ? 'check' : 'times'; ?>-circle me-1"></i>
                                    <?php echo $check['overall_status']; ?>
                                </span>
                                <span class="text-muted" style="font-size: 0.7rem;">(
                                    <?php echo date('H:i', strtotime($check['created_at'])); ?>)
                                </span>
                            <?php else: ?>
                                <span class="text-warning fw-bold">
                                    <i class="fas fa-exclamation-triangle me-1"></i> PENDING
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="text-end">
                            <div class="detail-label">S/N</div>
                            <div class="small text-muted font-monospace">
                                <?php echo $m['serial_number']; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        function updateClock() {
            const now = new Date();
            const time = now.toLocaleTimeString('th-TH', { hour12: false });
            document.getElementById('liveClock').innerText = time;
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Auto Refresh every 10 seconds to keep data near real-time
        setTimeout(() => {
            window.location.reload();
        }, 10000);
    </script>
</body>

</html>