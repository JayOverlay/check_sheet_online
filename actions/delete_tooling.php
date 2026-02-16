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
        $stmt = $pdo->prepare("DELETE FROM tooling WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: " . BASE_URL . "pages/tooling.php?deleted=1");
        exit();
    } catch (Exception $e) {
        header("Location: " . BASE_URL . "pages/tooling.php?error=1");
        exit();
    }
}
header("Location: " . BASE_URL . "pages/tooling.php");
exit();
?>