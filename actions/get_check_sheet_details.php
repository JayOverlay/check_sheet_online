<?php
require_once '../config/database.php';
header('Content-Type: application/json');

$id = $_GET['id'] ?? '';

if (!$id) {
    echo json_encode(['error' => 'Missing ID']);
    exit();
}

try {
    // 1. Get Header Info (Machine info resolved from target_id)
    $stmt = $pdo->prepare("
        SELECT cs.*,
        CASE 
            WHEN cs.target_id LIKE 'm_%' THEN (SELECT machine_code FROM machines WHERE id = SUBSTRING(cs.target_id, 3))
            ELSE 'Unknown'
        END as machine_code,
        CASE 
            WHEN cs.target_id LIKE 'm_%' THEN (SELECT machine_name FROM machines WHERE id = SUBSTRING(cs.target_id, 3))
            ELSE 'Unknown'
        END as machine_name
        FROM check_sheets cs 
        WHERE cs.id = ?
    ");
    $stmt->execute([$id]);
    $header = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$header) {
        echo json_encode(['error' => 'Record not found']);
        exit();
    }

    // 2. Get Details
    $detailStmt = $pdo->prepare("
        SELECT csd.*, ci.item_code, ci.name_en, ci.name_th
        FROM check_sheet_details csd
        INNER JOIN check_items ci ON csd.item_id = ci.id
        WHERE csd.check_sheet_id = ?
        ORDER BY ci.item_code ASC
    ");
    $detailStmt->execute([$id]);
    $details = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'header' => $header,
        'details' => $details
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>