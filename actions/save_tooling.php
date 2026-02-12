<?php
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tool_code = $_POST['tool_code'] ?? '';
    $serial_number = $_POST['serial_number'] ?? '';
    $tool_name = $_POST['tool_name'] ?? '';
    $product = $_POST['product'] ?? '';
    $family = $_POST['family'] ?? '';
    $status = $_POST['status'] ?? 'Active';
    $image_path = '';

    // Handle Image Upload
    if (isset($_FILES['tool_image']) && $_FILES['tool_image']['error'] == 0) {
        $target_dir = "uploads/tooling/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES["tool_image"]["name"], PATHINFO_EXTENSION);
        $file_name = $tool_code . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($_FILES["tool_image"]["tmp_name"], $target_file)) {
            $image_path = $target_file;
        }
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO tooling (tool_code, serial_number, tool_name, image_path, product, family, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$tool_code, $serial_number, $tool_name, $image_path, $product, $family, $status]);

        $tooling_id = $pdo->lastInsertId();

        // Save assigned check items
        if (isset($_POST['check_items']) && is_array($_POST['check_items'])) {
            $checkStmt = $pdo->prepare("INSERT INTO tooling_check_items (tooling_id, check_item_id, frequency) VALUES (?, ?, ?)");
            foreach ($_POST['check_items'] as $itemId) {
                $freq = $_POST['frequency'][$itemId] ?? 'daily';
                $checkStmt->execute([$tooling_id, $itemId, $freq]);
            }
        }

        $pdo->commit();

        header("Location: " . BASE_URL . "pages/tooling.php?success=1");
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Error saving tool: " . $e->getMessage());
    }
} else {
    header("Location: " . BASE_URL . "pages/tooling.php");
    exit();
}
?>