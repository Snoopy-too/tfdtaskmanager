<?php
declare(strict_types=1);

$container = require_once __DIR__ . '/../src/bootstrap.php';

// Auto-migration for template lock columns
try {
    $db = $container->get(PDO::class);
    $check = $db->query("SHOW COLUMNS FROM `bg_templates` LIKE 'locked_by_user_id'")->fetchAll();
    if (empty($check)) {
        $db->exec("ALTER TABLE `bg_templates` ADD COLUMN `locked_by_user_id` INT DEFAULT NULL AFTER `created_by`");
        $db->exec("ALTER TABLE `bg_templates` ADD COLUMN `locked_at` TIMESTAMP NULL DEFAULT NULL AFTER `locked_by_user_id`");
        $db->exec("ALTER TABLE `bg_templates` ADD CONSTRAINT `fk_bg_templates_locked_user` FOREIGN KEY (`locked_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL");
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

// Fetch all projects to let user choose
$projects = $projectService->getAllProjects();

// Select active project and synchronize with session storage
$activeProjectId = null;
if (isset($_GET['project_id'])) {
    if ($_GET['project_id'] !== '') {
        $activeProjectId = (int)$_GET['project_id'];
        $_SESSION['last_project_id'] = $activeProjectId;
    } else {
        // User explicitly cleared project selection (selected "None")
        unset($_SESSION['last_project_id']);
    }
} else {
    // If no project_id parameter in URL, default to last worked project from session
    if (isset($_SESSION['last_project_id'])) {
        $activeProjectId = (int)$_SESSION['last_project_id'];
        header("Location: index.php?project_id=" . $activeProjectId);
        exit;
    }
}

$activeProject = null;
if ($activeProjectId) {
    $activeProject = $projectService->getProjectById($activeProjectId);
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
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);

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
                $customHeightMm
            );
            header("Location: editor.php?id=" . $newTemplate->getId());
            exit;
        } catch (ValidationException $e) {
            $error = $e->getMessage();
        } catch (\Exception $e) {
            // Log internally; never expose internal exceptions or stack traces to the UI
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

// Fetch list of templates, assets, datasets, component types for active project
$templates = [];
$assetsCount = 0;
$datasets = [];
$compTypes = [];

if ($activeProjectId) {
    $templates = $templateService->getTemplatesByProject($activeProjectId);
    $assetsCount = count($assetService->getAssetsByProject($activeProjectId));
    $datasets = $datasetService->getDatasetsByProject($activeProjectId);
    $compTypes = $templateService->getComponentTypes();
    
    // Sort logically for the best user experience
    usort($compTypes, function($a, $b) {
        $logicalOrder = [
            'Poker Card' => 1,
            'Tarot Card' => 2,
            'Game Board (Medium Square)' => 3,
            'Game Board (Square)' => 4,
            'Game Board (Rectangular)' => 5,
            'Player Board (A5 Landscape)' => 6,
            'Player Board (A4 Landscape)' => 7,
            'Punchboard' => 8,
            'Custom' => 9
        ];
        $aOrder = $logicalOrder[$a->getName()] ?? 99;
        $bOrder = $logicalOrder[$b->getName()] ?? 99;
        return $aOrder <=> $bOrder;
    });
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="space-y-8">
    <!-- Top Bar with Project Select -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-white bg-gradient-to-r from-indigo-400 to-violet-400 bg-clip-text text-transparent">
                Board Game Design Studio
            </h1>
            <p class="text-slate-400 mt-1">Prototype board game components, manage print sheets, and bind card datasets.</p>
        </div>
        
        <?php if (!empty($projects)): ?>
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
        <?php endif; ?>
    </div>

    <?php if (empty($projects)): ?>
        <div class="p-8 text-center bg-slate-900/50 border border-slate-800 rounded-2xl max-w-lg mx-auto">
            <svg class="mx-auto h-12 w-12 text-slate-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <h2 class="text-lg font-bold text-slate-200">No Projects Found</h2>
            <p class="text-sm text-slate-400 mt-2 mb-6">You must create at least one project in the main task manager before using the Board Game Studio.</p>
            <a href="../projects.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg shadow-lg hover:shadow-indigo-500/20 transition duration-200">
                Go to Projects
            </a>
        </div>
    <?php else: ?>
        <?php if (!$activeProjectId): ?>
            <div class="p-12 text-center bg-slate-900/40 border border-slate-800/80 rounded-2xl max-w-2xl mx-auto my-8 space-y-6">
                <div class="inline-flex p-4 bg-indigo-500/10 rounded-2xl text-indigo-400 mx-auto justify-center">
                    <svg class="h-10 w-10 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
                <div class="space-y-2 text-center">
                    <h2 class="text-xl font-bold text-slate-200">Welcome to the Board Game Design Studio!</h2>
                    <p class="text-slate-400 max-w-md mx-auto text-sm">
                        Please select a project from the dropdown menu in the top right to start prototyping components, or click the button below to manage system-wide global assets.
                    </p>
                    <div class="pt-4">
                        <a href="assets.php" class="inline-flex items-center px-4 py-2 border border-slate-700 hover:border-indigo-500/50 hover:bg-slate-800 text-sm font-semibold rounded-xl text-slate-300 hover:text-white transition duration-200">
                            <span>Manage Global Assets</span>
                            <svg class="h-4 w-4 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
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

        <!-- Quick Stats / Navigation Links -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Asset Library Card -->
            <div class="bg-slate-900/40 border border-slate-800 p-6 rounded-2xl flex flex-col justify-between hover:border-slate-700 transition group">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-lg font-bold text-slate-200 group-hover:text-indigo-400 transition">Project Asset Library</h3>
                        <p class="text-xs text-slate-400 mt-1">Images, icon tokens, and custom print fonts.</p>
                    </div>
                    <span class="text-2xl font-black text-indigo-400/80 bg-indigo-500/10 px-3 py-1 rounded-lg">
                        <?php echo $assetsCount; ?>
                    </span>
                </div>
                <div class="mt-6">
                    <a href="assets.php?project_id=<?php echo $activeProjectId; ?>" class="text-sm font-semibold text-slate-300 hover:text-white inline-flex items-center space-x-1">
                        <span>Manage Assets</span>
                        <svg class="h-4 w-4 transform group-hover:translate-x-1 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>

            <!-- Datasets Card -->
            <div class="bg-slate-900/40 border border-slate-800 p-6 rounded-2xl flex flex-col justify-between hover:border-slate-700 transition group">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-lg font-bold text-slate-200 group-hover:text-violet-400 transition">Project Datasets</h3>
                        <p class="text-xs text-slate-400 mt-1">Bind spreadsheets or build grids to generate dynamic decks.</p>
                    </div>
                    <span class="text-2xl font-black text-violet-400/80 bg-violet-500/10 px-3 py-1 rounded-lg">
                        <?php echo count($datasets); ?>
                    </span>
                </div>
                <div class="mt-6">
                    <a href="datasets.php?project_id=<?php echo $activeProjectId; ?>" class="text-sm font-semibold text-slate-300 hover:text-white inline-flex items-center space-x-1">
                        <span>Manage Datasets</span>
                        <svg class="h-4 w-4 transform group-hover:translate-x-1 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>

            <!-- Export Studio Card -->
            <div class="bg-slate-900/40 border border-slate-800 p-6 rounded-2xl flex flex-col justify-between hover:border-slate-700 transition group">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-lg font-bold text-slate-200 group-hover:text-emerald-400 transition">Print & Export Studio</h3>
                        <p class="text-xs text-slate-400 mt-1">Generate PDF sheets with crop marks and TTS assets.</p>
                    </div>
                    <svg class="h-8 w-8 text-emerald-400/60 bg-emerald-500/10 p-1.5 rounded-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div class="mt-6">
                    <a href="export.php?project_id=<?php echo $activeProjectId; ?>" class="text-sm font-semibold text-slate-300 hover:text-white inline-flex items-center space-x-1">
                        <span>Generate Exports</span>
                        <svg class="h-4 w-4 transform group-hover:translate-x-1 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Templates List -->
            <div class="lg:col-span-2 space-y-4">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-bold text-slate-200">Design Templates</h2>
                    <span class="text-xs text-slate-400 bg-slate-800/80 px-2.5 py-1 rounded-full font-medium">
                        <?php echo count($templates); ?> Templates
                    </span>
                </div>

                <?php if (empty($templates)): ?>
                    <div class="p-12 text-center bg-slate-900/30 border border-dashed border-slate-800 rounded-2xl">
                        <svg class="mx-auto h-10 w-10 text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                        </svg>
                        <h4 class="text-sm font-semibold text-slate-300">No Templates Created</h4>
                        <p class="text-xs text-slate-500 mt-1 max-w-xs mx-auto">Create a Poker Card or Tarot Card template on the right to start designing.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($templates as $tmpl): ?>
                            <?php 
                            $cType = null;
                            foreach ($compTypes as $ct) {
                                if ($ct->getId() === $tmpl->getComponentTypeId()) {
                                    $cType = $ct;
                                    break;
                                }
                            }
                            $isLocked = $templateService->isTemplateLockedByOther($tmpl, $currentUserId);
                            $lockUser = null;
                            if ($isLocked) {
                                $userService = $container->get(\App\Application\Services\UserService::class);
                                $lockUser = $userService->getUserById($tmpl->getLockedByUserId());
                            }
                            ?>
                            <div class="bg-slate-900 border border-slate-800/80 p-5 rounded-2xl flex flex-col justify-between hover:border-slate-700/80 hover:shadow-lg transition">
                                <div class="space-y-1">
                                    <div class="flex justify-between items-start">
                                        <div class="space-y-1 min-w-0 flex-grow">
                                            <h3 class="font-bold text-slate-200 truncate pr-2"><?php echo SecurityHelper::escape($tmpl->getName()); ?></h3>
                                            <?php if ($isLocked): ?>
                                                <div class="flex items-center space-x-1 text-[10px] text-rose-400 font-semibold bg-rose-500/10 px-2 py-0.5 rounded w-fit">
                                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                                    <span>Locked by <?php echo SecurityHelper::escape($lockUser ? $lockUser->getName() : 'Other User'); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 h-fit flex-shrink-0">
                                            <?php echo $cType ? SecurityHelper::escape($cType->getName()) : 'Unknown'; ?>
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-400">
                                        Dimensions: <?php echo round(\App\Domain\Entities\BgTemplate::pxToMm($tmpl->getCanvasWidthPx(), 300), 1); ?>x<?php echo round(\App\Domain\Entities\BgTemplate::pxToMm($tmpl->getCanvasHeightPx(), 300), 1); ?>mm (<?php echo $tmpl->getCanvasWidthPx(); ?>x<?php echo $tmpl->getCanvasHeightPx(); ?>px)
                                    </p>
                                    <?php if ($tmpl->getDatasetId()): ?>
                                        <div class="flex items-center space-x-1.5 mt-2">
                                            <span class="w-1.5 h-1.5 rounded-full bg-violet-400"></span>
                                            <span class="text-[11px] text-violet-400 font-medium">Bound to Dataset</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex items-center justify-between mt-6 pt-3 border-t border-slate-800/60 gap-2">
                                    <?php if ($isLocked): ?>
                                        <a href="editor.php?id=<?php echo $tmpl->getId(); ?>" class="text-xs font-semibold text-slate-300 hover:text-white flex items-center space-x-1.5 bg-slate-800/50 hover:bg-slate-800 px-3 py-1.5 rounded-lg border border-slate-700 transition">
                                            <svg class="h-3.5 w-3.5 text-slate-450" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            <span>View Design</span>
                                        </a>
                                    <?php else: ?>
                                        <a href="editor.php?id=<?php echo $tmpl->getId(); ?>" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 flex items-center space-x-1.5 bg-indigo-500/5 hover:bg-indigo-500/10 px-3 py-1.5 rounded-lg border border-indigo-500/20 transition">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                            <span>Open Editor</span>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <div class="flex items-center space-x-2">
                                        <form action="" method="POST" class="m-0" onsubmit="return duplicateTemplate(<?php echo $tmpl->getId(); ?>, '<?php echo SecurityHelper::escape(addslashes($tmpl->getName())); ?>', this);">
                                            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                            <input type="hidden" name="action" value="duplicate_template">
                                            <input type="hidden" name="template_id" value="<?php echo $tmpl->getId(); ?>">
                                            <input type="hidden" name="new_name" id="dup_name_<?php echo $tmpl->getId(); ?>" value="">
                                            <button type="submit" class="text-xs text-indigo-400 hover:text-indigo-300 transition p-1 rounded hover:bg-indigo-500/10 border border-transparent hover:border-indigo-500/10" title="Duplicate Template">
                                                Copy
                                            </button>
                                        </form>

                                        <?php if (!$isLocked): ?>
                                            <form action="" method="POST" class="m-0" onsubmit="return showCustomConfirm('Are you sure you want to delete this template?', this);">
                                                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                                <input type="hidden" name="action" value="delete_template">
                                                <input type="hidden" name="template_id" value="<?php echo $tmpl->getId(); ?>">
                                                <button type="submit" class="text-xs text-rose-500 hover:text-rose-400 transition p-1 rounded hover:bg-rose-500/10 border border-transparent hover:border-rose-500/10">
                                                    Delete
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Create Template Sidebar Form -->
            <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl h-fit">
                <h2 class="text-xl font-bold text-slate-200 mb-4">New Design Template</h2>
                <form action="" method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                    <input type="hidden" name="action" value="create_template">

                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-300 mb-1">Template Name</label>
                        <input type="text" id="name" name="name" required placeholder="e.g. Card Front Layout" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 p-2.5">
                    </div>

                    <div>
                        <label for="component_type_id" class="block text-sm font-medium text-slate-300 mb-1">Component Type</label>
                        <select id="component_type_id" name="component_type_id" required onchange="toggleCustomDimensions(this)" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 p-2.5">
                            <?php foreach ($compTypes as $type): ?>
                                <option value="<?php echo $type->getId(); ?>" data-is-custom="<?php echo $type->getName() === 'Custom' ? '1' : '0'; ?>">
                                    <?php echo SecurityHelper::escape($type->getName()); ?> <?php echo $type->getName() !== 'Custom' ? "({$type->getWidthMm()}x{$type->getHeightMm()}mm)" : ""; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="custom_dimensions" class="grid grid-cols-2 gap-4 hidden">
                        <div>
                            <label for="custom_width_mm" class="block text-sm font-medium text-slate-300 mb-1">Custom Width (mm)</label>
                            <input type="number" id="custom_width_mm" name="custom_width_mm" min="10" max="1000" step="1" value="63" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 p-2.5">
                        </div>
                        <div>
                            <label for="custom_height_mm" class="block text-sm font-medium text-slate-300 mb-1">Custom Height (mm)</label>
                            <input type="number" id="custom_height_mm" name="custom_height_mm" min="10" max="1000" step="1" value="88" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 p-2.5">
                        </div>
                    </div>

                    <script>
                        function toggleCustomDimensions(select) {
                            const selectedOption = select.options[select.selectedIndex];
                            const isCustom = selectedOption.getAttribute('data-is-custom') === '1';
                            document.getElementById('custom_dimensions').style.display = isCustom ? 'grid' : 'none';
                        }
                        // Trigger on load in case Custom is default
                        document.addEventListener('DOMContentLoaded', () => toggleCustomDimensions(document.getElementById('component_type_id')));

                        function duplicateTemplate(templateId, originalName, form) {
                            const newName = prompt("Enter a name for the duplicated template:", originalName + " (Copy)");
                            if (newName === null) {
                                return false;
                            }
                            const trimmed = newName.trim();
                            if (trimmed === "") {
                                alert("Template name cannot be empty.");
                                return false;
                            }
                            document.getElementById("dup_name_" + templateId).value = trimmed;
                            return true;
                        }
                    </script>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="bleed_mm" class="block text-sm font-medium text-slate-300 mb-1">Bleed Edge (mm)</label>
                            <input type="number" id="bleed_mm" name="bleed_mm" min="0" max="20" step="0.1" value="3.0" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 p-2.5">
                        </div>
                        <div>
                            <label for="safe_margin_mm" class="block text-sm font-medium text-slate-300 mb-1">Safe Margin (mm)</label>
                            <input type="number" id="safe_margin_mm" name="safe_margin_mm" min="0" max="30" step="0.1" value="5.0" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 p-2.5">
                        </div>
                    </div>

                    <div>
                        <label for="dataset_id" class="block text-sm font-medium text-slate-300 mb-1">Dataset Binding (Optional)</label>
                        <select id="dataset_id" name="dataset_id" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 p-2.5">
                            <option value="">No Dataset Bound</option>
                            <?php foreach ($datasets as $data): ?>
                                <option value="<?php echo $data->getId(); ?>">
                                    <?php echo SecurityHelper::escape($data->getName()); ?> (<?php echo count($data->getRowData()); ?> rows)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-xl shadow-lg hover:shadow-indigo-500/20 py-2.5 px-4 transition duration-200">
                        Create & Design
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
