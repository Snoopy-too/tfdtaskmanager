<?php
declare(strict_types=1);

/**
 * 2D Playtest Sandbox Workspace (spec §3–§8).
 * Hosts Setup Mode (Staging) and Play Mode (Solo + Authoritative Multiplayer).
 */

$container = require_once __DIR__ . '/../src/bootstrap.php';

use App\Application\Services\BgAssetService;
use App\Application\Services\BgDatasetService;
use App\Application\Services\BgSandboxService;
use App\Application\Services\BgTemplateService;
use App\Application\Services\ProjectService;
use App\Application\Services\UserService;
use App\Infrastructure\Security\SecurityHelper;

SecurityHelper::requireLogin();

$projectService = $container->get(ProjectService::class);
$sandboxService = $container->get(BgSandboxService::class);
$assetService = $container->get(BgAssetService::class);
$templateService = $container->get(BgTemplateService::class);
$datasetService = $container->get(BgDatasetService::class);
$userService = $container->get(UserService::class);

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentUser = $userService->getUserById($currentUserId);
$currentUserName = $currentUser ? $currentUser->getName() : ('Tester #' . $currentUserId);

$sandboxId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sandbox = $sandboxService->getSandboxById($sandboxId);
if ($sandbox === null) {
    header('Location: sandboxes.php');
    exit;
}

$project = $projectService->getProjectById($sandbox->projectId, $currentUserId);
if ($project === null) {
    header('Location: sandboxes.php');
    exit;
}

$_SESSION['last_project_id'] = $sandbox->projectId;
$csrfToken = SecurityHelper::generateCsrfToken();

$autoHost = isset($_GET['host']) && $_GET['host'] === '1';
$roomInfo = null;
if ($autoHost) {
    $roomInfo = $sandboxService->hostLiveSession($sandbox, $currentUserId);
}

$isLockedByOther = $sandbox->isLockedByOther($currentUserId);
$lockOwnerName = null;
if ($isLockedByOther && $sandbox->lockedByUserId !== null) {
    $lockUser = $userService->getUserById($sandbox->lockedByUserId);
    $lockOwnerName = $lockUser ? $lockUser->getName() : 'Another user';
} else {
    $sandboxService->acquireOrRefreshLock($sandbox, $currentUserId);
}

// --- Build Project Asset Importer payload (spec §4.1) ---
$rawAssets = $assetService->getAssetsByProject($sandbox->projectId, true);
$rawTemplates = $templateService->getTemplatesByProject($sandbox->projectId);

$assetUrlFor = static function ($a): string {
    $folder = $a->getProjectId() === null ? 'global' : (string)$a->getProjectId();
    $url = '../uploads/board-game-studio/' . $folder . '/' . $a->getStoredFilename();
    if ($a->getProjectId() === null) {
        $uploadPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'board-game-studio' . DIRECTORY_SEPARATOR . 'global' . DIRECTORY_SEPARATOR . $a->getStoredFilename();
        if (!file_exists($uploadPath)) {
            $builtInPath = __DIR__ . DIRECTORY_SEPARATOR . 'icons' . DIRECTORY_SEPARATOR . $a->getOriginalFilename();
            if (file_exists($builtInPath)) {
                $url = 'icons/' . $a->getOriginalFilename();
            }
        }
    }
    return $url;
};

$boards = [];
$tokens = [];
$cardBatches = [];

foreach ($rawTemplates as $tpl) {
    $compType = $tpl->getComponentTypeId();
    $w = max(80, (int)round($tpl->getCanvasWidthPx() / 4));
    $h = max(80, (int)round($tpl->getCanvasHeightPx() / 4));

    if (in_array($compType, [3, 5, 6, 7, 8], true)) {
        $boards[] = [
            'assetId' => 'tpl_board_' . $tpl->getId(),
            'name' => $tpl->getName(),
            'imageUrl' => null,
            'width' => min(1600, max(420, $w)),
            'height' => min(1200, max(320, $h)),
        ];
    } elseif ($compType === 4) {
        $tokens[] = [
            'assetId' => 'tpl_token_' . $tpl->getId(),
            'name' => $tpl->getName(),
            'imageUrl' => null,
            'width' => 56,
            'height' => 56,
        ];
    } else {
        $cards = [];
        $dsId = $tpl->getDatasetId();
        if ($dsId !== null) {
            $ds = $datasetService->getDatasetById($dsId);
            if ($ds !== null) {
                foreach ($ds->getRowData() as $idx => $row) {
                    $rowArr = is_array($row) ? $row : [];
                    $cardName = (string)($rowArr['name'] ?? $rowArr['Name'] ?? $rowArr['title'] ?? $rowArr['Title'] ?? ($tpl->getName() . ' #' . ($idx + 1)));
                    $frontImg = (string)($rowArr['image'] ?? $rowArr['art'] ?? $rowArr['icon'] ?? '');
                    $cards[] = [
                        'cardAssetId' => 'c_' . $tpl->getId() . '_' . ($idx + 1),
                        'name' => $cardName,
                        'frontImageUrl' => $frontImg !== '' ? $frontImg : null,
                        'metadata' => array_slice($rowArr, 0, 8),
                    ];
                }
            }
        }
        if ($cards === []) {
            for ($k = 1; $k <= 5; $k++) {
                $cards[] = [
                    'cardAssetId' => 'c_' . $tpl->getId() . '_' . $k,
                    'name' => $tpl->getName() . ' #' . $k,
                    'frontImageUrl' => null,
                    'metadata' => ['template' => $tpl->getName(), 'copy' => $k],
                ];
            }
        }
        $cardBatches[] = [
            'batchId' => 'cb_' . $tpl->getId(),
            'name' => $tpl->getName(),
            'backImageUrl' => null,
            'width' => 140,
            'height' => 200,
            'cards' => $cards,
        ];
    }
}

foreach ($rawAssets as $a) {
    $tag = strtolower((string)($a->getTag() ?? ''));
    $url = $assetUrlFor($a);
    $baseName = pathinfo($a->getOriginalFilename(), PATHINFO_FILENAME);
    if (str_contains($tag, 'board') || str_contains($tag, 'map') || str_contains($tag, 'mat')) {
        $boards[] = [
            'assetId' => 'b_' . $a->getId(),
            'name' => $baseName,
            'imageUrl' => $url,
            'width' => 1000,
            'height' => 720,
        ];
    } else {
        $tokens[] = [
            'assetId' => 't_' . $a->getId(),
            'name' => $baseName,
            'imageUrl' => $url,
            'width' => 48,
            'height' => 48,
        ];
    }
}

if ($boards === []) {
    $boards[] = [
        'assetId' => 'b_default_grid',
        'name' => 'Main Tabletop Board (800×600)',
        'imageUrl' => null,
        'width' => 800,
        'height' => 600,
    ];
}
if ($cardBatches === []) {
    $cardBatches[] = [
        'batchId' => 'cb_starter',
        'name' => 'Starter Action Deck',
        'backImageUrl' => null,
        'width' => 140,
        'height' => 200,
        'cards' => [
            ['cardAssetId' => 'c_001', 'name' => 'Telegraph Office', 'frontImageUrl' => null, 'metadata' => ['type' => 'civic', 'cost' => 3]],
            ['cardAssetId' => 'c_002', 'name' => 'Steam Foundry', 'frontImageUrl' => null, 'metadata' => ['type' => 'industry', 'cost' => 4]],
            ['cardAssetId' => 'c_003', 'name' => 'Merchant Guild', 'frontImageUrl' => null, 'metadata' => ['type' => 'commerce', 'cost' => 2]],
            ['cardAssetId' => 'c_004', 'name' => 'Harbor Crane', 'frontImageUrl' => null, 'metadata' => ['type' => 'civic', 'cost' => 3]],
            ['cardAssetId' => 'c_005', 'name' => 'Rail Depot', 'frontImageUrl' => null, 'metadata' => ['type' => 'transport', 'cost' => 5]],
            ['cardAssetId' => 'c_006', 'name' => 'Clocktower', 'frontImageUrl' => null, 'metadata' => ['type' => 'monument', 'cost' => 6]],
        ],
    ];
}

$importedAssetsPayload = [
    'projectId' => 'proj_' . $sandbox->projectId,
    'importedAssets' => [
        'boards' => $boards,
        'cardBatches' => $cardBatches,
        'tokens' => $tokens,
    ],
];

require_once __DIR__ . '/../templates/header.php';
?>

<link rel="stylesheet" href="css/sandbox.css?v=<?php echo file_exists(__DIR__ . '/css/sandbox.css') ? filemtime(__DIR__ . '/css/sandbox.css') : '1'; ?>">

<div id="sandbox-workspace" class="h-[calc(100vh-3rem)] flex flex-col -mx-4 sm:-mx-6 lg:-mx-8 -my-8 bg-slate-950 text-slate-100 select-none overflow-hidden relative">

    <!-- Top Control Bar (hud/TopControlBar.js) -->
    <header id="sbx-top-bar" class="h-12 bg-slate-900/95 border-b border-slate-800 px-3 flex items-center justify-between gap-2 flex-shrink-0 z-30"></header>

    <?php if ($isLockedByOther): ?>
        <div class="bg-amber-500/10 border-b border-amber-500/30 text-amber-300 px-4 py-1.5 text-xs flex items-center justify-between flex-shrink-0 z-20">
            <span><strong>Shared Session Notice:</strong> Setup is currently locked by <strong><?php echo SecurityHelper::escape((string)$lockOwnerName); ?></strong>. Switch to Live Room to test together in real time.</span>
            <a href="sandboxes.php?project_id=<?php echo (int)$sandbox->projectId; ?>" class="underline hover:text-white">Back to Sandboxes</a>
        </div>
    <?php endif; ?>

    <!-- Middle Interactive Area: Left Component Drawer + 2D Viewport + Right Inspector / Resource & Event Log -->
    <div class="flex-1 flex min-h-0 relative overflow-hidden">

        <!-- Setup Mode Component Drawer (hud/ComponentDrawer.js) -->
        <aside id="sbx-component-drawer" class="w-72 bg-slate-900/95 border-r border-slate-800 flex flex-col flex-shrink-0 z-20 overflow-hidden"></aside>

        <!-- 2D Infinite Pan/Zoom Viewport (canvas/SandboxCanvas.js) -->
        <section id="sbx-viewport" class="flex-1 relative overflow-hidden bg-slate-950 cursor-grab" tabindex="0" aria-label="2D Playtest Sandbox Canvas">
            <!-- Transform Stage -->
            <div id="sbx-world" class="absolute top-0 left-0 origin-top-left" style="width:1px;height:1px;">
                <!-- Tier 0: Table Surface Grid -->
                <div id="sbx-grid-surface" class="sbx-grid-surface"></div>
                <!-- Tier 1: Boards & Mats (100-199) -->
                <div id="sbx-layer-boards" class="absolute top-0 left-0"></div>
                <!-- Tier 2: Zones & Trays (200-299) -->
                <div id="sbx-layer-zones" class="absolute top-0 left-0"></div>
                <!-- Tier 3: Cards & Decks (300-499) -->
                <div id="sbx-layer-cards" class="absolute top-0 left-0"></div>
                <!-- Tier 4: Tokens, Spawners & Dice (500-699) -->
                <div id="sbx-layer-tokens" class="absolute top-0 left-0"></div>
                <!-- Sticky Notes Overlay -->
                <div id="sbx-layer-notes" class="absolute top-0 left-0"></div>
                <!-- Tier 5: Active Drag, Ghost Cursors & Spatial Pings (900-999) -->
                <div id="sbx-layer-overlay" class="absolute top-0 left-0 pointer-events-none"></div>
            </div>

            <!-- Marquee Multi-Select Box -->
            <div id="sbx-marquee" class="hidden absolute border border-sky-400 bg-sky-400/15 pointer-events-none z-40"></div>

            <!-- Floating Canvas HUD (Zoom percentage, Snap Tool toggle, Shortcuts pill) -->
            <div id="sbx-canvas-hud" class="absolute bottom-3 left-3 flex items-center gap-2 z-20"></div>

            <!-- Alt-Hover High-Resolution Card Preview Overlay -->
            <div id="sbx-zoom-preview" class="hidden fixed pointer-events-none z-50"></div>

            <!-- Context Menu -->
            <div id="sbx-context-menu" class="hidden fixed bg-slate-900 border border-slate-700 rounded-xl shadow-2xl py-1.5 text-xs z-50 min-w-[190px]"></div>
        </section>

        <!-- Right Dock: Object Inspector, Resource Dashboard & Authoritative Event/Chat Log -->
        <aside id="sbx-right-dock" class="w-80 bg-slate-900/95 border-l border-slate-800 flex flex-col flex-shrink-0 z-20 overflow-hidden">
            <div id="sbx-inspector-panel" class="border-b border-slate-800 p-3 space-y-2 max-h-64 overflow-y-auto"></div>
            <div id="sbx-resource-dashboard" class="border-b border-slate-800 p-3 space-y-2 overflow-y-auto max-h-64"></div>
            <div id="sbx-event-log" class="flex-1 flex flex-col min-h-0"></div>
        </aside>
    </div>

    <!-- Bottom Private Player Hand Tray + Solo Seat-Switcher (hud/PlayerHandTray.js) -->
    <footer id="sbx-hand-tray" class="h-36 bg-slate-900/95 border-t border-slate-800 flex flex-col flex-shrink-0 z-30"></footer>

    <!-- Deck Search / Scenarios / Multiplayer Modal -->
    <div id="sbx-modal-backdrop" class="hidden fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div id="sbx-modal-content" class="bg-slate-900 border border-slate-800 rounded-2xl max-w-2xl w-full p-5 shadow-2xl space-y-4 max-h-[82vh] flex flex-col"></div>
    </div>
</div>

<script>
    window.sandboxBootstrap = {
        sandboxId: <?php echo (int)$sandbox->id; ?>,
        projectId: <?php echo (int)$sandbox->projectId; ?>,
        sandboxName: <?php echo json_encode($sandbox->name, JSON_HEX_TAG | JSON_HEX_AMP); ?>,
        defaultPlayerCount: <?php echo (int)$sandbox->defaultPlayerCount; ?>,
        csrfToken: <?php echo json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP); ?>,
        currentUserId: <?php echo (int)$currentUserId; ?>,
        currentUserName: <?php echo json_encode($currentUserName, JSON_HEX_TAG | JSON_HEX_AMP); ?>,
        isLockedByOther: <?php echo $isLockedByOther ? 'true' : 'false'; ?>,
        hasStartingSnapshot: <?php echo $sandbox->startingSnapshot !== null ? 'true' : 'false'; ?>,
        autoHost: <?php echo $autoHost ? 'true' : 'false'; ?>,
        roomCode: <?php echo json_encode($roomInfo['roomCode'] ?? null); ?>,
        initialState: <?php echo json_encode($sandboxService->presentState($sandbox), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>,
        projectAssets: <?php echo json_encode($importedAssetsPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>
    };
</script>

<script src="js/sandbox/sandboxSchema.js?v=<?php echo file_exists(__DIR__ . '/js/sandbox/sandboxSchema.js') ? filemtime(__DIR__ . '/js/sandbox/sandboxSchema.js') : '1'; ?>"></script>
<script src="js/sandbox/snapEngine.js?v=<?php echo file_exists(__DIR__ . '/js/sandbox/snapEngine.js') ? filemtime(__DIR__ . '/js/sandbox/snapEngine.js') : '1'; ?>"></script>
<script src="js/sandbox/tuckingEngine.js?v=<?php echo file_exists(__DIR__ . '/js/sandbox/tuckingEngine.js') ? filemtime(__DIR__ . '/js/sandbox/tuckingEngine.js') : '1'; ?>"></script>
<script src="js/sandbox/sandboxStore.js?v=<?php echo file_exists(__DIR__ . '/js/sandbox/sandboxStore.js') ? filemtime(__DIR__ . '/js/sandbox/sandboxStore.js') : '1'; ?>"></script>
<script src="js/sandbox/BoardLayer.js?v=<?php echo file_exists(__DIR__ . '/js/sandbox/BoardLayer.js') ? filemtime(__DIR__ . '/js/sandbox/BoardLayer.js') : '1'; ?>"></script>
<script src="js/sandbox/CardComponent.js?v=<?php echo file_exists(__DIR__ . '/js/sandbox/CardComponent.js') ? filemtime(__DIR__ . '/js/sandbox/CardComponent.js') : '1'; ?>"></script>
<script src="js/sandbox/DeckComponent.js?v=<?php echo file_exists(__DIR__ . '/js/sandbox/DeckComponent.js') ? filemtime(__DIR__ . '/js/sandbox/DeckComponent.js') : '1'; ?>"></script>
<script src="js/sandbox/TokenSpawner.js?v=<?php echo file_exists(__DIR__ . '/js/sandbox/TokenSpawner.js') ? filemtime(__DIR__ . '/js/sandbox/TokenSpawner.js') : '1'; ?>"></script>
<script src="js/sandbox/GhostCursorsOverlay.js?v=<?php echo file_exists(__DIR__ . '/js/sandbox/GhostCursorsOverlay.js') ? filemtime(__DIR__ . '/js/sandbox/GhostCursorsOverlay.js') : '1'; ?>"></script>
<script src="js/sandbox/SandboxCanvas.js?v=<?php echo file_exists(__DIR__ . '/js/sandbox/SandboxCanvas.js') ? filemtime(__DIR__ . '/js/sandbox/SandboxCanvas.js') : '1'; ?>"></script>
<script src="js/sandbox/TopControlBar.js?v=<?php echo file_exists(__DIR__ . '/js/sandbox/TopControlBar.js') ? filemtime(__DIR__ . '/js/sandbox/TopControlBar.js') : '1'; ?>"></script>
<script src="js/sandbox/ComponentDrawer.js?v=<?php echo file_exists(__DIR__ . '/js/sandbox/ComponentDrawer.js') ? filemtime(__DIR__ . '/js/sandbox/ComponentDrawer.js') : '1'; ?>"></script>
<script src="js/sandbox/PlayerHandTray.js?v=<?php echo file_exists(__DIR__ . '/js/sandbox/PlayerHandTray.js') ? filemtime(__DIR__ . '/js/sandbox/PlayerHandTray.js') : '1'; ?>"></script>
<script src="js/sandbox/ResourceDashboard.js?v=<?php echo file_exists(__DIR__ . '/js/sandbox/ResourceDashboard.js') ? filemtime(__DIR__ . '/js/sandbox/ResourceDashboard.js') : '1'; ?>"></script>
<script src="js/sandbox/EventAndChatLog.js?v=<?php echo file_exists(__DIR__ . '/js/sandbox/EventAndChatLog.js') ? filemtime(__DIR__ . '/js/sandbox/EventAndChatLog.js') : '1'; ?>"></script>
<script src="js/sandbox/multiplayerSync.js?v=<?php echo file_exists(__DIR__ . '/js/sandbox/multiplayerSync.js') ? filemtime(__DIR__ . '/js/sandbox/multiplayerSync.js') : '1'; ?>"></script>
<script src="js/sandbox/SandboxWorkspace.js?v=<?php echo file_exists(__DIR__ . '/js/sandbox/SandboxWorkspace.js') ? filemtime(__DIR__ . '/js/sandbox/SandboxWorkspace.js') : '1'; ?>"></script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
