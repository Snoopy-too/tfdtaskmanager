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
        'join_sandbox_room',
        'end_sandbox_room',
        'get_sandbox_room_status',
        'get_project_sandbox_rooms',
        'sync_sandbox_room',
        'publish_sandbox_to_lobby',
        'unpublish_sandbox_from_lobby',
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

    if ($action === 'get_project_sandbox_rooms') {
        $projectId = filter_var($_POST['project_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($projectId === false || $projectId === null || $projectService->getProjectById($projectId, $currentUserId) === null) {
            sandboxApiRespond(404, ['error' => 'Project not found or access denied.']);
            return true;
        }
        $rooms = $sandboxService->getActiveRoomsForProject($projectId);
        $summaries = [];
        foreach ($rooms as $sbxId => $room) {
            $presence = $sandboxService->prunePresence(is_array($room['presenceState'] ?? null) ? $room['presenceState'] : []);
            $summaries[(string)$sbxId] = [
                'sandboxId' => (int)$sbxId,
                'roomCode' => (string)$room['roomCode'],
                'hostUserId' => (int)$room['hostUserId'],
                'hostUserName' => (string)$room['hostUserName'],
                'memberCount' => count($presence['members'] ?? []),
                'version' => (int)$room['version'],
            ];
        }
        sandboxApiRespond(200, ['success' => true, 'rooms' => $summaries]);
        return true;
    }

    $sandbox = sandboxApiLoadAuthorized($sandboxService, $projectService, $currentUserId, $action === 'join_sandbox_room');
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
                $forceReset = (string)($_POST['reset'] ?? '0') === '1';
                $sandboxService->hostLiveSession($sandbox, $currentUserId, $forceReset);
                $joined = $sandboxService->joinLiveSession($sandbox, $currentUserId, $currentUserName, false);
                sandboxApiRespond(200, ['success' => true] + ($joined ?? []));
                break;

            case 'join_sandbox_room':
                $joined = $sandboxService->joinLiveSession($sandbox, $currentUserId, $currentUserName, false);
                if ($joined === null) {
                    sandboxApiRespond(404, ['error' => 'No active live room found for this sandbox.']);
                    break;
                }
                sandboxApiRespond(200, ['success' => true] + $joined);
                break;

            case 'end_sandbox_room':
                $sandboxService->endLiveSession($sandbox, $currentUserId);
                sandboxApiRespond(200, ['success' => true]);
                break;

            case 'get_sandbox_room_status':
                $room = $sandboxService->getActiveRoom($sandbox);
                if ($room === null) {
                    sandboxApiRespond(200, ['success' => true, 'active' => false]);
                    break;
                }
                $presence = $sandboxService->prunePresence(is_array($room['presenceState']) ? $room['presenceState'] : []);
                $hostId = (int)$room['hostUserId'];
                $projectRooms = $sandboxService->getActiveRoomsForProject($sandbox->projectId);
                $hostMember = $presence['members'][(string)$hostId] ?? null;
                $hostUserName = $projectRooms[(int)$sandbox->id]['hostUserName']
                    ?? ($hostMember['name'] ?? ('User #' . $hostId));
                sandboxApiRespond(200, [
                    'success' => true,
                    'active' => true,
                    'roomCode' => $room['roomCode'],
                    'hostUserId' => $hostId,
                    'hostUserName' => (string)$hostUserName,
                    'version' => (int)$room['version'],
                    'members' => array_values($presence['members'] ?? []),
                    'seats' => $room['roomState']['seats'] ?? [],
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

            case 'publish_sandbox_to_lobby':
                $userRole = strtolower((string)($_SESSION['role'] ?? ''));
                if (!in_array($userRole, ['super_admin', 'admin', 'administrator'], true)) {
                    sandboxApiRespond(403, ['error' => 'Only administrators can publish games to the lobby.']);
                    return true;
                }
                $rawPayload = $_POST['payload'] ?? '{}';
                if (!is_string($rawPayload) || !SandboxStateSchema::isValidJson($rawPayload)) {
                    sandboxApiRespond(422, ['error' => 'Invalid publish payload JSON.']);
                    break;
                }
                $payload = json_decode($rawPayload, true);
                if (!is_array($payload)) {
                    sandboxApiRespond(422, ['error' => 'Invalid publish payload.']);
                    break;
                }
                $published = $sandboxService->publishToLobby($sandbox, $payload, $currentUserId, $currentUserName);
                sandboxApiRespond(200, ['success' => true] + $published);
                break;

            case 'unpublish_sandbox_from_lobby':
                $userRole = strtolower((string)($_SESSION['role'] ?? ''));
                if (!in_array($userRole, ['super_admin', 'admin', 'administrator'], true)) {
                    sandboxApiRespond(403, ['error' => 'Only administrators can unpublish lobby games.']);
                    return true;
                }
                $sandboxService->unpublishFromLobby($sandbox);
                sandboxApiRespond(200, ['success' => true]);
                break;
        }
    } catch (ValidationException $e) {
        sandboxApiRespond(422, ['error' => $e->getMessage()]);
    } catch (ResourceLockedException $e) {
        sandboxApiRespond(423, ['error' => $e->getMessage()]);
    } catch (\RuntimeException $e) {
        sandboxApiRespond(502, ['error' => $e->getMessage()]);
    }
    return true;
}

function sandboxApiLoadAuthorized(
    BgSandboxService $sandboxService,
    ProjectService $projectService,
    int $userId,
    bool $allowRoomCodeLookup = false
): ?BgSandbox {
    $sandboxId = filter_var($_POST['sandbox_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (($sandboxId === false || $sandboxId === null) && $allowRoomCodeLookup) {
        $roomCode = trim((string)($_POST['room_code'] ?? ''));
        if ($roomCode !== '') {
            $room = $sandboxService->getActiveRoomByCode($roomCode);
            if ($room !== null) {
                $sandboxId = (int)$room['sandboxId'];
            }
        }
    }
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
