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

<!-- CSS for Editor Grid -->
<style>
    html, body {
        overflow: hidden !important;
        height: 100% !important;
    }
    .editor-container {
        height: calc(100vh - 120px);
        min-height: 350px;
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
</style>

<div class="space-y-4">
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
                <span id="zoom-value" class="text-xs font-semibold px-2 text-slate-300">100%</span>
                <button id="btn-zoom-in" class="p-1 hover:bg-slate-800 text-slate-400 hover:text-white rounded" title="Zoom In">+</button>
                <button id="btn-zoom-fit" class="p-1 hover:bg-slate-800 text-slate-500 hover:text-white rounded text-[10px] font-bold px-1.5" title="Fit to View">FIT</button>
            </div>

            <!-- Export -->
            <a href="export.php?project_id=<?php echo $template->getProjectId(); ?>&template_id=<?php echo $template->getId(); ?>" class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow transition">
                Export Studio
            </a>
        </div>
    </div>

    <!-- Main Workspace Grid -->
    <div class="editor-container grid grid-cols-1 lg:grid-cols-4 gap-4">
        
        <!-- Left Panel: Layers and Assets -->
        <div class="bg-slate-900/50 border border-slate-800 rounded-2xl flex flex-col h-full overflow-hidden">
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
        <div class="lg:col-span-2 flex flex-col h-full bg-slate-950 border border-slate-800/60 rounded-2xl overflow-hidden relative">
            <div class="canvas-viewport flex-grow overflow-auto flex items-center justify-center p-8 relative">
                <!-- Wrapper for absolute alignment and sizing -->
                <div id="canvas-container-wrapper" class="relative shadow-2xl border border-slate-700/50">
                    <canvas id="editor-canvas"></canvas>
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
        <div class="bg-slate-900/50 border border-slate-800 rounded-2xl flex flex-col h-full overflow-hidden">
            <div class="p-4 border-b border-slate-800">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-200">Properties Inspector</h2>
            </div>
            
            <div id="inspector-content" class="flex-grow overflow-y-auto pt-4 px-4 pb-12 space-y-4">
                <!-- Fallback notice when nothing is selected -->
                <div id="inspector-none-selected" class="text-center py-12 text-slate-500 text-xs">
                    <svg class="mx-auto h-8 w-8 text-slate-700 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/></svg>
                    <span>Select a layer on the canvas to configure properties.</span>
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
                                    <option value="Plus Jakarta Sans">Jakarta Sans</option>
                                    <option value="Arial">Arial</option>
                                    <option value="Times New Roman">Times Roman</option>
                                    <option value="Courier New">Courier</option>
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
                            <button id="btn-inspector-change-image" class="px-2 py-1 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 hover:bg-indigo-500/20 rounded transition">Change</button>
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

<!-- Configuration parameters injected to JavaScript -->
<script>
    window.studioConfig = {
        templateId: <?php echo $template->getId(); ?>,
        projectId: <?php echo $template->getProjectId(); ?>,
        csrfToken: "<?php echo SecurityHelper::escape($csrfToken); ?>",
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

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
