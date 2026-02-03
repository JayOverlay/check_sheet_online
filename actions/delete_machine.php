<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit();
}
require_once '../config/database.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM machines WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: machines?deleted=1");
        exit();
    } catch (Exception $e) {
        header("Location: machines?error=1");
        exit();
    }
}
header("Location: machines");
exit();
?>