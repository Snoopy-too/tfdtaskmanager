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

$currentUserId = (int)($_SESSION['user_id'] ?? 0);

$templateId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$template = $templateService->getTemplateById($templateId);

if (!$template) {
    header("Location: index.php");
    exit;
}

$project = $projectService->getProjectById($template->getProjectId(), $currentUserId);
if (!$project) {
    header("Location: index.php");
    exit;
}

// Check lock status
$lockUser = null;
$isViewMode = false;

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

$_SESSION['last_project_id'] = $template->getProjectId();
$compTypes = $templateService->getComponentTypes();
usort($compTypes, function($a, $b) {
    $order = ['Poker Card' => 1, 'Tarot Card' => 2, 'Board Game Box (Unfolded Die-Line)' => 3, 'Game Board (Medium Square)' => 4, 'Game Board (Square)' => 5, 'Game Board (Rectangular)' => 6, 'Player Board (A5 Landscape)' => 7, 'Player Board (A4 Landscape)' => 8, 'Punchboard' => 9, 'Custom' => 10, 'Japanese Business Card (A-one F10A4-1)' => 11, 'Japanese ID Card / Name Tag (A-one 51215)' => 12];
    return ($order[$a->getName()] ?? 99) <=> ($order[$b->getName()] ?? 99);
});
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

<div id="editor-root" class="space-y-3 flex-grow flex flex-col min-h-0 h-full overflow-hidden">
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

    <!-- ponytail: streamlined top editor header preventing button clipping on all screen widths -->
    <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-1.5 pb-2 border-b border-slate-800 min-w-0 w-full">
        <!-- Left: Project/Template Info & Metadata -->
        <div class="flex items-center space-x-2 min-w-[170px] flex-1 basis-[180px] overflow-hidden">
            <a href="index.php?project_id=<?php echo $template->getProjectId(); ?>" class="p-1.5 bg-slate-900 border border-slate-800 rounded-lg text-slate-400 hover:text-white transition flex-shrink-0" title="Back to Project Dashboard">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div class="min-w-0 flex-1">
                <?php
                $isLandscape = $template->getCanvasWidthPx() > $template->getCanvasHeightPx();
                $widthMm = round(\App\Domain\Entities\BgTemplate::pxToMm($template->getCanvasWidthPx()), 1);
                $heightMm = round(\App\Domain\Entities\BgTemplate::pxToMm($template->getCanvasHeightPx()), 1);
                ?>
                <h1 class="text-sm font-bold text-white flex items-center gap-1.5 leading-tight truncate">
                    <span id="template-title-display" class="truncate max-w-[140px] 2xl:max-w-[260px]" title="<?php echo SecurityHelper::escape($template->getName()); ?>"><?php echo SecurityHelper::escape($template->getName()); ?></span>
                    <?php if (!$isViewMode): ?>
                        <button onclick="promptRenameTemplate(<?php echo $template->getId(); ?>, '<?php echo SecurityHelper::escape(addslashes($template->getName())); ?>')" class="text-slate-400 hover:text-amber-400 transition p-0.5 flex-shrink-0" title="Rename Template">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        </button>
                    <?php endif; ?>
                    <span class="hidden 2xl:inline-block text-[10px] uppercase font-bold px-1.5 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 truncate max-w-[120px] flex-shrink-0" title="<?php echo $compType ? SecurityHelper::escape($compType->getName()) : 'Component'; ?>">
                        <?php echo $compType ? SecurityHelper::escape($compType->getName()) : 'Component'; ?>
                    </span>
                    <span id="template-orientation-badge" class="hidden 2xl:inline-block text-[10px] font-semibold px-1.5 py-0.5 rounded flex-shrink-0 <?php echo $isLandscape ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'bg-slate-800 text-slate-300 border border-slate-700'; ?>">
                        <?php echo $isLandscape ? 'Landscape' : 'Portrait'; ?>
                    </span>
                </h1>
                <p class="text-[10px] text-slate-400 flex items-center space-x-1 flex-nowrap truncate">
                    <span class="hidden xl:inline truncate max-w-[100px]"><?php echo SecurityHelper::escape($project->getName()); ?></span>
                    <span class="hidden xl:inline">•</span>
                    <span class="flex items-center space-x-1 flex-shrink-0">
                        <span id="template-size-display" class="truncate"><?php echo $widthMm; ?>x<?php echo $heightMm; ?> mm (<?php echo $template->getCanvasWidthPx(); ?>x<?php echo $template->getCanvasHeightPx(); ?> px)</span>
                        <?php if (!$isViewMode): ?>
                            <button onclick="openChangeSizeModal()" class="text-slate-400 hover:text-amber-400 transition p-0.5" title="Change Canvas / Template Size">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            </button>
                        <?php endif; ?>
                    </span>
                </p>
            </div>
        </div>

        <!-- Center & Right Controls -->
        <div class="flex flex-wrap items-center justify-end gap-1.5 flex-shrink-0">
            <!-- Auto-save Status Indicator -->
            <div id="save-status" class="hidden lg:flex items-center space-x-1.5 text-[11px] text-slate-400 bg-slate-900/60 border border-slate-800 px-2 py-1 rounded-lg shrink-0" title="Auto-Save Status">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span id="save-status-text">Saved</span>
            </div>

            <?php if (!$isViewMode): ?>
                <!-- Box Die-Line Generator / Configurator Button -->
                <button id="btn-open-box-dieline" type="button" onclick="if(window.boxDieline) window.boxDieline.openBoxDielineModal();" class="px-2 py-1.5 rounded-lg text-xs font-semibold bg-slate-900 border border-slate-800 text-slate-300 hover:text-white hover:border-slate-700 transition flex items-center gap-1 shrink-0" title="Configure 3D Board Game Box Dimensions & Unfolded Die-Line">
                    <span>📦</span>
                    <span class="hidden sm:inline 2xl:hidden">Box</span>
                    <span class="hidden 2xl:inline">Box Die-Line</span>
                </button>

                <!-- Design Mode vs. Cut-Out Stencil Toggle (shown when Box Die-Line is active) -->
                <button id="btn-toggle-box-stencil" type="button" onclick="if(window.boxDieline) window.boxDieline.toggleStencilPreview();" class="hidden px-2 py-1.5 rounded-lg text-xs font-semibold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30 hover:bg-indigo-500/25 transition shrink-0" title="Toggle between Full Artwork Design Mode and Pure Cut-Out Stencil Mode">
                    🎨 Design
                </button>

                <!-- Orientation Switch Button -->
                <button id="btn-toggle-orientation" onclick="toggleCanvasOrientation()" class="px-2 py-1.5 rounded-lg text-xs font-semibold bg-slate-900 border border-slate-800 text-slate-300 hover:text-white hover:border-slate-700 transition flex items-center gap-1 shrink-0" title="Switch Canvas Orientation (Portrait ↔ Landscape)">
                    <svg id="orient-btn-icon" class="w-3.5 h-3.5 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span id="orient-btn-text" class="hidden 2xl:inline">Switch to <?php echo $isLandscape ? 'Portrait' : 'Landscape'; ?></span>
                </button>
            <?php endif; ?>

            <!-- Guides Toggle -->
            <button id="btn-toggle-guides" class="px-2 py-1.5 rounded-lg text-xs font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 hover:bg-indigo-500/20 transition shrink-0" title="Toggle Bleed & Safe Zone Guidelines">
                Guides: ON
            </button>

            <!-- History controls -->
            <div class="flex items-center space-x-0.5 bg-slate-900 border border-slate-800 rounded-lg p-0.5 shrink-0">
                <button id="btn-undo" class="p-1.5 hover:bg-slate-800 text-slate-400 hover:text-white disabled:opacity-30 disabled:hover:bg-transparent rounded transition" title="Undo (Ctrl+Z)" disabled>
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                    </svg>
                </button>
                <button id="btn-redo" class="p-1.5 hover:bg-slate-800 text-slate-400 hover:text-white disabled:opacity-30 disabled:hover:bg-transparent rounded transition" title="Redo (Ctrl+Y)" disabled>
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10h-10a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6"/>
                    </svg>
                </button>
            </div>

            <!-- Zoom controls -->
            <div class="flex items-center space-x-0.5 bg-slate-900 border border-slate-800 rounded-lg p-0.5 shrink-0">
                <button id="btn-zoom-out" class="px-1.5 py-0.5 hover:bg-slate-800 text-slate-400 hover:text-white rounded text-xs" title="Zoom Out">-</button>
                <input type="text" id="zoom-value" class="text-xs font-semibold text-center text-slate-300 bg-transparent w-9 border-none focus:outline-none focus:ring-0 p-0" value="100%">
                <button id="btn-zoom-in" class="px-1.5 py-0.5 hover:bg-slate-800 text-slate-400 hover:text-white rounded text-xs" title="Zoom In">+</button>
                <button id="btn-zoom-fit" class="py-0.5 hover:bg-slate-800 text-slate-400 hover:text-white rounded text-[10px] font-bold px-1.5" title="Fit to View">FIT</button>
            </div>

            <!-- Sidebar Toggle Controls -->
            <div class="flex items-center space-x-0.5 bg-slate-900 border border-slate-800 rounded-lg p-0.5 shrink-0">
                <button id="btn-toggle-left-sidebar" onclick="toggleSidebar('left-layers-panel')" class="p-1.5 hover:bg-slate-800 text-slate-400 hover:text-white rounded text-xs flex items-center space-x-1" title="Toggle Left Layers Panel">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                    <span class="hidden 2xl:inline">Layers</span>
                </button>
                <button id="btn-toggle-right-sidebar" onclick="toggleSidebar('right-inspector-panel')" class="p-1.5 hover:bg-slate-800 text-slate-400 hover:text-white rounded text-xs flex items-center space-x-1" title="Toggle Right Inspector Panel">
                    <span class="hidden 2xl:inline">Inspector</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/></svg>
                </button>
            </div>

            <!-- Preview -->
            <button type="button" onclick="showFullscreenPreview()" class="px-2 py-1.5 bg-slate-900 border border-slate-800 text-slate-300 hover:text-white text-xs font-semibold rounded-lg shadow transition flex items-center gap-1 shrink-0" title="Full Screen Preview">
                <svg class="h-3.5 w-3.5 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <span class="hidden sm:inline">Preview</span>
            </button>

            <!-- Copy -->
            <button type="button" onclick="makeCopy()" class="px-2 py-1.5 bg-slate-900 border border-slate-800 text-slate-300 hover:text-white text-xs font-semibold rounded-lg shadow transition shrink-0 whitespace-nowrap flex items-center gap-1" title="Make a Copy (Duplicate Template)">
                <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <span class="hidden sm:inline 2xl:hidden">Copy</span>
                <span class="hidden 2xl:inline">Make a Copy</span>
            </button>

            <!-- Export -->
            <a href="export.php?project_id=<?php echo $template->getProjectId(); ?>&template_id=<?php echo $template->getId(); ?>" class="px-2.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-lg shadow transition shrink-0 whitespace-nowrap flex items-center gap-1.5" title="Open Export Studio">
                <svg class="w-3.5 h-3.5 text-indigo-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                <span class="2xl:hidden">Export</span>
                <span class="hidden 2xl:inline">Export Studio</span>
            </a>
        </div>
    </div>

    <!-- Main Workspace Flex Container (Fixed 280px sidebars + flex-1 expanded canvas) -->
    <div class="editor-container flex gap-3 flex-grow min-h-0 w-full overflow-hidden">
        
        <!-- Left Panel: Layers and Assets -->
        <?php include __DIR__ . '/views/editor-layers-panel.php'; ?>

        <!-- Central Panel: Canvas Area (Expands dynamically to fill remaining workspace) -->
        <div id="center-canvas-panel" style="margin-left: 0 !important;" class="flex-1 min-w-0 min-h-0 flex flex-col h-full bg-slate-950 border border-slate-800/60 rounded-2xl overflow-hidden relative transition-all duration-200">
            
            <!-- Canvas Loading Overlay (Displayed in center of canvas area during load) -->
            <div id="canvas-loading-overlay" class="absolute inset-0 z-30 flex flex-col items-center justify-center bg-slate-950/75 backdrop-blur-sm transition-all duration-300 pointer-events-auto">
                <div class="bg-slate-900/95 border border-slate-800 rounded-2xl p-6 shadow-2xl flex flex-col items-center text-center max-w-sm mx-4 space-y-4">
                    <!-- Animated Spinner with Pulsing Center Icon -->
                    <div class="relative flex items-center justify-center w-14 h-14">
                        <div class="absolute inset-0 rounded-full bg-indigo-500/20 animate-ping opacity-75"></div>
                        <div class="w-14 h-14 rounded-full border-4 border-indigo-500/20 border-t-indigo-500 border-r-indigo-400 animate-spin"></div>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <svg class="w-6 h-6 text-indigo-400 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                            </svg>
                        </div>
                    </div>
                    
                    <div class="space-y-1">
                        <h3 id="canvas-loading-title" class="text-sm font-bold text-white tracking-wide">
                            Loading Canvas...
                        </h3>
                        <p id="canvas-loading-subtitle" class="text-xs text-slate-400 leading-relaxed">
                            Restoring your saved template layers, assets, and typography...
                        </p>
                    </div>

                    <!-- Progress Badge with Pulsing Dot -->
                    <div class="inline-flex items-center space-x-2 bg-indigo-500/10 border border-indigo-500/20 px-3 py-1 rounded-full text-[11px] font-semibold text-indigo-300">
                        <span class="w-2 h-2 rounded-full bg-indigo-400 animate-ping"></span>
                        <span id="canvas-loading-status">Loading in progress...</span>
                    </div>
                </div>
            </div>

            <div class="canvas-viewport flex-1 min-h-0 min-w-0 overflow-auto flex p-2 md:p-3 relative">
                <!-- Outer scaled container to handle flex-scroll centering -->
                <div id="canvas-zoom-container" class="shrink-0" style="margin: auto; position: relative; flex-shrink: 0; overflow: hidden;">
                    <!-- Wrapper for absolute alignment and sizing -->
                    <div id="canvas-container-wrapper" class="shadow-2xl border border-slate-700/50" style="position: absolute; top: 0; left: 0; transform-origin: 0 0; max-width: none !important; max-height: none !important;">
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
                    <select id="editor-dataset-select" onchange="if(window.templateEngine && typeof window.templateEngine.switchDataset === 'function') window.templateEngine.switchDataset(this.value);" class="bg-slate-900 border border-slate-800 text-slate-200 text-xs rounded-xl p-1.5 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">No Dataset Bound</option>
                        <?php foreach ($projectDatasets as $ds): ?>
                            <option value="<?php echo $ds->getId(); ?>" <?php echo ($template->getDatasetId() === $ds->getId()) ? 'selected' : ''; ?>>
                                <?php echo SecurityHelper::escape($ds->getName()); ?> (<?php echo count($ds->getRowData()); ?> rows)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="dataset-filter-container" class="flex items-center space-x-1.5 bg-slate-900 border border-slate-800 rounded-xl px-2 py-1 <?php echo $dataset ? '' : 'hidden'; ?>">
                    <label for="template-row-filter" class="text-xs font-semibold text-slate-400">Rows Filter:</label>
                    <input type="text" id="template-row-filter" 
                           value="<?php echo SecurityHelper::escape($template->getRowFilter() ?? ''); ?>" 
                           placeholder="All (e.g. 1-42)" 
                           title="Specify active rows e.g. 1-42 or 43-82" 
                           class="w-28 bg-slate-900 border border-slate-800 text-slate-200 text-xs rounded px-2 py-0.5 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 font-mono text-center">
                </div>

                <div id="dataset-nav-controls" class="flex items-center space-x-3 <?php echo $dataset ? '' : 'hidden'; ?>">
                    <button id="btn-row-prev" class="p-1 bg-slate-900 border border-slate-800 rounded hover:bg-slate-800 text-slate-300 hover:text-white transition shadow-sm" title="Previous Row">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <span id="row-indicator" class="text-xs text-slate-300 font-bold min-w-[70px] text-center">
                        Row 1 of <?php echo $dataset ? count($dataset->getRowData()) : 1; ?>
                    </span>
                    <button id="btn-row-next" class="p-1 bg-slate-900 border border-slate-800 rounded hover:bg-slate-800 text-slate-300 hover:text-white transition shadow-sm" title="Next Row">
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
<script src="js/box-dieline.js?v=<?php echo filemtime(__DIR__ . '/js/box-dieline.js'); ?>"></script>
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

// Ensure outer layout fits viewport perfectly without page scrolling
(function() {
    const mainEl = document.querySelector('main');
    if (mainEl) {
        mainEl.classList.remove('py-8', 'max-w-7xl', 'px-4', 'sm:px-6', 'lg:px-8');
        mainEl.classList.add('editor-main-layout');
        if (mainEl.parentElement) {
            mainEl.parentElement.classList.add('editor-content-wrapper');
        }
    }
})();
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
