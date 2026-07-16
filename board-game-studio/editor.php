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
$compTypes = $templateService->getComponentTypes();
$compType = null;
foreach ($compTypes as $ct) {
    if ($ct->getId() === $template->getComponentTypeId()) {
        $compType = $ct;
        break;
    }
}

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
<style>
    html, body {
        overflow: hidden !important;
        height: 100% !important;
    }
    /* Override standard main layout for the full-screen editor */
    body > main {
        max-width: 100% !important;
        padding-top: 1rem !important;
        padding-bottom: 1rem !important;
        height: calc(100vh - 64px) !important;
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
    }
    .editor-container {
        flex-grow: 1;
        min-height: 0;
    }
    .canvas-viewport {
        background-color: #0b0f19;
        background-image: radial-gradient(#1e293b 1px, transparent 1px);
        background-size: 16px 16px;
    }
    /* Hidden file inputs */
    .hidden-upload {
        display: none;
    }
    /* Prevent FabricJS hidden textarea from triggering page scrolls */
    textarea[data-fabric-hiddentextarea] {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 1px !important;
        height: 1px !important;
        opacity: 0 !important;
        z-index: -9999 !important;
        pointer-events: none !important;
    }
    /* Scrollbars are managed by canvas-viewport, not the body */

    /* Read-only view styles */
    body.view-only-mode #left-layers-panel,
    body.view-only-mode #right-inspector-panel,
    body.view-only-mode #btn-toggle-guides,
    body.view-only-mode #save-status {
        pointer-events: none !important;
        opacity: 0.6 !important;
    }
    body.view-only-mode #tab-layers-view .grid {
        display: none !important; /* Hide Add layer buttons */
    }

    /* Drag & Drop Visual Indicator */
    #canvas-container-wrapper.drag-over {
        outline: 3px solid #6366f1 !important; /* Indigo-500 */
        outline-offset: 4px;
        box-shadow: 0 0 25px rgba(99, 102, 241, 0.4) !important;
        transition: outline-offset 0.15s ease, box-shadow 0.15s ease;
    }
</style>

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
                <h1 class="text-xl font-bold text-white flex items-center space-x-2">
                    <span><?php echo SecurityHelper::escape($template->getName()); ?></span>
                    <span class="text-xs uppercase font-extrabold px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                        <?php echo $compType ? SecurityHelper::escape($compType->getName()) : 'Component'; ?>
                    </span>
                </h1>
                <p class="text-xs text-slate-400">
                    Project: <?php echo SecurityHelper::escape($project->getName()); ?> | 
                    Size: <?php echo $compType ? $compType->getWidthMm() : 0; ?>x<?php echo $compType ? $compType->getHeightMm() : 0; ?> mm 
                    (<?php echo $template->getCanvasWidthPx(); ?>x<?php echo $template->getCanvasHeightPx(); ?> px)
                </p>
            </div>
        </div>

        <div class="flex items-center space-x-3">
            <!-- Auto-save Status Indicator -->
            <div id="save-status" class="flex items-center space-x-1.5 text-xs text-slate-400 bg-slate-900/60 border border-slate-800 px-3 py-1.5 rounded-lg">
                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span id="save-status-text">All changes saved</span>
            </div>

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

    <!-- Main Workspace Grid -->
    <div class="editor-container grid grid-cols-1 lg:grid-cols-4 gap-4">
        
        <!-- Left Panel: Layers and Assets -->
        <div id="left-layers-panel" class="min-w-0 bg-slate-900/50 border border-slate-800 rounded-2xl flex flex-col h-full overflow-hidden">
            <!-- Tabs -->
            <div class="flex border-b border-slate-800">
                <button id="tab-layers-btn" class="flex-1 py-3 text-center text-xs font-bold uppercase tracking-wider text-indigo-400 border-b-2 border-indigo-400">Layers</button>
                <button id="tab-assets-btn" class="flex-1 py-3 text-center text-xs font-bold uppercase tracking-wider text-slate-400 hover:text-slate-200">Assets</button>
            </div>

            <!-- Content Area -->
            <div class="flex-grow overflow-y-auto p-4 space-y-4">
                
                <!-- Layers Tab View -->
                <div id="tab-layers-view" class="space-y-4">
                    <!-- Layer Addition Controls -->
                    <div class="grid grid-cols-2 gap-2">
                        <button id="btn-add-text" class="py-2 bg-slate-950 border border-slate-800 text-xs text-slate-300 hover:text-white hover:border-slate-700 rounded-xl transition flex items-center justify-center space-x-1">
                            <span>Text</span>
                        </button>
                        <button id="btn-add-image" class="py-2 bg-slate-950 border border-slate-800 text-xs text-slate-300 hover:text-white hover:border-slate-700 rounded-xl transition flex items-center justify-center space-x-1">
                            <span>Image</span>
                        </button>
                        <button id="btn-add-rect" class="py-2 bg-slate-950 border border-slate-800 text-xs text-slate-300 hover:text-white hover:border-slate-700 rounded-xl transition flex items-center justify-center space-x-1">
                            <span>Rectangle</span>
                        </button>
                        <button id="btn-add-circle" class="py-2 bg-slate-950 border border-slate-800 text-xs text-slate-300 hover:text-white hover:border-slate-700 rounded-xl transition flex items-center justify-center space-x-1">
                            <span>Circle</span>
                        </button>
                    </div>

                    <!-- Layers list container -->
                    <div class="space-y-2">
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Layers Stack (Top-down)</div>
                        <div id="layers-list" class="space-y-1.5 min-h-[200px] border border-dashed border-slate-800/80 rounded-xl p-2 bg-slate-950/40">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                </div>

                <!-- Assets Tab View (Hidden initially) -->
                <div id="tab-assets-view" class="space-y-4 hidden">
                    <div class="text-xs font-bold text-slate-400 uppercase tracking-wider flex justify-between items-center">
                        <span>Project Assets</span>
                        <a href="assets.php?project_id=<?php echo $template->getProjectId(); ?>" target="_blank" class="text-[10px] text-indigo-400 hover:underline">Upload Files</a>
                    </div>
                    <div id="asset-picker-grid" class="grid grid-cols-2 gap-3">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Central Panel: Canvas Area -->
        <div class="lg:col-span-2 min-w-0 flex flex-col h-full bg-slate-950 border border-slate-800/60 rounded-2xl overflow-hidden relative">
            <div class="canvas-viewport flex-grow overflow-auto flex p-8 relative">
                <!-- Outer scaled container to handle flex-scroll centering -->
                <div id="canvas-zoom-container" class="shrink-0" style="margin: auto; position: relative; flex-shrink: 0;">
                    <!-- Wrapper for absolute alignment and sizing -->
                    <div id="canvas-container-wrapper" class="relative shadow-2xl border border-slate-700/50" style="transform-origin: 0 0; max-width: none !important; max-height: none !important;">
                        <canvas id="editor-canvas"></canvas>
                    </div>
                </div>
            </div>

            <!-- Bottom Row Data navigation if dataset is bound -->
            <?php if ($dataset): ?>
                <div class="bg-slate-900 border-t border-slate-800 p-4 flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-violet-400"></span>
                        <span class="text-xs font-semibold text-slate-300">
                            Bound: <?php echo SecurityHelper::escape($dataset->getName()); ?>
                        </span>
                    </div>

                    <div class="flex items-center space-x-3">
                        <button id="btn-row-prev" class="p-1 bg-slate-950 border border-slate-800 rounded hover:bg-slate-800 text-slate-400 hover:text-white transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <span id="row-indicator" class="text-xs text-slate-300 font-bold min-w-[70px] text-center">Row 1 of 1</span>
                        <button id="btn-row-next" class="p-1 bg-slate-950 border border-slate-800 rounded hover:bg-slate-800 text-slate-400 hover:text-white transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>

                    <div class="text-xs text-slate-400">
                        Total Rows: <span id="row-total" class="font-bold text-slate-200">0</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Panel: Properties Inspector -->
        <div id="right-inspector-panel" class="min-w-0 bg-slate-900/50 border border-slate-800 rounded-2xl flex flex-col h-full overflow-hidden">
            <div class="p-4 border-b border-slate-800">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-200">Properties Inspector</h2>
            </div>
            
            <div id="inspector-content" class="flex-grow overflow-y-auto pt-4 px-4 pb-12 space-y-4">
                <!-- Fallback notice when nothing is selected -->
                <div id="inspector-none-selected" class="space-y-5">
                    <div class="text-center py-6 text-slate-500 text-xs border-b border-slate-800/80">
                        <svg class="mx-auto h-8 w-8 text-slate-700 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/></svg>
                        <span>Select a layer on the canvas to configure properties.</span>
                    </div>

                    <div class="space-y-4 pt-2">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300">Canvas Properties</h3>
                        
                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-1">
                                <label for="prop-canvas-bg" class="block text-xs font-semibold text-slate-400 mb-1">Color</label>
                                <input type="color" id="prop-canvas-bg" value="#ffffff" class="w-full h-8 bg-slate-950 border border-slate-800 rounded-lg cursor-pointer p-0.5">
                            </div>
                            <div class="col-span-2">
                                <label for="prop-canvas-bg-hex" class="block text-xs font-semibold text-slate-400 mb-1">Hex Value</label>
                                <input type="text" id="prop-canvas-bg-hex" value="#ffffff" placeholder="#ffffff" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2 uppercase focus:ring-indigo-500">
                            </div>
                        </div>

                        <div class="flex items-center space-x-2.5 pt-1">
                            <input type="checkbox" id="prop-canvas-transparent" class="h-4 w-4 bg-slate-950 border border-slate-800 text-indigo-500 focus:ring-indigo-500 focus:ring-offset-slate-950 rounded">
                            <label for="prop-canvas-transparent" class="text-xs font-medium text-slate-400 cursor-pointer">Transparent Canvas</label>
                        </div>
                    </div>
                </div>

                <!-- Form Controls (Visible when layer selected, structured dynamically in JS) -->
                <form id="inspector-form" class="space-y-4 hidden" onsubmit="return false;">
                    <!-- Common Properties: Name, Position, Size, Opacity, Rotation -->
                    <div class="space-y-3 pb-4 border-b border-slate-800/80">
                        <div>
                            <label for="prop-name" class="block text-xs font-semibold text-slate-400 mb-1">Layer Name</label>
                            <input type="text" id="prop-name" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2 focus:ring-indigo-500">
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="prop-left" class="block text-xs font-semibold text-slate-400 mb-1">X Pos (px)</label>
                                <input type="number" id="prop-left" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                            </div>
                            <div>
                                <label for="prop-top" class="block text-xs font-semibold text-slate-400 mb-1">Y Pos (px)</label>
                                <input type="number" id="prop-top" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                            </div>
                        </div>
                        
                        <div class="flex space-x-2">
                            <button type="button" id="btn-align-h" class="flex-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-[10px] uppercase font-bold py-1.5 rounded transition">
                                Center X
                            </button>
                            <button type="button" id="btn-align-v" class="flex-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-[10px] uppercase font-bold py-1.5 rounded transition">
                                Center Y
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="prop-width" class="block text-xs font-semibold text-slate-400 mb-1">Width (px)</label>
                                <input type="number" id="prop-width" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                            </div>
                            <div>
                                <label for="prop-height" class="block text-xs font-semibold text-slate-400 mb-1">Height (px)</label>
                                <input type="number" id="prop-height" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="prop-width-mm" class="block text-xs font-semibold text-slate-400 mb-1">Width (mm)</label>
                                <input type="number" id="prop-width-mm" step="0.1" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                            </div>
                            <div>
                                <label for="prop-height-mm" class="block text-xs font-semibold text-slate-400 mb-1">Height (mm)</label>
                                <input type="number" id="prop-height-mm" step="0.1" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="prop-rotation" class="block text-xs font-semibold text-slate-400 mb-1">Rotation (°)</label>
                                <input type="number" id="prop-rotation" min="0" max="360" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                            </div>
                            <div>
                                <label for="prop-opacity" class="block text-xs font-semibold text-slate-400 mb-1">Opacity (%)</label>
                                <input type="number" id="prop-opacity" min="0" max="100" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                            </div>
                        </div>
                    </div>

                    <!-- Text-Specific Properties -->
                    <div id="inspector-text-section" class="space-y-3 pb-4 border-b border-slate-800/80 hidden">
                        <div>
                            <label for="prop-text-val" class="block text-xs font-semibold text-slate-400 mb-1">Text Content</label>
                            <textarea id="prop-text-val" rows="2" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2"></textarea>
                        </div>

                        <?php if ($dataset): ?>
                            <div>
                                <label for="prop-text-bind" class="block text-xs font-semibold text-slate-400 mb-1">Dataset Variable Binding</label>
                                <select id="prop-text-bind" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                                    <option value="">No Binding (Static Text)</option>
                                    <?php foreach ($dataset->getColumnMap() as $colName): ?>
                                        <option value="{{<?php echo SecurityHelper::escape($colName); ?>}}">
                                            {{<?php echo SecurityHelper::escape($colName); ?>}}
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="prop-font-size" class="block text-xs font-semibold text-slate-400 mb-1">Font Size (pt)</label>
                                <input type="number" id="prop-font-size" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                            </div>
                            <div>
                                <label for="prop-font-family" class="block text-xs font-semibold text-slate-400 mb-1">Font Family</label>
                                <select id="prop-font-family" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                                    <option value="Plus Jakarta Sans">Jakarta Sans (Default)</option>
                                    <optgroup label="Modern Sans-Serif">
                                        <option value="Inter">Inter</option>
                                        <option value="Montserrat">Montserrat</option>
                                        <option value="Outfit">Outfit</option>
                                        <option value="Arial">Arial</option>
                                    </optgroup>
                                    <optgroup label="RPG & Fantasy">
                                        <option value="Cinzel">Cinzel</option>
                                        <option value="MedievalSharp">MedievalSharp</option>
                                        <option value="Almendra">Almendra</option>
                                        <option value="Rye">Rye</option>
                                    </optgroup>
                                    <optgroup label="Retro & Typewriter">
                                        <option value="Courier Prime">Courier Prime</option>
                                        <option value="Special Elite">Special Elite</option>
                                    </optgroup>
                                    <optgroup label="Sci-Fi & Futuristic">
                                        <option value="Orbitron">Orbitron</option>
                                        <option value="Rajdhani">Rajdhani</option>
                                        <option value="Share Tech Mono">Share Tech Mono</option>
                                        <option value="Courier New">Courier New</option>
                                    </optgroup>
                                    <optgroup label="Classic Serif">
                                        <option value="Playfair Display">Playfair Display</option>
                                        <option value="Lora">Lora</option>
                                        <option value="EB Garamond">EB Garamond</option>
                                        <option value="Times New Roman">Times New Roman</option>
                                    </optgroup>
                                    <optgroup label="Spooky & Horror">
                                        <option value="Creepster">Creepster</option>
                                        <option value="Metal Mania">Metal Mania</option>
                                        <option value="Jolly Lodger">Jolly Lodger</option>
                                    </optgroup>
                                    <optgroup label="Comic & Casual">
                                        <option value="Bangers">Bangers</option>
                                        <option value="Fredoka">Fredoka</option>
                                        <option value="Luckiest Guy">Luckiest Guy</option>
                                        <option value="Comic Neue">Comic Neue</option>
                                    </optgroup>
                                    <!-- Uploaded project fonts added dynamically -->
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="prop-text-color" class="block text-xs font-semibold text-slate-400 mb-1">Font Color</label>
                                <input type="color" id="prop-text-color" class="w-full h-8 bg-slate-950 border border-slate-800 rounded-lg cursor-pointer">
                            </div>
                            <div>
                                <label for="prop-text-align" class="block text-xs font-semibold text-slate-400 mb-1">Align</label>
                                <select id="prop-text-align" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                                    <option value="left">Left</option>
                                    <option value="center">Center</option>
                                    <option value="right">Right</option>
                                    <option value="justify">Justify</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="inline-flex items-center text-xs font-semibold text-slate-400 cursor-pointer">
                                <input type="checkbox" id="prop-font-bold" class="rounded border-slate-800 text-indigo-600 bg-slate-950 focus:ring-indigo-500 mr-2">
                                Bold
                            </label>
                            <label class="inline-flex items-center text-xs font-semibold text-slate-400 cursor-pointer ml-4">
                                <input type="checkbox" id="prop-font-italic" class="rounded border-slate-800 text-indigo-600 bg-slate-950 focus:ring-indigo-500 mr-2">
                                Italic
                            </label>
                        </div>
                    </div>

                    <!-- Shape-Specific Properties (Rect, Circle) -->
                    <div id="inspector-shape-section" class="space-y-3 pb-4 border-b border-slate-800/80 hidden">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="prop-fill-color" class="block text-xs font-semibold text-slate-400 mb-1">Fill Color</label>
                                <input type="color" id="prop-fill-color" class="w-full h-8 bg-slate-950 border border-slate-800 rounded-lg cursor-pointer">
                            </div>
                            <div class="flex items-end pl-2">
                                <label class="inline-flex items-center text-xs font-semibold text-slate-400 cursor-pointer">
                                    <input type="checkbox" id="prop-fill-transparent" class="rounded border-slate-800 text-indigo-600 bg-slate-950 focus:ring-indigo-500 mr-2">
                                    Transparent
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="prop-fill-opacity" class="block text-xs font-semibold text-slate-400 mb-1">Fill Opacity (%)</label>
                                <input type="number" id="prop-fill-opacity" min="0" max="100" step="5" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="prop-stroke-color" class="block text-xs font-semibold text-slate-400 mb-1">Stroke Color</label>
                                <input type="color" id="prop-stroke-color" class="w-full h-8 bg-slate-950 border border-slate-800 rounded-lg cursor-pointer">
                            </div>
                            <div>
                                <label for="prop-stroke-width" class="block text-xs font-semibold text-slate-400 mb-1">Stroke Width (px)</label>
                                <input type="number" id="prop-stroke-width" min="0" max="50" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-lg p-2">
                            </div>
                        </div>
                    </div>

                    <!-- Image-Specific Properties -->
                    <div id="inspector-image-section" class="space-y-3 pb-4 border-b border-slate-800/80 hidden">
                        <div class="p-3 bg-slate-950 border border-slate-800 rounded-xl flex items-center justify-between text-xs">
                            <span class="text-slate-400 truncate max-w-[120px]" id="prop-image-filename">No image selected</span>
                            <button type="button" id="btn-inspector-change-image" class="px-2 py-1 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 hover:bg-indigo-500/20 rounded transition">Change</button>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-550 mb-1.5">Canvas Fitting</span>
                            <div class="flex space-x-2">
                                <button type="button" id="btn-inspector-fit-contain" class="flex-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-[10px] uppercase font-bold py-1.5 rounded transition flex items-center justify-center gap-1.5">
                                    <svg class="h-3.5 w-3.5 text-indigo-450" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16V4m0 0h12M4 4l8 8m-8 4h16m0 0v-4m0 4l-8-8"/></svg>
                                    Contain
                                </button>
                                <button type="button" id="btn-inspector-fit-cover" class="flex-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-[10px] uppercase font-bold py-1.5 rounded transition flex items-center justify-center gap-1.5">
                                    <svg class="h-3.5 w-3.5 text-indigo-450" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h16v16H4z"/></svg>
                                    Cover
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Full Screen Preview Overlay -->
<div id="preview-overlay" class="fixed inset-0 bg-slate-950/95 z-[9999] hidden flex-col items-center justify-center p-6 transition-all duration-300 opacity-0">
    <button onclick="closeFullscreenPreview()" class="absolute top-6 right-6 p-2 bg-slate-900/80 hover:bg-slate-850 border border-slate-800 text-slate-400 hover:text-white rounded-xl shadow-lg transition duration-200" title="Exit Preview (Esc)">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
    <div class="relative max-w-full max-h-full flex items-center justify-center">
        <img id="preview-image" src="" alt="Canvas Preview" class="max-w-[90vw] max-h-[85vh] rounded-xl shadow-2xl border border-slate-800 object-contain">
    </div>
    <p class="text-xs text-slate-500 mt-4 tracking-wider uppercase font-semibold">Press Esc to Exit Preview</p>
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
        componentTypeName: "<?php echo $compType ? SecurityHelper::escape($compType->getName()) : ''; ?>"
    };
</script>

<!-- Editor Scripts -->
<script src="js/editor-core.js"></script>
<script src="js/guide-renderer.js"></script>
<script src="js/layer-manager.js"></script>
<script src="js/property-inspector.js"></script>
<script src="js/asset-picker.js"></script>
<script src="js/template-engine.js"></script>

<script>
    function makeCopy() {
        const originalName = window.studioConfig.templateName;
        const newName = prompt("Enter a name for the duplicated template:", originalName + " (Copy)");
        if (newName === null) return;
        const trimmed = newName.trim();
        if (trimmed === "") {
            alert("Template name cannot be empty.");
            return;
        }
        
        if (window.editorCore && typeof window.editorCore.setSaveStatus === 'function') {
            window.editorCore.setSaveStatus('Saving and duplicating...', 'pulse');
        }
        
        // Save canvas first if NOT in view mode
        let savePromise = Promise.resolve();
        if (!window.studioConfig.isViewMode && window.editorCore && typeof window.editorCore.saveCanvas === 'function') {
            const res = window.editorCore.saveCanvas();
            if (res instanceof Promise) {
                savePromise = res;
            }
        }
        
        savePromise
            .then(() => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'duplicate_template';
                form.appendChild(actionInput);
                
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = window.studioConfig.csrfToken;
                form.appendChild(csrfInput);
                
                const nameInput = document.createElement('input');
                nameInput.type = 'hidden';
                nameInput.name = 'new_name';
                nameInput.value = trimmed;
                form.appendChild(nameInput);
                
                document.body.appendChild(form);
                form.submit();
            })
            .catch(err => {
                alert("Could not save the current state before duplicating: " + err.message);
            });
    }

    function showFullscreenPreview() {
        if (!window.editorCanvas) return;
        
        const canvas = window.editorCanvas;
        
        // Hide guides temporarily before exporting to keep preview clean
        let safeGuide = null;
        let bleedGuide = null;
        canvas.getObjects().forEach(obj => {
            if (obj.id === 'safe-zone-guide') safeGuide = obj;
            if (obj.id === 'bleed-zone-guide') bleedGuide = obj;
        });
        
        const safeVisible = safeGuide ? safeGuide.visible : false;
        const bleedVisible = bleedGuide ? bleedGuide.visible : false;
        
        if (safeGuide) safeGuide.visible = false;
        if (bleedGuide) bleedGuide.visible = false;
        canvas.discardActiveObject().renderAll();
        
        // Generate high-resolution clean preview
        const dataUrl = canvas.toDataURL({
            format: 'png',
            quality: 1.0,
            multiplier: 2
        });
        
        // Restore guides
        if (safeGuide) safeGuide.visible = safeVisible;
        if (bleedGuide) bleedGuide.visible = bleedVisible;
        canvas.renderAll();
        
        const overlay = document.getElementById('preview-overlay');
        const img = document.getElementById('preview-image');
        img.src = dataUrl;
        
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
        setTimeout(() => {
            overlay.style.opacity = '1';
        }, 50);
        
        document.addEventListener('keydown', onPreviewEscKey);
    }

    function closeFullscreenPreview() {
        const overlay = document.getElementById('preview-overlay');
        overlay.style.opacity = '0';
        setTimeout(() => {
            overlay.classList.remove('flex');
            overlay.classList.add('hidden');
            document.getElementById('preview-image').src = '';
        }, 300);
        
        document.removeEventListener('keydown', onPreviewEscKey);
    }

    function onPreviewEscKey(e) {
        if (e.key === 'Escape') {
            closeFullscreenPreview();
        }
    }
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
