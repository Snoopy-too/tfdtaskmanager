<?php
declare(strict_types=1);

header('Content-Type: application/json');

$container = require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/api-datasets.php';
require_once __DIR__ . '/api-rulebooks.php';

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
            $canvasJson = $template->getCanvasJson();
            if (is_string($canvasJson)) {
                $canvasJson = str_replace('"alphabetical"', '"alphabetic"', $canvasJson);
            }
            echo json_encode([
                'canvas_json' => $canvasJson,
                'width' => $template->getCanvasWidthPx(),
                'height' => $template->getCanvasHeightPx(),
                'bleed_mm' => $template->getBleedMm(),
                'safe_margin_mm' => $template->getSafeMarginMm(),
                'dataset_id' => $template->getDatasetId()
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
            if (is_string($canvasJson)) {
                $canvasJson = str_replace('"alphabetical"', '"alphabetic"', $canvasJson);
            }
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
            $projectId = isset($_GET['project_id']) && (int)$_GET['project_id'] > 0 ? (int)$_GET['project_id'] : null;
            $assetService->normalizeAllProjectSvgs($projectId);
            $assets = $assetService->getAssetsByProject($projectId, true);
            
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

        case 'list_templates':
            if ($method !== 'GET') {
                throw new \InvalidArgumentException('Method not allowed.');
            }
            $projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
            $excludeId = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : 0;
            $templates = $templateService->getTemplatesByProject($projectId);
            
            $formatted = [];
            foreach ($templates as $t) {
                if ($t->getId() === $excludeId) {
                    continue;
                }
                $formatted[] = [
                    'id' => $t->getId(),
                    'name' => $t->getName(),
                    'width' => $t->getCanvasWidthPx(),
                    'height' => $t->getCanvasHeightPx(),
                    'component_type_id' => $t->getComponentTypeId()
                ];
            }
            echo json_encode($formatted);
            break;

        case 'bind_template_dataset':
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
            $datasetId = (isset($_POST['dataset_id']) && $_POST['dataset_id'] !== '' && $_POST['dataset_id'] !== 'null' && $_POST['dataset_id'] !== '0') ? (int)$_POST['dataset_id'] : null;

            $template = $templateService->getTemplateById($templateId);
            if (!$template) {
                http_response_code(404);
                echo json_encode(['error' => 'Template not found.']);
                exit;
            }

            $currentUserId = (int)($_SESSION['user_id'] ?? 0);
            if ($templateService->isTemplateLockedByOther($template, $currentUserId)) {
                http_response_code(423);
                echo json_encode(['error' => 'Template is currently locked by another user.']);
                exit;
            }

            $updatedTemplate = $templateService->updateTemplate(
                $templateId,
                $template->getName(),
                $template->getBleedMm(),
                $template->getSafeMarginMm(),
                $datasetId
            );

            $datasetData = null;
            if ($datasetId) {
                $datasetObj = $datasetService->getDatasetById($datasetId);
                if ($datasetObj) {
                    $datasetData = [
                        'id' => $datasetObj->getId(),
                        'name' => $datasetObj->getName(),
                        'columnMap' => $datasetObj->getColumnMap(),
                        'rowData' => $datasetObj->getRowData()
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'dataset_id' => $updatedTemplate->getDatasetId(),
                'dataset' => $datasetData
            ]);
            break;

        case 'update_template_row_filter':
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
            $rowFilter = trim($_POST['row_filter'] ?? '');
            if ($rowFilter === '') {
                $rowFilter = null;
            }

            $templateService->updateTemplateRowFilter($templateId, $rowFilter);

            echo json_encode([
                'success' => true,
                'row_filter' => $rowFilter
            ]);
            break;

        case 'rename_template':
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
            $newName = trim($_POST['name'] ?? '');

            if (empty($newName)) {
                http_response_code(400);
                echo json_encode(['error' => 'Template name is required.']);
                exit;
            }

            $template = $templateService->getTemplateById($templateId);
            if (!$template) {
                http_response_code(404);
                echo json_encode(['error' => 'Template not found.']);
                exit;
            }

            $currentUserId = (int)($_SESSION['user_id'] ?? 0);
            if ($templateService->isTemplateLockedByOther($template, $currentUserId)) {
                http_response_code(423);
                echo json_encode(['error' => 'Template is currently locked by another user.']);
                exit;
            }

            $updatedTemplate = $templateService->updateTemplate(
                $templateId,
                $newName,
                $template->getBleedMm(),
                $template->getSafeMarginMm(),
                $template->getDatasetId()
            );

            echo json_encode([
                'success' => true,
                'id' => $updatedTemplate->getId(),
                'name' => $updatedTemplate->getName()
            ]);
            break;

        case 'toggle_orientation':
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
            $template = $templateService->getTemplateById($templateId);
            if (!$template) {
                http_response_code(404);
                echo json_encode(['error' => 'Template not found.']);
                exit;
            }

            $currentUserId = (int)($_SESSION['user_id'] ?? 0);
            if ($templateService->isTemplateLockedByOther($template, $currentUserId)) {
                http_response_code(423);
                echo json_encode(['error' => 'Template is currently locked by another user.']);
                exit;
            }

            $targetOrientation = $_POST['target_orientation'] ?? '';
            $curW = $template->getCanvasWidthPx();
            $curH = $template->getCanvasHeightPx();

            if ($targetOrientation === 'landscape') {
                $newW = max($curW, $curH);
                $newH = min($curW, $curH);
            } elseif ($targetOrientation === 'portrait') {
                $newW = min($curW, $curH);
                $newH = max($curW, $curH);
            } else {
                // Toggle / swap
                $newW = $curH;
                $newH = $curW;
            }

            $updatedTemplate = $templateService->updateTemplateDimensions($templateId, $newW, $newH);

            echo json_encode([
                'success' => true,
                'id' => $updatedTemplate->getId(),
                'canvasWidth' => $updatedTemplate->getCanvasWidthPx(),
                'canvasHeight' => $updatedTemplate->getCanvasHeightPx(),
                'orientation' => ($updatedTemplate->getCanvasWidthPx() > $updatedTemplate->getCanvasHeightPx()) ? 'landscape' : 'portrait'
            ]);
            break;

        case 'resize_template':
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
            $template = $templateService->getTemplateById($templateId);
            if (!$template) {
                http_response_code(404);
                echo json_encode(['error' => 'Template not found.']);
                exit;
            }

            $currentUserId = (int)($_SESSION['user_id'] ?? 0);
            if ($templateService->isTemplateLockedByOther($template, $currentUserId)) {
                http_response_code(423);
                echo json_encode(['error' => 'Template is currently locked by another user.']);
                exit;
            }

            $widthPx = isset($_POST['width_px']) ? (int)$_POST['width_px'] : 0;
            $heightPx = isset($_POST['height_px']) ? (int)$_POST['height_px'] : 0;

            if ($widthPx <= 0 && isset($_POST['width_mm'])) {
                $widthPx = \App\Domain\Entities\BgTemplate::mmToPx((float)$_POST['width_mm']);
            }
            if ($heightPx <= 0 && isset($_POST['height_mm'])) {
                $heightPx = \App\Domain\Entities\BgTemplate::mmToPx((float)$_POST['height_mm']);
            }

            if ($widthPx <= 0 || $heightPx <= 0) {
                http_response_code(422);
                echo json_encode(['error' => 'Width and height must be positive numbers.']);
                exit;
            }

            $updatedTemplate = $templateService->updateTemplateDimensions($templateId, $widthPx, $heightPx);

            echo json_encode([
                'success' => true,
                'id' => $updatedTemplate->getId(),
                'canvasWidth' => $updatedTemplate->getCanvasWidthPx(),
                'canvasHeight' => $updatedTemplate->getCanvasHeightPx(),
                'widthMm' => round(\App\Domain\Entities\BgTemplate::pxToMm($updatedTemplate->getCanvasWidthPx()), 1),
                'heightMm' => round(\App\Domain\Entities\BgTemplate::pxToMm($updatedTemplate->getCanvasHeightPx()), 1),
                'orientation' => ($updatedTemplate->getCanvasWidthPx() > $updatedTemplate->getCanvasHeightPx()) ? 'landscape' : 'portrait'
            ]);
            break;

        default:
            if (!handleDatasetApiAction($action, $method, $datasetService) && !handleRulebookApiAction($action, $method, $rulebookService)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action or route.']);
            }
            break;
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
