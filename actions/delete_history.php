<?php
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access.");
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $pdo->beginTransaction();
        // Delete details first
        $pdo->prepare("DELETE FROM check_sheet_details WHERE check_sheet_id = ?")->execute([$id]);
        // Delete main record
        $pdo->prepare("DELETE FROM check_sheets WHERE id = ?")->execute([$id]);
        $pdo->commit();
        header("Location: " . BASE_URL . "history?deleted=1");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: " . BASE_URL . "history?error=1");
        exit();
    }
}
header("Location: " . BASE_URL . "history");
exit();
?>