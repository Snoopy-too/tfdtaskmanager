<?php
declare(strict_types=1);

$container = require_once __DIR__ . '/../src/bootstrap.php';

use App\Infrastructure\Security\SecurityHelper;
use App\Application\Services\ProjectService;
use App\Application\Services\BgRulebookService;
use App\Application\Services\BgTemplateService;
use App\Application\Services\BgAssetService;
use App\Application\Services\BgDatasetService;
use App\Application\Services\UserService;

SecurityHelper::requireLogin();

$projectService = $container->get(ProjectService::class);
$rulebookService = $container->get(BgRulebookService::class);
$templateService = $container->get(BgTemplateService::class);
$assetService = $container->get(BgAssetService::class);
$datasetService = $container->get(BgDatasetService::class);
$userService = $container->get(UserService::class);

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$rulebookId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$rulebook = $rulebookService->getRulebookById($rulebookId);

if (!$rulebook) {
    header("Location: rulebooks.php");
    exit;
}

$project = $projectService->getProjectById($rulebook->getProjectId(), $currentUserId);
if (!$project) {
    header("Location: rulebooks.php");
    exit;
}

$lockUser = null;
$isLocked = false;
if ($rulebookService->isRulebookLockedByOther($rulebook, $currentUserId)) {
    $isLocked = true;
    $lockUser = $userService->getUserById($rulebook->getLockedByUserId());
} else {
    $rulebookService->acquireOrRefreshLock($rulebook->getId(), $currentUserId);
}

$_SESSION['last_project_id'] = $rulebook->getProjectId();

$csrfToken = SecurityHelper::generateCsrfToken();

// Fetch templates and assets for dynamic insertions
$templates = $templateService->getTemplatesByProject($rulebook->getProjectId());
$assets = $assetService->getAssetsByProject($rulebook->getProjectId(), true);
$glossary = $rulebookService->getGlossaryByProject($rulebook->getProjectId());

require_once __DIR__ . '/../templates/header.php';
?>

<!-- Google Fonts for Board Game Studio Components -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Almendra:ital,wght@0,400;0,700;1,400&family=Bangers&family=Cinzel:wght@400;700&family=Comic+Neue:wght@400;700&family=Creepster&family=EB+Garamond:ital,wght@0,400;0,700;1,400&family=Fredoka:wght@400;700&family=Inter:wght@400;700&family=Jolly+Lodger&family=Lora:ital,wght@0,400;0,700;1,400&family=Luckiest+Guy&family=MedievalSharp&family=Metal+Mania&family=Montserrat:wght@400;700&family=Orbitron:wght@400;700&family=Outfit:wght@400;700&family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Rajdhani:wght@500;700&family=Rye&family=Share+Tech+Mono&family=Courier+Prime&family=Special+Elite&display=swap" rel="stylesheet">

<!-- FabricJS for rendering component previews on visual tables -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>

<link rel="stylesheet" href="css/rulebook.css?v=<?php echo filemtime(__DIR__ . '/css/rulebook.css'); ?>">

<div class="flex-grow flex flex-col md:flex-row w-full min-h-full" id="editor-workspace">
    
    <?php include __DIR__ . '/views/rulebook-sidebar.php'; ?>

    <!-- Main Workspace Container -->
    <div id="main-workspace-container" class="flex-grow flex flex-col bg-slate-950 min-w-0">
        <!-- Top Toolbar -->
        <div id="editor-controls" class="h-14 bg-slate-900 border-b border-slate-800 flex items-center justify-between px-6 flex-shrink-0">
            <div class="flex items-center space-x-3">
                <span class="text-xs text-slate-400 bg-slate-850 px-2.5 py-1 rounded-full font-medium" id="status-indicator">All changes saved</span>
                <button onclick="saveRulebook()" class="bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold py-1.5 px-3 rounded-lg transition duration-200 flex items-center gap-1.5 shadow-sm">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                    <span class="hidden sm:inline">Save Document</span>
                </button>
                <button onclick="triggerPrint()" class="bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold py-1.5 px-3 rounded-lg border border-slate-700 transition duration-200 flex items-center gap-1.5">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-3a2 2 0 00-2-2H9a2 2 0 00-2 2v3a2 2 0 002 2zm5-17V4a2 2 0 00-2-2H9a2 2 0 00-2 2v3"/></svg>
                    <span class="hidden sm:inline">Print-Ready PDF</span>
                </button>
            </div>
            
            <div class="flex items-center space-x-2">
                <button type="button" onclick="toggleFullScreenEditor()" id="btn-fullscreen-mode" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-400 hover:text-white transition flex items-center gap-1.5 border border-slate-800 hover:border-slate-700" title="Toggle Fullscreen Editor (Hides Topnav & Side Menu)">
                    <i class="bi bi-arrows-fullscreen"></i>
                    <span class="hidden sm:inline">Fullscreen</span>
                </button>
                <button onclick="togglePreviewMode(false)" id="btn-edit-mode" class="px-3.5 py-1.5 rounded-lg text-xs font-bold bg-amber-500/10 text-amber-400 transition">
                    Editor View
                </button>
                <button onclick="togglePreviewMode(true)" id="btn-preview-mode" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold text-slate-400 hover:text-white transition">
                    Responsive Mobile View
                </button>
            </div>
        </div>

        <?php if ($isLocked): ?>
            <div class="bg-rose-500/10 border-b border-rose-500/20 text-rose-400 px-6 py-3 text-xs flex items-center justify-between gap-4 flex-shrink-0">
                <div class="flex items-center space-x-2">
                    <svg class="h-4 w-4 text-rose-500 animate-pulse flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    <span><strong>Read-Only View:</strong> This rulebook is currently locked for editing by <strong><?php echo SecurityHelper::escape($lockUser ? $lockUser->getName() : 'another user'); ?></strong>.</span>
                </div>
                <a href="rulebooks.php?project_id=<?php echo $rulebook->getProjectId(); ?>" class="px-2.5 py-1 bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 hover:text-white rounded-lg text-[10px] font-semibold transition flex-shrink-0">
                    Back to Dashboard
                </a>
            </div>
        <?php endif; ?>

        <!-- Rulebook Content Container -->
        <div class="flex-grow p-4 md:p-8" id="rulebook-viewport-container">
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

<?php include __DIR__ . '/views/rulebook-modals.php'; ?>

<!-- Bootstrapping configs into Window global objects -->
<script>
    window.rulebookConfig = {
        rulebookId: <?php echo $rulebook->getId(); ?>,
        projectId: <?php echo $rulebook->getProjectId(); ?>,
        csrfToken: '<?php echo SecurityHelper::escape($csrfToken); ?>',
        isLocked: <?php echo $isLocked ? 'true' : 'false'; ?>,
        initialBlocks: <?php echo json_encode($rulebook->getContent()); ?>,
        templates: <?php echo json_encode(array_map(function($t) use ($datasetService) {
            $qty = 1;
            if ($t->getDatasetId() !== null) {
                $dataset = $datasetService->getDatasetById($t->getDatasetId());
                if ($dataset && is_array($dataset->getRowData())) {
                    $qty = count($dataset->getRowData());
                }
            }
            return [
                'id' => $t->getId(),
                'name' => $t->getName(),
                'width' => $t->getCanvasWidthPx(),
                'height' => $t->getCanvasHeightPx(),
                'component_type' => $t->getComponentTypeId(),
                'quantity' => $qty
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

<script src="js/rulebook-parser.js?v=<?php echo filemtime(__DIR__ . '/js/rulebook-parser.js'); ?>"></script>
<script src="js/rulebook-canvas.js?v=<?php echo filemtime(__DIR__ . '/js/rulebook-canvas.js'); ?>"></script>
<script src="js/rulebook-theme.js?v=<?php echo filemtime(__DIR__ . '/js/rulebook-theme.js'); ?>"></script>
<script src="js/rulebook-blocks.js?v=<?php echo filemtime(__DIR__ . '/js/rulebook-blocks.js'); ?>"></script>
<script src="js/rulebook-renderer.js?v=<?php echo filemtime(__DIR__ . '/js/rulebook-renderer.js'); ?>"></script>

<script>
window.toggleFullScreenEditor = function() {
    document.body.classList.toggle('fullscreen-editor');
    const isFull = document.body.classList.contains('fullscreen-editor');
    const btn = document.getElementById('btn-fullscreen-mode');
    if (btn) {
        btn.classList.toggle('text-indigo-400', isFull);
        btn.classList.toggle('bg-indigo-500/10', isFull);
        btn.classList.toggle('border-indigo-500/30', isFull);
        const icon = btn.querySelector('i');
        if (icon) {
            icon.className = isFull ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen';
        }
    }
};

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.body.classList.contains('fullscreen-editor')) {
        window.toggleFullScreenEditor();
    }
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
