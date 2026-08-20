<?php
declare(strict_types=1);

$container = require_once __DIR__ . '/../src/bootstrap.php';

use App\Infrastructure\Security\SecurityHelper;
use App\Application\Services\ProjectService;
use App\Application\Services\BgDatasetService;
use App\Application\Services\BgTemplateService;
use App\Application\Exceptions\ValidationException;

SecurityHelper::requireLogin();

$projectService = $container->get(ProjectService::class);
$datasetService = $container->get(BgDatasetService::class);
$templateService = $container->get(BgTemplateService::class);

$error = '';
$success = '';
$csrfToken = SecurityHelper::generateCsrfToken();

// Projects dropdown
$projects = $projectService->getAllProjects();
$activeProjectId = (isset($_GET['project_id']) && $_GET['project_id'] !== '') ? (int)$_GET['project_id'] : null;

// Default to last project from session if not specified, otherwise default to first project
if ($activeProjectId === null && !isset($_GET['project_id'])) {
    if (isset($_SESSION['last_project_id'])) {
        $activeProjectId = (int)$_SESSION['last_project_id'];
    } elseif (!empty($projects)) {
        $activeProjectId = $projects[0]->getId();
    }
}

if ($activeProjectId) {
    $_SESSION['last_project_id'] = $activeProjectId;
}

$activeProject = null;
if ($activeProjectId) {
    $activeProject = $projectService->getProjectById($activeProjectId);
}

if (!$activeProject) {
    header("Location: index.php");
    exit;
}

$allTemplates = $templateService->getTemplatesByProject($activeProjectId);

// Handle CSV Export Action
if (isset($_GET['action']) && $_GET['action'] === 'export_csv' && isset($_GET['inspect_id'])) {
    $dsId = (int)$_GET['inspect_id'];
    $ds = $datasetService->getDatasetById($dsId);
    if ($ds && $ds->getProjectId() === $activeProjectId) {
        $csvContent = $datasetService->generateCsvContent($ds);
        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $ds->getName()) . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo $csvContent;
        exit;
    }
}

// Global dataset locking check for modifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dataset_id'])) {
    $dsId = (int)$_POST['dataset_id'];
    $ds = $datasetService->getDatasetById($dsId);
    $currUid = (int)($_SESSION['user_id'] ?? 0);
    if ($ds && $datasetService->isDatasetLockedByOther($ds, $currUid)) {
        $error = "Action failed: This dataset is currently locked for editing by another user.";
        $_SERVER['REQUEST_METHOD'] = 'GET'; // Bypass mutation execution
    }
}
require_once __DIR__ . '/dataset-actions.php';


// Fetch all datasets in active project
$datasets = $datasetService->getDatasetsByProject($activeProjectId);

// Active dataset to inspect in detail
$inspectDatasetId = isset($_GET['inspect_id']) ? (int)$_GET['inspect_id'] : null;
$inspectDataset = null;
$lockUser = null;
$isDatasetLocked = false;
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

if ($inspectDatasetId) {
    $inspectDataset = $datasetService->getDatasetById($inspectDatasetId);
    if ($inspectDataset) {
        if ($datasetService->isDatasetLockedByOther($inspectDataset, $currentUserId)) {
            $isDatasetLocked = true;
            $userService = $container->get(\App\Application\Services\UserService::class);
            $lockUser = $userService->getUserById($inspectDataset->getLockedByUserId());
        } else {
            $datasetService->acquireOrRefreshLock($inspectDataset->getId(), $currentUserId);
        }
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="space-y-8">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-sm text-slate-400">
                <a href="index.php?project_id=<?php echo $activeProjectId; ?>" class="hover:text-white transition">Studio</a>
                <span>/</span>
                <span class="text-slate-200">Datasets</span>
            </div>
            <h1 class="text-3xl font-extrabold tracking-tight text-white mt-1">Imported Datasets</h1>
            <p class="text-slate-400 mt-1">Manage card datasets, dynamic variable bindings, and spreadsheet rows.</p>
        </div>

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
    </div>

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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <?php include __DIR__ . '/views/datasets-sidebar.php'; ?>

        <?php include __DIR__ . '/views/datasets-preview.php'; ?>
    </div>
</div>

<script>
    window.studioConfig = window.studioConfig || {};
    window.studioConfig.csrfToken = '<?php echo SecurityHelper::escape($csrfToken); ?>';
    window.studioConfig.projectId = <?php echo (int)($activeProjectId ?? 0); ?>;
    <?php if ($inspectDataset): ?>
    window.studioConfig.datasetId = <?php echo (int)$inspectDataset->getId(); ?>;
    <?php endif; ?>
</script>
<script src="js/dataset-builder.js"></script>
<?php if (isset($activeTab) && $activeTab === 'build'): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const tabBuildBtn = document.getElementById('tab-build');
        if (tabBuildBtn) tabBuildBtn.click();
    });
</script>
<?php endif; ?>

<script src="js/dataset-grid.js"></script>
<script>
<?php if ($inspectDataset && !$isDatasetLocked): ?>
// Heartbeat lock refresh
setInterval(() => {
    const formData = new FormData();
    formData.append('dataset_id', '<?php echo $inspectDataset->getId(); ?>');
    formData.append('csrf_token', '<?php echo SecurityHelper::escape($csrfToken); ?>');

    fetch('api.php?action=heartbeat_lock_dataset', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.locked) {
            alert("This dataset has been locked by another user or your session expired. Entering read-only mode.");
            window.location.reload();
        }
    })
    .catch(err => console.error('Lock heartbeat failed:', err));
}, 20000);

// Release lock on page unload
window.addEventListener('beforeunload', () => {
    const formData = new FormData();
    formData.append('dataset_id', '<?php echo $inspectDataset->getId(); ?>');
    formData.append('csrf_token', '<?php echo SecurityHelper::escape($csrfToken); ?>');
    navigator.sendBeacon('api.php?action=release_lock_dataset', formData);
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/views/datasets-modals.php'; ?>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
