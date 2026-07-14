<?php
$container = require 'src/bootstrap.php';
$stmt = $container->get(PDO::class)->query("SELECT id, name FROM projects");
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Projects:\n";
print_r($projects);

$stmt2 = $container->get(PDO::class)->query("SELECT id, project_id, original_filename FROM bg_assets");
$assets = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "\nAssets:\n";
print_r($assets);
