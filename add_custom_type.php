<?php
$container = require 'src/bootstrap.php';
$pdo = $container->get(PDO::class);

// check if Custom exists
$stmt = $pdo->query("SELECT id FROM bg_component_types WHERE name = 'Custom'");
if (!$stmt->fetch()) {
    $pdo->exec("INSERT INTO bg_component_types (name, width_mm, height_mm, description) VALUES ('Custom', 0, 0, 'Custom dimensions defined by user.')");
    echo "Inserted Custom type.\n";
} else {
    echo "Custom type already exists.\n";
}
