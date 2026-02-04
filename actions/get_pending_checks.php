<?php
require_once '../config/database.php';
header('Content-Type: application/json');

try {
    // Current date logic (Today)
    // You can extend this to accept a 'date' parameter if needed

    $sql = "SELECT m.id, m.machine_code, m.machine_name, m.product, m.family, m.status
            FROM machines m 
            WHERE m.status = 'Active' 
            AND NOT EXISTS (
                SELECT 1 FROM check_sheets cs 
                WHERE cs.target_id = CONCAT('m_', m.id) 
                AND DATE(cs.created_at) = CURDATE()
            )
            ORDER BY m.machine_code ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'count' => count($pending),
        'machines' => $pending
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>