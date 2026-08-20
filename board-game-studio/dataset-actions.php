<?php
declare(strict_types=1);

use App\Infrastructure\Security\SecurityHelper;
use App\Application\Exceptions\ValidationException;

// Handle Re-import / Overwrite Dataset Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'overwrite_dataset') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $dsId = (int)($_POST['dataset_id'] ?? 0);
        $ds = $datasetService->getDatasetById($dsId);
        $file = $_FILES['csv_file'] ?? null;
        $csvText = $_POST['csv_text'] ?? '';

        try {
            if (!$ds || $ds->getProjectId() !== $activeProjectId) {
                throw new ValidationException("Target dataset not found.");
            }

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

            $parsed = $datasetService->parseCsvContent($parsedContent);
            $datasetService->updateDataset($dsId, $ds->getName(), $parsed['columnMap'], $parsed['rowData']);

            $success = "Dataset '" . SecurityHelper::escape($ds->getName()) . "' successfully updated with " . count($parsed['columnMap']) . " columns and " . count($parsed['rowData']) . " rows.";
        } catch (ValidationException $e) {
            $error = $e->getMessage();
        } catch (\Exception $e) {
            $error = "An error occurred while updating dataset: " . $e->getMessage();
        }
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
