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

// Handle Actions
require_once __DIR__ . '/asset-actions.php';


// Auto-sync built-in global SVG icons from repository
$currentUserId = (int)($_SESSION['user_id'] ?? 1);
$assetService->syncBuiltinGlobalIcons($currentUserId);

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
        <?php include __DIR__ . '/views/assets-sidebar.php'; ?>

        <?php include __DIR__ . '/views/assets-grid.php'; ?>
    </div>
</div>

<?php include __DIR__ . '/views/assets-modals.php'; ?>

<script src="js/assets-page.js"></script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
