<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access.");
}
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['user_id'] ?? '';
    $username = $_POST['username'];
    $password = $_POST['password'] ?? '';
    $full_name = $_POST['full_name'];
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'];
    $status = $_POST['status'];
    $department = $_POST['department'];

    try {
        if (!empty($id)) {
            // Update existing user
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET username = ?, password = ?, full_name = ?, email = ?, role = ?, status = ?, department = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$username, $hashed_password, $full_name, $email, $role, $status, $department, $id]);
            } else {
                $sql = "UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, status = ?, department = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$username, $full_name, $email, $role, $status, $department, $id]);
            }
        } else {
            // Insert new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, password, full_name, email, role, status, department) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username, $hashed_password, $full_name, $email, $role, $status, $department]);
        }

        header("Location: users?success=1");
        exit();
    } catch (Exception $e) {
        // Redirect back with error message for better UX
        $error_msg = urlencode($e->getMessage());
        header("Location: users?error=save_failed&details=$error_msg");
        exit();
    }
} else {
    header("Location: users");
    exit();
}
?>