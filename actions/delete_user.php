<?php
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access.");
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Prevent deleting self
    if ($id == $_SESSION['user_id']) {
        header("Location: " . BASE_URL . "pages/users.php?error=self_delete");
        exit();
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: " . BASE_URL . "pages/users.php?deleted=1");
        exit();
    } catch (Exception $e) {
        header("Location: " . BASE_URL . "pages/users.php?error=1");
        exit();
    }
}
header("Location: " . BASE_URL . "pages/users.php");
exit();
?>