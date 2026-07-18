<?php
declare(strict_types=1);

$container = require_once __DIR__ . '/../src/bootstrap.php';
$pdo = $container->get(PDO::class);

$dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
echo "Active Database: $dbName\n";

echo "=== TEMPLATES ===\n";
$stmt = $pdo->query("SELECT id, name, dataset_id FROM bg_templates");
while ($row = $stmt->fetch()) {
    echo "ID: " . $row['id'] . " | Name: " . $row['name'] . " | Dataset ID: " . ($row['dataset_id'] ?? 'NULL') . "\n";
}
