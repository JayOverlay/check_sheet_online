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

    // 2. Determine Overall Status
    $has_problem = false;
    $problems_found = [];

    foreach ($results as $item_id => $status) {
        if ($status === 'NG') {
            $has_problem = true;
            $notes = $comments[$item_id] ?? '';
            // Fetch item name for better reporting (optional, skipping for speed)
            $problems_found[] = "Item #$item_id: $notes";
        }
    }

    $overall_status = $has_problem ? 'Fail' : 'Pass';

    try {
        $pdo->beginTransaction();

        // 3. Insert into check_sheets
        $stmt = $pdo->prepare("INSERT INTO check_sheets (target_id, inspector_name, overall_status, remarks, check_type, created_at) VALUES (?, ?, ?, ?, 'Daily', NOW())");
        $stmt->execute([$target_id, $employee_id, $overall_status, $overall]);
        $check_sheet_id = $pdo->lastInsertId();

        // 4. Insert into check_sheet_details
        $detailStmt = $pdo->prepare("INSERT INTO check_sheet_details (check_sheet_id, item_id, result, comment) VALUES (?, ?, ?, ?)");

        foreach ($results as $item_id => $status) {
            $comment = $comments[$item_id] ?? '';
            $detailStmt->execute([$check_sheet_id, $item_id, $status, $comment]);
        }

        // 5. Handle Downtime Logic if NG
        if ($has_problem) {
            $problem_desc = implode(" | ", $problems_found);
            if (!empty($overall)) {
                $problem_desc .= " [Overall: $overall]";
            }

            // Insert Downtime Ticket
            $dtStmt = $pdo->prepare("INSERT INTO downtime (ref_id, ref_type, problem, reported_by, status, created_at) VALUES (?, 'machine', ?, ?, 'Pending', NOW())");
            $dtStmt->execute([$machine_id, $problem_desc, $employee_id]);

            // Update Machine Status
            $updStmt = $pdo->prepare("UPDATE machines SET status = 'Maintenance' WHERE id = ?");
            $updStmt->execute([$machine_id]);
        } else {
            // Update Last Check Date (Optional)
            // $pdo->prepare("UPDATE machines SET last_check = NOW() WHERE id = ?")->execute([$machine_id]);
        }

        $pdo->commit();

        // 6. Redirect to Success Page
        // Redirect back to machines page since check_history might not exist yet
        header("Location: ../machines?success=check_completed");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        // Log error and redirect with error message
        $error_msg = urlencode($e->getMessage());
        header("Location: ../check_form?machine_id=$machine_id&employee_id=$employee_id&error=$error_msg");
        exit();
    }
} else {
    // If accessed directly without POST
    header("Location: ../machines");
    exit();
}
?>