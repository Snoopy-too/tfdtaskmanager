<?php
declare(strict_types=1);

use App\Infrastructure\Security\SecurityHelper;
use App\Application\Services\BgRulebookService;

/**
 * Handles Rulebook and Glossary API routes.
 * 
 * @param string $action
 * @param string $method
 * @param BgRulebookService $rulebookService
 * @return bool True if the action was handled, false otherwise
 */
function handleRulebookApiAction(string $action, string $method, BgRulebookService $rulebookService): bool
{
    switch ($action) {
        case 'heartbeat_lock_rulebook':
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
            $currentUserId = (int)($_SESSION['user_id'] ?? 0);

            $rulebook = $rulebookService->getRulebookById($rulebookId);
            if (!$rulebook) {
                http_response_code(404);
                echo json_encode(['error' => 'Rulebook not found.']);
                exit;
            }

            if ($rulebookService->isRulebookLockedByOther($rulebook, $currentUserId)) {
                echo json_encode(['success' => false, 'locked' => true]);
                exit;
            }

            $success = $rulebookService->acquireOrRefreshLock($rulebookId, $currentUserId);
            echo json_encode(['success' => $success, 'locked' => !$success]);
            return true;

        case 'release_lock_rulebook':
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
            $currentUserId = (int)($_SESSION['user_id'] ?? 0);

            $rulebookService->releaseLock($rulebookId, $currentUserId);
            echo json_encode(['success' => true]);
            return true;

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
            return true;

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
                $rulebook = $rulebookService->getRulebookById($rulebookId);
                if ($rulebook && $rulebookService->isRulebookLockedByOther($rulebook, $currentUserId)) {
                    http_response_code(423);
                    echo json_encode(['error' => 'This rulebook is currently locked by another user.']);
                    exit;
                }
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
            return true;

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
            $currentUserId = (int)($_SESSION['user_id'] ?? 0);
            $rulebook = $rulebookService->getRulebookById($rulebookId);
            if ($rulebook && $rulebookService->isRulebookLockedByOther($rulebook, $currentUserId)) {
                http_response_code(423);
                echo json_encode(['error' => 'This rulebook is currently locked by another user.']);
                exit;
            }
            $rulebookService->deleteRulebook($rulebookId);
            echo json_encode(['success' => true]);
            return true;

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
            return true;

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
            return true;

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
            return true;

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
            return true;

        default:
            return false;
    }
}
