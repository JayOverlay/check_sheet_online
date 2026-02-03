<?php
require '../config/database.php';
try {
    $sql = "ALTER TABLE inspections_master ADD COLUMN spec_image VARCHAR(255) DEFAULT NULL AFTER default_minus";
    $pdo->exec($sql);
    echo "Column 'spec_image' added successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>