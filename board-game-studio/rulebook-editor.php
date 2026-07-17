<?php
declare(strict_types=1);

$container = require_once __DIR__ . '/../src/bootstrap.php';

use App\Infrastructure\Security\SecurityHelper;
use App\Application\Services\ProjectService;
use App\Application\Services\BgRulebookService;
use App\Application\Services\BgTemplateService;
use App\Application\Services\BgAssetService;

SecurityHelper::requireLogin();

$projectService = $container->get(ProjectService::class);
$rulebookService = $container->get(BgRulebookService::class);
$templateService = $container->get(BgTemplateService::class);
$assetService = $container->get(BgAssetService::class);

$rulebookId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$rulebook = $rulebookService->getRulebookById($rulebookId);

if (!$rulebook) {
    header("Location: rulebooks.php");
    exit;
}

$project = $projectService->getProjectById($rulebook->getProjectId());
$_SESSION['last_project_id'] = $rulebook->getProjectId();

$csrfToken = SecurityHelper::generateCsrfToken();

// Fetch templates and assets for dynamic insertions
$templates = $templateService->getTemplatesByProject($rulebook->getProjectId());
$assets = $assetService->getAssetsByProject($rulebook->getProjectId());
$glossary = $rulebookService->getGlossaryByProject($rulebook->getProjectId());

require_once __DIR__ . '/../templates/header.php';
?>

<!-- FabricJS for rendering component previews on visual tables -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>

<style>
    /* Styling rules for print view vs screen view */
    @media print {
        body {
            background: white !important;
            color: black !important;
        }
        body > nav, #editor-sidebar, #editor-controls, #add-block-panel {
            display: none !important;
        }
        #rulebook-content-wrapper {
            margin: 0 !important;
            padding: 0 !important;
            box-shadow: none !important;
            width: 100% !important;
        }
        .page-break {
            page-break-after: always;
        }
        /* Crop marks styling */
        .crop-mark {
            display: block !important;
            position: absolute;
            width: 15px;
            height: 15px;
            border-color: #666;
            border-style: solid;
            pointer-events: none;
        }
        .crop-tl { top: -5mm; left: -5mm; border-width: 0 1px 1px 0; }
        .crop-tr { top: -5mm; right: -5mm; border-width: 0 0 1px 1px; }
        .crop-bl { bottom: -5mm; left: -5mm; border-width: 1px 1px 0 0; }
        .crop-br { bottom: -5mm; right: -5mm; border-width: 1px 0 0 1px; }
    }

    .crop-mark {
        display: none;
    }

    /* Screen rules for the block editor workspace */
    .block-card:hover .block-actions {
        opacity: 1;
    }
    
    .anatomy-pin {
        position: absolute;
        width: 24px;
        height: 24px;
        background: #f59e0b;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 800;
        cursor: pointer;
        box-shadow: 0 0 10px rgba(245, 158, 11, 0.6);
        border: 2px solid white;
        transform: translate(-50%, -50%);
        transition: transform 0.2s;
    }
    .anatomy-pin:hover {
        transform: translate(-50%, -50%) scale(1.2);
    }
</style>

<div class="h-[calc(100vh-4rem)] flex flex-col md:flex-row -mx-4 md:-mx-8 -my-8 overflow-hidden" id="editor-workspace">
    
    <!-- Sidebar: Templates & Block types -->
    <div id="editor-sidebar" class="w-full md:w-80 bg-slate-900 border-r border-slate-800 flex flex-col justify-between flex-shrink-0">
        <div class="p-6 space-y-6 overflow-y-auto flex-grow h-1">
            <div class="space-y-1">
                <a href="rulebooks.php?project_id=<?php echo $rulebook->getProjectId(); ?>" class="text-xs font-semibold text-slate-400 hover:text-white transition duration-200 inline-flex items-center">
                    <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                    Rulebooks list
                </a>
                <h2 class="text-lg font-black text-white truncate"><?php echo SecurityHelper::escape($rulebook->getName()); ?></h2>
                <p class="text-xs text-slate-500">Project: <?php echo SecurityHelper::escape($project->getName()); ?></p>
            </div>

            <!-- Block Adder Options -->
            <div class="space-y-3">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Add Rulebook Block</h3>
                <div class="grid grid-cols-1 gap-2">
                    <button onclick="addBlock('markdown')" class="w-full text-left bg-slate-950 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 p-3 rounded-xl flex items-center space-x-3 transition duration-200">
                        <span class="p-2 rounded-lg bg-amber-500/10 text-amber-400">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-200">Markdown Text</p>
                            <p class="text-[10px] text-slate-500">Write rules, map icons, and terms.</p>
                        </div>
                    </button>

                    <button onclick="addBlock('setup')" class="w-full text-left bg-slate-950 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 p-3 rounded-xl flex items-center space-x-3 transition duration-200">
                        <span class="p-2 rounded-lg bg-indigo-500/10 text-indigo-400">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-200">Game Setup Diagram</p>
                            <p class="text-[10px] text-slate-500">Drag & drop visual components.</p>
                        </div>
                    </button>

                    <button onclick="addBlock('component_list')" class="w-full text-left bg-slate-950 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 p-3 rounded-xl flex items-center space-x-3 transition duration-200">
                        <span class="p-2 rounded-lg bg-emerald-500/10 text-emerald-400">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-200">Component Inventory</p>
                            <p class="text-[10px] text-slate-500">Auto-generated templates list.</p>
                        </div>
                    </button>

                    <button onclick="addBlock('anatomy')" class="w-full text-left bg-slate-950 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 p-3 rounded-xl flex items-center space-x-3 transition duration-200">
                        <span class="p-2 rounded-lg bg-rose-500/10 text-rose-400">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-200">Anatomy of a Component</p>
                            <p class="text-[10px] text-slate-500">Label regions with coordinate pins.</p>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Glossary and Assets list overview -->
            <div class="space-y-3">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Icon Tag Cheat Sheet</h3>
                <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 space-y-1.5 max-h-48 overflow-y-auto">
                    <?php if (empty($assets)): ?>
                        <p class="text-[10px] text-slate-500">Upload assets in Studio to see tags.</p>
                    <?php else: ?>
                        <?php foreach ($assets as $asset): ?>
                            <?php if ($asset->getTag()): ?>
                                <div class="flex items-center justify-between text-[11px]">
                                    <code class="text-amber-400 font-mono">[<?php echo SecurityHelper::escape($asset->getTag()); ?>]</code>
                                    <span class="text-slate-500 truncate max-w-[120px]" title="<?php echo SecurityHelper::escape($asset->getOriginalFilename()); ?>"><?php echo SecurityHelper::escape($asset->getOriginalFilename()); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="p-6 border-t border-slate-800 bg-slate-950 space-y-3">
            <button onclick="saveRulebook()" class="w-full bg-amber-600 hover:bg-amber-500 text-white text-sm font-bold py-2 rounded-xl transition duration-200 flex items-center justify-center space-x-2">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                <span>Save Document</span>
            </button>
            <button onclick="triggerPrint()" class="w-full bg-slate-800 hover:bg-slate-700 text-slate-350 text-sm font-semibold py-2 rounded-xl border border-slate-700 transition duration-200 flex items-center justify-center space-x-2">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-3a2 2 0 00-2-2H9a2 2 0 00-2 2v3a2 2 0 002 2zm5-17V4a2 2 0 00-2-2H9a2 2 0 00-2 2v3"/></svg>
                <span>Print-Ready PDF</span>
            </button>
        </div>
    </div>

    <!-- Main Workspace Container -->
    <div class="flex-grow flex flex-col bg-slate-950 overflow-hidden h-full">
        <!-- Top Toolbar -->
        <div id="editor-controls" class="h-14 bg-slate-900 border-b border-slate-800 flex items-center justify-between px-6 flex-shrink-0">
            <div class="flex items-center space-x-4">
                <span class="text-xs text-slate-400 bg-slate-850 px-2.5 py-1 rounded-full font-medium" id="status-indicator">All changes saved</span>
            </div>
            
            <div class="flex items-center space-x-2">
                <button onclick="togglePreviewMode(false)" id="btn-edit-mode" class="px-3.5 py-1.5 rounded-lg text-xs font-bold bg-amber-500/10 text-amber-400 transition">
                    Editor View
                </button>
                <button onclick="togglePreviewMode(true)" id="btn-preview-mode" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold text-slate-400 hover:text-white transition">
                    Responsive Mobile View
                </button>
            </div>
        </div>

        <!-- Scrollable Blocks Canvas -->
        <div class="flex-grow overflow-y-auto p-8" id="rulebook-viewport-container">
            <div id="rulebook-content-wrapper" class="max-w-3xl mx-auto bg-slate-900 border border-slate-850 shadow-2xl rounded-2xl min-h-[80vh] p-10 relative space-y-8 transition-colors duration-300">
                
                <!-- Crop mark targets (will show during print layout) -->
                <div class="crop-mark crop-tl"></div>
                <div class="crop-mark crop-tr"></div>
                <div class="crop-mark crop-bl"></div>
                <div class="crop-mark crop-br"></div>

                <!-- Empty State -->
                <div id="empty-blocks-state" class="py-20 text-center flex flex-col items-center justify-center space-y-4">
                    <svg class="h-14 w-14 text-slate-650" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                    <div class="space-y-1">
                        <h4 class="text-slate-350 font-bold">Rulebook is Empty</h4>
                        <p class="text-xs text-slate-500 max-w-xs">Click one of the block buttons in the left sidebar to add section blocks to this document.</p>
                    </div>
                </div>

                <!-- Dynamic block list container -->
                <div id="blocks-list" class="space-y-8"></div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Confirmation Modal -->
<div id="custom-confirm-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center hidden">
    <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl w-full max-w-sm space-y-4 shadow-2xl">
        <div class="flex items-center space-x-3">
            <div class="p-2 rounded-lg bg-rose-500/10 text-rose-500">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <h3 class="text-base font-bold text-slate-200">Confirm Action</h3>
        </div>
        <p id="custom-confirm-message" class="text-xs text-slate-405">Are you sure you want to proceed?</p>
        <div class="flex justify-end space-x-2 pt-2">
            <button id="btn-confirm-cancel" class="px-4 py-2 bg-slate-850 hover:bg-slate-800 text-slate-300 text-xs font-semibold rounded-xl transition duration-200">Cancel</button>
            <button id="btn-confirm-ok" class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold rounded-xl transition duration-200">Confirm</button>
        </div>
    </div>
</div>

<!-- Modal configurations/helpers -->
<div id="diagram-item-picker" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center hidden">
    <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl w-full max-w-md space-y-4 shadow-2xl">
        <h3 class="text-base font-bold text-slate-200">Add Component to Diagram</h3>
        <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1">Select Component</label>
            <select id="diagram-select-template" class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-sm rounded-xl p-2.5">
                <?php foreach ($templates as $tmpl): ?>
                    <option value="<?php echo $tmpl->getId(); ?>"><?php echo SecurityHelper::escape($tmpl->getName()); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Scale (0.1 to 2.0)</label>
                <input type="number" id="diagram-item-scale" min="0.1" max="2.0" step="0.1" value="1.0" class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-sm rounded-xl p-2">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Rotation (Degrees)</label>
                <input type="number" id="diagram-item-rotation" min="0" max="360" step="15" value="0" class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-sm rounded-xl p-2">
            </div>
        </div>
        <div class="flex justify-end space-x-2 pt-2">
            <button onclick="closeDiagramPicker()" class="px-4 py-2 text-xs font-semibold text-slate-400 hover:text-white transition">Cancel</button>
            <button id="btn-add-to-diagram" class="px-4 py-2 text-xs font-bold bg-amber-600 hover:bg-amber-500 text-white rounded-xl transition">Add Item</button>
        </div>
    </div>
</div>

<!-- Bootstrapping configs into Window global objects -->
<script>
    window.rulebookConfig = {
        rulebookId: <?php echo $rulebook->getId(); ?>,
        projectId: <?php echo $rulebook->getProjectId(); ?>,
        csrfToken: '<?php echo SecurityHelper::escape($csrfToken); ?>',
        initialBlocks: <?php echo json_encode($rulebook->getContent()); ?>,
        templates: <?php echo json_encode(array_map(function($t) {
            return [
                'id' => $t->getId(),
                'name' => $t->getName(),
                'width' => $t->getCanvasWidthPx(),
                'height' => $t->getCanvasHeightPx(),
                'component_type' => $t->getComponentTypeId()
            ];
        }, $templates)); ?>,
        assets: <?php echo json_encode(array_map(function($a) {
            return [
                'id' => $a->getId(),
                'tag' => $a->getTag(),
                'filename' => $a->getOriginalFilename(),
                'url' => '../uploads/board-game-studio/' . ($a->getProjectId() === null ? 'global' : $a->getProjectId()) . '/' . $a->getStoredFilename()
            ];
        }, $assets)); ?>,
        glossary: <?php echo json_encode(array_map(function($g) {
            return [
                'id' => $g->getId(),
                'key' => $g->getTermKey(),
                'name' => $g->getTermName(),
                'description' => $g->getTermDescription()
            ];
        }, $glossary)); ?>
    };
</script>

<script src="js/rulebook-renderer.js"></script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
