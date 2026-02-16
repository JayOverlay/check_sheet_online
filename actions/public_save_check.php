<?php
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Receive Inputs
    $machine_id = $_POST['machine_id'] ?? '';
    $employee_id = $_POST['employee_id'] ?? '';
    $results = $_POST['result'] ?? [];
    $comments = $_POST['comment'] ?? [];
    $overall = $_POST['overall_remarks'] ?? '';

    // Validate
    if (empty($machine_id) || empty($employee_id)) {
        die("Error: Missing required fields (Machine ID or Employee ID)");
    }

    // Construct target_id for legacy compatibility (e.g. m_1)
    $target_id = "m_" . $machine_id;

    // --- Duplicate Check Logic (By Shift) ---
    $currentTime = date('H:i:s');
    $currentDate = date('Y-m-d');
    $shiftStart = '';
    $shiftEnd = '';
    $shiftLabel = '';

    if ($currentTime >= '07:00:00' && $currentTime < '19:00:00') {
        // Day Shift
        $shiftLabel = "กะเช้า (Day Shift)";
        $shiftStart = $currentDate . ' 07:00:00';
        $shiftEnd = $currentDate . ' 18:59:59';
    } else {
        // Night Shift
        $shiftLabel = "กะกลางคืน (Night Shift)";
        if ($currentTime >= '19:00:00') {
            $shiftStart = $currentDate . ' 19:00:00';
            $shiftEnd = date('Y-m-d', strtotime('+1 day')) . ' 06:59:59';
        } else {
            $shiftStart = date('Y-m-d', strtotime('-1 day')) . ' 19:00:00';
            $shiftEnd = $currentDate . ' 06:59:59';
        }
    }

    // Receive check_type
    $check_type = $_POST['check_type'] ?? 'Daily';

    // Check database (Specific to Shift AND Check Type)
    $dupSql = "SELECT COUNT(*) FROM check_sheets WHERE target_id = ? AND check_type = ? AND created_at BETWEEN ? AND ?";
    $dupStmt = $pdo->prepare($dupSql);
    $dupStmt->execute([$target_id, $check_type, $shiftStart, $shiftEnd]);
    if ($dupStmt->fetchColumn() > 0) {
        $typeLabel = $check_type ? "หัวข้อ $check_type" : "ข้อมูล";
        $msg = urlencode("เครื่องจักรนี้ถูกลงบันทึกใน $typeLabel สำหรับ $shiftLabel เรียบร้อยแล้ว");
        header("Location: " . BASE_URL . "pages/public_check.php?machine_id=$machine_id&employee_id=$employee_id&error=$msg");
        exit();
    }
    // --- End Duplicate Check ---

    // 2. Determine Overall Status
    $has_problem = false;
    $problems_found = [];

    foreach ($results as $item_id => $status) {
        if ($status === 'NG') {
            $has_problem = true;
            $notes = $comments[$item_id] ?? '';
            // Fetch item name for better reporting (optional)
            $problems_found[] = "Item #$item_id ($check_type): $notes";
        }
    }

    $overall_status = $has_problem ? 'Fail' : 'Pass';

    try {
        $pdo->beginTransaction();

        // 3. Insert into check_sheets
        $stmt = $pdo->prepare("INSERT INTO check_sheets (target_id, inspector_name, overall_status, remarks, check_type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$target_id, $employee_id, $overall_status, $overall, $check_type]);
        $check_sheet_id = $pdo->lastInsertId();

        // 4. Insert into check_sheet_details
        $detailStmt = $pdo->prepare("INSERT INTO check_sheet_details (check_sheet_id, item_id, result, comment) VALUES (?, ?, ?, ?)");

        foreach ($results as $item_id => $status) {
            $comment = $comments[$item_id] ?? '';
            $detailStmt->execute([$check_sheet_id, $item_id, $status, $comment]);
        }

        $pdo->commit();

        // 5. If NG found → Redirect to Public Downtime page to report issue
        if ($has_problem) {
            $problem_desc = implode(" | ", $problems_found);
            if (!empty($overall)) {
                $problem_desc .= " [Overall: $overall]";
            }

            // Redirect to public downtime page with pre-filled problem
            $redirect_params = http_build_query([
                'machine_id' => $machine_id,
                'auto_problem' => $problem_desc,
                'from_check' => 1,
                'employee_id' => $employee_id
            ]);
            header("Location: " . BASE_URL . "pages/public_downtime.php?" . $redirect_params);
        } else {
            // 6. All OK → Redirect to Scan page
            header("Location: " . BASE_URL . "pages/scan.php?machine_id=$machine_id&success=check_completed");
        }
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        // Log error and redirect with error message
        $error_msg = urlencode($e->getMessage());
        header("Location: " . BASE_URL . "pages/public_check.php?machine_id=$machine_id&employee_id=$employee_id&error=$error_msg");
        exit();
    }
} else {
    // If accessed directly without POST
    header("Location: " . BASE_URL . "pages/machines.php");
    exit();
}
?>