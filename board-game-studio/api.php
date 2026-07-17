<?php
declare(strict_types=1);

header('Content-Type: application/json');

$container = require_once __DIR__ . '/../src/bootstrap.php';

use App\Infrastructure\Security\SecurityHelper;
use App\Application\Services\BgTemplateService;
use App\Application\Services\BgAssetService;
use App\Application\Services\BgDatasetService;
use App\Application\Services\BgRulebookService;

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
$rulebookService = $container->get(BgRulebookService::class);

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
                    'url' => '../uploads/board-game-studio/' . ($asset->getProjectId() === null ? 'global' : $asset->getProjectId()) . '/' . $asset->getStoredFilename()
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

        case 'list_rulebooks':
            if ($method !== 'GET') {
                throw new \InvalidArgumentException('Method not allowed.');
            }
            $projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
            $rulebooks = $rulebookService->getRulebooksByProject($projectId);
            $formatted = [];
            foreach ($rulebooks as $rb) {
                $formatted[] = [
                    'id' => $rb->getId(),
                    'project_id' => $rb->getProjectId(),
                    'name' => $rb->getName(),
                    'content' => $rb->getContent(),
                    'created_by' => $rb->getCreatedBy(),
                    'created_at' => $rb->getCreatedAt(),
                    'updated_at' => $rb->getUpdatedAt(),
                ];
            }
            echo json_encode($formatted);
            break;

        case 'save_rulebook':
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

            $projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
            $rulebookId = isset($_POST['rulebook_id']) && $_POST['rulebook_id'] !== '' ? (int)$_POST['rulebook_id'] : null;
            $name = $_POST['name'] ?? '';
            $contentRaw = $_POST['content'] ?? '[]';
            $content = json_decode($contentRaw, true);
            if (!is_array($content)) {
                throw new \InvalidArgumentException('Invalid content format.');
            }

            $currentUserId = (int)($_SESSION['user_id'] ?? 0);
            if ($rulebookId === null) {
                $saved = $rulebookService->createRulebook($projectId, $name, $content, $currentUserId);
            } else {
                $saved = $rulebookService->updateRulebook($rulebookId, $name, $content);
            }

            echo json_encode([
                'success' => true,
                'rulebook' => [
                    'id' => $saved->getId(),
                    'name' => $saved->getName(),
                    'content' => $saved->getContent()
                ]
            ]);
            break;

        case 'delete_rulebook':
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

            $rulebookId = isset($_POST['rulebook_id']) ? (int)$_POST['rulebook_id'] : 0;
            $rulebookService->deleteRulebook($rulebookId);
            echo json_encode(['success' => true]);
            break;

        case 'list_glossary':
            if ($method !== 'GET') {
                throw new \InvalidArgumentException('Method not allowed.');
            }
            $projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
            $terms = $rulebookService->getGlossaryByProject($projectId);
            $formatted = [];
            foreach ($terms as $t) {
                $formatted[] = [
                    'id' => $t->getId(),
                    'project_id' => $t->getProjectId(),
                    'term_key' => $t->getTermKey(),
                    'term_name' => $t->getTermName(),
                    'term_description' => $t->getTermDescription(),
                    'created_by' => $t->getCreatedBy(),
                    'created_at' => $t->getCreatedAt()
                ];
            }
            echo json_encode($formatted);
            break;

        case 'save_glossary_term':
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

            $projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
            $termId = isset($_POST['term_id']) && $_POST['term_id'] !== '' ? (int)$_POST['term_id'] : null;
            $termKey = $_POST['term_key'] ?? '';
            $termName = $_POST['term_name'] ?? '';
            $termDescription = $_POST['term_description'] ?? '';
            $currentUserId = (int)($_SESSION['user_id'] ?? 0);

            $saved = $rulebookService->saveGlossaryTerm($projectId, $termId, $termKey, $termName, $termDescription, $currentUserId);
            echo json_encode([
                'success' => true,
                'term' => [
                    'id' => $saved->getId(),
                    'term_key' => $saved->getTermKey(),
                    'term_name' => $saved->getTermName(),
                    'term_description' => $saved->getTermDescription()
                ]
            ]);
            break;

        case 'delete_glossary_term':
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

            $termId = isset($_POST['term_id']) ? (int)$_POST['term_id'] : 0;
            $rulebookService->deleteGlossaryTerm($termId);
            echo json_encode(['success' => true]);
            break;

        case 'import_glossary_csv':
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

            $projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
            $csvText = $_POST['csv_text'] ?? '';
            $currentUserId = (int)($_SESSION['user_id'] ?? 0);

            // Handle file upload
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['csv_file'];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new \InvalidArgumentException('Failed to upload CSV file.');
                }
                if ($file['size'] > 5 * 1024 * 1024) {
                    throw new \InvalidArgumentException('CSV file exceeds 5MB size limit.');
                }
                $csvText = file_get_contents($file['tmp_name']);
            }

            if (empty(trim($csvText))) {
                throw new \InvalidArgumentException('Please upload a CSV file or paste raw CSV text.');
            }

            $importedCount = $rulebookService->importGlossaryFromCsv($projectId, $csvText, $currentUserId);
            echo json_encode(['success' => true, 'count' => $importedCount]);
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
