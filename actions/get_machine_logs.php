<?php
require_once '../config/database.php';
header('Content-Type: application/json');

$machine_id = $_GET['id'] ?? '';

if (!$machine_id) {
    echo json_encode(['success' => false, 'error' => 'Missing Machine ID']);
    exit();
}

try {
    // Get Machine Info
    $mStmt = $pdo->prepare("SELECT machine_code, machine_name FROM machines WHERE id = ?");
    $mStmt->execute([$machine_id]);
    $machine = $mStmt->fetch(PDO::FETCH_ASSOC);

    if (!$machine) {
        echo json_encode(['success' => false, 'error' => 'Machine not found']);
        exit();
    }

    // Get Logs (Last 100 entries)
    $target_id = "m_" . $machine_id;
    $stmt = $pdo->prepare("
        SELECT id, inspector_name, overall_status, remarks, created_at, check_type
        FROM check_sheets 
        WHERE target_id = ? 
        ORDER BY created_at DESC 
        LIMIT 100
    ");
    $stmt->execute([$target_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'machine' => $machine,
        'logs' => $logs
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>