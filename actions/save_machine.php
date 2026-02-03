<?php
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $machine_code = $_POST['machine_code'] ?? '';
    $serial_number = $_POST['serial_number'] ?? '';
    $machine_name = $_POST['machine_name'] ?? '';
    $product = $_POST['product'] ?? '';
    $family = $_POST['family'] ?? '';
    $status = $_POST['status'] ?? 'Active';
    $image_path = '';

    // Handle Image Upload
    if (isset($_FILES['machine_image']) && $_FILES['machine_image']['error'] == 0) {
        $target_dir = "uploads/machines/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES["machine_image"]["name"], PATHINFO_EXTENSION);
        $file_name = $machine_code . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($_FILES["machine_image"]["tmp_name"], $target_file)) {
            $image_path = $target_file;
        }
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO machines (machine_code, serial_number, machine_name, image_path, product, family, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$machine_code, $serial_number, $machine_name, $image_path, $product, $family, $status]);

        $machine_id = $pdo->lastInsertId();

        // Save assigned check items
        if (isset($_POST['check_items']) && is_array($_POST['check_items'])) {
            $checkStmt = $pdo->prepare("INSERT INTO machine_check_items (machine_id, check_item_id, frequency) VALUES (?, ?, ?)");
            foreach ($_POST['check_items'] as $itemId) {
                $freq = $_POST['frequency'][$itemId] ?? 'daily';
                $checkStmt->execute([$machine_id, $itemId, $freq]);
            }
        }

        // Save machine parameters
        if (isset($_POST['param_ids']) && is_array($_POST['param_ids'])) {
            $paramStmt = $pdo->prepare("INSERT INTO machine_parameters (machine_id, parameter_id, target_value, plus_tolerance, minus_tolerance) VALUES (?, ?, ?, ?, ?)");
            foreach ($_POST['param_ids'] as $pid) {
                $target = $_POST['param_target'][$pid] ?? '';
                if ($target !== '') {
                    $plus = $_POST['param_plus'][$pid] ?? 0;
                    $minus = $_POST['param_minus'][$pid] ?? 0;
                    $paramStmt->execute([$machine_id, $pid, $target, $plus, $minus]);
                }
            }
        }

        // Save machine inspections (Buy-off)
        if (isset($_POST['inspection_ids']) && is_array($_POST['inspection_ids'])) {
            $insStmt = $pdo->prepare("INSERT INTO machine_inspections (machine_id, inspection_id, target_value, plus_tolerance, minus_tolerance) VALUES (?, ?, ?, ?, ?)");
            foreach ($_POST['inspection_ids'] as $iid) {
                $itarget = $_POST['inspection_target'][$iid] ?? '';
                if ($itarget !== '') {
                    $iplus = $_POST['inspection_plus'][$iid] ?? 0;
                    $iminus = $_POST['inspection_minus'][$iid] ?? 0;
                    $insStmt->execute([$machine_id, $iid, $itarget, $iplus, $iminus]);
                }
            }
        }

        $pdo->commit();

        header("Location: machines?success=1");
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Error saving machine: " . $e->getMessage());
    }
} else {
    header("Location: machines");
    exit();
}
?>