<?php
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $target_str = $_POST['target_id'] ?? ''; // m_1 or t_5
    $category = $_POST['category'] ?? '';
    $problem = $_POST['problem'] ?? '';
    $reported_by = $_SESSION['username']; // Store Username (Employee ID) for precise tracking

    // Parse target
    $parts = explode('_', $target_str);
    if (count($parts) < 2) {
        header("Location: " . BASE_URL . "pages/downtime.php?error=invalid_target");
        exit();
    }
    $type_prefix = $parts[0];
    $ref_id = $parts[1];
    $type = ($type_prefix == 'm') ? 'machine' : 'tooling';

    try {
        $pdo->beginTransaction();

        // 1. Create Downtime Record
        $stmt = $pdo->prepare("INSERT INTO downtime (ref_id, ref_type, category, problem, reported_by, status) VALUES (?, ?, ?, ?, ?, 'Reported')");
        $stmt->execute([$ref_id, $type, $category, $problem, $reported_by]);

        // 2. Update Asset Status to Maintenance
        if ($type == 'machine') {
            $pdo->prepare("UPDATE machines SET status = 'Maintenance' WHERE id = ?")->execute([$ref_id]);
        } else {
            $pdo->prepare("UPDATE tooling SET status = 'Maintenance' WHERE id = ?")->execute([$ref_id]);
        }

        $pdo->commit();
        header("Location: " . BASE_URL . "pages/downtime.php?success=1");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: " . BASE_URL . "pages/downtime.php?error=save_failed&details=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: " . BASE_URL . "pages/downtime.php");
    exit();
}
?>