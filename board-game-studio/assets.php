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
$activeProjectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
if (!$activeProjectId && !empty($projects)) {
    $activeProjectId = $projects[0]->getId();
}

$activeProject = null;
if ($activeProjectId) {
    $activeProject = $projectService->getProjectById($activeProjectId);
}

if (!$activeProject) {
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
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);

        try {
            if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
                throw new ValidationException("Please select a file to upload.");
            }
            $assetService->uploadAsset($activeProjectId, $file, $tag, $currentUserId);
            $success = "Asset uploaded successfully.";
        } catch (ValidationException $e) {
            $error = $e->getMessage();
        } catch (\Exception $e) {
            $error = "An error occurred during file upload: " . $e->getMessage();
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

// Load assets
$assets = $activeProjectId ? $assetService->getAssetsByProject($activeProjectId) : [];

// Filters
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$typeFilter = isset($_GET['type']) ? trim($_GET['type']) : 'all';

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
            <h1 class="text-3xl font-extrabold tracking-tight text-white mt-1">Global Asset Library</h1>
            <p class="text-slate-400 mt-1">Upload and organize images, icons, and fonts for canvas template layers.</p>
        </div>

        <div class="flex items-center space-x-2 bg-slate-900 border border-slate-800 p-2 rounded-xl">
            <label for="project_select" class="text-xs font-semibold text-slate-400 uppercase tracking-wider pl-2">Project:</label>
            <form method="GET" class="m-0">
                <select id="project_select" name="project_id" onchange="this.form.submit()" class="bg-slate-950 border-0 text-slate-100 text-sm rounded-lg focus:ring-2 focus:ring-indigo-500 py-1.5 pl-3 pr-8 font-medium cursor-pointer">
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
                
                <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                    <input type="hidden" name="action" value="upload_asset">
                    
                    <div>
                        <label for="asset_file" class="block text-sm font-medium text-slate-300 mb-1">Select File</label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-slate-800 border-dashed rounded-xl hover:border-indigo-500/50 transition cursor-pointer relative group">
                            <input type="file" id="asset_file" name="asset_file" accept=".png,.jpg,.jpeg,.svg,.ttf,.otf" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                            <div class="space-y-1 text-center pointer-events-none">
                                <svg class="mx-auto h-10 w-10 text-slate-500 group-hover:text-indigo-400 transition" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4-4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="text-xs text-slate-300">
                                    <span class="font-medium text-indigo-400 group-hover:text-indigo-300 transition">Click to upload</span> or drag and drop
                                </div>
                                <p class="text-[10px] text-slate-500">PNG, JPG, SVG, TTF, OTF up to 10MB</p>
                            </div>
                        </div>
                        <div id="file_selected_name" class="text-xs text-indigo-400 mt-2 font-medium hidden"></div>
                    </div>

                    <div>
                        <label for="tag" class="block text-sm font-medium text-slate-300 mb-1">Asset Tag (Optional)</label>
                        <input type="text" id="tag" name="tag" placeholder="e.g. icon_health or font_title" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 p-2.5">
                        <p class="text-[10px] text-slate-500 mt-1">Tags let you insert dynamic icons via text boxes (e.g. [icon_health]).</p>
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-xl shadow-lg hover:shadow-indigo-500/20 py-2.5 px-4 transition duration-200">
                        Upload Asset
                    </button>
                </form>
            </div>
        </div>

        <!-- Main Assets Grid Area -->
        <div class="lg:col-span-3 space-y-6">
            <!-- Search and Filter Bar -->
            <div class="bg-slate-900/40 border border-slate-800 p-4 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4">
                <form method="GET" class="flex flex-col md:flex-row md:items-center gap-4 m-0 w-full">
                    <input type="hidden" name="project_id" value="<?php echo $activeProjectId; ?>">
                    
                    <div class="relative w-full md:max-w-xs">
                        <input type="text" name="search" value="<?php echo SecurityHelper::escape($searchQuery); ?>" placeholder="Search filename or tag..." class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 pl-9 pr-4 py-2">
                        <svg class="absolute left-3 top-2.5 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>

                    <div class="flex items-center space-x-2">
                        <a href="?project_id=<?php echo $activeProjectId; ?>&type=all&search=<?php echo urlencode($searchQuery); ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold <?php echo $typeFilter === 'all' ? 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/30' : 'bg-slate-950 text-slate-400 hover:text-slate-200 border border-slate-800/80'; ?> transition">
                            All Assets
                        </a>
                        <a href="?project_id=<?php echo $activeProjectId; ?>&type=image&search=<?php echo urlencode($searchQuery); ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold <?php echo $typeFilter === 'image' ? 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/30' : 'bg-slate-950 text-slate-400 hover:text-slate-200 border border-slate-800/80'; ?> transition">
                            Images
                        </a>
                        <a href="?project_id=<?php echo $activeProjectId; ?>&type=font&search=<?php echo urlencode($searchQuery); ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold <?php echo $typeFilter === 'font' ? 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/30' : 'bg-slate-950 text-slate-400 hover:text-slate-200 border border-slate-800/80'; ?> transition">
                            Fonts
                        </a>
                    </div>
                    
                    <?php if ($searchQuery !== '' || $typeFilter !== 'all'): ?>
                        <a href="?project_id=<?php echo $activeProjectId; ?>" class="text-xs text-slate-500 hover:text-slate-300 self-center md:ml-auto">Clear Filters</a>
                    <?php endif; ?>
                </form>
            </div>

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
                        $fileUrl = '../uploads/board-game-studio/' . $asset->getProjectId() . '/' . $asset->getStoredFilename();
                        ?>
                        <div class="bg-slate-900 border border-slate-800/80 rounded-2xl overflow-hidden flex flex-col justify-between hover:border-slate-700 hover:shadow-lg transition group">
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
                                    <h4 class="text-sm font-bold text-slate-200 truncate" title="<?php echo SecurityHelper::escape($asset->getOriginalFilename()); ?>">
                                        <?php echo SecurityHelper::escape($asset->getOriginalFilename()); ?>
                                    </h4>
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

                                <!-- Delete button -->
                                <div class="flex justify-end pt-1">
                                    <form action="" method="POST" class="m-0" onsubmit="return showCustomConfirm('Are you sure you want to delete this asset? This cannot be undone and may break canvas layers referencing this asset.', this);">
                                        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                        <input type="hidden" name="action" value="delete_asset">
                                        <input type="hidden" name="asset_id" value="<?php echo $asset->getId(); ?>">
                                        
                                        <button type="submit" class="text-xs text-rose-500 hover:text-rose-400 bg-rose-500/5 hover:bg-rose-500/10 border border-rose-500/10 hover:border-rose-500/20 px-2.5 py-1 rounded-lg transition">
                                            Delete File
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

<script>
    // Show selected file name inside upload box
    const fileInput = document.getElementById('asset_file');
    const fileNameDiv = document.getElementById('file_selected_name');
    if (fileInput) {
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                fileNameDiv.textContent = `Selected: ${e.target.files[0].name}`;
                fileNameDiv.classList.remove('hidden');
            } else {
                fileNameDiv.classList.add('hidden');
            }
        });
    }
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
