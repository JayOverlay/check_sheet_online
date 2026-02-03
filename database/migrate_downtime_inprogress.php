<?php
require '../config/database.php';
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Adding 'In Progress' back to ENUM...\n";
    // Current: 'Reported', 'Waiting for Technician', 'Technician Finished', 'Verified', 'Rejected', 'Ready'
    // Target: 'Reported', 'Waiting for Technician', 'In Progress', 'Technician Finished', 'Verified', 'Rejected', 'Ready'

    $sql = "ALTER TABLE downtime MODIFY COLUMN status ENUM('Reported', 'Waiting for Technician', 'In Progress', 'Technician Finished', 'Verified', 'Rejected', 'Ready') DEFAULT 'Reported'";
    $pdo->exec($sql);

    echo "Enum updated successfully.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>