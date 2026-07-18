<?php
declare(strict_types=1);

$pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$dbsStmt = $pdo->query("SHOW DATABASES");
while ($dbName = $dbsStmt->fetchColumn()) {
    if (in_array($dbName, ['information_schema', 'mysql', 'performance_schema', 'sys'])) {
        continue;
    }
    
    try {
        $pdo->exec("USE `$dbName`");
        $tablesStmt = $pdo->query("SHOW TABLES");
        while ($table = $tablesStmt->fetchColumn()) {
            if (preg_match('/templates|rulebooks/i', $table)) {
                echo "Database: $dbName | Table: $table\n";
                try {
                    $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $r) {
                        echo "  Row: ID=" . ($r['id'] ?? 'N/A') . " | Name=" . ($r['name'] ?? 'N/A') . "\n";
                    }
                } catch (Exception $e) {
                    echo "  Failed to query table: " . $e->getMessage() . "\n";
                }
            }
        }
    } catch (Exception $e) {
        // Skip inaccessible databases
    }
}
