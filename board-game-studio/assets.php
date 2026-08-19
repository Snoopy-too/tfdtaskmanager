<?php
declare(strict_types=1);

$container = require_once __DIR__ . '/../src/bootstrap.php';

use App\Infrastructure\Security\SecurityHelper;
use App\Application\Services\ProjectService;
use App\Application\Services\BgAssetService;
use App\Application\Exceptions\ValidationException;

SecurityHelper::requireLogin();

$projectService = $container->get(ProjectService::class);
$assetService = $container->get(BgAssetService::class);

$error = '';
$success = '';
$csrfToken = SecurityHelper::generateCsrfToken();

// Projects dropdown
$projects = $projectService->getAllProjects();
$activeProjectId = (isset($_GET['project_id']) && $_GET['project_id'] !== '' && $_GET['project_id'] !== 'global') ? (int)$_GET['project_id'] : null;

// Default to last project from session if not specified in URL query at all
if ($activeProjectId === null && !isset($_GET['project_id'])) {
    if (isset($_SESSION['last_project_id'])) {
        $activeProjectId = (int)$_SESSION['last_project_id'];
    } elseif (!empty($projects)) {
        $activeProjectId = $projects[0]->getId();
    }
}

if ($activeProjectId) {
    $_SESSION['last_project_id'] = $activeProjectId;
}

$activeProject = null;
if ($activeProjectId) {
    $activeProject = $projectService->getProjectById($activeProjectId);
}

if ($activeProjectId && !$activeProject) {
    header("Location: index.php");
    exit;
}

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

// Load assets (loads global assets when activeProjectId is null)
$assets = $assetService->getAssetsByProject($activeProjectId, false);

// Filters & Sorting
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$typeFilter = isset($_GET['type']) ? trim($_GET['type']) : 'all';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'date_desc';

// Apply filters on assets list
if ($searchQuery !== '' || $typeFilter !== 'all') {
    $filteredAssets = [];
    foreach ($assets as $asset) {
        $matchesSearch = true;
        $matchesType = true;

        if ($searchQuery !== '') {
            $tagMatch = $asset->getTag() ? stripos($asset->getTag(), $searchQuery) !== false : false;
            $nameMatch = stripos($asset->getOriginalFilename(), $searchQuery) !== false;
            $matchesSearch = $tagMatch || $nameMatch;
        }

        if ($typeFilter !== 'all') {
            $isImage = str_starts_with($asset->getMimeType(), 'image/');
            $isFont = str_contains($asset->getMimeType(), 'font') || in_array(strtolower(pathinfo($asset->getStoredFilename(), PATHINFO_EXTENSION)), ['ttf', 'otf']);
            if ($typeFilter === 'image') {
                $matchesType = $isImage;
            } elseif ($typeFilter === 'font') {
                $matchesType = $isFont;
            }
        }

        if ($matchesSearch && $matchesType) {
            $filteredAssets[] = $asset;
        }
    }
    $assets = $filteredAssets;
}

// Apply sorting (Alphabetical or Upload Date)
if ($sort === 'name_asc') {
    usort($assets, fn($a, $b) => strnatcasecmp($a->getOriginalFilename(), $b->getOriginalFilename()));
} elseif ($sort === 'name_desc') {
    usort($assets, fn($a, $b) => strnatcasecmp($b->getOriginalFilename(), $a->getOriginalFilename()));
} elseif ($sort === 'date_asc') {
    usort($assets, fn($a, $b) => strcmp($a->getCreatedAt(), $b->getCreatedAt()));
} else {
    // Default: date_desc (newest first)
    usort($assets, fn($a, $b) => strcmp($b->getCreatedAt(), $a->getCreatedAt()));
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="space-y-8">
    <!-- Header Area -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-sm text-slate-400">
                <a href="index.php?project_id=<?php echo $activeProjectId; ?>" class="hover:text-white transition">Studio</a>
                <span>/</span>
                <span class="text-slate-200">Asset Library</span>
            </div>
            <h1 class="text-3xl font-extrabold tracking-tight text-white mt-1">
                <?php echo $activeProjectId === null ? 'Global Asset Library' : 'Project Asset Library'; ?>
            </h1>
            <p class="text-slate-400 mt-1">
                <?php echo $activeProjectId === null 
                    ? 'Upload and organize assets available across all game projects.' 
                    : 'Upload and organize assets for the current project: <span class="text-indigo-400 font-semibold">' . SecurityHelper::escape($activeProject->getName()) . '</span>'; ?>
            </p>
        </div>

        <div class="flex items-center space-x-2 bg-slate-900 border border-slate-800 p-2 rounded-xl">
            <label for="project_select" class="text-xs font-semibold text-slate-400 uppercase tracking-wider pl-2">Project:</label>
            <form method="GET" class="m-0">
                <select id="project_select" name="project_id" onchange="this.form.submit()" class="bg-slate-950 border-0 text-slate-100 text-sm rounded-lg focus:ring-2 focus:ring-indigo-500 py-1.5 pl-3 pr-8 font-medium cursor-pointer">
                    <option value="" <?php echo $activeProjectId === null ? 'selected' : ''; ?>>None (Global Library)</option>
                    <?php foreach ($projects as $proj): ?>
                        <option value="<?php echo $proj->getId(); ?>" <?php echo $proj->getId() === $activeProjectId ? 'selected' : ''; ?>>
                            <?php echo SecurityHelper::escape($proj->getName()); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-xl text-sm">
            <?php echo SecurityHelper::escape($error); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-xl text-sm">
            <?php echo SecurityHelper::escape($success); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Sidebar Upload Section -->
        <div class="space-y-6">
            <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl">
                <h2 class="text-xl font-bold text-slate-200 mb-4">Upload Asset</h2>
                
                <form action="" method="POST" enctype="multipart/form-data" class="space-y-4" id="asset-upload-form" onsubmit="return handleAssetUploadSubmit(this)">
                    <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                    <input type="hidden" name="action" value="upload_asset">
                    
                    <div>
                        <label for="asset_file" class="block text-sm font-medium text-slate-300 mb-1">Select File(s) or ZIP Archive</label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-slate-800 border-dashed rounded-xl hover:border-indigo-500/50 transition cursor-pointer relative group">
                            <input type="file" id="asset_file" name="asset_file[]" accept=".png,.jpg,.jpeg,.svg,.ttf,.otf,.zip" multiple required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                            <div class="space-y-1 text-center pointer-events-none">
                                <svg class="mx-auto h-10 w-10 text-slate-500 group-hover:text-indigo-400 transition" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4-4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="text-xs text-slate-300">
                                    <span class="font-medium text-indigo-400 group-hover:text-indigo-300 transition">Click to upload files or ZIP</span> or drag and drop
                                </div>
                                <p class="text-[10px] text-slate-500">PNG, JPG, SVG, TTF, OTF or ZIP up to 10MB each</p>
                        </div>
                        <div id="file_selected_preview" class="mt-3 hidden"></div>
                    </div>

                    <div>
                        <label for="tag" class="block text-sm font-medium text-slate-300 mb-1">Asset Tag (Optional)</label>
                        <input type="text" id="tag" name="tag" placeholder="e.g. icon_health or font_title" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 p-2.5">
                        <p class="text-[10px] text-slate-500 mt-1">Tags let you insert dynamic icons via text boxes (e.g. [icon_health]).</p>
                    </div>

                    <?php if ($activeProjectId !== null): ?>
                        <div class="flex items-center space-x-2 py-1">
                            <input type="checkbox" id="is_global" name="is_global" value="1" class="rounded bg-slate-950 border-slate-800 text-indigo-600 focus:ring-indigo-500 h-4 w-4 cursor-pointer">
                            <label for="is_global" class="text-sm font-medium text-slate-300 cursor-pointer select-none">Make Global (available to all projects)</label>
                        </div>
                    <?php endif; ?>

                    <button type="submit" id="btn-upload-submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-xl shadow-lg hover:shadow-indigo-500/20 py-2.5 px-4 transition duration-200">
                        Upload Asset
                    </button>
                </form>
            </div>
        </div>

        <!-- Main Assets Grid Area -->
        <div class="lg:col-span-3 space-y-6">
            <!-- Search and Filter Bar -->
            <div class="bg-slate-900/40 border border-slate-800 p-4 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4">
                <form method="GET" class="flex flex-col md:flex-row md:items-center gap-3 m-0 w-full flex-wrap">
                    <input type="hidden" name="project_id" value="<?php echo $activeProjectId; ?>">
                    <input type="hidden" name="type" value="<?php echo SecurityHelper::escape($typeFilter); ?>">
                    
                    <div class="relative w-full md:max-w-xs">
                        <input type="text" name="search" value="<?php echo SecurityHelper::escape($searchQuery); ?>" placeholder="Search filename or tag..." class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 pl-9 pr-4 py-2">
                        <svg class="absolute left-3 top-2.5 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>

                    <div class="flex items-center space-x-1.5">
                        <a href="?project_id=<?php echo $activeProjectId; ?>&type=all&search=<?php echo urlencode($searchQuery); ?>&sort=<?php echo urlencode($sort); ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold <?php echo $typeFilter === 'all' ? 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/30' : 'bg-slate-950 text-slate-400 hover:text-slate-200 border border-slate-800/80'; ?> transition">
                            All Assets
                        </a>
                        <a href="?project_id=<?php echo $activeProjectId; ?>&type=image&search=<?php echo urlencode($searchQuery); ?>&sort=<?php echo urlencode($sort); ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold <?php echo $typeFilter === 'image' ? 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/30' : 'bg-slate-950 text-slate-400 hover:text-slate-200 border border-slate-800/80'; ?> transition">
                            Images
                        </a>
                        <a href="?project_id=<?php echo $activeProjectId; ?>&type=font&search=<?php echo urlencode($searchQuery); ?>&sort=<?php echo urlencode($sort); ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold <?php echo $typeFilter === 'font' ? 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/30' : 'bg-slate-950 text-slate-400 hover:text-slate-200 border border-slate-800/80'; ?> transition">
                            Fonts
                        </a>
                    </div>

                    <!-- Sort Selector -->
                    <div class="flex items-center space-x-1.5">
                        <label for="sort-select" class="text-slate-400 text-xs font-medium whitespace-nowrap">Sort:</label>
                        <select id="sort-select" name="sort" onchange="this.form.submit()" class="bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-xl focus:ring-1 focus:ring-indigo-500 py-1.5 pl-3 pr-8 font-medium cursor-pointer">
                            <option value="date_desc" class="bg-slate-950 text-slate-100" <?php echo $sort === 'date_desc' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="date_asc" class="bg-slate-950 text-slate-100" <?php echo $sort === 'date_asc' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="name_asc" class="bg-slate-950 text-slate-100" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name (A → Z)</option>
                            <option value="name_desc" class="bg-slate-950 text-slate-100" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name (Z → A)</option>
                        </select>
                    </div>
                    
                    <?php if ($searchQuery !== '' || $typeFilter !== 'all' || $sort !== 'date_desc'): ?>
                        <a href="?project_id=<?php echo $activeProjectId; ?>" class="text-xs text-slate-500 hover:text-slate-300 self-center md:ml-auto">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Batch Selection & Action Bar -->
            <?php if (!empty($assets)): ?>
                <div id="batch-action-bar" class="bg-slate-900/80 border border-slate-800 p-3 rounded-2xl flex flex-wrap items-center justify-between gap-3 transition">
                    <div class="flex items-center space-x-3">
                        <label class="flex items-center space-x-2 text-xs font-semibold text-slate-200 cursor-pointer select-none">
                            <input type="checkbox" id="select-all-checkbox" onchange="toggleSelectAllAssets(this)" class="rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-indigo-500 h-4 w-4 cursor-pointer">
                            <span>Select All (<span id="selected-count" class="text-indigo-400 font-bold">0</span> / <?php echo count($assets); ?>)</span>
                        </label>
                        <button type="button" onclick="clearAssetSelection()" class="text-[11px] text-slate-400 hover:text-slate-200 underline">Clear</button>
                    </div>

                    <div class="flex items-center space-x-2 flex-wrap">
                        <!-- Batch Move / Assign -->
                        <form id="batch-move-form" action="" method="POST" class="m-0 flex items-center space-x-1.5" onsubmit="return handleBatchMoveSubmit(this);">
                            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                            <input type="hidden" name="action" value="batch_update_project">
                            <input type="hidden" name="selected_ids" id="batch-move-ids" value="">
                            
                            <select name="target_project_id" id="batch-target-project" class="bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-xl focus:ring-1 focus:ring-indigo-500 py-1.5 pl-2.5 pr-7 font-medium cursor-pointer">
                                <option value="" class="bg-slate-950 text-slate-100">🌐 Move to Global</option>
                                <?php foreach ($projects as $p): ?>
                                    <option value="<?php echo $p->getId(); ?>" class="bg-slate-950 text-slate-100" <?php echo ($activeProjectId === $p->getId()) ? 'disabled' : ''; ?>>
                                        📁 Assign to <?php echo SecurityHelper::escape($p->getName()); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button type="submit" id="btn-batch-move" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl shadow transition disabled:opacity-40 disabled:cursor-not-allowed" disabled>
                                Move Selected
                            </button>
                        </form>

                        <!-- Batch Delete -->
                        <form id="batch-delete-form" action="" method="POST" class="m-0" onsubmit="return handleBatchDeleteSubmit(this);">
                            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                            <input type="hidden" name="action" value="batch_delete">
                            <input type="hidden" name="selected_ids" id="batch-delete-ids" value="">
                            
                            <button type="submit" id="btn-batch-delete" class="px-3 py-1.5 bg-rose-600/20 hover:bg-rose-600/40 text-rose-300 border border-rose-500/30 text-xs font-semibold rounded-xl transition disabled:opacity-40 disabled:cursor-not-allowed" disabled>
                                Delete Selected
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Grid -->
            <?php if (empty($assets)): ?>
                <div class="p-16 text-center bg-slate-900/30 border border-dashed border-slate-800 rounded-3xl">
                    <svg class="mx-auto h-12 w-12 text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <h3 class="text-lg font-bold text-slate-300">No Assets Found</h3>
                    <p class="text-sm text-slate-500 mt-1 max-w-sm mx-auto">No assets match your current filters. Add standard board game images or design fonts in the left panel.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php foreach ($assets as $asset): ?>
                        <?php 
                        $isImage = str_starts_with($asset->getMimeType(), 'image/');
                        $ext = strtolower(pathinfo($asset->getStoredFilename(), PATHINFO_EXTENSION));
                        $isFont = str_contains($asset->getMimeType(), 'font') || in_array($ext, ['ttf', 'otf']);
                        $folderName = ($asset->getProjectId() === null) ? 'global' : $asset->getProjectId();
                        $fileUrl = '../uploads/board-game-studio/' . $folderName . '/' . $asset->getStoredFilename();
                        ?>
                        <div class="bg-slate-900 border border-slate-800/80 rounded-2xl overflow-hidden flex flex-col justify-between hover:border-slate-700 hover:shadow-lg transition group relative" id="asset-card-<?php echo $asset->getId(); ?>">
                            <!-- Checkbox selector -->
                            <label class="absolute top-2.5 left-2.5 z-20 flex items-center justify-center cursor-pointer p-1 rounded-lg bg-slate-900/90 hover:bg-slate-900 border border-slate-700/80 transition shadow select-none" title="Select asset">
                                <input type="checkbox" name="asset_select" value="<?php echo $asset->getId(); ?>" class="asset-item-checkbox rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-indigo-500 h-4 w-4 cursor-pointer" onchange="updateBatchActionBar()">
                            </label>

                            <!-- Preview Box -->
                            <div class="bg-slate-950 h-44 flex items-center justify-center relative overflow-hidden p-4 border-b border-slate-800/60">
                                <?php if ($isImage): ?>
                                    <img src="<?php echo $fileUrl; ?>" alt="<?php echo SecurityHelper::escape($asset->getOriginalFilename()); ?>" class="max-h-full max-w-full object-contain group-hover:scale-[1.03] transition duration-300">
                                <?php elseif ($isFont): ?>
                                    <div class="text-center space-y-2">
                                        <svg class="mx-auto h-12 w-12 text-indigo-400 bg-indigo-500/10 p-2.5 rounded-2xl" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                        </svg>
                                        <span class="text-xs uppercase font-extrabold tracking-wider text-slate-400 bg-slate-900 border border-slate-800 px-2 py-0.5 rounded">Font (<?php echo strtoupper($ext); ?>)</span>
                                    </div>
                                <?php else: ?>
                                    <div class="text-slate-500 text-center">
                                        <svg class="mx-auto h-10 w-10 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                        <span class="text-xs">Generic Asset</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Details Block -->
                            <div class="p-4 space-y-3">
                                <div>
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-sm font-bold text-slate-200 truncate pr-2 pl-1" title="<?php echo SecurityHelper::escape($asset->getOriginalFilename()); ?>">
                                            <?php echo SecurityHelper::escape($asset->getOriginalFilename()); ?>
                                        </h4>
                                        <?php if ($asset->getProjectId() === null): ?>
                                            <span class="text-[9px] font-bold uppercase tracking-wider text-indigo-400 bg-indigo-500/10 px-2 py-0.5 border border-indigo-500/20 rounded-md shrink-0">Global</span>
                                        <?php else: ?>
                                            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 bg-slate-800 px-2 py-0.5 border border-slate-700/80 rounded-md shrink-0">Project</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center justify-between text-[10px] text-slate-500 mt-1">
                                        <span><?php echo round($asset->getFileSizeBytes() / 1024, 1); ?> KB</span>
                                        <span>Uploaded <?php echo date('Y-m-d', strtotime($asset->getCreatedAt())); ?></span>
                                    </div>
                                </div>

                                <!-- Tag input form -->
                                <form action="" method="POST" class="m-0 flex items-center space-x-1.5">
                                    <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                    <input type="hidden" name="action" value="update_tag">
                                    <input type="hidden" name="asset_id" value="<?php echo $asset->getId(); ?>">
                                    
                                    <input type="text" name="tag" value="<?php echo SecurityHelper::escape($asset->getTag() ?? ''); ?>" placeholder="Add tag [icon]" class="bg-slate-950 border border-slate-800 text-slate-300 text-[11px] rounded-lg px-2 py-1 w-full focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                                    <button type="submit" class="p-1 text-xs text-indigo-400 hover:text-indigo-300 bg-indigo-500/5 border border-indigo-500/10 hover:border-indigo-500/30 rounded-lg transition" title="Save tag">
                                        Save
                                    </button>
                                </form>

                                <!-- Project Assignment & Delete Actions -->
                                <div class="pt-2 border-t border-slate-800/60 flex items-center justify-between gap-2">
                                    <form action="" method="POST" class="m-0 flex items-center gap-1.5 flex-grow">
                                        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                        <input type="hidden" name="action" value="update_asset_project">
                                        <input type="hidden" name="asset_id" value="<?php echo $asset->getId(); ?>">
                                        
                                        <select name="target_project_id" onchange="this.form.submit()" class="bg-slate-950 border border-slate-800 text-slate-300 text-[11px] rounded-lg px-2 py-1 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer w-full" title="Change asset scope (Global or assign to a specific project)">
                                            <option value="" <?php echo $asset->getProjectId() === null ? 'selected' : ''; ?>>🌐 Global</option>
                                            <?php foreach ($projects as $p): ?>
                                                <option value="<?php echo $p->getId(); ?>" <?php echo $asset->getProjectId() === $p->getId() ? 'selected' : ''; ?>>
                                                    📁 <?php echo SecurityHelper::escape($p->getName()); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>

                                    <form action="" method="POST" class="m-0" onsubmit="return showCustomConfirm('Are you sure you want to delete this asset? This cannot be undone and may break canvas layers referencing this asset.', this, 'Delete', 'Delete Asset');">
                                        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                        <input type="hidden" name="action" value="delete_asset">
                                        <input type="hidden" name="asset_id" value="<?php echo $asset->getId(); ?>">
                                        
                                        <button type="submit" class="text-xs text-rose-500 hover:text-rose-400 bg-rose-500/5 hover:bg-rose-500/10 border border-rose-500/10 hover:border-rose-500/20 px-2 py-1 rounded-lg transition" title="Delete File">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Upload Processing Overlay Modal -->
<div id="upload-processing-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-md hidden transition-opacity duration-300">
    <div class="bg-slate-900 border border-slate-800 p-8 rounded-2xl shadow-2xl max-w-md w-full text-center space-y-5">
        <!-- Animated Spinner -->
        <div class="relative w-16 h-16 mx-auto">
            <div class="w-16 h-16 rounded-full border-4 border-indigo-500/20 border-t-indigo-500 animate-spin"></div>
            <svg class="w-7 h-7 text-indigo-400 absolute inset-0 m-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
        </div>
        <div class="space-y-1.5">
            <h3 class="text-lg font-bold text-slate-100">Processing Upload & Unpacking Assets...</h3>
            <p class="text-xs text-slate-400 leading-relaxed">Please wait while your files or ZIP archive are being uploaded, extracted, and registered. Do not refresh or navigate away from this page.</p>
        </div>
        <div class="inline-flex items-center space-x-2 bg-indigo-500/10 border border-indigo-500/20 px-3 py-1.5 rounded-full text-[11px] font-semibold text-indigo-400">
            <span class="w-2 h-2 rounded-full bg-indigo-400 animate-ping"></span>
            <span id="upload-status-text">Import in progress...</span>
        </div>
    </div>
</div>

<script>
    // ponytail: rich file preview card with live thumbnail and clear action
    const fileInput = document.getElementById('asset_file');
    const previewContainer = document.getElementById('file_selected_preview');
    const tagInput = document.getElementById('tag');

    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function clearSelectedFiles() {
        if (fileInput) fileInput.value = '';
        if (previewContainer) {
            previewContainer.innerHTML = '';
            previewContainer.classList.add('hidden');
        }
    }

    if (fileInput && previewContainer) {
        fileInput.addEventListener('change', (e) => {
            const files = e.target.files;
            if (!files || files.length === 0) {
                clearSelectedFiles();
                return;
            }

            previewContainer.innerHTML = '';
            previewContainer.classList.remove('hidden');

            if (files.length === 1) {
                const file = files[0];
                const isImg = file.type.startsWith('image/') || file.name.toLowerCase().endsWith('.svg');
                const thumbSrc = isImg ? URL.createObjectURL(file) : null;

                const card = document.createElement('div');
                card.className = "bg-slate-950 border border-indigo-500/40 rounded-xl p-3 flex items-center justify-between gap-3 shadow-lg shadow-indigo-500/5";

                card.innerHTML = `
                    <div class="flex items-center space-x-3 overflow-hidden min-w-0">
                        <div class="w-10 h-10 rounded-lg bg-slate-900 border border-slate-800 flex items-center justify-center flex-shrink-0 overflow-hidden p-1">
                            ${thumbSrc 
                                ? `<img src="${thumbSrc}" class="w-full h-full object-contain" alt="Preview">` 
                                : `<svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>`}
                        </div>
                        <div class="min-w-0">
                            <div class="text-xs font-semibold text-slate-100 truncate" title="${file.name}">${file.name}</div>
                            <div class="flex items-center space-x-2 mt-0.5">
                                <span class="text-[10px] text-slate-400 font-mono">${formatBytes(file.size)}</span>
                                <span class="text-[10px] text-emerald-400 font-medium flex items-center gap-0.5">
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                    Ready to upload
                                </span>
                            </div>
                        </div>
                    </div>
                    <button type="button" id="btn-clear-file" title="Remove file" class="text-slate-400 hover:text-rose-400 transition p-1 rounded-lg hover:bg-slate-900 flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                `;

                previewContainer.appendChild(card);
                document.getElementById('btn-clear-file')?.addEventListener('click', clearSelectedFiles);

                if (tagInput && !tagInput.value.trim()) {
                    const cleanTag = file.name.substring(0, file.name.lastIndexOf('.')) || file.name;
                    tagInput.placeholder = cleanTag;
                }
            } else {
                let totalBytes = 0;
                for (let i = 0; i < files.length; i++) totalBytes += files[i].size;

                const card = document.createElement('div');
                card.className = "bg-slate-950 border border-indigo-500/40 rounded-xl p-3 flex items-center justify-between gap-3 shadow-lg shadow-indigo-500/5";
                card.innerHTML = `
                    <div class="flex items-center space-x-3 overflow-hidden min-w-0">
                        <div class="w-10 h-10 rounded-lg bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center flex-shrink-0 text-indigo-400 font-bold text-xs">
                            ${files.length}x
                        </div>
                        <div class="min-w-0">
                            <div class="text-xs font-semibold text-slate-100">${files.length} files selected</div>
                            <div class="flex items-center space-x-2 mt-0.5">
                                <span class="text-[10px] text-slate-400 font-mono">${formatBytes(totalBytes)} total</span>
                                <span class="text-[10px] text-emerald-400 font-medium flex items-center gap-0.5">
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                    Ready
                                </span>
                            </div>
                        </div>
                    </div>
                    <button type="button" id="btn-clear-file" title="Remove all files" class="text-slate-400 hover:text-rose-400 transition p-1 rounded-lg hover:bg-slate-900 flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                `;
                previewContainer.appendChild(card);
                document.getElementById('btn-clear-file')?.addEventListener('click', clearSelectedFiles);
            }
        });
    }

    function handleAssetUploadSubmit(form) {
        const input = document.getElementById('asset_file');
        if (input && input.files.length > 0) {
            const modal = document.getElementById('upload-processing-modal');
            const statusText = document.getElementById('upload-status-text');
            const submitBtn = document.getElementById('btn-upload-submit');

            if (input.files.length > 1) {
                statusText.textContent = `Uploading & registering ${input.files.length} files...`;
            } else if (input.files[0].name.endsWith('.zip')) {
                statusText.textContent = `Extracting & importing ZIP archive...`;
            } else {
                statusText.textContent = `Uploading ${input.files[0].name}...`;
            }

            if (modal) modal.classList.remove('hidden');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                submitBtn.textContent = 'Processing Upload...';
            }
        }
        return true;
    }

    // Batch Action Operations
    function getSelectedAssetIds() {
        const checkboxes = document.querySelectorAll('.asset-item-checkbox:checked');
        return Array.from(checkboxes).map(cb => cb.value);
    }

    function updateBatchActionBar() {
        const selected = getSelectedAssetIds();
        const count = selected.length;
        const countSpan = document.getElementById('selected-count');
        if (countSpan) countSpan.textContent = count;

        const allCheckboxes = document.querySelectorAll('.asset-item-checkbox');
        const selectAll = document.getElementById('select-all-checkbox');
        if (selectAll) {
            selectAll.checked = allCheckboxes.length > 0 && count === allCheckboxes.length;
            selectAll.indeterminate = count > 0 && count < allCheckboxes.length;
        }

        const btnMove = document.getElementById('btn-batch-move');
        const btnDelete = document.getElementById('btn-batch-delete');
        if (btnMove) btnMove.disabled = count === 0;
        if (btnDelete) btnDelete.disabled = count === 0;

        // Card ring highlight
        allCheckboxes.forEach(cb => {
            const card = document.getElementById(`asset-card-${cb.value}`);
            if (card) {
                if (cb.checked) {
                    card.classList.add('ring-2', 'ring-indigo-500');
                } else {
                    card.classList.remove('ring-2', 'ring-indigo-500');
                }
            }
        });
    }

    function toggleSelectAllAssets(master) {
        const checkboxes = document.querySelectorAll('.asset-item-checkbox');
        checkboxes.forEach(cb => { cb.checked = master.checked; });
        updateBatchActionBar();
    }

    function clearAssetSelection() {
        const checkboxes = document.querySelectorAll('.asset-item-checkbox');
        checkboxes.forEach(cb => { cb.checked = false; });
        updateBatchActionBar();
    }

    function handleBatchMoveSubmit(form) {
        const selected = getSelectedAssetIds();
        if (selected.length === 0) return false;
        const selectEl = document.getElementById('batch-target-project');
        const targetText = selectEl.options[selectEl.selectedIndex].text.trim();
        
        window.studioConfirm(`Move ${selected.length} selected asset(s) to "${targetText}"?`, 'Move', 'Move Assets').then(confirmed => {
            if (confirmed) {
                document.getElementById('batch-move-ids').value = selected.join(',');
                form.submit();
            }
        });
        return false;
    }

    function handleBatchDeleteSubmit(form) {
        const selected = getSelectedAssetIds();
        if (selected.length === 0) return false;

        window.studioConfirm(`Are you sure you want to permanently delete ${selected.length} selected asset(s)? This action cannot be undone.`, 'Delete', 'Delete Assets').then(confirmed => {
            if (confirmed) {
                document.getElementById('batch-delete-ids').value = selected.join(',');
                form.submit();
            }
        });
        return false;
    }
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
