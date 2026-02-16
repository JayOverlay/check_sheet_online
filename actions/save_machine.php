<?php
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

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
        $clean_code = preg_replace('/[^A-Za-z0-9_\-]/', '', $machine_code);
        $file_name = $clean_code . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $file_name;

        // Debug upload
        if (!move_uploaded_file($_FILES["machine_image"]["tmp_name"], "../" . $target_file)) {
            // Try absolute path if relative fails, or just handle error
            if (!move_uploaded_file($_FILES["machine_image"]["tmp_name"], $target_file)) {
                throw new Exception("Failed to move uploaded file. Check permissions for $target_dir");
            }
        }
        $image_path = $target_file; // Store relative path for DB
    }

    $id = $_POST['machine_id'] ?? ''; // Check if ID exists for Edit

    try {
        $pdo->beginTransaction();

        if (empty($id)) {
            // Check for duplicates before INSERT
            $checkStmt = $pdo->prepare("SELECT id FROM machines WHERE machine_code = ? OR machine_name = ?");
            $checkStmt->execute([$machine_code, $machine_name]);
            if ($checkStmt->fetch()) {
                throw new Exception("Duplicate Machine Code or Machine Name found.");
            }

            // INSERT
            $stmt = $pdo->prepare("INSERT INTO machines (machine_code, serial_number, machine_name, image_path, product, family, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$machine_code, $serial_number, $machine_name, $image_path, $product, $family, $status]);
            $machine_id = $pdo->lastInsertId();
        } else {
            // Check for duplicates before UPDATE (ignoring current ID)
            $checkStmt = $pdo->prepare("SELECT id FROM machines WHERE (machine_code = ? OR machine_name = ?) AND id != ?");
            $checkStmt->execute([$machine_code, $machine_name, $id]);
            if ($checkStmt->fetch()) {
                throw new Exception("Duplicate Machine Code or Machine Name found.");
            }

            // UPDATE
            $machine_id = $id;
            $sql = "UPDATE machines SET machine_code=?, serial_number=?, machine_name=?, product=?, family=?, status=?";
            $params = [$machine_code, $serial_number, $machine_name, $product, $family, $status];

            if (!empty($image_path)) {
                // Fetch old image to delete
                $oldImgStmt = $pdo->prepare("SELECT image_path FROM machines WHERE id = ?");
                $oldImgStmt->execute([$machine_id]);
                $oldImg = $oldImgStmt->fetchColumn();

                // Delete if exists
                if ($oldImg && file_exists("../" . $oldImg)) {
                    unlink("../" . $oldImg);
                }

                $sql .= ", image_path=?";
                $params[] = $image_path;
            }

            $sql .= " WHERE id=?";
            $params[] = $machine_id;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // Clear existing relations to re-insert
            $pdo->prepare("DELETE FROM machine_check_items WHERE machine_id = ?")->execute([$machine_id]);
            $pdo->prepare("DELETE FROM machine_parameters WHERE machine_id = ?")->execute([$machine_id]);
            $pdo->prepare("DELETE FROM machine_inspections WHERE machine_id = ?")->execute([$machine_id]);
        }

        // Save assigned check items (Common for both Insert and Update)
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

        header("Location: " . BASE_URL . "pages/machines.php?success=1");
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Error saving machine: " . $e->getMessage());
    }
} else {
    header("Location: " . BASE_URL . "pages/machines.php");
    exit();
}
?>