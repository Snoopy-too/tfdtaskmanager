<?php
declare(strict_types=1);

$container = require_once __DIR__ . '/../src/bootstrap.php';

// Auto-migration for template lock columns & F10A4-1 component type
try {
    $db = $container->get(PDO::class);
    $check = $db->query("SHOW COLUMNS FROM `bg_templates` LIKE 'locked_by_user_id'")->fetchAll();
    if (empty($check)) {
        $db->exec("ALTER TABLE `bg_templates` ADD COLUMN `locked_by_user_id` INT DEFAULT NULL AFTER `created_by`");
        $db->exec("ALTER TABLE `bg_templates` ADD COLUMN `locked_at` TIMESTAMP NULL DEFAULT NULL AFTER `locked_by_user_id`");
        $db->exec("ALTER TABLE `bg_templates` ADD CONSTRAINT `fk_bg_templates_locked_user` FOREIGN KEY (`locked_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL");
    }

    $checkComp = $db->query("SELECT id FROM `bg_component_types` WHERE `name` LIKE '%F10A4-1%'")->fetch();
    if (!$checkComp) {
        $db->exec("INSERT INTO `bg_component_types` (`name`, `width_mm`, `height_mm`, `description`) VALUES ('Japanese Business Card (A-one F10A4-1)', 91.00, 55.00, 'Standard Japanese business card / A-one 10-card sheet (91x55 mm, Format F10A4-1)')");
    }

    // ponytail: auto-migrate A-one 51215 preset
    $check51215 = $db->query("SELECT id FROM `bg_component_types` WHERE `name` LIKE '%51215%' OR `name` LIKE '%F8A4-5%'")->fetch();
    if (!$check51215) {
        $db->exec("INSERT INTO `bg_component_types` (`name`, `width_mm`, `height_mm`, `description`) VALUES ('Japanese ID Card / Name Tag (A-one 51215)', 97.00, 69.00, 'Standard Japanese hanging name tag / ID card / A-one 8-card sheet (97x69 mm, Format F8A4-5, Model 51215)')");
    }

    // Auto-migrate Board Game Box (Unfolded Die-Line) preset
    $checkBox = $db->query("SELECT id FROM `bg_component_types` WHERE `name` LIKE '%Board Game Box%'")->fetch();
    if (!$checkBox) {
        $db->exec("INSERT INTO `bg_component_types` (`name`, `width_mm`, `height_mm`, `description`) VALUES ('Board Game Box (Unfolded Die-Line)', 297.20, 312.00, 'Unfolded flat board game box template generated from finished 3D Width, Length, and Height')");
    }
} catch (\Exception $e) {
    // Ignore db connection issues here; standard page loads will handle them
}

use App\Infrastructure\Security\SecurityHelper;
use App\Application\Services\ProjectService;
use App\Application\Services\BgTemplateService;
use App\Application\Services\BgAssetService;
use App\Application\Services\BgDatasetService;
use App\Application\Exceptions\ValidationException;

SecurityHelper::requireLogin();

$projectService = $container->get(ProjectService::class);
$templateService = $container->get(BgTemplateService::class);
$assetService = $container->get(BgAssetService::class);
$datasetService = $container->get(BgDatasetService::class);

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$error = '';
$success = '';
$csrfToken = SecurityHelper::generateCsrfToken();

// Fetch all projects accessible to current user
$allProjects = $projectService->getAllProjects($currentUserId);

// Filter ONLY Board Game projects
$boardGameProjects = array_values(array_filter($allProjects, fn($p) => $p->isBoardGame()));

// Active project selection
$activeProjectId = null;
if (isset($_GET['project_id']) && $_GET['project_id'] !== '') {
    $activeProjectId = (int)$_GET['project_id'];
    $_SESSION['last_project_id'] = $activeProjectId;
} else {
    // When visiting the studio root without project_id, stay on project selection cards screen
    unset($_SESSION['last_project_id']);
}

$activeProject = null;
if ($activeProjectId) {
    $activeProject = $projectService->getProjectById($activeProjectId, $currentUserId);
    if (!$activeProject || !$activeProject->isBoardGame()) {
        $activeProjectId = null;
        $activeProject = null;
        unset($_SESSION['last_project_id']);
    }
}

// Handle Quick Creation of a Board Game Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_boardgame_project') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isPrivate = isset($_POST['is_private']) && $_POST['is_private'] === '1';

        if ($name === '') {
            $error = 'Project name cannot be empty.';
        } else {
            try {
                $newProject = $projectService->createProject($name, $description, $isPrivate, $currentUserId, true, 'boardgame');
                header("Location: index.php?project_id=" . $newProject->getId());
                exit;
            } catch (ValidationException $e) {
                $error = $e->getMessage();
            } catch (\Exception $e) {
                $error = 'Failed to create board game: ' . $e->getMessage();
            }
        }
    }
}

// Handle Template Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_template') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $name = $_POST['name'] ?? '';
        $compTypeId = isset($_POST['component_type_id']) ? (int)$_POST['component_type_id'] : 0;
        $bleedMm = isset($_POST['bleed_mm']) ? (float)$_POST['bleed_mm'] : 3.0;
        $safeMarginMm = isset($_POST['safe_margin_mm']) ? (float)$_POST['safe_margin_mm'] : 5.0;
        $datasetId = (isset($_POST['dataset_id']) && $_POST['dataset_id'] !== '') ? (int)$_POST['dataset_id'] : null;
        $customWidthMm = isset($_POST['custom_width_mm']) ? (float)$_POST['custom_width_mm'] : null;
        $customHeightMm = isset($_POST['custom_height_mm']) ? (float)$_POST['custom_height_mm'] : null;
        $orientation = isset($_POST['orientation']) && $_POST['orientation'] === 'landscape' ? 'landscape' : 'portrait';
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);

        $boxParams = [
            'box_type'            => $_POST['box_type'] ?? 'double_wall_tray',
            'box_part'            => $_POST['box_part'] ?? 'base',
            'box_dim_mode'        => $_POST['box_dim_mode'] ?? 'usable_cavity',
            'box_width_mm'        => isset($_POST['box_width_mm']) ? (float)$_POST['box_width_mm'] : 120.0,
            'box_length_mm'       => isset($_POST['box_length_mm']) ? (float)$_POST['box_length_mm'] : 160.0,
            'box_height_mm'       => isset($_POST['box_height_mm']) ? (float)$_POST['box_height_mm'] : 40.0,
            'box_stock_mm'        => isset($_POST['box_stock_mm']) ? (float)$_POST['box_stock_mm'] : 0.6,
            'box_lid_height_mode' => $_POST['box_lid_height_mode'] ?? 'full_coverage',
            'box_clearance_mm'    => isset($_POST['box_clearance_mm']) && $_POST['box_clearance_mm'] !== '' ? (float)$_POST['box_clearance_mm'] : null,
            'box_show_labels'     => isset($_POST['box_show_labels']) && $_POST['box_show_labels'] === '1',
            'box_fill_style'      => $_POST['box_fill_style'] ?? 'stencil',
        ];

        try {
            if (!$activeProjectId) {
                throw new ValidationException("No active project selected.");
            }
            $newTemplate = $templateService->createTemplate(
                $activeProjectId,
                $compTypeId,
                $name,
                $bleedMm,
                $safeMarginMm,
                $datasetId,
                $currentUserId,
                $customWidthMm,
                $customHeightMm,
                $orientation,
                $boxParams
            );
            $selectedCompType = $templateService->getComponentTypeById($compTypeId);
            $createdBoxPair = $selectedCompType !== null
                && str_contains($selectedCompType->getName(), 'Board Game Box')
                && ($boxParams['box_part'] ?? '') === 'pair'
                && ($boxParams['box_type'] ?? '') !== 'tuck_top_box';
            if ($createdBoxPair) {
                $trimmedBaseName = trim($name);
                $success = "Created both box templates: \"{$trimmedBaseName} (Bottom Base)\" and \"{$trimmedBaseName} (Top Lid)\".";
            } else {
                header("Location: editor.php?id=" . $newTemplate->getId());
                exit;
            }
        } catch (ValidationException $e) {
            $error = $e->getMessage();
        } catch (\Exception $e) {
            error_log('[BoardGameStudio] create_template error: ' . $e->getMessage());
            $error = 'An unexpected error occurred creating the template. Please try again.';
        }
    }
}

// Handle Template Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_template') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $templateId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
        try {
            $templateService->deleteTemplate($templateId);
            $success = 'Template deleted successfully.';
        } catch (\Exception $e) {
            $error = 'Failed to delete template: ' . $e->getMessage();
        }
    }
}

// Handle Template Duplication
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'duplicate_template') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $templateId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
        $newName = $_POST['new_name'] ?? '';
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        try {
            $newTemplate = $templateService->cloneTemplate($templateId, $newName, $currentUserId);
            header("Location: editor.php?id=" . $newTemplate->getId());
            exit;
        } catch (ValidationException $e) {
            $error = $e->getMessage();
        } catch (\Exception $e) {
            error_log('[BoardGameStudio] duplicate_template error: ' . $e->getMessage());
            $error = 'An unexpected error occurred duplicating the template. Please try again.';
        }
    }
}

// Handle Template Renaming
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rename_template') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $templateId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
        $newName = trim($_POST['name'] ?? '');
        try {
            $template = $templateService->getTemplateById($templateId);
            if ($template) {
                $templateService->updateTemplate(
                    $templateId,
                    $newName,
                    $template->getBleedMm(),
                    $template->getSafeMarginMm(),
                    $template->getDatasetId()
                );
                $success = 'Template renamed successfully.';
            }
        } catch (ValidationException $e) {
            $error = $e->getMessage();
        } catch (\Exception $e) {
            $error = 'Failed to rename template: ' . $e->getMessage();
        }
    }
}

// Handle Template Dataset Binding Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_template_dataset') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $templateId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
        $datasetId = (isset($_POST['dataset_id']) && $_POST['dataset_id'] !== '' && $_POST['dataset_id'] !== 'null' && $_POST['dataset_id'] !== '0') ? (int)$_POST['dataset_id'] : null;
        try {
            $template = $templateService->getTemplateById($templateId);
            if ($template) {
                $templateService->updateTemplate(
                    $templateId,
                    $template->getName(),
                    $template->getBleedMm(),
                    $template->getSafeMarginMm(),
                    $datasetId
                );
                $success = 'Dataset binding updated successfully.';
            }
        } catch (\Exception $e) {
            $error = 'Failed to update dataset binding: ' . $e->getMessage();
        }
    }
}

// Data fetching depending on whether a project is selected
$templates = [];
$assetsCount = 0;
$datasets = [];
$compTypes = [];
$projectCounts = [];

if ($activeProjectId) {
    // Inside a specific Board Game Project workspace
    $templates = $templateService->getTemplatesByProject($activeProjectId);
    $assetsCount = count($assetService->getAssetsByProject($activeProjectId));
    $datasets = $datasetService->getDatasetsByProject($activeProjectId);
    $compTypes = $templateService->getComponentTypes();
    
    // Sort component presets logically
    usort($compTypes, function($a, $b) {
        $logicalOrder = [
            'Poker Card' => 1,
            'Tarot Card' => 2,
            'Board Game Box (Unfolded Die-Line)' => 3,
            'Game Board (Medium Square)' => 4,
            'Game Board (Square)' => 5,
            'Game Board (Rectangular)' => 6,
            'Player Board (A5 Landscape)' => 7,
            'Player Board (A4 Landscape)' => 8,
            'Punchboard' => 9,
            'Custom' => 10,
            'Japanese Business Card (A-one F10A4-1)' => 11,
            'Japanese ID Card / Name Tag (A-one 51215)' => 12
        ];
        $aOrder = $logicalOrder[$a->getName()] ?? 99;
        $bOrder = $logicalOrder[$b->getName()] ?? 99;
        return $aOrder <=> $bOrder;
    });
} else {
    // On the Board Game Projects Selection Page: fetch module counts for all board games
    $db = $container->get(PDO::class);
    foreach ($boardGameProjects as $bgp) {
        $projectCounts[$bgp->getId()] = [
            'templates' => 0,
            'assets'    => 0,
            'datasets'  => 0,
            'rulebooks' => 0,
        ];
    }

    if (!empty($boardGameProjects)) {
        $pids = array_map(fn($p) => $p->getId(), $boardGameProjects);
        $inClause = implode(',', $pids);

        try {
            $tRows = $db->query("SELECT project_id, COUNT(*) as cnt FROM `bg_templates` WHERE project_id IN ($inClause) GROUP BY project_id")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($tRows as $r) {
                $projectCounts[(int)$r['project_id']]['templates'] = (int)$r['cnt'];
            }

            $aRows = $db->query("SELECT project_id, COUNT(*) as cnt FROM `bg_assets` WHERE project_id IN ($inClause) GROUP BY project_id")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($aRows as $r) {
                $projectCounts[(int)$r['project_id']]['assets'] = (int)$r['cnt'];
            }

            $dRows = $db->query("SELECT project_id, COUNT(*) as cnt FROM `bg_datasets` WHERE project_id IN ($inClause) GROUP BY project_id")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($dRows as $r) {
                $projectCounts[(int)$r['project_id']]['datasets'] = (int)$r['cnt'];
            }

            $rRows = $db->query("SELECT project_id, COUNT(*) as cnt FROM `bg_rulebooks` WHERE project_id IN ($inClause) GROUP BY project_id")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rRows as $r) {
                $projectCounts[(int)$r['project_id']]['rulebooks'] = (int)$r['cnt'];
            }
        } catch (\Exception $e) {
            error_log('[BoardGameStudio] Count query error: ' . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/../templates/header.php';

// Prepare Sticky Page Header
if ($activeProject) {
    renderPageHeader($activeProject->getName(), 'Components', '', 'index.php');
} else {
    ob_start();
    ?>
    <div class="flex items-center gap-2">
        <button type="button" onclick="toggleCollapsible('new-boardgame-panel')" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow-sm transition">
            <i class="bi bi-plus-lg"></i>
            <span>New Board Game</span>
        </button>
        <a href="assets.php?project_id=" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium text-xs rounded-lg border border-slate-700 transition" title="Manage Global Assets">
            <i class="bi bi-images"></i>
            <span class="hidden sm:inline">Global Assets</span>
        </a>
    </div>
    <?php
    $actionsHtml = ob_get_clean();
    renderPageHeader('Game Studio', count($boardGameProjects) . ' Games', $actionsHtml);
}
?>

<div class="page-container">

    <?php if (!empty($error)): ?>
        <div class="p-4 mb-6 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-xl text-sm flex items-center justify-between">
            <span><?php echo SecurityHelper::escape($error); ?></span>
            <button type="button" onclick="this.parentElement.remove()" class="text-rose-400 hover:text-rose-200 font-bold ml-2">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="p-4 mb-6 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-xl text-sm flex items-center justify-between">
            <span><?php echo SecurityHelper::escape($success); ?></span>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-emerald-200 font-bold ml-2">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (!$activeProject): ?>
        <!-- ============================================================== -->
        <!-- VIEW 1: BOARD GAME PROJECTS CARDS VIEW                          -->
        <!-- ============================================================== -->

        <!-- Collapsible Create Board Game Form Panel -->
        <div id="new-boardgame-panel" class="form-panel-collapsible mb-6">
            <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-800">
                <div>
                    <h2 class="text-base font-bold text-slate-100 flex items-center gap-2">
                        <span>🎲</span>
                        <span>Create New Board Game Project</span>
                    </h2>
                    <p class="text-xs text-slate-400">Add a new board game to start designing card sheets, tokens, boards, and rulebooks.</p>
                </div>
                <button type="button" onclick="toggleCollapsible('new-boardgame-panel')" class="text-slate-400 hover:text-slate-200 text-sm font-semibold p-1">
                    &times; Close
                </button>
            </div>

            <form action="index.php" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                <input type="hidden" name="action" value="create_boardgame_project">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="bg_name" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Game Title *</label>
                        <input type="text" id="bg_name" name="name" required
                            placeholder="e.g., Mystic Realms, Galaxy Express, Royal Duel"
                            class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Privacy Setting</label>
                        <label class="flex items-center gap-2.5 p-2 bg-slate-950/40 border border-slate-800 rounded-lg hover:border-slate-700 transition cursor-pointer select-none">
                            <input type="checkbox" id="bg_is_private" name="is_private" value="1"
                                class="rounded bg-slate-900 border-slate-700 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                            <span class="text-xs font-medium text-slate-200 flex items-center gap-1.5">
                                <span>🔒</span> Private Game (Only visible to you)
                            </span>
                        </label>
                    </div>
                </div>

                <div>
                    <label for="bg_description" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Game Concept & Description (Optional)</label>
                    <textarea id="bg_description" name="description" rows="2"
                        placeholder="Theme, target player count, core mechanics, or component summary..."
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800/80">
                    <button type="button" onclick="toggleCollapsible('new-boardgame-panel')" class="px-4 py-2 rounded-lg text-xs font-medium text-slate-400 hover:text-slate-200 transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-lg text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm transition">
                        + Create & Enter Studio
                    </button>
                </div>
            </form>
        </div>

        <?php if (empty($boardGameProjects)): ?>
            <!-- Empty State: No Board Game Projects -->
            <div class="p-12 text-center bg-slate-900/40 border border-slate-800/80 rounded-2xl max-w-xl mx-auto my-8 space-y-5">
                <div class="w-14 h-14 rounded-2xl bg-purple-500/10 text-purple-400 border border-purple-500/20 flex items-center justify-center text-3xl mx-auto">
                    🎲
                </div>
                <div class="space-y-1.5 text-center">
                    <h2 class="text-lg font-bold text-slate-200">No Board Game Projects Found</h2>
                    <p class="text-xs text-slate-400 max-w-md mx-auto leading-relaxed">
                        To access the studio tools (Components, Assets, Datasets, Rulebooks, and Export sheets), create a board game project or classify an existing project as a 'Board Game'.
                    </p>
                </div>
                <div class="flex items-center justify-center gap-3 pt-2">
                    <button type="button" onclick="toggleCollapsible('new-boardgame-panel')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg transition shadow-sm">
                        + Create First Board Game
                    </button>
                    <a href="../projects.php" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium rounded-lg border border-slate-700 transition">
                        Manage Projects
                    </a>
                </div>
            </div>
        <?php else: ?>
            <style>
                .game-metric-btn {
                    transition: background-color 0.15s ease, transform 0.1s ease;
                }
                .game-metric-btn:hover {
                    background-color: #4f46e5 !important; /* Vibrant Indigo */
                }
                .game-metric-btn:hover .stat-count {
                    color: #ffffff !important;
                }
                .game-metric-btn:hover .stat-label {
                    color: #e0e7ff !important;
                }
                .game-metric-btn:active {
                    transform: scale(0.95);
                }
            </style>
            <!-- Grid of Board Game Cards (Compact, Balanced Layout) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3">
                <?php foreach ($boardGameProjects as $bgp): ?>
                    <?php
                    $pid = $bgp->getId();
                    $counts = $projectCounts[$pid] ?? ['templates' => 0, 'assets' => 0, 'datasets' => 0, 'rulebooks' => 0];
                    $desc = trim((string)$bgp->getDescription());
                    ?>
                    <div class="bg-slate-900/70 border border-slate-800 rounded-xl p-3 flex flex-col justify-between shadow-sm backdrop-blur-sm">
                        <div>
                            <!-- Card Header: Icon + Title + Privacy Badge -->
                            <div class="flex items-center justify-between gap-2 mb-1.5">
                                <div class="flex items-center gap-2 min-w-0">
                                    <div class="w-7 h-7 rounded-lg bg-indigo-500/15 border border-indigo-500/25 text-indigo-400 flex items-center justify-center text-sm flex-shrink-0">
                                        🎲
                                    </div>
                                    <div class="min-w-0">
                                        <a href="index.php?project_id=<?php echo $pid; ?>" class="text-xs sm:text-sm font-bold text-slate-100 hover:text-indigo-300 transition truncate block" title="<?php echo SecurityHelper::escape($bgp->getName()); ?>">
                                            <?php echo SecurityHelper::escape($bgp->getName()); ?>
                                        </a>
                                    </div>
                                </div>
                                <div class="flex-shrink-0">
                                    <?php if ($bgp->isPrivate()): ?>
                                        <span class="status-pill status-pill--private text-[9px] px-1.5 py-0.5">🔒 Private</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Description (single-line aligned slot to avoid uneven vertical gaps) -->
                            <p class="text-[11px] text-slate-400 line-clamp-1 mb-2 leading-relaxed min-h-[16px]" title="<?php echo SecurityHelper::escape($desc); ?>">
                                <?php echo $desc !== '' ? SecurityHelper::escape($desc) : '<span class="text-slate-600 italic">No description</span>'; ?>
                            </p>

                            <!-- Interactive Module Metric Segmented Strip (Cards, Assets, Data, Rules Hover Effects) -->
                            <div class="grid grid-cols-4 bg-slate-950/60 border border-slate-800/80 rounded-lg divide-x divide-slate-800/80 mb-2.5 overflow-hidden">
                                <a href="index.php?project_id=<?php echo $pid; ?>" class="game-metric-btn py-1.5 px-1 text-center block cursor-pointer" title="Cards & Components">
                                    <div class="stat-count text-xs font-bold text-slate-200 leading-tight transition-colors"><?php echo $counts['templates']; ?></div>
                                    <div class="stat-label text-[9px] text-slate-400 font-medium leading-tight mt-0.5 transition-colors">Cards</div>
                                </a>
                                <a href="assets.php?project_id=<?php echo $pid; ?>" class="game-metric-btn py-1.5 px-1 text-center block cursor-pointer" title="Assets">
                                    <div class="stat-count text-xs font-bold text-slate-200 leading-tight transition-colors"><?php echo $counts['assets']; ?></div>
                                    <div class="stat-label text-[9px] text-slate-400 font-medium leading-tight mt-0.5 transition-colors">Assets</div>
                                </a>
                                <a href="datasets.php?project_id=<?php echo $pid; ?>" class="game-metric-btn py-1.5 px-1 text-center block cursor-pointer" title="Datasets">
                                    <div class="stat-count text-xs font-bold text-slate-200 leading-tight transition-colors"><?php echo $counts['datasets']; ?></div>
                                    <div class="stat-label text-[9px] text-slate-400 font-medium leading-tight mt-0.5 transition-colors">Data</div>
                                </a>
                                <a href="rulebooks.php?project_id=<?php echo $pid; ?>" class="game-metric-btn py-1.5 px-1 text-center block cursor-pointer" title="Rulebooks">
                                    <div class="stat-count text-xs font-bold text-slate-200 leading-tight transition-colors"><?php echo $counts['rulebooks']; ?></div>
                                    <div class="stat-label text-[9px] text-slate-400 font-medium leading-tight mt-0.5 transition-colors">Rules</div>
                                </a>
                            </div>
                        </div>

                        <!-- Card Footer: Quick Export Action + Open Studio -->
                        <div class="flex items-center justify-between pt-2 border-t border-slate-800/70 text-xs">
                            <a href="export.php?project_id=<?php echo $pid; ?>" class="inline-flex items-center gap-1 text-[11px] text-slate-400 hover:text-slate-200 transition py-0.5 px-1 rounded hover:bg-slate-800/60" title="Export Print Sheets">
                                <i class="bi bi-printer text-slate-400"></i>
                                <span>Export</span>
                            </a>
                            <a href="index.php?project_id=<?php echo $pid; ?>" class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-400 hover:text-indigo-300 transition py-0.5 px-2 rounded-md bg-indigo-500/10 hover:bg-indigo-500/20 border border-indigo-500/20 hover:border-indigo-500/40">
                                <span>Studio</span>
                                <i class="bi bi-arrow-right text-[10px]"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- ============================================================== -->
        <!-- VIEW 2: ACTIVE PROJECT COMPONENTS WORKSPACE                    -->
        <!-- ============================================================== -->

        <?php
        $activeTab = 'components';
        include __DIR__ . '/views/studio-nav.php';
        include __DIR__ . '/views/create-template-sidebar.php';
        include __DIR__ . '/views/template-grid.php';
        ?>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
