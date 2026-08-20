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

    public function getAssetsByProject(?int $projectId, bool $includeGlobal = false): array
    {
        return $this->assetRepository->findByProjectId($projectId, $includeGlobal);
    }

    /**
     * Synchronizes all built-in SVG icons from board-game-studio/icons into the
     * global assets database (project_id IS NULL) and the uploads/board-game-studio/global folder.
     */
    public function syncBuiltinGlobalIcons(int $uploadedByUserId = 1): int
    {
        $iconsDir = dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'board-game-studio' . DIRECTORY_SEPARATOR . 'icons';
        if (!is_dir($iconsDir)) {
            return 0;
        }

        $globalUploadDir = $this->uploadDirBase . DIRECTORY_SEPARATOR . 'global';
        if (!is_dir($globalUploadDir)) {
            if (!mkdir($globalUploadDir, 0755, true) && !is_dir($globalUploadDir)) {
                return 0;
            }
        }

        // Ensure .htaccess exists
        $htaccessPath = $this->uploadDirBase . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($htaccessPath)) {
            $htaccessContent = "# Disable directory listing\nOptions -Indexes\n\n# Prevent PHP execution\n<FilesMatch \"\\.php$\">\n    Require all denied\n</FilesMatch>\n\n# Protect .htaccess\n<Files \".htaccess\">\n    Require all denied\n</Files>\n\n# Allow cross-origin asset loading\n<IfModule mod_headers.c>\n    Header set Access-Control-Allow-Origin \"*\"\n</IfModule>\n";
            @file_put_contents($htaccessPath, $htaccessContent);
        }

        $existingGlobalAssets = $this->assetRepository->findByProjectId(null, false);
        $existingMap = [];
        foreach ($existingGlobalAssets as $asset) {
            $existingMap[$asset->getOriginalFilename()] = $asset;
        }

        $files = scandir($iconsDir);
        if ($files === false) {
            return 0;
        }

        $syncedCount = 0;
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || !str_ends_with(strtolower($file), '.svg')) {
                continue;
            }

            $sourcePath = $iconsDir . DIRECTORY_SEPARATOR . $file;
            if (!is_file($sourcePath)) {
                continue;
            }

            $tag = '[' . pathinfo($file, PATHINFO_FILENAME) . ']';

            if (isset($existingMap[$file])) {
                $asset = $existingMap[$file];
                $targetPath = $globalUploadDir . DIRECTORY_SEPARATOR . $asset->getStoredFilename();
                // Ensure target file exists, is non-empty, and is kept up-to-date with source icon
                if (!file_exists($targetPath) || filesize($targetPath) === 0 || (filemtime($sourcePath) > filemtime($targetPath))) {
                    if (copy($sourcePath, $targetPath)) {
                        $this->normalizeSvgFile($targetPath);
                    }
                }
                $syncedCount++;
                continue;
            }

            $storedFilename = bin2hex(random_bytes(16)) . '.svg';
            $targetPath = $globalUploadDir . DIRECTORY_SEPARATOR . $storedFilename;

            if (copy($sourcePath, $targetPath)) {
                $this->normalizeSvgFile($targetPath);
                $fileSize = (int)filesize($targetPath);
                $newAsset = new BgAsset(
                    null,
                    null,
                    $file,
                    $storedFilename,
                    'image/svg+xml',
                    $fileSize > 0 ? $fileSize : (int)filesize($sourcePath),
                    $tag,
                    $uploadedByUserId
                );
                $this->assetRepository->save($newAsset);
                $syncedCount++;
            }
        }
        return $syncedCount;
    }

    public function normalizeAllProjectSvgs(?int $projectId): void
    {
        $assets = $this->assetRepository->findByProjectId($projectId, true);
        foreach ($assets as $asset) {
            $ext = strtolower(pathinfo($asset->getOriginalFilename(), PATHINFO_EXTENSION));
            if ($ext === 'svg' || $asset->getMimeType() === 'image/svg+xml') {
                $folderName = ($asset->getProjectId() === null) ? 'global' : (string)$asset->getProjectId();
                $filePath = $this->uploadDirBase . DIRECTORY_SEPARATOR . $folderName . DIRECTORY_SEPARATOR . $asset->getStoredFilename();
                if (file_exists($filePath)) {
                    $this->normalizeSvgFile($filePath);
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
            $htaccessContent = "# Disable directory listing\nOptions -Indexes\n\n# Prevent PHP execution\n<FilesMatch \"\\.php$\">\n    Require all denied\n</FilesMatch>\n\n# Protect .htaccess\n<Files \".htaccess\">\n    Require all denied\n</Files>\n\n# Allow cross-origin asset loading\n<IfModule mod_headers.c>\n    Header set Access-Control-Allow-Origin \"*\"\n</IfModule>\n";
            file_put_contents($htaccessPath, $htaccessContent);
        }

        $targetPath = $projectUploadDir . DIRECTORY_SEPARATOR . $storedFilename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new ValidationException("Failed to save uploaded file.");
        }

        if ($targetExt === 'svg' || $mime === 'image/svg+xml') {
            $this->normalizeSvgFile($targetPath);
        }

        $fileSize = (int)filesize($targetPath);

        $asset = new BgAsset(
            null,
            $projectId,
            $originalName,
            $storedFilename,
            $mime,
            $fileSize > 0 ? $fileSize : $file['size'],
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

    public function updateAssetProject(int $id, ?int $newProjectId): BgAsset
    {
        $asset = $this->assetRepository->findById($id);
        if (!$asset) {
            throw new ValidationException("Asset not found.");
        }

        $oldProjectId = $asset->getProjectId();
        if ($oldProjectId === $newProjectId) {
            return $asset;
        }

        $oldFolder = ($oldProjectId === null) ? 'global' : (string)$oldProjectId;
        $newFolder = ($newProjectId === null) ? 'global' : (string)$newProjectId;

        $oldPath = $this->uploadDirBase . DIRECTORY_SEPARATOR . $oldFolder . DIRECTORY_SEPARATOR . $asset->getStoredFilename();
        $newDir = $this->uploadDirBase . DIRECTORY_SEPARATOR . $newFolder;
        $newPath = $newDir . DIRECTORY_SEPARATOR . $asset->getStoredFilename();

        if (!is_dir($newDir)) {
            mkdir($newDir, 0755, true);
        }

        if (file_exists($oldPath)) {
            rename($oldPath, $newPath);
        }

        $updatedAsset = new BgAsset(
            $asset->getId(),
            $newProjectId,
            $asset->getOriginalFilename(),
            $asset->getStoredFilename(),
            $asset->getMimeType(),
            $asset->getFileSizeBytes(),
            $asset->getTag(),
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
                
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($ext === 'zip') {
                    $zipResults = $this->uploadZipAsset($projectId, $file, $uploadedByUserId);
                    $results = array_merge($results, $zipResults);
                } else {
                    try {
                        $results[] = $this->uploadAsset($projectId, $file, null, $uploadedByUserId);
                    } catch (\Exception $e) {
                        // Skip invalid individual files
                    }
                }
            }
        }
        return $results;
    }

    public function uploadZipAsset(?int $projectId, array $zipFile, int $uploadedByUserId): array
    {
        if (!class_exists('\ZipArchive')) {
            throw new ValidationException("ZipArchive PHP extension is not enabled on this server.");
        }
        if (!isset($zipFile['error']) || $zipFile['error'] !== UPLOAD_ERR_OK) {
            throw new ValidationException("Failed to upload ZIP file. Error code: " . ($zipFile['error'] ?? 'unknown'));
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipFile['tmp_name']) !== true) {
            throw new ValidationException("Invalid or corrupt ZIP archive.");
        }

        $extractDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bgs_zip_' . uniqid();
        if (!is_dir($extractDir)) {
            mkdir($extractDir, 0755, true);
        }

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

            // Resolve subfolder prefix (e.g. heavyweight/boxer_1.png or fighter_images/heavyweight/boxer_1.png)
            $relativePath = str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($extractDir) + 1));
            $parts = explode('/', $relativePath);

            $prefix = '';
            foreach ($parts as $part) {
                $subfolder = strtolower($part);
                if (str_contains($subfolder, 'heavy')) {
                    $prefix = 'hw_';
                    break;
                } elseif (str_contains($subfolder, 'welter') || str_contains($subfolder, 'middle')) {
                    $prefix = 'wm_';
                    break;
                } elseif (str_contains($subfolder, 'light') || str_contains($subfolder, 'bantam') || str_contains($subfolder, 'feather')) {
                    $prefix = 'lbf_';
                    break;
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
                if ($ext === 'svg' || $mime === 'image/svg+xml') {
                    $this->normalizeSvgFile($targetPath);
                }
                $fileSize = (int)filesize($targetPath);
                $asset = new BgAsset(
                    null,
                    $projectId,
                    $originalFilename,
                    $storedFilename,
                    $mime,
                    $fileSize > 0 ? $fileSize : $fileInfo->getSize(),
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

    /**
     * Normalizes SVG files so that width and height attributes match high-res viewBox dimensions
     * and currentColor is replaced with #000000 for standalone canvas image rendering.
     */
    private function normalizeSvgFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            return;
        }

        $content = file_get_contents($filePath);
        if ($content === false || trim($content) === '') {
            return;
        }

        // Replace any currentColor references with solid black for isolated image contexts
        $content = preg_replace('/\bcurrentColor\b/i', '#000000', $content) ?? $content;

        // Match opening <svg ...> tag
        if (preg_match('/<svg\b([^>]*)>/i', $content, $matches)) {
            $svgTag = $matches[0];
            $attributes = $matches[1];

            // Extract viewBox dimensions if present (minX minY width height)
            if (preg_match('/viewBox=["\']\s*([-\d\.]+)\s+([-\d\.]+)\s+([-\d\.]+)\s+([-\d\.]+)\s*["\']/i', $attributes, $vbMatches)) {
                $vbWidth = (float)$vbMatches[3];
                $vbHeight = (float)$vbMatches[4];

                if ($vbWidth > 0 && $vbHeight > 0) {
                    $maxDim = max($vbWidth, $vbHeight);
                    $scale = ($maxDim < 512) ? (512 / $maxDim) : 1.0;
                    $targetW = (int)round($vbWidth * $scale);
                    $targetH = (int)round($vbHeight * $scale);

                    // Remove existing width/height if any to replace with target high-res dimensions
                    $cleanAttributes = preg_replace('/\s*(?:width|height)=["\'][^"\']*["\']/i', '', $attributes);
                    $newAttributes = $cleanAttributes . ' width="' . $targetW . '" height="' . $targetH . '"';

                    $newSvgTag = '<svg' . $newAttributes . '>';
                    $pos = strpos($content, $svgTag);
                    if ($pos !== false) {
                        $content = substr_replace($content, $newSvgTag, $pos, strlen($svgTag));
                    }
                }
            }
        }

        file_put_contents($filePath, $content);
    }
}
