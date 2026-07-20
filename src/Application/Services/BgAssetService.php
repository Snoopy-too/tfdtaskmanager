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

    public function getAssetsByProject(?int $projectId): array
    {
        $this->ensureDefaultIconsPopulated($projectId);
        return $this->assetRepository->findByProjectId($projectId);
    }

    private function ensureDefaultIconsPopulated(?int $projectId): void
    {
        $defaultIcons = [
            'icon_speed' => 'icon_speed.svg',
            'icon_power' => 'icon_power.svg',
            'icon_appeal' => 'icon_appeal.svg',
            'icon_chin' => 'icon_chin.svg',
            'icon_stamina' => 'icon_stamina.svg'
        ];

        $folderName = ($projectId === null) ? 'global' : (string)$projectId;
        $projectUploadDir = $this->uploadDirBase . DIRECTORY_SEPARATOR . $folderName;
        if (!is_dir($projectUploadDir)) {
            mkdir($projectUploadDir, 0755, true);
        }

        $srcIconsDir = dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'board-game-studio' . DIRECTORY_SEPARATOR . 'icons';

        $existingAssets = $this->assetRepository->findByProjectId($projectId, false);
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

    public function uploadAsset(?int $projectId, array $file, ?string $tag, int $uploadedByUserId): BgAsset
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
        $folderName = ($projectId === null) ? 'global' : (string)$projectId;
        $projectUploadDir = $this->uploadDirBase . DIRECTORY_SEPARATOR . $folderName;
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

        $folderName = ($asset->getProjectId() === null) ? 'global' : (string)$asset->getProjectId();
        $filePath = $this->uploadDirBase . DIRECTORY_SEPARATOR . $folderName . DIRECTORY_SEPARATOR . $asset->getStoredFilename();
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

    public function uploadMultipleAssets(?int $projectId, array $files, int $uploadedByUserId): array
    {
        $results = [];
        if (isset($files['name']) && is_array($files['name'])) {
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                if (!isset($files['error'][$i]) || $files['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }
                $file = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];
                try {
                    $results[] = $this->uploadAsset($projectId, $file, null, $uploadedByUserId);
                } catch (\Exception $e) {
                    // Skip invalid files
                }
            }
        }
        return $results;
    }

    public function uploadZipAsset(?int $projectId, array $zipFile, int $uploadedByUserId): array
    {
        if (!class_exists('\ZipArchive')) {
            throw new ValidationException("ZipArchive extension is not enabled on server.");
        }
        if (!isset($zipFile['error']) || $zipFile['error'] !== UPLOAD_ERR_OK) {
            throw new ValidationException("Failed to upload ZIP file.");
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipFile['tmp_name']) !== true) {
            throw new ValidationException("Invalid or corrupt ZIP archive.");
        }

        $extractDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bgs_zip_' . uniqid();
        mkdir($extractDir, 0755, true);
        $zip->extractTo($extractDir);
        $zip->close();

        $results = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($extractDir));

        $allowedMimes = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'svg' => 'image/svg+xml'];

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) continue;
            $ext = strtolower($fileInfo->getExtension());
            if (!isset($allowedMimes[$ext])) continue;

            $fileName = $fileInfo->getFilename();
            if (str_starts_with($fileName, '.')) continue;

            // Resolve subfolder prefix to avoid filename collisions (e.g. heavyweight/boxer_1.png -> hw_boxer_1.png)
            $relativePath = str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($extractDir) + 1));
            $parts = explode('/', $relativePath);

            $prefix = '';
            if (count($parts) > 1) {
                $subfolder = strtolower($parts[0]);
                if (str_contains($subfolder, 'heavy')) {
                    $prefix = 'hw_';
                } elseif (str_contains($subfolder, 'welter') || str_contains($subfolder, 'middle')) {
                    $prefix = 'wm_';
                } elseif (str_contains($subfolder, 'light') || str_contains($subfolder, 'bantam') || str_contains($subfolder, 'feather')) {
                    $prefix = 'lbf_';
                } else {
                    $prefix = $subfolder . '_';
                }
            }

            $originalFilename = ($prefix !== '' && !str_starts_with($fileName, $prefix)) ? ($prefix . $fileName) : $fileName;

            $mime = $allowedMimes[$ext];
            $storedFilename = bin2hex(random_bytes(16)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
            $folderName = ($projectId === null) ? 'global' : (string)$projectId;
            $projectUploadDir = $this->uploadDirBase . DIRECTORY_SEPARATOR . $folderName;
            if (!is_dir($projectUploadDir)) {
                mkdir($projectUploadDir, 0755, true);
            }

            $targetPath = $projectUploadDir . DIRECTORY_SEPARATOR . $storedFilename;
            if (copy($fileInfo->getPathname(), $targetPath)) {
                $asset = new BgAsset(
                    null,
                    $projectId,
                    $originalFilename,
                    $storedFilename,
                    $mime,
                    $fileInfo->getSize(),
                    null,
                    $uploadedByUserId
                );
                $results[] = $this->assetRepository->save($asset);
            }

            @unlink($fileInfo->getPathname());
        }

        @rmdir($extractDir);
        return $results;
    }
}
