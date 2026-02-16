<?php
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['downtime_id'] ?? '';
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];

    try {
        $pdo->beginTransaction();

        // Get current record
        $stmt = $pdo->prepare("SELECT * FROM downtime WHERE id = ?");
        $stmt->execute([$id]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            throw new Exception("Ticket not found");
        }

        if ($action == 'call_tech') {
            // Reported -> Waiting for Technician
            // Allows Leader/Admin/Tech to acknowledge and call for repair
            $pdo->prepare("UPDATE downtime SET status = 'Waiting for Technician', called_at = NOW() WHERE id = ?")
                ->execute([$id]);

        } elseif ($action == 'accept') {
            // Waiting -> In Progress
            if ($role !== 'Technicien' && $role !== 'admin')
                throw new Exception("Unauthorized");

            $pdo->prepare("UPDATE downtime SET status = 'In Progress', technician_id = ?, accepted_at = NOW() WHERE id = ?")
                ->execute([$user_id, $id]);

        } elseif ($action == 'finish') {
            // Technician finishes job
            if ($role !== 'Technicien' && $role !== 'admin')
                throw new Exception("Unauthorized");

            $solution = $_POST['solution'] ?? '';
            $remarks = $_POST['remarks'] ?? '';
            // Status: In Progress -> Technician Finished
            // Assign technician_id at finish time if oversight (redundancy)
            $pdo->prepare("UPDATE downtime SET status = 'Technician Finished', solution = ?, remarks = ?, technician_id = ?, fixed_at = NOW() WHERE id = ?")
                ->execute([$solution, $remarks, $user_id, $id]);

        } elseif ($action == 'verify') {
            // Leader approves
            if ($role !== 'leader' && $role !== 'admin')
                throw new Exception("Unauthorized");

            $comment = $_POST['leader_comment'] ?? 'Verified OK';
            $pdo->prepare("UPDATE downtime SET status = 'Ready', leader_id = ?, leader_comment = ?, verified_at = NOW() WHERE id = ?")
                ->execute([$user_id, $comment, $id]);

            // Set Asset back to Active
            $table = ($ticket['ref_type'] == 'machine') ? 'machines' : 'tooling';
            $pdo->prepare("UPDATE $table SET status = 'Active' WHERE id = ?")
                ->execute([$ticket['ref_id']]);

        } elseif ($action == 'reject') {
            // Leader rejects -> Close current as rejected, Open new one
            if ($role !== 'leader' && $role !== 'admin')
                throw new Exception("Unauthorized");

            $comment = trim($_POST['leader_comment'] ?? '');
            if (empty($comment)) {
                throw new Exception("Reason for rejection is required");
            }

            // 1. Close current
            $rejectMsg = "Rejected: " . $comment;
            $pdo->prepare("UPDATE downtime SET status = 'Rejected', leader_id = ?, leader_comment = ? WHERE id = ?")
                ->execute([$user_id, $rejectMsg, $id]);

            // 2. Open new ticket
            // Use the leader's comment directly as the new problem description
            $newProblem = $comment;
            $pdo->prepare("INSERT INTO downtime (ref_id, ref_type, category, problem, reported_by, status, reported_at) VALUES (?, ?, ?, ?, ?, 'Reported', NOW())")
                ->execute([$ticket['ref_id'], $ticket['ref_type'], $ticket['category'], $newProblem, $_SESSION['username']]);
        }

        $pdo->commit();
        header("Location: " . BASE_URL . "pages/downtime.php?success=1");
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header("Location: " . BASE_URL . "pages/downtime.php?error=1&details=" . urlencode($e->getMessage()));
        exit();
    }
}
?>