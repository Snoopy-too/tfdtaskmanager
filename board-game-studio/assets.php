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

$currentUserId = (int)($_SESSION['user_id'] ?? 0);

// Projects dropdown - only board games
$projects = $projectService->getAllProjects($currentUserId);
$boardGameProjects = array_values(array_filter($projects, fn($p) => $p->isBoardGame()));

$isExplicitGlobal = isset($_GET['project_id']) && ($_GET['project_id'] === '' || $_GET['project_id'] === 'global');
$activeProjectId = (isset($_GET['project_id']) && $_GET['project_id'] !== '' && $_GET['project_id'] !== 'global') ? (int)$_GET['project_id'] : null;

// If neither a project nor explicit global was requested, redirect to index to choose a board game
if ($activeProjectId === null && !$isExplicitGlobal) {
    if (isset($_SESSION['last_project_id'])) {
        $candidateId = (int)$_SESSION['last_project_id'];
        $candProject = $projectService->getProjectById($candidateId, $currentUserId);
        if ($candProject && $candProject->isBoardGame()) {
            $activeProjectId = $candidateId;
        } else {
            unset($_SESSION['last_project_id']);
        }
    }
    if ($activeProjectId === null) {
        header("Location: index.php");
        exit;
    }
}

if ($activeProjectId) {
    $_SESSION['last_project_id'] = $activeProjectId;
} else {
    unset($_SESSION['last_project_id']);
}

$activeProject = null;
if ($activeProjectId) {
    $activeProject = $projectService->getProjectById($activeProjectId, $currentUserId);
}

if ($activeProjectId && (!$activeProject || !$activeProject->isBoardGame())) {
    unset($_SESSION['last_project_id']);
    header("Location: index.php");
    exit;
}

// Handle Actions
require_once __DIR__ . '/asset-actions.php';


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

if ($activeProject) {
    renderPageHeader($activeProject->getName(), 'Asset Library', '', 'index.php');
} else {
    renderPageHeader('Asset Library', 'Global Shared Assets', '', 'index.php');
}
?>

<div class="page-container space-y-6">
    <?php if ($activeProject): ?>
        <?php
        $activeTab = 'assets';
        include __DIR__ . '/views/studio-nav.php';
        ?>
    <?php else: ?>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-slate-900/90 border border-slate-800 rounded-2xl p-4 backdrop-blur-md shadow-sm">
            <div class="flex items-center gap-3">
                <a href="index.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition duration-150 border border-slate-700/60">
                    <i class="bi bi-arrow-left"></i>
                    <span>All Games</span>
                </a>
                <div class="h-4 w-[1px] bg-slate-800 hidden sm:block"></div>
                <div class="text-sm font-bold text-slate-100 flex items-center gap-2">
                    <span>🌐</span>
                    <span>Global Asset Library <span class="text-xs text-slate-400 font-normal">(Shared across all games)</span></span>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <label for="project_select" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Game:</label>
                <form method="GET" class="m-0">
                    <select id="project_select" name="project_id" onchange="this.form.submit()" class="bg-slate-950 border border-slate-700/80 text-slate-100 text-xs font-semibold rounded-lg focus:ring-1 focus:ring-indigo-500 py-1.5 pl-2.5 pr-8 cursor-pointer">
                        <option value="" selected>None (Global Library)</option>
                        <?php foreach ($boardGameProjects as $bgp): ?>
                            <option value="<?php echo $bgp->getId(); ?>">
                                <?php echo SecurityHelper::escape('🎲 ' . $bgp->getName()); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>
    <?php endif; ?>

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

    <?php include __DIR__ . '/views/assets-upload-panel.php'; ?>

    <?php include __DIR__ . '/views/assets-grid.php'; ?>
</div>

<?php include __DIR__ . '/views/assets-modals.php'; ?>

<script src="js/assets-page.js"></script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
