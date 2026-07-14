<?php
// Test list_assets
$url = 'http://localhost/tfdtaskmanager/board-game-studio/api.php?action=list_assets&project_id=7';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP Code: $httpCode\n";
echo "Response: $result\n";
