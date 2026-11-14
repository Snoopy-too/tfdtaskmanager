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

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$error = '';
$success = '';
$csrfToken = SecurityHelper::generateCsrfToken();

// Projects dropdown - only board games
$projects = $projectService->getAllProjects($currentUserId);
$boardGameProjects = array_values(array_filter($projects, fn($p) => $p->isBoardGame()));

$activeProjectId = (isset($_GET['project_id']) && $_GET['project_id'] !== '') ? (int)$_GET['project_id'] : null;

if (!$activeProjectId && isset($_SESSION['last_project_id'])) {
    $candidateId = (int)$_SESSION['last_project_id'];
    $candProject = $projectService->getProjectById($candidateId, $currentUserId);
    if ($candProject && $candProject->isBoardGame()) {
        $activeProjectId = $candidateId;
    }
}

if (!$activeProjectId) {
    header("Location: index.php");
    exit;
}

$activeProject = $projectService->getProjectById($activeProjectId, $currentUserId);
if (!$activeProject || !$activeProject->isBoardGame()) {
    unset($_SESSION['last_project_id']);
    header("Location: index.php");
    exit;
}

$_SESSION['last_project_id'] = $activeProjectId;

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

if ($activeProject) {
    renderPageHeader($activeProject->getName(), 'Datasets', '', 'index.php');
} else {
    renderPageHeader('Datasets', 'Game Studio', '', 'index.php');
}
?>

<div class="page-container space-y-6">
    <?php
    $activeTab = 'datasets';
    include __DIR__ . '/views/studio-nav.php';
    ?>

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

    <?php include __DIR__ . '/views/datasets-create-panel.php'; ?>
    <?php include __DIR__ . '/views/datasets-preview.php'; ?>
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
            const msg = "This dataset has been locked by another user or your session expired. Entering read-only mode.";
            if (typeof window.studioAlert === 'function') {
                window.studioAlert(msg, "Dataset Locked").then(() => window.location.reload());
            } else {
                alert(msg);
                window.location.reload();
            }
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
