<?php
require_once __DIR__ . '/../../../config.php';

function reportSchema($pdo)
{
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "--- HANA CHECK SYSTEM SCHEMA REPORT ---\n";
    foreach ($tables as $table) {
        echo "\nTable: $table\n";
        $columns = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "  - {$col['Field']} ({$col['Type']}) " . ($col['Key'] == 'PRI' ? '[PK]' : '') . "\n";
        }
    }
}

try {
    reportSchema($pdo);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>