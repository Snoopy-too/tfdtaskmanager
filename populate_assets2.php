<?php
declare(strict_types=1);
$container = require 'src/bootstrap.php';
use App\Application\Services\ProjectService;
use App\Domain\Entities\BgAsset;
use App\Infrastructure\Repository\PDOBgAssetRepository;
use App\Infrastructure\Database\DatabaseConnection;

$assetRepo = $container->get(PDOBgAssetRepository::class);

$projectId = 7;

$assets = [
    [
        'source' => 'C:\\Users\\fmont\\.gemini\\antigravity\\brain\\c293ee8d-0b9a-423c-83ce-6b0516215eee\\fantasy_card_frame_1784011822083.jpg',
        'name' => 'fantasy_card_frame.jpg'
    ],
    [
        'source' => 'C:\\Users\\fmont\\.gemini\\antigravity\\brain\\c293ee8d-0b9a-423c-83ce-6b0516215eee\\sci_fi_card_back_1784011832908.jpg',
        'name' => 'sci_fi_card_back.jpg'
    ],
    [
        'source' => 'C:\\Users\\fmont\\.gemini\\antigravity\\brain\\c293ee8d-0b9a-423c-83ce-6b0516215eee\\gold_coin_token_1784011841977.jpg',
        'name' => 'gold_coin_token.jpg'
    ]
];

$uploadDir = __DIR__ . '/uploads/board-game-studio/' . $projectId;
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

foreach ($assets as $assetInfo) {
    $storedName = uniqid() . '_' . $assetInfo['name'];
    $dest = $uploadDir . '/' . $storedName;
    
    if (copy($assetInfo['source'], $dest)) {
        $size = filesize($dest);
        $asset = new BgAsset(
            null,
            $projectId,
            $assetInfo['name'],
            $storedName,
            'image/jpeg',
            $size,
            'general',
            1
        );
        $assetRepo->save($asset);
        echo "Added asset: " . $assetInfo['name'] . "\n";
    } else {
        echo "Failed to copy " . $assetInfo['name'] . "\n";
    }
}
echo "Done.\n";
