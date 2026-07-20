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

// Handle CSV File Upload or Pasted CSV Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_dataset') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $csvText = $_POST['csv_text'] ?? '';
        $file = $_FILES['csv_file'] ?? null;
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);

        try {
            $parsedContent = '';
            
            if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new ValidationException("Failed to upload CSV file.");
                }
                if ($file['size'] > 5 * 1024 * 1024) {
                    throw new ValidationException("CSV file size exceeds 5MB limit.");
                }
                $parsedContent = file_get_contents($file['tmp_name']);
            } elseif (!empty($csvText)) {
                $parsedContent = $csvText;
            } else {
                throw new ValidationException("Please upload a CSV file or paste CSV content.");
            }

            if (empty($name)) {
                throw new ValidationException("Dataset name is required.");
            }

            // Parse and save
            $parsed = $datasetService->parseCsvContent($parsedContent);
            $datasetService->createDataset(
                $activeProjectId,
                $name,
                $parsed['columnMap'],
                $parsed['rowData'],
                $currentUserId
            );
            
            $success = "Dataset '$name' imported successfully with " . count($parsed['rowData']) . " rows.";
        } catch (ValidationException $e) {
            $error = $e->getMessage();
        } catch (\Exception $e) {
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}

// Handle Build Dataset Action
$activeTab = 'import';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'build_dataset') {
    $activeTab = 'build';
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $gridJson = $_POST['grid_json'] ?? '';
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);

        try {
            if (empty($name)) throw new ValidationException("Dataset name is required.");
            
            $gridData = json_decode($gridJson, true);
            if (!is_array($gridData) || !isset($gridData['columnMap']) || !isset($gridData['rowData'])) {
                throw new ValidationException("Invalid dataset grid structure.");
            }

            // Sanitize column names and convert 2D array to associative array matching the CSV parser logic
            $rawColumnMap = $gridData['columnMap'];
            $rawRowData = $gridData['rowData'];
            
            $columnMap = [];
            foreach ($rawColumnMap as $index => $col) {
                $colName = trim($col);
                if ($colName === '') {
                    $colName = 'Column_' . ($index + 1);
                }
                $colName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $colName);
                $columnMap[] = $colName;
            }

            $rowData = [];
            foreach ($rawRowData as $rowValues) {
                $rowObject = [];
                foreach ($columnMap as $index => $colName) {
                    $rowObject[$colName] = isset($rowValues[$index]) ? trim($rowValues[$index]) : '';
                }
                $rowData[] = $rowObject;
            }

            $datasetService->createDataset(
                $activeProjectId,
                $name,
                $columnMap,
                $rowData,
                $currentUserId
            );
            
            $success = "Dataset '$name' built successfully with " . count($rowData) . " rows.";
        } catch (ValidationException $e) {
            $error = $e->getMessage();
        } catch (\Exception $e) {
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}

// Handle Delete Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_dataset') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $datasetId = isset($_POST['dataset_id']) ? (int)$_POST['dataset_id'] : 0;
        try {
            $datasetService->deleteDataset($datasetId);
            $success = "Dataset deleted successfully.";
        } catch (\Exception $e) {
            $error = "Failed to delete dataset: " . $e->getMessage();
        }
    }
}

// Handle Delete Column Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_dataset_column') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $datasetId = isset($_POST['dataset_id']) ? (int)$_POST['dataset_id'] : 0;
        $columnName = $_POST['column_name'] ?? '';
        try {
            $dataset = $datasetService->getDatasetById($datasetId);
            if (!$dataset) {
                throw new \Exception("Dataset not found.");
            }
            
            $columnMap = $dataset->getColumnMap();
            $rowData = $dataset->getRowData();
            
            // Remove column from map
            $colIndex = array_search($columnName, $columnMap);
            if ($colIndex !== false) {
                array_splice($columnMap, $colIndex, 1);
            }
            
            // Remove column from each row
            foreach ($rowData as &$row) {
                unset($row[$columnName]);
            }
            
            $datasetService->updateDataset($datasetId, $dataset->getName(), $columnMap, $rowData);
            $success = "Column '$columnName' deleted successfully.";
        } catch (\Exception $e) {
            $error = "Failed to delete column: " . $e->getMessage();
        }
    }
}

// Handle Delete Row Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_dataset_row') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $datasetId = isset($_POST['dataset_id']) ? (int)$_POST['dataset_id'] : 0;
        $rowIndex = isset($_POST['row_index']) ? (int)$_POST['row_index'] : -1;
        try {
            $dataset = $datasetService->getDatasetById($datasetId);
            if (!$dataset) {
                throw new \Exception("Dataset not found.");
            }
            
            $rowData = $dataset->getRowData();
            if ($rowIndex >= 0 && $rowIndex < count($rowData)) {
                array_splice($rowData, $rowIndex, 1);
                $datasetService->updateDataset($datasetId, $dataset->getName(), $dataset->getColumnMap(), $rowData);
                $success = "Row " . ($rowIndex + 1) . " deleted successfully.";
            } else {
                throw new \Exception("Invalid row index.");
            }
        } catch (\Exception $e) {
            $error = "Failed to delete row: " . $e->getMessage();
        }
    }
}

// Handle Add Column Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_dataset_column') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $datasetId = isset($_POST['dataset_id']) ? (int)$_POST['dataset_id'] : 0;
        $columnName = trim($_POST['column_name'] ?? '');
        try {
            $columnName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $columnName);
            if (empty($columnName)) {
                throw new \Exception("Invalid column name.");
            }

            $dataset = $datasetService->getDatasetById($datasetId);
            if (!$dataset) {
                throw new \Exception("Dataset not found.");
            }
            
            $columnMap = $dataset->getColumnMap();
            $rowData = $dataset->getRowData();
            
            if (in_array($columnName, $columnMap)) {
                throw new \Exception("Column '$columnName' already exists.");
            }
            
            $columnMap[] = $columnName;
            
            foreach ($rowData as &$row) {
                $row[$columnName] = '';
            }
            
            $datasetService->updateDataset($datasetId, $dataset->getName(), $columnMap, $rowData);
            $success = "Column '$columnName' added successfully.";
        } catch (\Exception $e) {
            $error = "Failed to add column: " . $e->getMessage();
        }
    }
}

// Handle Add Row Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_dataset_row') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $datasetId = isset($_POST['dataset_id']) ? (int)$_POST['dataset_id'] : 0;
        try {
            $dataset = $datasetService->getDatasetById($datasetId);
            if (!$dataset) {
                throw new \Exception("Dataset not found.");
            }
            
            $columnMap = $dataset->getColumnMap();
            $rowData = $dataset->getRowData();
            
            $newRow = [];
            foreach ($columnMap as $col) {
                $newRow[$col] = '';
            }
            $rowData[] = $newRow;
            
            $datasetService->updateDataset($datasetId, $dataset->getName(), $columnMap, $rowData);
            $success = "New row added successfully.";
        } catch (\Exception $e) {
            $error = "Failed to add row: " . $e->getMessage();
        }
    }
}

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
        <!-- Left: Upload/Paste CSV Form -->
        <div class="space-y-6">
            <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl">
                <div class="flex items-center space-x-4 mb-4 border-b border-slate-800 pb-2">
                    <button type="button" id="tab-import" class="text-sm font-bold text-indigo-400 hover:text-indigo-300 transition pb-2 border-b-2 border-indigo-500">Import CSV</button>
                    <button type="button" id="tab-build" class="text-sm font-bold text-slate-500 hover:text-slate-300 transition pb-2 border-b-2 border-transparent">Build Manually</button>
                </div>
                
                <!-- Import CSV Form -->
                <form id="form-import" action="" method="POST" enctype="multipart/form-data" class="space-y-4 block">
                    <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                    <input type="hidden" name="action" value="import_dataset">
                    
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-300 mb-1">Dataset Name</label>
                        <input type="text" id="name" name="name" placeholder="e.g. Monster Deck V1" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 p-2.5">
                    </div>

                    <div>
                        <label for="csv_file" class="block text-sm font-medium text-slate-300 mb-1">Upload CSV File</label>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv" class="w-full text-sm text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-500 cursor-pointer">
                    </div>

                    <div class="relative">
                        <div class="absolute inset-0 flex items-center" aria-hidden="true">
                            <div class="w-full border-t border-slate-800"></div>
                        </div>
                        <div class="relative flex justify-center text-xs uppercase font-extrabold tracking-wider">
                            <span class="bg-slate-900 px-3 text-slate-500">Or Paste Raw CSV</span>
                        </div>
                    </div>

                    <div>
                        <label for="csv_text" class="block text-sm font-medium text-slate-300 mb-1">Pasted Tabular Data</label>
                        <textarea id="csv_text" name="csv_text" rows="5" placeholder="Name,Attack,Health&#10;Goblin,3,2&#10;Dragon,12,25" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-xl focus:ring-indigo-500 p-2.5 font-mono"></textarea>
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-xl shadow-lg hover:shadow-indigo-500/20 py-2.5 px-4 transition duration-200">
                        Import Dataset
                    </button>
                </form>

                <!-- Build Manually Form -->
                <form id="form-build" action="" method="POST" class="space-y-4 hidden" onsubmit="return saveManualDataset(event);">
                    <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                    <input type="hidden" name="action" value="build_dataset">
                    <input type="hidden" id="grid_json" name="grid_json" value="">

                    <div>
                        <label for="build_name" class="block text-sm font-medium text-slate-300 mb-1">Dataset Name</label>
                        <input type="text" id="build_name" name="name" placeholder="e.g. Custom Event Deck" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-indigo-500 p-2.5">
                    </div>

                    <div class="border border-slate-800 rounded-xl overflow-hidden bg-slate-950">
                        <div class="flex items-center justify-between p-2 border-b border-slate-800 bg-slate-900/50">
                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Data Grid</h4>
                            <div class="flex space-x-2">
                                <button type="button" id="btn-add-col" class="text-[10px] uppercase font-bold px-2 py-1 bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 rounded transition">+ Column</button>
                                <button type="button" id="btn-add-row" class="text-[10px] uppercase font-bold px-2 py-1 bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 rounded transition">+ Row</button>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table id="builder-grid" class="w-full text-left text-sm text-slate-300">
                                <thead class="bg-slate-900/30 text-xs uppercase text-slate-500 border-b border-slate-800">
                                    <tr id="builder-header-row">
                                        <!-- Columns injected by JS -->
                                    </tr>
                                </thead>
                                <tbody id="builder-body" class="divide-y divide-slate-800/50">
                                    <!-- Rows injected by JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-violet-600 hover:bg-violet-500 text-white font-medium rounded-xl shadow-lg hover:shadow-violet-500/20 py-2.5 px-4 transition duration-200">
                        Build & Save Dataset
                    </button>
                </form>
            </div>
            
            <!-- List of Datasets -->
            <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl space-y-4">
                <h2 class="text-xl font-bold text-slate-200">Available Datasets</h2>
                
                <?php if (empty($datasets)): ?>
                    <p class="text-xs text-slate-500 py-4 text-center">No datasets imported yet.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($datasets as $data): ?>
                            <div class="flex items-center justify-between p-3 rounded-xl border <?php echo $inspectDatasetId === $data->getId() ? 'bg-indigo-500/10 border-indigo-500/30 text-white' : 'bg-slate-950 border-slate-800/80 text-slate-300'; ?> transition">
                                <a href="?project_id=<?php echo $activeProjectId; ?>&inspect_id=<?php echo $data->getId(); ?>" class="flex-grow font-semibold text-xs hover:underline truncate pr-2">
                                    <?php echo SecurityHelper::escape($data->getName()); ?>
                                    <span class="block text-[10px] text-slate-500 mt-0.5"><?php echo count($data->getRowData()); ?> rows | <?php echo count($data->getColumnMap()); ?> cols</span>
                                </a>
                                
                                <form action="" method="POST" class="m-0" onsubmit="event.preventDefault(); window.studioConfirm('Are you sure you want to delete this dataset? This may break active variable bindings on templates.', 'Delete', 'Delete Dataset').then((confirmed) => { if (confirmed) this.submit(); });">
                                    <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                    <input type="hidden" name="action" value="delete_dataset">
                                    <input type="hidden" name="dataset_id" value="<?php echo $data->getId(); ?>">
                                    
                                    <button type="submit" class="text-xs text-rose-500 hover:text-rose-400 p-1.5 rounded hover:bg-rose-500/10 border border-transparent">
                                        Remove
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Dataset Preview Grid / Details -->
        <div class="lg:col-span-2 space-y-6">
            <?php if (!$inspectDataset): ?>
                <div class="p-16 text-center bg-slate-900/30 border border-dashed border-slate-800 rounded-3xl h-full flex flex-col justify-center items-center">
                    <svg class="h-12 w-12 text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <h3 class="text-lg font-bold text-slate-300">Select a Dataset to Preview</h3>
                    <p class="text-sm text-slate-500 mt-1 max-w-sm">Choose from the left sidebar to preview row values, variable column maps, and verify CSV structure.</p>
                </div>
            <?php else: ?>
                <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl space-y-4">
                    <?php if ($isDatasetLocked): ?>
                        <div class="bg-rose-500/10 border border-rose-500/20 text-rose-400 p-3 rounded-xl text-sm flex items-center justify-between gap-4 mb-4">
                            <div class="flex items-center space-x-2">
                                <svg class="h-5 w-5 text-rose-500 animate-pulse flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                <span><strong>Read-Only View:</strong> This dataset is currently locked for editing by <strong><?php echo SecurityHelper::escape($lockUser ? $lockUser->getName() : 'another user'); ?></strong>.</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                        <div>
                            <div class="flex items-center space-x-3">
                                <h2 class="text-xl font-bold text-slate-200"><?php echo SecurityHelper::escape($inspectDataset->getName()); ?></h2>
                                <span id="dataset-save-status" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                    Auto-Saved
                                </span>
                            </div>
                            <p class="text-xs text-slate-400 mt-0.5">Use binding format `{{ColumnName}}` on card layers to substitute values.</p>
                        </div>
                        <div class="flex space-x-2">
                            <?php if (!$isDatasetLocked): ?>
                                <form action="" method="POST" class="m-0" id="form-add-column-inspect" onsubmit="event.preventDefault(); window.studioPrompt('Enter new column name (e.g. Health, Attack, Image):', '', 'Add Column').then((newCol) => { if (newCol && newCol.trim()) { this.querySelector('[name=column_name]').value = newCol.trim(); this.submit(); } });">
                                    <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                    <input type="hidden" name="action" value="add_dataset_column">
                                    <input type="hidden" name="dataset_id" value="<?php echo $inspectDataset->getId(); ?>">
                                    <input type="hidden" name="column_name" value="">
                                    <button type="submit" class="text-xs uppercase font-bold px-3 py-1.5 bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 rounded-xl transition">+ Column</button>
                                </form>
                                
                                <form action="" method="POST" class="m-0">
                                    <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                    <input type="hidden" name="action" value="add_dataset_row">
                                    <input type="hidden" name="dataset_id" value="<?php echo $inspectDataset->getId(); ?>">
                                    <button type="submit" class="text-xs uppercase font-bold px-3 py-1.5 bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 rounded-xl transition">+ Row</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Column Map Variables Badges -->
                    <div>
                        <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-2">Available Bindings</h4>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($inspectDataset->getColumnMap() as $col): ?>
                                <span class="text-xs font-mono font-semibold px-2.5 py-1 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                    {{<?php echo SecurityHelper::escape($col); ?>}}
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Bound Templates Section -->
                    <div>
                        <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-2">Bound Design Templates</h4>
                        <div class="flex flex-wrap gap-2">
                            <?php 
                            $boundTemplates = array_filter($allTemplates, function($t) use ($inspectDataset) {
                                return $t->getDatasetId() === $inspectDataset->getId();
                            });
                            ?>
                            <?php if (empty($boundTemplates)): ?>
                                <span class="text-xs text-slate-500 italic">No templates currently bound to this dataset.</span>
                            <?php else: ?>
                                <?php foreach ($boundTemplates as $bTmpl): ?>
                                    <a href="editor.php?id=<?php echo $bTmpl->getId(); ?>" class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-violet-500/10 text-violet-400 border border-violet-500/20 hover:bg-violet-500/20 transition flex items-center space-x-1">
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        <span><?php echo SecurityHelper::escape($bTmpl->getName()); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Row Data Table Preview -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-400">Data Rows Preview</h4>
                            <span class="text-[10px] text-slate-500 font-mono">Shift + Scroll to pan horizontally</span>
                        </div>
                        <div id="dataset-table-container" class="overflow-auto max-h-[calc(100vh-280px)] border border-slate-800 rounded-xl relative shadow-inner">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead class="sticky top-0 z-20 bg-slate-950 text-slate-300 border-b border-slate-800 shadow-sm">
                                    <tr>
                                        <th class="p-3 font-semibold w-12 text-center sticky left-0 top-0 z-30 bg-slate-950 border-r border-slate-800 shadow-[2px_0_5px_rgba(0,0,0,0.5)]">Row</th>
                                        <?php foreach ($inspectDataset->getColumnMap() as $col): ?>
                                            <th class="p-3 font-semibold relative group pr-6 bg-slate-950">
                                                <span><?php echo SecurityHelper::escape($col); ?></span>
                                                <?php if (!$isDatasetLocked): ?>
                                                    <form action="" method="POST" class="absolute right-1 top-2.5 m-0 inline" onsubmit="event.preventDefault(); window.studioConfirm('Remove column: <?php echo SecurityHelper::escape($col); ?>? This will delete all cell values for this column.', 'Remove', 'Remove Column').then((confirmed) => { if (confirmed) this.submit(); });">
                                                        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                                        <input type="hidden" name="action" value="delete_dataset_column">
                                                        <input type="hidden" name="dataset_id" value="<?php echo $inspectDataset->getId(); ?>">
                                                        <input type="hidden" name="column_name" value="<?php echo SecurityHelper::escape($col); ?>">
                                                        <button type="submit" class="text-rose-500 hover:text-rose-450 font-bold opacity-0 group-hover:opacity-100 transition text-[13px] leading-none" title="Delete Column">&times;</button>
                                                    </form>
                                                <?php endif; ?>
                                            </th>
                                        <?php endforeach; ?>
                                        <?php if (!$isDatasetLocked): ?>
                                            <th class="p-3 font-semibold w-16 text-center bg-slate-950">Action</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $allRows = $inspectDataset->getRowData();
                                    $previewLimit = 200;
                                    $rows = array_slice($allRows, 0, $previewLimit);
                                    $totalRows = count($allRows);
                                    if (empty($rows)): 
                                    ?>
                                        <tr>
                                            <td colspan="<?php echo count($inspectDataset->getColumnMap()) + ($isDatasetLocked ? 1 : 2); ?>" class="p-8 text-center text-slate-500">
                                                No rows of data found.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($rows as $index => $row): ?>
                                            <tr class="border-b border-slate-800/60 hover:bg-slate-800/30 text-slate-300">
                                                <td class="p-3 text-center text-slate-400 bg-slate-950 sticky left-0 z-10 border-r border-slate-800 font-bold shadow-[2px_0_5px_rgba(0,0,0,0.5)]"><?php echo $index + 1; ?></td>
                                                <?php foreach ($inspectDataset->getColumnMap() as $col): ?>
                                                    <td class="p-1 border-r border-slate-800/40 last:border-r-0 transition-colors duration-200">
                                                        <input type="text" 
                                                               value="<?php echo SecurityHelper::escape($row[$col] ?? ''); ?>" 
                                                               data-dataset-id="<?php echo $inspectDataset->getId(); ?>"
                                                               data-row-index="<?php echo $index; ?>"
                                                               data-column-name="<?php echo SecurityHelper::escape($col); ?>"
                                                               class="dataset-cell-input w-full bg-transparent border-0 focus:border-0 focus:ring-0 text-xs text-slate-300 focus:text-white px-2 py-2"
                                                               <?php echo $isDatasetLocked ? 'disabled' : ''; ?>
                                                        >
                                                    </td>
                                                <?php endforeach; ?>
                                                <?php if (!$isDatasetLocked): ?>
                                                    <td class="p-3 text-center bg-slate-950/20 border-l border-slate-800/40">
                                                        <form action="" method="POST" class="m-0" onsubmit="event.preventDefault(); window.studioConfirm('Delete Row <?php echo $index + 1; ?>?', 'Delete', 'Delete Row').then((confirmed) => { if (confirmed) this.submit(); });">
                                                            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                                            <input type="hidden" name="action" value="delete_dataset_row">
                                                            <input type="hidden" name="dataset_id" value="<?php echo $inspectDataset->getId(); ?>">
                                                            <input type="hidden" name="row_index" value="<?php echo $index; ?>">
                                                            <button type="submit" class="text-rose-500 hover:text-rose-450 font-bold text-sm px-1" title="Delete Row">&times;</button>
                                                        </form>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if ($totalRows > $previewLimit): ?>
                                            <tr>
                                                <td colspan="<?php echo count($inspectDataset->getColumnMap()) + ($isDatasetLocked ? 1 : 2); ?>" class="p-3 text-center text-xs text-amber-500/80 bg-amber-500/5 border-t border-amber-500/20">
                                                    Showing first <?php echo $previewLimit; ?> of <?php echo $totalRows; ?> rows. All rows will be used during export.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="js/dataset-builder.js"></script>
<?php if (isset($activeTab) && $activeTab === 'build'): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const tabBuildBtn = document.getElementById('tab-build');
        if (tabBuildBtn) tabBuildBtn.click();
    });
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const cellInputs = document.querySelectorAll('.dataset-cell-input');
    const saveStatusEl = document.getElementById('dataset-save-status');
    const debounceTimers = new Map();

    function setSaveStatus(status) {
        if (!saveStatusEl) return;
        if (status === 'saving') {
            saveStatusEl.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20';
            saveStatusEl.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span> Saving...';
        } else if (status === 'error') {
            saveStatusEl.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20';
            saveStatusEl.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span> Save Failed';
        } else {
            saveStatusEl.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
            saveStatusEl.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Auto-Saved';
        }
    }

    function saveCellInput(input) {
        if (!input || !input.dataset.dirty) return;
        delete input.dataset.dirty;

        if (debounceTimers.has(input)) {
            clearTimeout(debounceTimers.get(input));
            debounceTimers.delete(input);
        }

        const datasetId = input.getAttribute('data-dataset-id');
        const rowIndex = input.getAttribute('data-row-index');
        const columnName = input.getAttribute('data-column-name');
        const value = input.value;
        const parentTd = input.closest('td');

        setSaveStatus('saving');
        if (parentTd) {
            parentTd.className = 'p-1 border-r border-slate-800/40 last:border-r-0 transition-colors duration-200 bg-indigo-500/10';
        }

        const formData = new FormData();
        formData.append('csrf_token', '<?php echo SecurityHelper::escape($csrfToken); ?>');
        formData.append('dataset_id', datasetId);
        formData.append('row_index', rowIndex);
        formData.append('column_name', columnName);
        formData.append('value', value);

        fetch('api.php?action=update_dataset_cell', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': '<?php echo SecurityHelper::escape($csrfToken); ?>'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (parentTd) {
                parentTd.className = 'p-1 border-r border-slate-800/40 last:border-r-0 transition-colors duration-200';
            }
            if (data.success) {
                if (parentTd) {
                    parentTd.classList.add('bg-emerald-500/10');
                    setTimeout(() => parentTd.classList.remove('bg-emerald-500/10'), 600);
                }
                setSaveStatus('saved');
            } else {
                if (parentTd) {
                    parentTd.classList.add('bg-rose-500/10');
                    setTimeout(() => parentTd.classList.remove('bg-rose-500/10'), 1500);
                }
                setSaveStatus('error');
                console.error("Cell save failed:", data.error);
            }
        })
        .catch(err => {
            if (parentTd) {
                parentTd.className = 'p-1 border-r border-slate-800/40 last:border-r-0 transition-colors duration-200 bg-rose-500/10';
                setTimeout(() => parentTd.className = 'p-1 border-r border-slate-800/40 last:border-r-0 transition-colors duration-200', 1500);
            }
            setSaveStatus('error');
            console.error("Cell save failed:", err);
        });
    }

    cellInputs.forEach(input => {
        // Real-time debounced auto-save as user types
        input.addEventListener('input', () => {
            input.dataset.dirty = 'true';
            setSaveStatus('saving');

            if (debounceTimers.has(input)) {
                clearTimeout(debounceTimers.get(input));
            }

            const timer = setTimeout(() => {
                saveCellInput(input);
            }, 450);
            debounceTimers.set(input, timer);
        });

        // Immediate save on blur or change
        input.addEventListener('blur', () => saveCellInput(input));
        input.addEventListener('change', () => saveCellInput(input));

        // Save immediately on Enter keypress
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveCellInput(input);
                input.blur();
            }
        });
    });

    // Flush any pending unsaved dirty inputs before page navigation or tab close
    window.addEventListener('beforeunload', () => {
        cellInputs.forEach(input => {
            if (input.dataset.dirty === 'true') {
                const formData = new FormData();
                formData.append('csrf_token', '<?php echo SecurityHelper::escape($csrfToken); ?>');
                formData.append('dataset_id', input.getAttribute('data-dataset-id'));
                formData.append('row_index', input.getAttribute('data-row-index'));
                formData.append('column_name', input.getAttribute('data-column-name'));
                formData.append('value', input.value);
                navigator.sendBeacon('api.php?action=update_dataset_cell', formData);
            }
        });
    });

    // ponytail: horizontal mouse wheel scrolling support for wide data grids
    const tableContainer = document.getElementById('dataset-table-container');
    if (tableContainer) {
        tableContainer.addEventListener('wheel', (e) => {
            if (e.shiftKey) {
                e.preventDefault();
                tableContainer.scrollLeft += (e.deltaY || e.deltaX);
            }
        }, { passive: false });
    }
});

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

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
