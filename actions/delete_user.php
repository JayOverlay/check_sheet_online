<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access.");
}
require_once '../config/database.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Prevent deleting self
    if ($id == $_SESSION['user_id']) {
        header("Location: users?error=self_delete");
        exit();
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: users?deleted=1");
        exit();
    } catch (Exception $e) {
        header("Location: users?error=1");
        exit();
    }
}
header("Location: users");
exit();
?>