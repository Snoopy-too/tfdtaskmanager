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
        $orientation = isset($_POST['orientation']) && $_POST['orientation'] === 'landscape' ? 'landscape' : 'portrait';
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
                $customHeightMm,
                $orientation
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
                        <a href="assets.php?project_id=" class="inline-flex items-center px-4 py-2 border border-slate-700 hover:border-indigo-500/50 hover:bg-slate-800 text-sm font-semibold rounded-xl text-slate-300 hover:text-white transition duration-200">
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
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
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

            <!-- Rulebooks & Glossary Card -->
            <div class="bg-slate-900/40 border border-slate-800 p-6 rounded-2xl flex flex-col justify-between hover:border-slate-700 transition group">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-lg font-bold text-slate-200 group-hover:text-amber-400 transition">Rulebooks & Glossary</h3>
                        <p class="text-xs text-slate-400 mt-1">Write rules with synchronized iconography and glossary terms.</p>
                    </div>
                    <svg class="h-8 w-8 text-amber-400/60 bg-amber-500/10 p-1.5 rounded-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                </div>
                <div class="mt-6">
                    <a href="rulebooks.php?project_id=<?php echo $activeProjectId; ?>" class="text-sm font-semibold text-slate-300 hover:text-white inline-flex items-center space-x-1">
                        <span>Manage Rulebooks</span>
                        <svg class="h-4 w-4 transform group-hover:translate-x-1 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <?php include __DIR__ . '/views/template-grid.php'; ?>


            <?php include __DIR__ . '/views/create-template-sidebar.php'; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
