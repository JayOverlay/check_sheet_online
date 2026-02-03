<?php
require '../config/database.php';
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Adding 'Waiting for Technician' to ENUM...\n";
    $sql1 = "ALTER TABLE downtime MODIFY COLUMN status ENUM('Pending', 'Waiting for Technician', 'In Progress', 'Completed', 'Verified', 'Rejected') DEFAULT 'Pending'";
    $pdo->exec($sql1);

    echo "Updating existing 'Pending' records...\n";
    $sql2 = "UPDATE downtime SET status = 'Waiting for Technician' WHERE status = 'Pending'";
    $count = $pdo->exec($sql2);
    echo "Updated $count records.\n";

    echo "Removing 'Pending' from ENUM and setting new default...\n";
    $sql3 = "ALTER TABLE downtime MODIFY COLUMN status ENUM('Waiting for Technician', 'In Progress', 'Completed', 'Verified', 'Rejected') DEFAULT 'Waiting for Technician'";
    $pdo->exec($sql3);

    echo "Migration completed successfully.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>