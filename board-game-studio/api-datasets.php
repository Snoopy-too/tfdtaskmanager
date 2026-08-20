<?php
declare(strict_types=1);

use App\Infrastructure\Security\SecurityHelper;
use App\Application\Services\BgDatasetService;

/**
 * Handles Dataset API routes.
 * 
 * @param string $action
 * @param string $method
 * @param BgDatasetService $datasetService
 * @return bool True if the action was handled, false otherwise
 */
function handleDatasetApiAction(string $action, string $method, BgDatasetService $datasetService): bool
{
    switch ($action) {
        case 'heartbeat_lock_dataset':
            if ($method !== 'POST') {
                throw new \InvalidArgumentException('Method not allowed.');
            }
            $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            $token = $_POST['csrf_token'] ?? $headerToken;
            if (!SecurityHelper::verifyCsrfToken($token)) {
                http_response_code(403);
                echo json_encode(['error' => 'CSRF verification failed.']);
                exit;
            }

            $datasetId = isset($_POST['dataset_id']) ? (int)$_POST['dataset_id'] : 0;
            $currentUserId = (int)($_SESSION['user_id'] ?? 0);

            $dataset = $datasetService->getDatasetById($datasetId);
            if (!$dataset) {
                http_response_code(404);
                echo json_encode(['error' => 'Dataset not found.']);
                exit;
            }

            if ($datasetService->isDatasetLockedByOther($dataset, $currentUserId)) {
                echo json_encode(['success' => false, 'locked' => true]);
                exit;
            }

            $success = $datasetService->acquireOrRefreshLock($datasetId, $currentUserId);
            echo json_encode(['success' => $success, 'locked' => !$success]);
            return true;

        case 'release_lock_dataset':
            if ($method !== 'POST') {
                throw new \InvalidArgumentException('Method not allowed.');
            }
            $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            $token = $_POST['csrf_token'] ?? $headerToken;
            if (!SecurityHelper::verifyCsrfToken($token)) {
                http_response_code(403);
                echo json_encode(['error' => 'CSRF verification failed.']);
                exit;
            }

            $datasetId = isset($_POST['dataset_id']) ? (int)$_POST['dataset_id'] : 0;
            $currentUserId = (int)($_SESSION['user_id'] ?? 0);

            $datasetService->releaseLock($datasetId, $currentUserId);
            echo json_encode(['success' => true]);
            return true;

        case 'get_dataset':
            if ($method !== 'GET') {
                throw new \InvalidArgumentException('Method not allowed.');
            }
            $datasetId = isset($_GET['dataset_id']) ? (int)$_GET['dataset_id'] : 0;
            $dataset = $datasetService->getDatasetById($datasetId);
            if (!$dataset) {
                http_response_code(404);
                echo json_encode(['error' => 'Dataset not found.']);
                exit;
            }
            echo json_encode([
                'id' => $dataset->getId(),
                'name' => $dataset->getName(),
                'columnMap' => $dataset->getColumnMap(),
                'rowData' => $dataset->getRowData()
            ]);
            return true;

        case 'update_dataset_cell':
            if ($method !== 'POST') {
                throw new \InvalidArgumentException('Method not allowed.');
            }
            $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            $token = $_POST['csrf_token'] ?? $headerToken;
            if (!SecurityHelper::verifyCsrfToken($token)) {
                http_response_code(403);
                echo json_encode(['error' => 'CSRF verification failed.']);
                exit;
            }

            $datasetId = isset($_POST['dataset_id']) ? (int)$_POST['dataset_id'] : 0;
            $rowIndex = isset($_POST['row_index']) ? (int)$_POST['row_index'] : -1;
            $columnName = trim($_POST['column_name'] ?? '');
            $value = $_POST['value'] ?? '';

            $dataset = $datasetService->getDatasetById($datasetId);
            if (!$dataset) {
                http_response_code(404);
                echo json_encode(['error' => 'Dataset not found.']);
                exit;
            }

            $currentUserId = (int)($_SESSION['user_id'] ?? 0);
            if ($datasetService->isDatasetLockedByOther($dataset, $currentUserId)) {
                http_response_code(423);
                echo json_encode(['error' => 'Dataset is currently locked for editing by another user.']);
                exit;
            }

            $rowData = $dataset->getRowData();
            if ($rowIndex < 0 || $rowIndex >= count($rowData)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid row index.']);
                exit;
            }

            $columnMap = $dataset->getColumnMap();
            if (!in_array($columnName, $columnMap)) {
                http_response_code(400);
                echo json_encode(['error' => 'Column not found in dataset mapping.']);
                exit;
            }

            $rowData[$rowIndex][$columnName] = $value;
            $datasetService->updateDataset($datasetId, $dataset->getName(), $columnMap, $rowData);

            echo json_encode(['success' => true]);
            return true;

        default:
            return false;
    }
}
