<?php
declare(strict_types=1);

header('Content-Type: application/json');

$container = require_once __DIR__ . '/../src/bootstrap.php';

use App\Infrastructure\Security\SecurityHelper;
use App\Application\Services\BgTemplateService;
use App\Application\Services\BgAssetService;
use App\Application\Services\BgDatasetService;

// API requires active login session
SecurityHelper::initSession();
if (!SecurityHelper::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Please login.']);
    exit;
}

$templateService = $container->get(BgTemplateService::class);
$assetService = $container->get(BgAssetService::class);
$datasetService = $container->get(BgDatasetService::class);

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'load_canvas':
            if ($method !== 'GET') {
                throw new \InvalidArgumentException('Method not allowed.');
            }
            $templateId = isset($_GET['template_id']) ? (int)$_GET['template_id'] : 0;
            $template = $templateService->getTemplateById($templateId);
            if (!$template) {
                http_response_code(404);
                echo json_encode(['error' => 'Template not found.']);
                exit;
            }
            echo json_encode([
                'canvas_json' => $template->getCanvasJson(),
                'width' => $template->getCanvasWidthPx(),
                'height' => $template->getCanvasHeightPx(),
                'bleed_mm' => $template->getBleedMm(),
                'safe_margin_mm' => $template->getSafeMarginMm()
            ]);
            break;

        case 'save_canvas':
            if ($method !== 'POST') {
                throw new \InvalidArgumentException('Method not allowed.');
            }
            
            // Check CSRF - prefer POST body field, fall back to request header.
            // Note: getallheaders() is not available in CGI mode; use $_SERVER instead.
            $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            $token = $_POST['csrf_token'] ?? $headerToken;
            if (!SecurityHelper::verifyCsrfToken($token)) {
                http_response_code(403);
                echo json_encode(['error' => 'CSRF verification failed.']);
                exit;
            }

            $templateId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
            
            // Security check: Verify that the template is not locked by another user
            $template = $templateService->getTemplateById($templateId);
            if (!$template) {
                http_response_code(404);
                echo json_encode(['error' => 'Template not found.']);
                exit;
            }
            $currentUserId = (int)($_SESSION['user_id'] ?? 0);
            if ($templateService->isTemplateLockedByOther($template, $currentUserId)) {
                http_response_code(423); // Locked
                echo json_encode(['error' => 'Template is currently locked for editing by another user.']);
                exit;
            }

            $canvasJson = $_POST['canvas_json'] ?? '';
            $layersRaw = $_POST['layers'] ?? '[]';
            
            $layers = json_decode($layersRaw, true);
            if (!is_array($layers)) {
                throw new \InvalidArgumentException('Invalid layers format.');
            }

            $templateService->saveCanvas($templateId, $canvasJson, $layers);
            echo json_encode(['success' => true]);
            break;

        case 'heartbeat_lock':
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

            $templateId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
            $currentUserId = (int)($_SESSION['user_id'] ?? 0);

            $template = $templateService->getTemplateById($templateId);
            if (!$template) {
                http_response_code(404);
                echo json_encode(['error' => 'Template not found.']);
                exit;
            }

            if ($templateService->isTemplateLockedByOther($template, $currentUserId)) {
                echo json_encode(['success' => false, 'locked' => true]);
                exit;
            }

            $success = $templateService->acquireOrRefreshLock($templateId, $currentUserId);
            echo json_encode(['success' => $success, 'locked' => !$success]);
            break;

        case 'release_lock':
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

            $templateId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
            $currentUserId = (int)($_SESSION['user_id'] ?? 0);

            $templateService->releaseLock($templateId, $currentUserId);
            echo json_encode(['success' => true]);
            break;

        case 'list_assets':
            if ($method !== 'GET') {
                throw new \InvalidArgumentException('Method not allowed.');
            }
            $projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
            $assets = $assetService->getAssetsByProject($projectId);
            
            $formatted = [];
            foreach ($assets as $asset) {
                $formatted[] = [
                    'id' => $asset->getId(),
                    'original_filename' => $asset->getOriginalFilename(),
                    'stored_filename' => $asset->getStoredFilename(),
                    'mime_type' => $asset->getMimeType(),
                    'file_size_bytes' => $asset->getFileSizeBytes(),
                    'tag' => $asset->getTag(),
                    // Client-side relative URL path to files in upload folder
                    'url' => '../uploads/board-game-studio/' . $asset->getProjectId() . '/' . $asset->getStoredFilename()
                ];
            }
            echo json_encode($formatted);
            break;

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
            break;

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
            $columnName = $_POST['column_name'] ?? '';
            $value = $_POST['value'] ?? '';

            $dataset = $datasetService->getDatasetById($datasetId);
            if (!$dataset) {
                http_response_code(404);
                echo json_encode(['error' => 'Dataset not found.']);
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
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action or route.']);
            break;
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
