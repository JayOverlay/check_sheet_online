<?php
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $target_str = $_POST['target_id'] ?? '';
    $category = $_POST['category'] ?? '';
    $problem = $_POST['problem'] ?? '';
    $reported_by = $_POST['reported_by'] ?? 'Guest';

    // Parse target
    $parts = explode('_', $target_str);
    if (count($parts) < 2) {
        die("Error: Invalid Target");
    }
    $type_prefix = $parts[0];
    $ref_id = $parts[1];
    $type = ($type_prefix == 'm') ? 'machine' : 'tooling';

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO downtime (ref_id, ref_type, category, problem, reported_by, status, created_at) VALUES (?, ?, ?, ?, ?, 'Reported', NOW())");
        $stmt->execute([$ref_id, $type, $category, $problem, $reported_by]);

        if ($type == 'machine') {
            $pdo->prepare("UPDATE machines SET status = 'Maintenance' WHERE id = ?")->execute([$ref_id]);
        } else {
            $pdo->prepare("UPDATE tooling SET status = 'Maintenance' WHERE id = ?")->execute([$ref_id]);
        }

        $pdo->commit();
        header("Location: " . BASE_URL . "pages/scan.php?machine_id=$ref_id&success=downtime_reported");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: " . BASE_URL . "pages/public_downtime.php?machine_id=$ref_id&error=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: " . BASE_URL . "pages/scan.php");
    exit();
}
?>