<?php
require '../config/database.php';
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Modifying ENUM to include 'Reported' and 'Technician Finished'...\n";
    // We update the ENUM to include all the statuses we need.
    // Mapping:
    // 1. Reported (New)
    // 2. Waiting for Technician (Was 'Pending')
    // 3. Technician Finished (Was 'Completed')
    // 4. Verified

    // First, convert 'Pending'/'In Progress'/etc to temporary safe values or map them.
    // Current ENUM: 'Waiting for Technician', 'In Progress', 'Completed', 'Verified', 'Rejected'

    // We want: 'Reported', 'Waiting for Technician', 'Technician Finished', 'Verified', 'Rejected'

    // Step 1: Change column to allow all
    $sql = "ALTER TABLE downtime MODIFY COLUMN status ENUM('Reported', 'Waiting for Technician', 'In Progress', 'Completed', 'Technician Finished', 'Verified', 'Rejected') DEFAULT 'Reported'";
    $pdo->exec($sql);

    // Step 2: Update Data
    // 'Waiting for Technician' currently holds new items (from previous step). 
    // User wants 'Reported' as Step 1.
    // So current 'Waiting for Technician' (which was Pending) should probably be 'Reported' (fresh).
    $pdo->exec("UPDATE downtime SET status = 'Reported' WHERE status = 'Waiting for Technician'");

    // 'In Progress' -> This effectively becomes 'Waiting for Technician' (Waiting for tech to accept/finish?) 
    // BUT User said "2. รอช่างรับงาน" (Wait for Tech to Accept).
    // So 'Reported' -> 'Waiting for Technician' -> 'Finished'.
    // Existing 'In Progress' items are technically 'Waiting for Finish', so maybe we map them to 'Waiting for Technician'? 
    // Or we just map them to 'Reported' and let them follow flow? 
    // Let's map 'In Progress' to 'Waiting for Technician' for now (assuming they are in phase 2).
    $pdo->exec("UPDATE downtime SET status = 'Waiting for Technician' WHERE status = 'In Progress'");

    // 'Completed' -> 'Technician Finished'
    $pdo->exec("UPDATE downtime SET status = 'Technician Finished' WHERE status = 'Completed'");

    // Step 3: Finalize ENUM
    $sqlFinal = "ALTER TABLE downtime MODIFY COLUMN status ENUM('Reported', 'Waiting for Technician', 'Technician Finished', 'Verified', 'Rejected') DEFAULT 'Reported'";
    $pdo->exec($sqlFinal);

    echo "Migration to 4-step status completed successfully.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>