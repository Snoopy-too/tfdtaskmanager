<?php
declare(strict_types=1);

$container = require_once __DIR__ . '/../src/bootstrap.php';

use App\Infrastructure\Security\SecurityHelper;
use App\Application\Services\ProjectService;
use App\Application\Services\BgTemplateService;
use App\Application\Services\BgDatasetService;
use App\Application\Exceptions\ValidationException;

SecurityHelper::requireLogin();

$projectService = $container->get(ProjectService::class);
$templateService = $container->get(BgTemplateService::class);
$datasetService = $container->get(BgDatasetService::class);

$csrfToken = SecurityHelper::generateCsrfToken();

$templateId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$template = $templateService->getTemplateById($templateId);

if (!$template) {
    header("Location: index.php");
    exit;
}

// Check lock status
$lockUser = null;
$isViewMode = false;
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

if ($templateService->isTemplateLockedByOther($template, $currentUserId)) {
    $isViewMode = true;
    $userService = $container->get(\App\Application\Services\UserService::class);
    $lockUser = $userService->getUserById($template->getLockedByUserId());
} else {
    // Acquire or refresh lock
    $templateService->acquireOrRefreshLock($template->getId(), $currentUserId);
}

// Handle Template Duplication from Editor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'duplicate_template') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        header("Location: index.php");
        exit;
    } else {
        $newName = $_POST['new_name'] ?? '';
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        try {
            $newTemplate = $templateService->cloneTemplate($templateId, $newName, $currentUserId);
            header("Location: editor.php?id=" . $newTemplate->getId());
            exit;
        } catch (\Exception $e) {
            error_log('[BoardGameStudio] duplicate_template in editor error: ' . $e->getMessage());
            header("Location: editor.php?id=" . $templateId . "&error=" . urlencode($e->getMessage()));
            exit;
        }
    }
}

$project = $projectService->getProjectById($template->getProjectId());
$_SESSION['last_project_id'] = $template->getProjectId();
$compTypes = $templateService->getComponentTypes();
$compType = null;
foreach ($compTypes as $ct) {
    if ($ct->getId() === $template->getComponentTypeId()) {
        $compType = $ct;
        break;
    }
}

// Fetch project datasets
$projectDatasets = $datasetService->getDatasetsByProject($template->getProjectId());

// Check if dataset is bound
$dataset = null;
if ($template->getDatasetId()) {
    $dataset = $datasetService->getDatasetById($template->getDatasetId());
}

require_once __DIR__ . '/../templates/header.php';
?>

<!-- FabricJS and export libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<!-- Google Fonts for Board Game Creators -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Almendra:ital,wght@0,400;0,700;1,400&family=Bangers&family=Cinzel:wght@400;700&family=Comic+Neue:wght@400;700&family=Creepster&family=EB+Garamond:ital,wght@0,400;0,700;1,400&family=Fredoka:wght@400;700&family=Inter:wght@400;700&family=Jolly+Lodger&family=Lora:ital,wght@0,400;0,700;1,400&family=Luckiest+Guy&family=MedievalSharp&family=Metal+Mania&family=Montserrat:wght@400;700&family=Orbitron:wght@400;700&family=Outfit:wght@400;700&family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Rajdhani:wght@500;700&family=Rye&family=Share+Tech+Mono&family=Courier+Prime&family=Special+Elite&display=swap" rel="stylesheet">

<!-- CSS for Editor Grid -->
<link rel="stylesheet" href="css/editor.css?v=<?php echo filemtime(__DIR__ . '/css/editor.css'); ?>">

<div class="space-y-4 flex-grow flex flex-col min-h-0">
    <?php if ($isViewMode): ?>
        <div class="bg-rose-500/10 border border-rose-500/20 text-rose-450 p-3 rounded-xl text-sm flex items-center justify-between gap-4">
            <div class="flex items-center space-x-2">
                <svg class="h-5 w-5 text-rose-455 animate-pulse flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <span><strong>Read-Only View:</strong> This template is currently locked for editing by <strong><?php echo SecurityHelper::escape($lockUser ? $lockUser->getName() : 'another user'); ?></strong>.</span>
            </div>
            <a href="index.php?project_id=<?php echo $template->getProjectId(); ?>" class="px-3 py-1 bg-rose-500/20 hover:bg-rose-500/30 text-rose-350 hover:text-white rounded-lg text-xs font-semibold transition flex-shrink-0">
                Back to Dashboard
            </a>
        </div>
    <?php endif; ?>

    <!-- Top editor status/controls -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-2 border-b border-slate-800">
        <div class="flex items-center space-x-3">
            <a href="index.php?project_id=<?php echo $template->getProjectId(); ?>" class="p-1.5 bg-slate-900 border border-slate-800 rounded-lg text-slate-400 hover:text-white transition">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <?php
                $isLandscape = $template->getCanvasWidthPx() > $template->getCanvasHeightPx();
                $widthMm = round(\App\Domain\Entities\BgTemplate::pxToMm($template->getCanvasWidthPx()), 1);
                $heightMm = round(\App\Domain\Entities\BgTemplate::pxToMm($template->getCanvasHeightPx()), 1);
                ?>
                <h1 class="text-xl font-bold text-white flex items-center space-x-2">
                    <span id="template-title-display"><?php echo SecurityHelper::escape($template->getName()); ?></span>
                    <?php if (!$isViewMode): ?>
                        <button onclick="promptRenameTemplate(<?php echo $template->getId(); ?>, '<?php echo SecurityHelper::escape(addslashes($template->getName())); ?>')" class="text-slate-400 hover:text-amber-400 transition p-1" title="Rename Template">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        </button>
                    <?php endif; ?>
                    <span class="text-xs uppercase font-extrabold px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                        <?php echo $compType ? SecurityHelper::escape($compType->getName()) : 'Component'; ?>
                    </span>
                    <span id="template-orientation-badge" class="text-xs font-semibold px-2 py-0.5 rounded <?php echo $isLandscape ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'bg-slate-800 text-slate-300 border border-slate-700'; ?>">
                        <?php echo $isLandscape ? 'Landscape' : 'Portrait'; ?>
                    </span>
                </h1>
                <p class="text-xs text-slate-400 flex items-center space-x-1.5 flex-wrap">
                    <span>Project: <?php echo SecurityHelper::escape($project->getName()); ?></span>
                    <span>|</span>
                    <span class="flex items-center space-x-1">
                        <span>Size: <span id="template-size-display"><?php echo $widthMm; ?>x<?php echo $heightMm; ?> mm (<?php echo $template->getCanvasWidthPx(); ?>x<?php echo $template->getCanvasHeightPx(); ?> px)</span></span>
                        <?php if (!$isViewMode): ?>
                            <button onclick="openChangeSizeModal()" class="text-slate-400 hover:text-amber-400 transition p-0.5" title="Change Canvas / Template Size">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            </button>
                        <?php endif; ?>
                    </span>
                </p>
            </div>
        </div>

        <div class="flex items-center space-x-3">
            <!-- Auto-save Status Indicator -->
            <div id="save-status" class="flex items-center space-x-1.5 text-xs text-slate-400 bg-slate-900/60 border border-slate-800 px-3 py-1.5 rounded-lg">
                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span id="save-status-text">All changes saved</span>
            </div>

            <?php if (!$isViewMode): ?>
                <!-- Orientation Switch Button -->
                <button id="btn-toggle-orientation" onclick="toggleCanvasOrientation()" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-900 border border-slate-800 text-slate-300 hover:text-white hover:border-slate-700 rounded transition flex items-center gap-1.5" title="Switch Canvas Orientation (Portrait ↔ Landscape)">
                    <svg id="orient-btn-icon" class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span id="orient-btn-text">Switch to <?php echo $isLandscape ? 'Portrait' : 'Landscape'; ?></span>
                </button>
            <?php endif; ?>

            <!-- Guides Toggle -->
            <button id="btn-toggle-guides" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 hover:bg-indigo-500/20 rounded transition">
                Guides: ON
            </button>

            <!-- History controls -->
            <div class="flex items-center space-x-1 bg-slate-900 border border-slate-800 rounded-lg p-0.5">
                <button id="btn-undo" class="p-1.5 hover:bg-slate-800 text-slate-400 hover:text-white disabled:opacity-30 disabled:hover:bg-transparent rounded transition" title="Undo (Ctrl+Z)" disabled>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                    </svg>
                </button>
                <button id="btn-redo" class="p-1.5 hover:bg-slate-800 text-slate-400 hover:text-white disabled:opacity-30 disabled:hover:bg-transparent rounded transition" title="Redo (Ctrl+Y)" disabled>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10h-10a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6"/>
                    </svg>
                </button>
            </div>

            <!-- Zoom controls -->
            <div class="flex items-center space-x-1 bg-slate-900 border border-slate-800 rounded-lg p-0.5">
                <button id="btn-zoom-out" class="p-1 hover:bg-slate-800 text-slate-400 hover:text-white rounded" title="Zoom Out">-</button>
                <input type="text" id="zoom-value" class="text-xs font-semibold text-center text-slate-300 bg-transparent w-12 border-none focus:outline-none focus:ring-0 p-0" value="100%">
                <button id="btn-zoom-in" class="p-1 hover:bg-slate-800 text-slate-400 hover:text-white rounded" title="Zoom In">+</button>
                <button id="btn-zoom-fit" class="p-1 hover:bg-slate-800 text-slate-500 hover:text-white rounded text-[10px] font-bold px-1.5" title="Fit to View">FIT</button>
            </div>

            <!-- Sidebar Toggle Controls -->
            <div class="flex items-center space-x-1 bg-slate-900 border border-slate-800 rounded-lg p-0.5">
                <button id="btn-toggle-left-sidebar" onclick="toggleSidebar('left-layers-panel')" class="p-1 hover:bg-slate-800 text-slate-400 hover:text-white rounded text-xs px-2 flex items-center space-x-1" title="Toggle Left Layers Panel">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                    <span>Layers</span>
                </button>
                <button id="btn-toggle-right-sidebar" onclick="toggleSidebar('right-inspector-panel')" class="p-1 hover:bg-slate-800 text-slate-400 hover:text-white rounded text-xs px-2 flex items-center space-x-1" title="Toggle Right Inspector Panel">
                    <span>Inspector</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/></svg>
                </button>
            </div>

            <!-- Preview -->
            <button type="button" onclick="showFullscreenPreview()" class="px-4 py-1.5 bg-slate-900 border border-slate-800 text-slate-350 hover:text-white text-xs font-semibold rounded-lg shadow transition flex items-center gap-1.5" title="Full Screen Preview">
                <svg class="h-3.5 w-3.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <span>Preview</span>
            </button>
            <!-- Copy -->
            <button type="button" onclick="makeCopy()" class="px-4 py-1.5 bg-slate-900 border border-slate-800 text-slate-300 hover:text-white text-xs font-semibold rounded-lg shadow transition">
                Make a Copy
            </button>
            <!-- Export -->
            <a href="export.php?project_id=<?php echo $template->getProjectId(); ?>&template_id=<?php echo $template->getId(); ?>" class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow transition">
                Export Studio
            </a>
        </div>
    </div>

    <!-- Main Workspace Flex Container (Fixed 280px sidebars + flex-1 expanded canvas) -->
    <div class="editor-container flex gap-3 flex-grow min-h-0 w-full overflow-hidden">
        
        <!-- Left Panel: Layers and Assets -->
        <?php include __DIR__ . '/views/editor-layers-panel.php'; ?>

        <!-- Central Panel: Canvas Area (Expands dynamically to fill remaining workspace) -->
        <div id="center-canvas-panel" class="flex-1 min-w-0 flex flex-col h-full bg-slate-950 border border-slate-800/60 rounded-2xl overflow-hidden relative transition-all duration-200">
            <div class="canvas-viewport flex-grow overflow-auto flex p-2 md:p-3 relative">
                <!-- Outer scaled container to handle flex-scroll centering -->
                <div id="canvas-zoom-container" class="shrink-0" style="margin: auto; position: relative; flex-shrink: 0;">
                    <!-- Wrapper for absolute alignment and sizing -->
                    <div id="canvas-container-wrapper" class="relative shadow-2xl border border-slate-700/50" style="transform-origin: 0 0; max-width: none !important; max-height: none !important;">
                        <canvas id="editor-canvas"></canvas>
                    </div>
                </div>
            </div>

            <!-- Bottom Row Data navigation & Dataset Switcher -->
            <div class="bg-slate-900 border-t border-slate-800 p-2.5 flex flex-wrap items-center justify-between gap-2.5">
                <div class="flex items-center space-x-2 shrink-0">
                    <span class="w-2.5 h-2.5 rounded-full <?php echo $dataset ? 'bg-violet-400' : 'bg-slate-600'; ?>" id="dataset-status-dot"></span>
                    <label for="editor-dataset-select" class="text-xs font-semibold text-slate-300">
                        Dataset:
                    </label>
                    <select id="editor-dataset-select" onchange="if(window.templateEngine && typeof window.templateEngine.switchDataset === 'function') window.templateEngine.switchDataset(this.value);" class="bg-slate-950 border border-slate-800 text-slate-200 text-xs rounded-xl p-1.5 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">No Dataset Bound</option>
                        <?php foreach ($projectDatasets as $ds): ?>
                            <option value="<?php echo $ds->getId(); ?>" <?php echo ($template->getDatasetId() === $ds->getId()) ? 'selected' : ''; ?>>
                                <?php echo SecurityHelper::escape($ds->getName()); ?> (<?php echo count($ds->getRowData()); ?> rows)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="dataset-filter-container" class="flex items-center space-x-1.5 bg-slate-950 border border-slate-800 rounded-xl px-2 py-1 <?php echo $dataset ? '' : 'hidden'; ?>">
                    <label for="template-row-filter" class="text-xs font-semibold text-slate-400">Rows Filter:</label>
                    <input type="text" id="template-row-filter" 
                           value="<?php echo SecurityHelper::escape($template->getRowFilter() ?? ''); ?>" 
                           placeholder="All (e.g. 1-42)" 
                           title="Specify active rows e.g. 1-42 or 43-82" 
                           class="w-28 bg-slate-900 border border-slate-800 text-slate-200 text-xs rounded px-2 py-0.5 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 font-mono text-center">
                </div>

                <div id="dataset-nav-controls" class="flex items-center space-x-3 <?php echo $dataset ? '' : 'hidden'; ?>">
                    <button id="btn-row-prev" class="p-1 bg-slate-950 border border-slate-800 rounded hover:bg-slate-800 text-slate-400 hover:text-white transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <span id="row-indicator" class="text-xs text-slate-300 font-bold min-w-[70px] text-center">
                        Row 1 of <?php echo $dataset ? count($dataset->getRowData()) : 1; ?>
                    </span>
                    <button id="btn-row-next" class="p-1 bg-slate-950 border border-slate-800 rounded hover:bg-slate-800 text-slate-400 hover:text-white transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>

                <div id="dataset-total-container" class="text-xs text-slate-400 shrink-0 <?php echo $dataset ? '' : 'hidden'; ?>">
                    Total Rows: <span id="row-total" class="font-bold text-slate-200"><?php echo $dataset ? count($dataset->getRowData()) : 0; ?></span>
                </div>
            </div>
        </div>

        <!-- Right Panel: Properties Inspector -->
        <?php include __DIR__ . '/views/editor-inspector-panel.php'; ?>
    </div>
</div>


<!-- Configuration parameters injected to JavaScript -->
<script>
    window.studioConfig = {
        templateId: <?php echo $template->getId(); ?>,
        projectId: <?php echo $template->getProjectId(); ?>,
        csrfToken: "<?php echo SecurityHelper::escape($csrfToken); ?>",
        templateName: "<?php echo SecurityHelper::escape(addslashes($template->getName())); ?>",
        isViewMode: <?php echo $isViewMode ? 'true' : 'false'; ?>,
        datasetId: <?php echo $template->getDatasetId() ? $template->getDatasetId() : 'null'; ?>,
        canvasWidth: <?php echo $template->getCanvasWidthPx(); ?>,
        canvasHeight: <?php echo $template->getCanvasHeightPx(); ?>,
        bleedMm: <?php echo $template->getBleedMm(); ?>,
        safeMarginMm: <?php echo $template->getSafeMarginMm(); ?>,
        rowFilter: "<?php echo SecurityHelper::escape(addslashes($template->getRowFilter() ?? '')); ?>",
        componentTypeName: "<?php echo $compType ? SecurityHelper::escape($compType->getName()) : ''; ?>",
        orientation: "<?php echo ($template->getCanvasWidthPx() > $template->getCanvasHeightPx()) ? 'landscape' : 'portrait'; ?>"
    };
</script>

<!-- Editor Scripts -->
<script src="js/editor-viewport.js?v=<?php echo filemtime(__DIR__ . '/js/editor-viewport.js'); ?>"></script>
<script src="js/editor-history.js?v=<?php echo filemtime(__DIR__ . '/js/editor-history.js'); ?>"></script>
<script src="js/editor-importer.js?v=<?php echo filemtime(__DIR__ . '/js/editor-importer.js'); ?>"></script>
<script src="js/editor-core.js?v=<?php echo filemtime(__DIR__ . '/js/editor-core.js'); ?>"></script>
<script src="js/guide-renderer.js?v=<?php echo filemtime(__DIR__ . '/js/guide-renderer.js'); ?>"></script>
<script src="js/layer-manager.js?v=<?php echo filemtime(__DIR__ . '/js/layer-manager.js'); ?>"></script>
<script src="js/inspector-canvas.js?v=<?php echo filemtime(__DIR__ . '/js/inspector-canvas.js'); ?>"></script>
<script src="js/inspector-crop.js?v=<?php echo filemtime(__DIR__ . '/js/inspector-crop.js'); ?>"></script>
<script src="js/inspector-text.js?v=<?php echo filemtime(__DIR__ . '/js/inspector-text.js'); ?>"></script>
<script src="js/inspector-populate.js?v=<?php echo filemtime(__DIR__ . '/js/inspector-populate.js'); ?>"></script>
<script src="js/property-inspector.js?v=<?php echo filemtime(__DIR__ . '/js/property-inspector.js'); ?>"></script>
<script src="js/asset-picker.js?v=<?php echo filemtime(__DIR__ . '/js/asset-picker.js'); ?>"></script>
<script src="js/text-style-parser.js?v=<?php echo filemtime(__DIR__ . '/js/text-style-parser.js'); ?>"></script>
<script src="js/template-engine.js?v=<?php echo filemtime(__DIR__ . '/js/template-engine.js'); ?>"></script>
<script src="js/editor-actions.js?v=<?php echo filemtime(__DIR__ . '/js/editor-actions.js'); ?>"></script>

<?php include __DIR__ . '/views/editor-modals.php'; ?>
<script>
// ponytail: lightweight toggle function to expand canvas workspace and hide/show sidebars
function toggleSidebar(panelId) {
    const panel = document.getElementById(panelId);
    if (!panel) return;
    panel.classList.toggle('hidden');
    setTimeout(() => {
        const fitBtn = document.getElementById('btn-zoom-fit');
        if (fitBtn) fitBtn.click();
    }, 150);
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
