<?php
declare(strict_types=1);

use App\Application\Exceptions\ResourceLockedException;
use App\Application\Exceptions\ValidationException;
use App\Application\Services\BgSandboxService;
use App\Application\Services\ProjectService;
use App\Domain\Entities\BgSandbox;
use App\Domain\Sandbox\SandboxStateSchema;
use App\Infrastructure\Security\SecurityHelper;

/**
 * Handles Playtest Sandbox API routes (dispatched from api.php).
 *
 * All routes are POST + CSRF protected and verify project access before touching a sandbox.
 *
 * @return bool True if the action was handled, false otherwise.
 */
function handleSandboxApiAction(string $action, string $method, BgSandboxService $sandboxService, ProjectService $projectService): bool
{
    $routes = [
        'save_sandbox_state',
        'save_sandbox_snapshot',
        'reset_sandbox_snapshot',
        'heartbeat_lock_sandbox',
        'release_lock_sandbox',
        'host_sandbox_room',
        'sync_sandbox_room',
    ];
    if (!in_array($action, $routes, true)) {
        return false;
    }

    $currentUserId = (int)($_SESSION['user_id'] ?? 0);
    $currentUserName = (string)($_SESSION['user_name'] ?? ('User #' . $currentUserId));

    if ($method !== 'POST') {
        sandboxApiRespond(405, ['error' => 'Method not allowed.']);
        return true;
    }
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!SecurityHelper::verifyCsrfToken(is_string($token) ? $token : '')) {
        sandboxApiRespond(403, ['error' => 'CSRF verification failed.']);
        return true;
    }

    $sandbox = sandboxApiLoadAuthorized($sandboxService, $projectService, $currentUserId);
    if ($sandbox === null) {
        sandboxApiRespond(404, ['error' => 'Sandbox not found or access denied.']);
        return true;
    }

    try {
        switch ($action) {
            case 'save_sandbox_state':
                $raw = $_POST['state'] ?? '';
                $saved = $sandboxService->saveTableState($sandbox, is_string($raw) ? $raw : '', $currentUserId);
                sandboxApiRespond(200, ['success' => true, 'updatedAt' => $saved->updatedAt]);
                break;

            case 'save_sandbox_snapshot':
                $raw = isset($_POST['state']) && is_string($_POST['state']) ? $_POST['state'] : null;
                $saved = $sandboxService->saveStartingSnapshot($sandbox, $raw, $currentUserId);
                sandboxApiRespond(200, [
                    'success' => true,
                    'updatedAt' => $saved->updatedAt,
                    'state' => $sandboxService->presentState($saved),
                ]);
                break;

            case 'reset_sandbox_snapshot':
                $saved = $sandboxService->resetToStartingSnapshot($sandbox, $currentUserId);
                sandboxApiRespond(200, [
                    'success' => true,
                    'updatedAt' => $saved->updatedAt,
                    'state' => $sandboxService->presentState($saved),
                ]);
                break;

            case 'heartbeat_lock_sandbox':
                $acquired = $sandboxService->acquireOrRefreshLock($sandbox, $currentUserId);
                sandboxApiRespond(200, ['success' => $acquired, 'locked' => !$acquired]);
                break;

            case 'release_lock_sandbox':
                $sandboxService->releaseLock($sandbox, $currentUserId);
                sandboxApiRespond(200, ['success' => true]);
                break;

            case 'host_sandbox_room':
                $room = $sandboxService->hostLiveSession($sandbox, $currentUserId);
                sandboxApiRespond(200, [
                    'success' => true,
                    'roomCode' => $room['roomCode'] ?? '',
                    'version' => $room['version'] ?? 1,
                ]);
                break;

            case 'sync_sandbox_room':
                $rawPayload = $_POST['payload'] ?? '{}';
                $payload = is_string($rawPayload) && SandboxStateSchema::isValidJson($rawPayload)
                    ? (json_decode($rawPayload, true) ?: [])
                    : [];
                $sync = $sandboxService->syncLiveRoom($sandbox, $currentUserId, $currentUserName, $payload);
                sandboxApiRespond(200, ['success' => true] + $sync);
                break;
        }
    } catch (ValidationException $e) {
        sandboxApiRespond(422, ['error' => $e->getMessage()]);
    } catch (ResourceLockedException $e) {
        sandboxApiRespond(423, ['error' => $e->getMessage()]);
    }
    return true;
}

function sandboxApiLoadAuthorized(BgSandboxService $sandboxService, ProjectService $projectService, int $userId): ?BgSandbox
{
    $sandboxId = filter_var($_POST['sandbox_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($sandboxId === false || $sandboxId === null) {
        return null;
    }
    $sandbox = $sandboxService->getSandboxById($sandboxId);
    if ($sandbox === null || $projectService->getProjectById($sandbox->projectId, $userId) === null) {
        return null;
    }
    return $sandbox;
}

/** @param array<string,mixed> $payload */
function sandboxApiRespond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR);
}
