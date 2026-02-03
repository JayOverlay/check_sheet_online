<?php
require '../config/database.php';
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Adding 'Ready' to ENUM...\n";
    // Current: 'Reported', 'Waiting for Technician', 'Technician Finished', 'Verified', 'Rejected'
    // Target: Include 'Ready'

    $sql = "ALTER TABLE downtime MODIFY COLUMN status ENUM('Reported', 'Waiting for Technician', 'Technician Finished', 'Verified', 'Rejected', 'Ready') DEFAULT 'Reported'";
    $pdo->exec($sql);

    echo "Updating 'Verified' records to 'Ready'...\n";
    $sql2 = "UPDATE downtime SET status = 'Ready' WHERE status = 'Verified'";
    $count = $pdo->exec($sql2);
    echo "Updated $count records.\n";

    // Optional: Remove 'Verified' from ENUM to keep it clean? 
    // User might have said "Add another", so I will keep Verified in the ENUM definition just in case, but unused.
    // Actually, "Verified" leads to "Ready". 
    // Wait, the user logic "Lead Verify then End".

    echo "Migration completed successfully.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>