<?php
require '../config/database.php';
try {
    $stmt = $pdo->query("DESCRIBE inspections_master");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns: " . implode(", ", $columns);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>