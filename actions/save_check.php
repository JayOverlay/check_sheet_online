<?php
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $target_str = $_POST['target_id']; // format: m_1 or t_5
    $inspector = $_POST['inspector_name'];
    $results = $_POST['result'] ?? [];
    $comments = $_POST['comment'] ?? [];
    $overall = $_POST['overall_remarks'] ?? '';

    // Parse target
    $parts = explode('_', $target_str);
    if (count($parts) < 2) {
        header("Location: check_form?error=invalid_target");
        exit();
    }
    $type_prefix = $parts[0];
    $ref_id = $parts[1];
    $type = ($type_prefix == 'm') ? 'machine' : 'tooling';

    $has_problem = false;
    foreach ($results as $status) {
        if ($status == "NG") {
            $has_problem = true;
            break;
        }
    }
    $overall_status = $has_problem ? "Fail" : "Pass";

    try {
        $pdo->beginTransaction();

        // 1. Save main check sheet record
        $stmt = $pdo->prepare("INSERT INTO check_sheets (target_id, inspector_name, overall_status, remarks) VALUES (?, ?, ?, ?)");
        $stmt->execute([$target_str, $inspector, $overall_status, $overall]);
        $sheet_id = $pdo->lastInsertId();

        // 2. Save individual item results
        $itemStmt = $pdo->prepare("INSERT INTO check_sheet_details (check_sheet_id, item_id, result, comment) VALUES (?, ?, ?, ?)");

        $problems_found = [];
        foreach ($results as $item_id => $status) {
            $comment = $comments[$item_id] ?? "";
            $itemStmt->execute([$sheet_id, $item_id, $status, $comment]);

            if ($status == "NG") {
                $problems_found[] = "Item ID $item_id: $comment";
            }
        }

        // 3. If problem found, create downtime record
        if ($has_problem) {
            $problem_desc = implode(" | ", $problems_found);
            if (!empty($overall)) {
                $problem_desc .= " [Overall: $overall]";
            }

            $dtStmt = $pdo->prepare("INSERT INTO downtime (ref_id, ref_type, problem, reported_by, status) VALUES (?, ?, ?, ?, 'Pending')");
            $dtStmt->execute([$ref_id, $type, $problem_desc, $inspector]);

            // 4. Update machine/tooling status to Maintenance
            if ($type == "machine") {
                $updStmt = $pdo->prepare("UPDATE machines SET status = 'Maintenance' WHERE id = ?");
                $updStmt->execute([$ref_id]);
            } else {
                $updStmt = $pdo->prepare("UPDATE tooling SET status = 'Maintenance' WHERE id = ?");
                $updStmt->execute([$ref_id]);
            }
        }

        $pdo->commit();
        header("Location: downtime?success=1" . ($has_problem ? "&problem=1" : ""));
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}
?>