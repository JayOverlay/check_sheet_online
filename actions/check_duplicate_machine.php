<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $machine_code = $_POST['machine_code'] ?? '';
    $machine_name = $_POST['machine_name'] ?? '';
    $machine_id = $_POST['machine_id'] ?? ''; // For edit mode, ignore itself

    try {
        $response = [
            'exists' => false,
            'duplicate_field' => ''
        ];

        // Check Machine Code
        if (!empty($machine_code)) {
            $sql = "SELECT COUNT(*) FROM machines WHERE machine_code = ?";
            $params = [$machine_code];
            if (!empty($machine_id)) {
                $sql .= " AND id != ?";
                $params[] = $machine_id;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetchColumn() > 0) {
                $response['exists'] = true;
                $response['duplicate_field'] = 'Machine Code';
                echo json_encode($response);
                exit();
            }
        }

        // Check Machine Name
        if (!empty($machine_name)) {
            $sql = "SELECT COUNT(*) FROM machines WHERE machine_name = ?";
            $params = [$machine_name];
            if (!empty($machine_id)) {
                $sql .= " AND id != ?";
                $params[] = $machine_id;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetchColumn() > 0) {
                $response['exists'] = true;
                $response['duplicate_field'] = 'Machine Name';
                echo json_encode($response);
                exit();
            }
        }

        echo json_encode($response);

    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>