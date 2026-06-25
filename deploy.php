<?php
declare(strict_types=1);

// Security token to prevent unauthorized deploy triggers
// Change this secret to any strong password you prefer
$secret = 'tfd_deploy_secret_2026';

$key = $_GET['key'] ?? '';
if ($key !== $secret) {
    http_response_code(403);
    echo "Forbidden: Invalid deploy key.";
    exit();
}

// Switch to the project root directory
chdir(__DIR__);

$output = [];
$returnVar = 0;

// Execute the git pull command and redirect error streams to output
exec('git pull origin main 2>&1', $output, $returnVar);

header('Content-Type: text/plain');
if ($returnVar === 0) {
    echo "SUCCESS: Git pull executed successfully.\n\n";
} else {
    echo "FAILURE: Git pull failed with exit code $returnVar.\n\n";
}

echo implode("\n", $output);
