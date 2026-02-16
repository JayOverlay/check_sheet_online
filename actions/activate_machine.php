<?php
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    try {
        $stmt = $pdo->prepare("UPDATE machines SET status = 'Active' WHERE id = ?");
        $stmt->execute([$id]);

        header("Location: " . BASE_URL . "pages/machines.php?success=activated");
    } catch (Exception $e) {
        header("Location: " . BASE_URL . "pages/machines.php?error=" . urlencode($e->getMessage()));
    }
} else {
    header("Location: " . BASE_URL . "pages/machines.php");
}
exit();
?>