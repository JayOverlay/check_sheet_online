<?php
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['user_id'] ?? '';
    $username = $_POST['username'];
    $password = $_POST['password'] ?? '';
    $full_name = $_POST['full_name'];
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'];
    $status = $_POST['status'];
    $department = $_POST['department'];
    $responsible_family = $_POST['responsible_family'] ?? null;
    if (is_array($responsible_family)) {
        $responsible_family = implode(', ', $responsible_family);
    }

    try {
        if (!empty($id)) {
            // Update existing user
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET username = ?, password = ?, full_name = ?, email = ?, role = ?, status = ?, department = ?, responsible_family = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$username, $hashed_password, $full_name, $email, $role, $status, $department, $responsible_family, $id]);
            } else {
                $sql = "UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, status = ?, department = ?, responsible_family = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$username, $full_name, $email, $role, $status, $department, $responsible_family, $id]);
            }
        } else {
            // Insert new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, password, full_name, email, role, status, department, responsible_family) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username, $hashed_password, $full_name, $email, $role, $status, $department, $responsible_family]);
        }

        header("Location: " . BASE_URL . "pages/users.php?success=1");
        exit();
    } catch (Exception $e) {
        // Redirect back with error message for better UX
        $error_msg = urlencode($e->getMessage());
        header("Location: " . BASE_URL . "pages/users.php?error=save_failed&details=$error_msg");
        exit();
    }
} else {
    header("Location: " . BASE_URL . "pages/users.php");
    exit();
}
?>