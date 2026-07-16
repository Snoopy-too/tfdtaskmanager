<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\BgAsset;
use App\Domain\Repositories\BgAssetRepositoryInterface;
use App\Application\Exceptions\ValidationException;

class BgAssetService
{
    private BgAssetRepositoryInterface $assetRepository;
    private string $uploadDirBase;

    public function __construct(BgAssetRepositoryInterface $assetRepository)
    {
        $this->assetRepository = $assetRepository;
        // Base upload folder
        $this->uploadDirBase = dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'board-game-studio';
    }

    public function getAssetsByProject(int $projectId): array
    {
        $this->ensureDefaultIconsPopulated($projectId);
        return $this->assetRepository->findByProjectId($projectId);
    }

    private function ensureDefaultIconsPopulated(int $projectId): void
    {
        $defaultIcons = [
            'icon_speed' => 'icon_speed.svg',
            'icon_power' => 'icon_power.svg',
            'icon_appeal' => 'icon_appeal.svg',
            'icon_chin' => 'icon_chin.svg',
            'icon_stamina' => 'icon_stamina.svg'
        ];

        $projectUploadDir = $this->uploadDirBase . DIRECTORY_SEPARATOR . $projectId;
        if (!is_dir($projectUploadDir)) {
            mkdir($projectUploadDir, 0755, true);
        }

        $srcIconsDir = dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'board-game-studio' . DIRECTORY_SEPARATOR . 'icons';

        $existingAssets = $this->assetRepository->findByProjectId($projectId);
        $existingTags = [];
        foreach ($existingAssets as $asset) {
            if ($asset->getTag() !== null) {
                $cleanTag = trim($asset->getTag(), '[]');
                $existingTags[$cleanTag] = $asset;
            }
        }

        foreach ($defaultIcons as $tag => $filename) {
            if (!isset($existingTags[$tag])) {
                $srcPath = $srcIconsDir . DIRECTORY_SEPARATOR . $filename;
                if (file_exists($srcPath)) {
                    $storedName = uniqid() . '_' . $filename;
                    $destPath = $projectUploadDir . DIRECTORY_SEPARATOR . $storedName;
                    
                    if (copy($srcPath, $destPath)) {
                        $size = filesize($destPath);
                        $asset = new BgAsset(
                            null,
                            $projectId,
                            $filename,
                            $storedName,
                            'image/svg+xml',
                            $size,
                            '[' . $tag . ']',
                            1
                        );
                        $this->assetRepository->save($asset);
                    }
                }
            } else {
                $asset = $existingTags[$tag];
                $filePath = $projectUploadDir . DIRECTORY_SEPARATOR . $asset->getStoredFilename();
                if (!file_exists($filePath)) {
                    $srcPath = $srcIconsDir . DIRECTORY_SEPARATOR . $filename;
                    if (file_exists($srcPath)) {
                        copy($srcPath, $filePath);
                    }
                }
            }
        }
    }

    public function getAssetById(int $id): ?BgAsset
    {
        return $this->assetRepository->findById($id);
    }

    public function uploadAsset(int $projectId, array $file, ?string $tag, int $uploadedByUserId): BgAsset
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new ValidationException("Failed to upload file. Error code: " . ($file['error'] ?? 'unknown'));
        }

        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $maxSize) {
            throw new ValidationException("File size exceeds 10MB limit.");
        }

        // Validate file type
        $allowedMimes = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/svg+xml' => 'svg',
            'font/ttf' => 'ttf',
            'font/otf' => 'otf',
            'application/x-font-truetype' => 'ttf',
            'application/x-font-opentype' => 'otf'
        ];

        // Perform basic extension validation too
        $originalName = $file['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // Use mime content type check if possible, or fallback to file-reported mime
        $detectedMime = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
        $mime = $detectedMime ?: $file['type'];

        if (!isset($allowedMimes[$mime]) && !in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'ttf', 'otf'])) {
            throw new ValidationException("Invalid file type. Only PNG, JPG, SVG, TTF, and OTF are allowed.");
        }

        // Standardize extension
        $targetExt = $allowedMimes[$mime] ?? $ext;
        if ($targetExt === 'jpeg') {
            $targetExt = 'jpg';
        }

        // Clean tag if provided: replace whitespace, make lowercase, ensure valid format
        $cleanTag = null;
        if ($tag !== null && trim($tag) !== '') {
            $cleanTag = trim($tag);
            // Auto add brackets if not present for tag reference syntax
            if (!str_starts_with($cleanTag, '[') || !str_ends_with($cleanTag, ']')) {
                $cleanTag = '[' . trim($cleanTag, '[]') . ']';
            }
        }

        // Generate a random unique file name
        $storedFilename = bin2hex(random_bytes(16)) . '.' . $targetExt;

        // Ensure directories exist
        $projectUploadDir = $this->uploadDirBase . DIRECTORY_SEPARATOR . $projectId;
        if (!is_dir($projectUploadDir)) {
            if (!mkdir($projectUploadDir, 0755, true) && !is_dir($projectUploadDir)) {
                throw new \RuntimeException("Failed to create upload directory: " . $projectUploadDir);
            }
        }

        // Copy .htaccess to protect uploads from execution if it does not exist
        $htaccessPath = $this->uploadDirBase . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($htaccessPath)) {
            $htaccessContent = "<FilesMatch \"\\.php$\">\n    Require all denied\n</FilesMatch>\n";
            file_put_contents($htaccessPath, $htaccessContent);
        }

        $targetPath = $projectUploadDir . DIRECTORY_SEPARATOR . $storedFilename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new ValidationException("Failed to save uploaded file.");
        }

        $asset = new BgAsset(
            null,
            $projectId,
            $originalName,
            $storedFilename,
            $mime,
            $file['size'],
            $cleanTag,
            $uploadedByUserId
        );

        return $this->assetRepository->save($asset);
    }

    public function deleteAsset(int $id): void
    {
        $asset = $this->assetRepository->findById($id);
        if (!$asset) {
            throw new ValidationException("Asset not found.");
        }

        $filePath = $this->uploadDirBase . DIRECTORY_SEPARATOR . $asset->getProjectId() . DIRECTORY_SEPARATOR . $asset->getStoredFilename();
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        $this->assetRepository->delete($id);
    }

    public function updateAssetTag(int $id, ?string $tag): BgAsset
    {
        $asset = $this->assetRepository->findById($id);
        if (!$asset) {
            throw new ValidationException("Asset not found.");
        }

        $cleanTag = null;
        if ($tag !== null && trim($tag) !== '') {
            $cleanTag = trim($tag);
            if (!str_starts_with($cleanTag, '[') || !str_ends_with($cleanTag, ']')) {
                $cleanTag = '[' . trim($cleanTag, '[]') . ']';
            }
        }

        $updatedAsset = new BgAsset(
            $asset->getId(),
            $asset->getProjectId(),
            $asset->getOriginalFilename(),
            $asset->getStoredFilename(),
            $asset->getMimeType(),
            $asset->getFileSizeBytes(),
            $cleanTag,
            $asset->getUploadedBy(),
            $asset->getCreatedAt()
        );

        return $this->assetRepository->save($updatedAsset);
    }
}
