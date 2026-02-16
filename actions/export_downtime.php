<?php
// Ensure absolutely no whitespace before <?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("HTTP/1.1 403 Forbidden");
    exit("Access Denied");
}

// Get Filters
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

    $sql = "SELECT dt.*, 
                   CASE WHEN dt.ref_type = 'machine' THEN m.machine_code ELSE t.tool_code END as asset_code,
                   CASE WHEN dt.ref_type = 'machine' THEN m.machine_name ELSE t.tool_name END as asset_name,
                   u_tech.full_name as tech_name, u_tech.username as tech_username,
                   u_lead.full_name as lead_name, u_lead.username as lead_username
            FROM downtime dt
            LEFT JOIN machines m ON dt.ref_id = m.id AND dt.ref_type = 'machine'
            LEFT JOIN tooling t ON dt.ref_id = t.id AND dt.ref_type = 'tooling'
            LEFT JOIN users u_tech ON dt.technician_id = u_tech.id
            LEFT JOIN users u_lead ON dt.leader_id = u_lead.id
            $where_sql
            ORDER BY dt.reported_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare filename
    $filename = "downtime_export_" . date('Ymd_His') . ".csv";

    // Clear any buffering
    if (ob_get_level())
        ob_end_clean();

    // Headers for standard CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    // BOM for Excel Thai support
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    // Header Row
    fputcsv($output, [
        'Asset Code',
        'Asset Name',
        'Category',
        'Problem',
        'Reported By',
        'Reported At',
        'Accepted By',
        'Accepted At',
        'Solution',
        'Remarks',
        'Finished At',
        'Verified By',
        'Verified At',
        'Leader Comment',
        'Status',
        'Duration (Text)',
        'Total Minutes'
    ]);

    foreach ($data as $r) {
        $duration_text = '';
        $total_minutes = '';
        if ($r['reported_at'] && $r['fixed_at']) {
            $diff = strtotime($r['fixed_at']) - strtotime($r['reported_at']);
            $total_minutes = floor($diff / 60);

            // Format to 1d 5h 30m style
            if ($diff > 0) {
                $days = floor($diff / 86400);
                $hours = floor(($diff % 86400) / 3600);
                $mins = floor(($diff % 3600) / 60);

                $parts = [];
                if ($days > 0)
                    $parts[] = $days . 'd';
                if ($hours > 0)
                    $parts[] = $hours . 'h';
                if ($mins > 0 || empty($parts))
                    $parts[] = $mins . 'm';
                $duration_text = implode(' ', $parts);
            }
        }

        fputcsv($output, [
            $r['asset_code'],
            $r['asset_name'],
            $r['category'],
            $r['problem'],
            $r['reported_by'],
            $r['reported_at'],
            $r['tech_username'],
            $r['accepted_at'],
            $r['solution'],
            $r['remarks'],
            $r['fixed_at'],
            $r['lead_username'],
            $r['verified_at'],
            $r['leader_comment'],
            $r['status'],
            $duration_text,
            $total_minutes
        ]);
    }

    fclose($output);
    exit();

} catch (Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    echo "Export failed: " . $e->getMessage();
}
?>