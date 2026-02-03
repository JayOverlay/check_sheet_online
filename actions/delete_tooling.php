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
        $stmt = $pdo->prepare("DELETE FROM tooling WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: tooling?deleted=1");
        exit();
    } catch (Exception $e) {
        header("Location: tooling?error=1");
        exit();
    }
}
header("Location: tooling");
exit();
?>