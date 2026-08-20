<?php
declare(strict_types=1);

use App\Infrastructure\Security\SecurityHelper;
use App\Application\Exceptions\ValidationException;

// Handle Upload Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_asset') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $tag = isset($_POST['tag']) && trim($_POST['tag']) !== '' ? $_POST['tag'] : null;
        $file = $_FILES['asset_file'] ?? null;
        $zipFile = $_FILES['zip_file'] ?? null;
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);

        try {
            $isGlobal = isset($_POST['is_global']) && $_POST['is_global'] === '1';
            $uploadProjectId = ($activeProjectId === null || $isGlobal) ? null : $activeProjectId;

            if ($zipFile && isset($zipFile['tmp_name']) && !empty($zipFile['tmp_name'])) {
                $uploaded = $assetService->uploadZipAsset($uploadProjectId, $zipFile, $currentUserId);
                $success = count($uploaded) . " assets extracted and imported from ZIP archive.";
            } elseif ($file && isset($file['name']) && is_array($file['name'])) {
                $uploaded = $assetService->uploadMultipleAssets($uploadProjectId, $file, $currentUserId);
                $success = count($uploaded) . " assets uploaded successfully.";
            } elseif ($file && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK) {
                $assetService->uploadAsset($uploadProjectId, $file, $tag, $currentUserId);
                $success = "Asset uploaded successfully.";
            } else {
                throw new ValidationException("Please select one or more files (or a ZIP archive) to upload.");
            }
        } catch (ValidationException $e) {
            $error = $e->getMessage();
        } catch (\Exception $e) {
            $error = "An error occurred during file upload: " . $e->getMessage();
        }
    }
}

// Handle Project / Global Assignment Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_asset_project') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $assetId = isset($_POST['asset_id']) ? (int)$_POST['asset_id'] : 0;
        $targetProjectId = (isset($_POST['target_project_id']) && $_POST['target_project_id'] !== '' && $_POST['target_project_id'] !== 'global') ? (int)$_POST['target_project_id'] : null;
        try {
            $updated = $assetService->updateAssetProject($assetId, $targetProjectId);
            if ($targetProjectId === null) {
                $success = "Asset '" . SecurityHelper::escape($updated->getOriginalFilename()) . "' moved to Global Asset Library.";
            } else {
                $targetProj = $projectService->getProjectById($targetProjectId);
                $projName = $targetProj ? $targetProj->getName() : "Project #{$targetProjectId}";
                $success = "Asset '" . SecurityHelper::escape($updated->getOriginalFilename()) . "' assigned to {$projName}.";
            }
        } catch (\Exception $e) {
            $error = "Failed to update asset assignment: " . $e->getMessage();
        }
    }
}

// Handle Delete Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_asset') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $assetId = isset($_POST['asset_id']) ? (int)$_POST['asset_id'] : 0;
        try {
            $assetService->deleteAsset($assetId);
            $success = "Asset deleted successfully.";
        } catch (\Exception $e) {
            $error = "Failed to delete asset: " . $e->getMessage();
        }
    }
}

// Handle Tag Update Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_tag') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $assetId = isset($_POST['asset_id']) ? (int)$_POST['asset_id'] : 0;
        $newTag = isset($_POST['tag']) ? $_POST['tag'] : null;
        try {
            $assetService->updateAssetTag($assetId, $newTag);
            $success = "Asset tag updated successfully.";
        } catch (\Exception $e) {
            $error = "Failed to update tag: " . $e->getMessage();
        }
    }
}

// Handle Batch Actions (Batch Move / Batch Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['batch_update_project', 'batch_delete'], true)) {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $rawIds = $_POST['selected_ids'] ?? '';
        $idList = is_array($rawIds) ? $rawIds : explode(',', (string)$rawIds);
        $selectedIds = array_filter(array_map('intval', $idList), fn($id) => $id > 0);

        if (empty($selectedIds)) {
            $error = 'No assets selected.';
        } elseif ($_POST['action'] === 'batch_update_project') {
            $targetProjectId = (isset($_POST['target_project_id']) && $_POST['target_project_id'] !== '' && $_POST['target_project_id'] !== 'global') ? (int)$_POST['target_project_id'] : null;
            $count = 0;
            foreach ($selectedIds as $assetId) {
                try {
                    $assetService->updateAssetProject($assetId, $targetProjectId);
                    $count++;
                } catch (\Exception $e) {
                    // ignore individual failure
                }
            }
            if ($targetProjectId === null) {
                $success = "{$count} asset(s) moved to Global Asset Library.";
            } else {
                $targetProj = $projectService->getProjectById($targetProjectId);
                $projName = $targetProj ? $targetProj->getName() : "Project #{$targetProjectId}";
                $success = "{$count} asset(s) assigned to {$projName}.";
            }
        } elseif ($_POST['action'] === 'batch_delete') {
            $count = 0;
            foreach ($selectedIds as $assetId) {
                try {
                    $assetService->deleteAsset($assetId);
                    $count++;
                } catch (\Exception $e) {
                    // ignore individual failure
                }
            }
            $success = "{$count} asset(s) deleted successfully.";
        }
    }
}

// Handle Sync Built-in System Icons Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sync_builtin_icons') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $currentUserId = (int)($_SESSION['user_id'] ?? 1);
        try {
            $count = $assetService->syncBuiltinGlobalIcons($currentUserId);
            $success = "{$count} built-in repository icon(s) synchronized into Global Asset Library.";
        } catch (\Exception $e) {
            $error = "Failed to synchronize built-in icons: " . $e->getMessage();
        }
    }
}
