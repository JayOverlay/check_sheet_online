<?php
ob_start();
require_once '../config/database.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'No ID provided']);
    exit;
}

$id = $_GET['id'];

try {
    // 1. Machine Details
    $stmt = $pdo->prepare("SELECT * FROM machines WHERE id = ?");
    $stmt->execute([$id]);
    $machine = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$machine) {
        throw new Exception('Machine not found');
    }

    // 2. Check Items
    $stmt = $pdo->prepare("
        SELECT mci.*, ci.item_code, ci.name_en, ci.name_th 
        FROM machine_check_items mci
        JOIN check_items ci ON mci.check_item_id = ci.id
        WHERE mci.machine_id = ?
    ");
    $stmt->execute([$id]);
    $checkItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Parameters
    $stmt = $pdo->prepare("
        SELECT mp.*, pm.name_en, pm.unit 
        FROM machine_parameters mp
        JOIN parameters_master pm ON mp.parameter_id = pm.id
        WHERE mp.machine_id = ?
    ");
    $stmt->execute([$id]);
    $parameters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Inspections
    $stmt = $pdo->prepare("
        SELECT mi.*, im.name_en, im.unit 
        FROM machine_inspections mi
        JOIN inspections_master im ON mi.inspection_id = im.id
        WHERE mi.machine_id = ?
    ");
    $stmt->execute([$id]);
    $inspections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_clean(); // Ensure buffer is empty before JSON
    echo json_encode([
        'machine' => $machine,
        'checkItems' => $checkItems,
        'parameters' => $parameters,
        'inspections' => $inspections
    ]);

} catch (Throwable $e) {
    // Clear any previous output
    if (ob_get_length())
        ob_clean();

    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
