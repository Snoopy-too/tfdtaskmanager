<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\ResourceLockedException;
use App\Application\Exceptions\ValidationException;
use App\Domain\Entities\BgSandbox;
use App\Domain\Repositories\BgSandboxRepositoryInterface;
use App\Domain\Sandbox\InvalidSandboxStateException;
use App\Domain\Sandbox\SandboxObjectType;
use App\Domain\Sandbox\SandboxStateSchema;
use App\Infrastructure\Http\LobbyPublishClient;

/**
 * Application use-cases for the Playtest Sandbox module.
 *
 * Project-level authorization (does the user see this project?) is the caller's
 * responsibility via ProjectService; this service enforces sandbox invariants and edit locks.
 */
final class BgSandboxService
{
    public function __construct(
        private readonly BgSandboxRepositoryInterface $repository,
        private readonly SandboxStateSchema $schema,
        private readonly ?LobbyPublishClient $lobbyClient = null,
    ) {
    }

    /** @return BgSandbox[] */
    public function getSandboxesByProject(int $projectId): array
    {
        return $this->repository->findByProjectId($projectId);
    }

    public function getSandboxById(int $id): ?BgSandbox
    {
        return $id > 0 ? $this->repository->findById($id) : null;
    }

    /**
     * @throws ValidationException
     */
    public function createSandbox(int $projectId, string $name, int $playerCount, int $userId): BgSandbox
    {
        $name = trim($name);
        if ($name === '') {
            throw new ValidationException('Sandbox name cannot be empty.');
        }
        $nameLen = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
        if ($nameLen > BgSandbox::MAX_NAME_LENGTH) {
            throw new ValidationException('Sandbox name must be ' . BgSandbox::MAX_NAME_LENGTH . ' characters or fewer.');
        }
        if ($playerCount < BgSandbox::MIN_PLAYERS || $playerCount > BgSandbox::MAX_PLAYERS) {
            throw new ValidationException('Player count must be between ' . BgSandbox::MIN_PLAYERS . ' and ' . BgSandbox::MAX_PLAYERS . '.');
        }

        return $this->repository->save(new BgSandbox(
            id: null,
            projectId: $projectId,
            name: $name,
            defaultPlayerCount: $playerCount,
            tableState: $this->schema->createInitial($playerCount),
            startingSnapshot: null,
            createdBy: $userId,
        ));
    }

    /**
     * Validates and persists a client-submitted table state JSON document.
     *
     * @throws ValidationException|ResourceLockedException
     */
    public function saveTableState(BgSandbox $sandbox, string $rawJson, int $userId): BgSandbox
    {
        $this->assertNotLockedByOther($sandbox, $userId);

        if (strlen($rawJson) > SandboxStateSchema::MAX_STATE_BYTES) {
            throw new ValidationException('Sandbox state is too large to save.');
        }
        if (!SandboxStateSchema::isValidJson($rawJson)) {
            throw new ValidationException('Sandbox state is not valid JSON.');
        }
        $decoded = json_decode($rawJson, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new ValidationException('Sandbox state must be a JSON object.');
        }

        try {
            $normalized = $this->schema->normalize($decoded);
        } catch (InvalidSandboxStateException $e) {
            throw new ValidationException('Invalid sandbox state: ' . $e->getMessage(), 0, $e);
        }

        $saved = $this->repository->save($sandbox->withTableState($normalized));
        $this->repository->updateLock((int)$sandbox->id, $userId, date('Y-m-d H:i:s'));
        return $saved;
    }

    /**
     * Freezes the current table layout into `startingSnapshot` (spec §5.1.5).
     *
     * @throws ValidationException|ResourceLockedException
     */
    public function saveStartingSnapshot(BgSandbox $sandbox, ?string $rawJson, int $userId): BgSandbox
    {
        $this->assertNotLockedByOther($sandbox, $userId);

        if ($rawJson !== null && trim($rawJson) !== '') {
            if (strlen($rawJson) > SandboxStateSchema::MAX_STATE_BYTES || !SandboxStateSchema::isValidJson($rawJson)) {
                throw new ValidationException('Invalid starting snapshot JSON.');
            }
            $decoded = json_decode($rawJson, true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new ValidationException('Snapshot must be a JSON object.');
            }
            try {
                $snapshot = $this->schema->normalize($decoded);
            } catch (InvalidSandboxStateException $e) {
                throw new ValidationException('Invalid snapshot: ' . $e->getMessage(), 0, $e);
            }
            return $this->repository->save($sandbox->withTableState($snapshot)->withStartingSnapshot($snapshot));
        }

        return $this->repository->save($sandbox->withStartingSnapshot($sandbox->tableState));
    }

    /**
     * Resets `tableState` back to `startingSnapshot` (and shuffles decks with `autoShuffleOnReset: true`).
     *
     * @throws ValidationException|ResourceLockedException
     */
    public function resetToStartingSnapshot(BgSandbox $sandbox, int $userId): BgSandbox
    {
        $this->assertNotLockedByOther($sandbox, $userId);
        $snapshot = $sandbox->startingSnapshot ?? $sandbox->tableState;
        if (isset($snapshot['boardObjects']) && is_array($snapshot['boardObjects'])) {
            foreach ($snapshot['boardObjects'] as $id => $obj) {
                if (($obj['type'] ?? '') === 'DECK' && !empty($obj['autoShuffleOnReset']) && !empty($obj['cardIds']) && is_array($obj['cardIds'])) {
                    $cards = array_values($obj['cardIds']);
                    shuffle($cards);
                    $snapshot['boardObjects'][$id]['cardIds'] = $cards;
                }
            }
        }
        return $this->repository->save($sandbox->withTableState($snapshot));
    }

    /**
     * Applies server-side hidden information filtering (spec §7.3).
     * Strips `frontImageUrl`, `name`, and `metadata` from cards in other seats' hands or face-down inside decks,
     * unless the inspecting user is in `godMode` or is explicitly peeking at the card.
     *
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    public function redactForSeat(array $state, ?int $viewerSeat, int $viewerUserId, bool $godMode = false): array
    {
        if ($godMode || ($state['mode'] ?? 'SETUP') === 'SETUP') {
            return $state;
        }

        $deckCardIds = [];
        $handOwnerByCard = [];
        foreach (($state['seats'] ?? []) as $seat) {
            $sIdx = (int)($seat['seatIndex'] ?? 0);
            foreach (($seat['handCardIds'] ?? []) as $cid) {
                $handOwnerByCard[(string)$cid] = $sIdx;
            }
        }
        foreach (($state['boardObjects'] ?? []) as $obj) {
            if (($obj['type'] ?? '') === 'DECK') {
                foreach (($obj['cardIds'] ?? []) as $cid) {
                    $deckCardIds[(string)$cid] = empty($obj['faceUp']);
                }
            }
        }

        foreach (($state['boardObjects'] ?? []) as $id => $obj) {
            if (($obj['type'] ?? '') !== 'CARD') {
                continue;
            }
            $peekingIds = is_array($obj['peekingUserIds'] ?? null) ? $obj['peekingUserIds'] : [];
            $isPeeking = in_array($viewerUserId, $peekingIds, true);
            $inHandOf = $handOwnerByCard[$id] ?? ($obj['ownerSeat'] ?? null);
            $inFaceDownDeck = $deckCardIds[$id] ?? false;
            $faceUp = !empty($obj['faceUp']);

            $visible = false;
            if ($inHandOf !== null) {
                $visible = ($viewerSeat !== null && (int)$inHandOf === $viewerSeat);
            } elseif ($inFaceDownDeck) {
                $visible = $isPeeking;
            } else {
                $visible = $faceUp || $isPeeking;
            }

            if (!$visible) {
                $state['boardObjects'][$id]['frontImageUrl'] = null;
                $state['boardObjects'][$id]['name'] = 'Hidden Card';
                $state['boardObjects'][$id]['metadata'] = [];
                $state['boardObjects'][$id]['rowData'] = [];
            }
        }

        return $state;
    }

    /** @return array<string,mixed>|null */
    public function getActiveRoom(BgSandbox $sandbox): ?array
    {
        return $sandbox->id !== null ? $this->repository->findRoomBySandboxId($sandbox->id) : null;
    }

    /** @return array<string,mixed>|null */
    public function getActiveRoomByCode(string $roomCode): ?array
    {
        return $this->repository->findRoomByCode($roomCode);
    }

    /** @return array<int,array<string,mixed>> */
    public function getActiveRoomsForProject(int $projectId): array
    {
        return $this->repository->findRoomsByProjectId($projectId);
    }

    /**
     * Hosts (or explicitly resets) a live multiplayer room initialized from `startingSnapshot` (or `tableState`).
     * Never resets an already-active room unless `$forceReset` is true.
     *
     * @return array<string,mixed>
     */
    public function hostLiveSession(BgSandbox $sandbox, int $hostUserId, bool $forceReset = false): array
    {
        if (!$forceReset && $sandbox->id !== null) {
            $existing = $this->repository->findRoomBySandboxId($sandbox->id);
            if ($existing !== null) {
                return $existing;
            }
        }

        $initial = $sandbox->startingSnapshot ?? $sandbox->tableState;
        $initial['mode'] = 'PLAY';
        return $this->repository->createOrResetRoom((int)$sandbox->id, $hostUserId, $initial);
    }

    /**
     * Joins an existing live room, registers member presence, and auto-assigns an open seat if needed.
     *
     * @return array<string,mixed>|null
     */
    public function joinLiveSession(BgSandbox $sandbox, int $userId, string $userName, bool $createIfMissing = false): ?array
    {
        $room = $this->getActiveRoom($sandbox);
        if ($room === null) {
            if (!$createIfMissing) {
                return null;
            }
            $this->hostLiveSession($sandbox, $userId, false);
        }

        $shortName = $this->truncateName($userName, 'Tester #' . $userId);
        $outcome = $this->repository->mutateRoom((int)$sandbox->id, function (array $room) use ($sandbox, $userId, $shortName): array {
            $roomState = is_array($room['roomState']) ? $room['roomState'] : $sandbox->tableState;
            $now = time();
            $presence = $this->prunePresence(is_array($room['presenceState']) ? $room['presenceState'] : [], $now);
            $presence['members'][(string)$userId] = [
                'userId' => $userId,
                'name' => $shortName,
                'ts' => $now,
            ];

            $assignedSeat = null;
            $seatChanged = false;
            foreach (($roomState['seats'] ?? []) as $s) {
                if (isset($s['assignedUserId']) && (int)$s['assignedUserId'] === $userId) {
                    $assignedSeat = (int)$s['seatIndex'];
                    break;
                }
            }

            if ($assignedSeat === null && isset($roomState['seats']) && is_array($roomState['seats'])) {
                foreach ($roomState['seats'] as $i => $s) {
                    if (($s['assignedUserId'] ?? null) === null) {
                        $assignedSeat = (int)($s['seatIndex'] ?? ($i + 1));
                        $roomState['seats'][$i]['assignedUserId'] = $userId;
                        $roomState['seats'][$i]['playerName'] = $shortName;
                        $presence['seats'][(string)$userId] = $assignedSeat;
                        $seatChanged = true;
                        break;
                    }
                }
            }

            return [
                'roomState' => $roomState,
                'presenceState' => $presence,
                'stateChanged' => $seatChanged,
                'presenceChanged' => true,
                'bumpVersion' => $seatChanged,
                'result' => [
                    'assignedSeat' => $assignedSeat,
                ],
            ];
        });

        if ($outcome === null) {
            return null;
        }

        $updatedRoom = $outcome['room'];
        $assignedSeat = $outcome['result']['assignedSeat'] ?? null;

        return [
            'roomCode' => $updatedRoom['roomCode'],
            'hostUserId' => (int)$updatedRoom['hostUserId'],
            'version' => (int)$updatedRoom['version'],
            'assignedSeat' => $assignedSeat,
            'state' => $this->presentRoomState($sandbox, $updatedRoom['roomState'], $assignedSeat, $userId, false),
            'presence' => $updatedRoom['presenceState'],
        ];
    }

    /**
     * Ends an active live room. Only the host may end it while they remain active.
     *
     * @throws ResourceLockedException
     */
    public function endLiveSession(BgSandbox $sandbox, int $userId): void
    {
        $room = $this->getActiveRoom($sandbox);
        if ($room === null) {
            return;
        }
        $presence = $this->prunePresence(is_array($room['presenceState']) ? $room['presenceState'] : [], time());
        $hostId = (int)$room['hostUserId'];
        $hostOnline = isset($presence['members'][(string)$hostId]);
        if ($hostId !== $userId && $hostOnline) {
            throw new ResourceLockedException('Only the active room host can end this live room.');
        }
        $this->repository->deleteRoom((int)$sandbox->id);
    }

    /**
     * Synchronizes a live multiplayer room action (seat claim, soft-lock, cursor move, ping, or state commit).
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     * @throws ResourceLockedException
     */
    public function syncLiveRoom(BgSandbox $sandbox, int $userId, string $userName, array $payload): array
    {
        $shortName = $this->truncateName($userName, 'Tester #' . $userId);
        $subAction = (string)($payload['subAction'] ?? 'POLL');

        $outcome = $this->repository->mutateRoom((int)$sandbox->id, function (array $room) use ($sandbox, $userId, $shortName, $subAction, $payload): array {
            $roomState = is_array($room['roomState']) ? $room['roomState'] : $sandbox->tableState;
            $now = time();
            $presence = $this->prunePresence(is_array($room['presenceState']) ? $room['presenceState'] : [], $now);
            $stateChanged = false;
            $bumpVersion = false;

            if ($subAction === 'LEAVE_ROOM') {
                unset($presence['members'][(string)$userId], $presence['cursors'][(string)$userId], $presence['seats'][(string)$userId]);
                if ($this->vacateUserSeats($roomState, $userId)) {
                    $stateChanged = true;
                    $bumpVersion = true;
                }
                if ($this->releaseUserObjectLocks($roomState, $userId)) {
                    $stateChanged = true;
                    $bumpVersion = true;
                }
                return [
                    'roomState' => $roomState,
                    'presenceState' => $presence,
                    'stateChanged' => $stateChanged,
                    'presenceChanged' => true,
                    'bumpVersion' => $bumpVersion,
                    'result' => ['left' => true],
                ];
            }

            $presence['members'][(string)$userId] = [
                'userId' => $userId,
                'name' => $shortName,
                'ts' => $now,
            ];

            if ($this->releaseStaleObjectLocks($roomState, $presence['members'])) {
                $stateChanged = true;
                $bumpVersion = true;
            }

            if ($subAction === 'CLAIM_SEAT') {
                $seatIdx = isset($payload['seatIndex']) && $payload['seatIndex'] !== null && $payload['seatIndex'] !== ''
                    ? (int)$payload['seatIndex']
                    : null;
                if ($seatIdx !== null) {
                    foreach (($roomState['seats'] ?? []) as $s) {
                        if ((int)($s['seatIndex'] ?? 0) === $seatIdx) {
                            $holder = $s['assignedUserId'] ?? null;
                            if ($holder !== null && (int)$holder !== $userId) {
                                throw new ResourceLockedException('That seat is already claimed by another player.');
                            }
                        }
                    }
                }
                $this->vacateUserSeats($roomState, $userId);
                if ($seatIdx !== null) {
                    foreach (($roomState['seats'] ?? []) as $i => $s) {
                        if ((int)($s['seatIndex'] ?? 0) === $seatIdx) {
                            $roomState['seats'][$i]['assignedUserId'] = $userId;
                            $roomState['seats'][$i]['playerName'] = $shortName;
                        }
                    }
                }
                $presence['seats'][(string)$userId] = $seatIdx;
                $stateChanged = true;
                $bumpVersion = true;
            } elseif ($subAction === 'RELEASE_SEAT') {
                $seatIdx = isset($payload['seatIndex']) ? (int)$payload['seatIndex'] : 0;
                if ($seatIdx > 0 && ((int)$room['hostUserId'] === $userId)) {
                    foreach (($roomState['seats'] ?? []) as $i => $s) {
                        if ((int)($s['seatIndex'] ?? 0) === $seatIdx) {
                            $holder = $s['assignedUserId'] ?? null;
                            $roomState['seats'][$i]['assignedUserId'] = null;
                            $roomState['seats'][$i]['playerName'] = 'Player ' . $seatIdx;
                            if ($holder !== null) {
                                unset($presence['seats'][(string)$holder]);
                            }
                            $stateChanged = true;
                            $bumpVersion = true;
                        }
                    }
                }
            } elseif ($subAction === 'LOCK_REQUEST') {
                $objId = (string)($payload['objectId'] ?? '');
                if ($objId !== '' && isset($roomState['boardObjects'][$objId])) {
                    $curLock = $roomState['boardObjects'][$objId]['lockedByUserId'] ?? null;
                    if ($curLock === null || (int)$curLock === $userId) {
                        $roomState['boardObjects'][$objId]['lockedByUserId'] = $userId;
                        $stateChanged = true;
                        $bumpVersion = true;
                    }
                }
            } elseif ($subAction === 'OBJECT_DRAG_MOVE') {
                $objId = (string)($payload['objectId'] ?? '');
                if ($objId !== '' && isset($roomState['boardObjects'][$objId])) {
                    $curLock = $roomState['boardObjects'][$objId]['lockedByUserId'] ?? null;
                    if ($curLock === null || (int)$curLock === $userId) {
                        $roomState['boardObjects'][$objId]['x'] = round((float)($payload['x'] ?? 0), 2);
                        $roomState['boardObjects'][$objId]['y'] = round((float)($payload['y'] ?? 0), 2);
                        $roomState['boardObjects'][$objId]['lockedByUserId'] = $userId;
                        if (($roomState['boardObjects'][$objId]['type'] ?? '') === 'CARD') {
                            $this->syncTuckedChildrenPositions($roomState['boardObjects'], $objId);
                        }
                        $stateChanged = true;
                        $bumpVersion = true;
                    }
                }
            } elseif ($subAction === 'OBJECT_DROP') {
                $objId = (string)($payload['objectId'] ?? '');
                if ($objId !== '' && isset($roomState['boardObjects'][$objId])) {
                    $curLock = $roomState['boardObjects'][$objId]['lockedByUserId'] ?? null;
                    if ($curLock === null || (int)$curLock === $userId) {
                        if (isset($payload['x'])) {
                            $roomState['boardObjects'][$objId]['x'] = round((float)$payload['x'], 2);
                        }
                        if (isset($payload['y'])) {
                            $roomState['boardObjects'][$objId]['y'] = round((float)$payload['y'], 2);
                        }
                        if (isset($payload['zIndex']) && is_numeric($payload['zIndex'])) {
                            $objType = SandboxObjectType::tryFrom((string)($roomState['boardObjects'][$objId]['type'] ?? ''));
                            if ($objType !== null) {
                                $roomState['boardObjects'][$objId]['zIndex'] = $objType->clampZ((int)$payload['zIndex']);
                            }
                        }
                        $roomState['boardObjects'][$objId]['lockedByUserId'] = null;
                        if (($roomState['boardObjects'][$objId]['type'] ?? '') === 'CARD') {
                            $this->syncTuckedChildrenPositions($roomState['boardObjects'], $objId);
                        }
                        $stateChanged = true;
                        $bumpVersion = true;
                    }
                }
            } elseif ($subAction === 'COMMIT_STATE' && isset($payload['state']) && is_string($payload['state']) && SandboxStateSchema::isValidJson($payload['state'])) {
                $decoded = json_decode($payload['state'], true);
                if (is_array($decoded)) {
                    try {
                        $normalized = $this->schema->normalize($decoded);
                        $normalized = $this->mergeAuthoritativeCardSecrets($normalized, $roomState);
                        $normalized = $this->preserveSeatOwnership($normalized, $roomState);
                        $roomState = $normalized;
                        $stateChanged = true;
                        $bumpVersion = true;
                    } catch (InvalidSandboxStateException $e) {
                        error_log('[BgSandboxService] Ignored invalid COMMIT_STATE: ' . $e->getMessage());
                    }
                }
            }

            if (isset($payload['cursor']) && is_array($payload['cursor'])) {
                $presence['cursors'][(string)$userId] = [
                    'userId' => $userId,
                    'name' => $shortName,
                    'color' => (string)($payload['cursor']['color'] ?? '#38BDF8'),
                    'x' => round((float)($payload['cursor']['x'] ?? 0), 1),
                    'y' => round((float)($payload['cursor']['y'] ?? 0), 1),
                    'ts' => $now,
                ];
            }

            if (isset($payload['ping']) && is_array($payload['ping'])) {
                $presence['pings'][] = [
                    'id' => 'ping_' . bin2hex(random_bytes(3)),
                    'userId' => $userId,
                    'name' => $shortName,
                    'color' => (string)($payload['ping']['color'] ?? '#F43F5E'),
                    'x' => round((float)($payload['ping']['x'] ?? 0), 1),
                    'y' => round((float)($payload['ping']['y'] ?? 0), 1),
                    'ts' => $now,
                ];
            }

            return [
                'roomState' => $roomState,
                'presenceState' => $presence,
                'stateChanged' => $stateChanged,
                'presenceChanged' => true,
                'bumpVersion' => $bumpVersion,
                'result' => ['left' => false],
            ];
        });

        if ($outcome === null) {
            return [
                'roomEnded' => true,
                'roomCode' => null,
                'version' => 0,
            ];
        }

        $updatedRoom = $outcome['room'];
        if (!empty($outcome['result']['left'])) {
            return [
                'left' => true,
                'roomCode' => $updatedRoom['roomCode'],
                'version' => (int)$updatedRoom['version'],
            ];
        }

        $viewerSeat = isset($payload['viewerSeat']) && $payload['viewerSeat'] !== '' ? (int)$payload['viewerSeat'] : null;
        $godMode = !empty($payload['godMode']);

        return [
            'roomCode' => $updatedRoom['roomCode'],
            'hostUserId' => (int)$updatedRoom['hostUserId'],
            'version' => (int)$updatedRoom['version'],
            'state' => $this->redactForSeat($updatedRoom['roomState'], $viewerSeat, $userId, $godMode),
            'presence' => $updatedRoom['presenceState'],
        ];
    }

    /**
     * Client-facing live room state with sandbox identity fields and seat-based redaction.
     *
     * @param array<string,mixed> $roomState
     * @return array<string,mixed>
     */
    public function presentRoomState(BgSandbox $sandbox, array $roomState, ?int $viewerSeat, int $viewerUserId, bool $godMode = false): array
    {
        return [
            'sandboxId' => $sandbox->id,
            'projectId' => $sandbox->projectId,
            'name' => $sandbox->name,
        ] + $this->redactForSeat($roomState, $viewerSeat, $viewerUserId, $godMode);
    }

    /** @param array<string,mixed> $presence @return array<string,mixed> */
    public function prunePresence(array $presence, ?int $now = null): array
    {
        $now ??= time();
        $cursors = is_array($presence['cursors'] ?? null) ? $presence['cursors'] : [];
        foreach ($cursors as $uid => $c) {
            if (($now - (int)($c['ts'] ?? 0)) > 12) {
                unset($cursors[$uid]);
            }
        }
        $members = is_array($presence['members'] ?? null) ? $presence['members'] : [];
        foreach ($members as $uid => $m) {
            if (($now - (int)($m['ts'] ?? 0)) > 15) {
                unset($members[$uid]);
            }
        }
        $pings = array_values(array_filter(
            is_array($presence['pings'] ?? null) ? $presence['pings'] : [],
            fn (mixed $p): bool => is_array($p) && (($now - (int)($p['ts'] ?? 0)) <= 4)
        ));
        return [
            'cursors' => $cursors,
            'pings' => $pings,
            'seats' => is_array($presence['seats'] ?? null) ? $presence['seats'] : [],
            'members' => $members,
        ];
    }

    /** @param array<string,mixed> $roomState */
    private function vacateUserSeats(array &$roomState, int $userId): bool
    {
        $changed = false;
        foreach (($roomState['seats'] ?? []) as $i => $s) {
            if (isset($s['assignedUserId']) && (int)$s['assignedUserId'] === $userId) {
                $seatIdx = (int)($s['seatIndex'] ?? ($i + 1));
                $roomState['seats'][$i]['assignedUserId'] = null;
                $roomState['seats'][$i]['playerName'] = 'Player ' . $seatIdx;
                $changed = true;
            }
        }
        return $changed;
    }

    /** @param array<string,mixed> $roomState */
    private function releaseUserObjectLocks(array &$roomState, int $userId): bool
    {
        $changed = false;
        if (!isset($roomState['boardObjects']) || !is_array($roomState['boardObjects'])) {
            return false;
        }
        foreach ($roomState['boardObjects'] as $id => $obj) {
            if (is_array($obj) && isset($obj['lockedByUserId']) && (int)$obj['lockedByUserId'] === $userId) {
                $roomState['boardObjects'][$id]['lockedByUserId'] = null;
                $changed = true;
            }
        }
        return $changed;
    }

    /**
     * Releases object drag locks held by users no longer active in presence.
     *
     * @param array<string,mixed> $roomState
     * @param array<string,mixed> $activeMembers
     */
    private function releaseStaleObjectLocks(array &$roomState, array $activeMembers): bool
    {
        $changed = false;
        if (!isset($roomState['boardObjects']) || !is_array($roomState['boardObjects'])) {
            return false;
        }
        foreach ($roomState['boardObjects'] as $id => $obj) {
            if (!is_array($obj)) {
                continue;
            }
            $lockUid = $obj['lockedByUserId'] ?? null;
            if ($lockUid !== null && !isset($activeMembers[(string)$lockUid])) {
                $roomState['boardObjects'][$id]['lockedByUserId'] = null;
                $changed = true;
            }
        }
        return $changed;
    }

    /** @param array<string,mixed> $boardObjects */
    private function syncTuckedChildrenPositions(array &$boardObjects, string $parentId): void
    {
        $parent = $boardObjects[$parentId] ?? null;
        if (!is_array($parent) || empty($parent['tuckedChildren']) || !is_array($parent['tuckedChildren'])) {
            return;
        }
        $px = (float)($parent['x'] ?? 0);
        $py = (float)($parent['y'] ?? 0);
        $pz = (int)($parent['zIndex'] ?? 350);
        foreach ($parent['tuckedChildren'] as $i => $tc) {
            if (!is_array($tc)) {
                continue;
            }
            $cid = (string)($tc['cardId'] ?? '');
            if ($cid === '' || !isset($boardObjects[$cid]) || !is_array($boardObjects[$cid])) {
                continue;
            }
            $offsetX = (float)($tc['offsetX'] ?? 0);
            $offsetY = (float)($tc['offsetY'] ?? (-35 * ($i + 1)));
            $zOffset = (int)($tc['zIndexOffset'] ?? -($i + 1));
            $boardObjects[$cid]['parentId'] = $parentId;
            $boardObjects[$cid]['x'] = round($px + $offsetX, 2);
            $boardObjects[$cid]['y'] = round($py + $offsetY, 2);
            $boardObjects[$cid]['zIndex'] = SandboxObjectType::Card->clampZ($pz + $zOffset);
        }
    }

    /**
     * Prevents a non-omniscient client from overwriting hidden cards with redacted placeholders.
     *
     * @param array<string,mixed> $incoming
     * @param array<string,mixed> $existing
     * @return array<string,mixed>
     */
    private function mergeAuthoritativeCardSecrets(array $incoming, array $existing): array
    {
        $existingObjects = is_array($existing['boardObjects'] ?? null) ? $existing['boardObjects'] : [];
        foreach (($incoming['boardObjects'] ?? []) as $id => $obj) {
            if (($obj['type'] ?? '') !== 'CARD') {
                continue;
            }
            $prev = $existingObjects[$id] ?? null;
            if (!is_array($prev) || ($prev['type'] ?? '') !== 'CARD') {
                continue;
            }
            $looksRedacted = ($obj['name'] ?? '') === 'Hidden Card'
                && empty($obj['frontImageUrl'])
                && empty($obj['metadata'])
                && empty($obj['rowData']);
            if ($looksRedacted && ($prev['name'] ?? '') !== 'Hidden Card') {
                $incoming['boardObjects'][$id]['name'] = $prev['name'];
                $incoming['boardObjects'][$id]['frontImageUrl'] = $prev['frontImageUrl'] ?? null;
                $incoming['boardObjects'][$id]['metadata'] = $prev['metadata'] ?? [];
                if (array_key_exists('rowData', $prev)) {
                    $incoming['boardObjects'][$id]['rowData'] = $prev['rowData'];
                }
            }
        }
        return $incoming;
    }

    /**
     * Keeps authoritative seat claims intact across concurrent state commits.
     *
     * @param array<string,mixed> $incoming
     * @param array<string,mixed> $existing
     * @return array<string,mixed>
     */
    private function preserveSeatOwnership(array $incoming, array $existing): array
    {
        $byIdx = [];
        foreach (($existing['seats'] ?? []) as $s) {
            if (isset($s['seatIndex'])) {
                $byIdx[(int)$s['seatIndex']] = $s;
            }
        }
        foreach (($incoming['seats'] ?? []) as $i => $s) {
            $idx = (int)($s['seatIndex'] ?? 0);
            if (isset($byIdx[$idx])) {
                $incoming['seats'][$i]['assignedUserId'] = $byIdx[$idx]['assignedUserId'] ?? null;
                $incoming['seats'][$i]['playerName'] = $byIdx[$idx]['playerName'] ?? ('Player ' . $idx);
            }
        }
        return $incoming;
    }

    private function truncateName(string $name, string $fallback): string
    {
        $clean = trim($name) !== '' ? trim($name) : $fallback;
        return function_exists('mb_substr') ? mb_substr($clean, 0, 40) : substr($clean, 0, 40);
    }

    /**
     * @throws ResourceLockedException
     */
    public function deleteSandbox(BgSandbox $sandbox, int $userId): void
    {
        $this->assertNotLockedByOther($sandbox, $userId);
        $this->repository->delete((int)$sandbox->id);
    }

    public function acquireOrRefreshLock(BgSandbox $sandbox, int $userId): bool
    {
        if ($sandbox->id === null || $sandbox->isLockedByOther($userId)) {
            return false;
        }
        $this->repository->updateLock($sandbox->id, $userId, date('Y-m-d H:i:s'));
        return true;
    }

    public function releaseLock(BgSandbox $sandbox, int $userId): void
    {
        if ($sandbox->id !== null && $sandbox->lockedByUserId === $userId) {
            $this->repository->updateLock($sandbox->id, null, null);
        }
    }

    /**
     * Client-facing state: normalized table content plus authoritative identity fields.
     *
     * @return array<string,mixed>
     */
    public function presentState(BgSandbox $sandbox): array
    {
        return [
            'sandboxId' => $sandbox->id,
            'projectId' => $sandbox->projectId,
            'name' => $sandbox->name,
        ] + $sandbox->tableState;
    }

    /** Number of components on the table (for list summaries). */
    public function countComponents(BgSandbox $sandbox): int
    {
        $objects = $sandbox->tableState['boardObjects'] ?? [];
        return is_array($objects) ? count($objects) : 0;
    }

    private function assertNotLockedByOther(BgSandbox $sandbox, int $userId): void
    {
        if ($sandbox->isLockedByOther($userId)) {
            throw new ResourceLockedException('This sandbox is currently being edited by another user.');
        }
    }

    public function getLobbyPublicUrl(): string
    {
        return $this->lobbyClient ? $this->lobbyClient->getPublicUrl() : 'https://lobby.theflyingdutchmen.games';
    }

    /** @return array<string,mixed>|null */
    public function getPublication(int $sandboxId): ?array
    {
        return $sandboxId > 0 ? $this->repository->findPublicationBySandboxId($sandboxId) : null;
    }

    /** @return array<int,array<string,mixed>> */
    public function getPublicationsForProject(int $projectId): array
    {
        return $projectId > 0 ? $this->repository->findPublicationsByProjectId($projectId) : [];
    }

    /**
     * Publish (or update) this sandbox to the TFD Game Lobby as an Admin-Only playable game.
     *
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     * @throws ValidationException
     */
    public function publishToLobby(BgSandbox $sandbox, array $options, int $userId, string $username): array
    {
        if ($sandbox->id === null || $sandbox->id <= 0) {
            throw new ValidationException('Sandbox must be saved before publishing.');
        }
        if ($this->lobbyClient === null || !$this->lobbyClient->isConfigured()) {
            throw new \RuntimeException('Lobby publish integration is not configured.');
        }

        $name = trim((string)($options['name'] ?? $sandbox->name));
        if ($name === '') {
            throw new ValidationException('Published game title is required.');
        }
        $nameLen = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
        if ($nameLen > BgSandbox::MAX_NAME_LENGTH) {
            throw new ValidationException('Published game title must be ' . BgSandbox::MAX_NAME_LENGTH . ' characters or fewer.');
        }

        $description = trim((string)($options['description'] ?? ''));
        $descLen = function_exists('mb_strlen') ? mb_strlen($description) : strlen($description);
        if ($descLen > 2000) {
            throw new ValidationException('Description must be 2000 characters or fewer.');
        }

        $minPlayers = max(1, min(6, (int)($options['minPlayers'] ?? 1)));
        $maxPlayers = max($minPlayers, min(6, (int)($options['maxPlayers'] ?? max($minPlayers, $sandbox->defaultPlayerCount))));
        $playerCounts = range($minPlayers, $maxPlayers);

        $snapshotSource = (string)($options['snapshotSource'] ?? 'setup');
        $rawSnapshot = $options['snapshot'] ?? null;
        if (!is_array($rawSnapshot)) {
            $rawSnapshot = $snapshotSource === 'table'
                ? $sandbox->tableState
                : ($sandbox->startingSnapshot ?? $sandbox->tableState);
        }

        try {
            $cleanSnapshot = $this->schema->normalize($rawSnapshot);
        } catch (InvalidSandboxStateException $e) {
            throw new ValidationException('Invalid sandbox snapshot: ' . $e->getMessage());
        }

        $assets = $this->sanitizePublishAssets($options['assets'] ?? []);

        $response = $this->lobbyClient->publishSandbox([
            'sandboxId' => $sandbox->id,
            'projectId' => $sandbox->projectId,
            'name' => $name,
            'description' => $description,
            'minPlayers' => $minPlayers,
            'maxPlayers' => $maxPlayers,
            'playerCounts' => $playerCounts,
            'snapshot' => $cleanSnapshot,
            'assets' => $assets,
            'publishedBy' => $username,
        ]);

        $version = max(1, (int)($response['version'] ?? 1));
        $lobbyGameId = (string)($response['slug'] ?? ('sandbox-' . $sandbox->id));

        $publication = $this->repository->savePublication($sandbox->id, [
            'lobbyGameId' => $lobbyGameId,
            'publishedVersion' => $version,
            'description' => $description,
            'minPlayers' => $minPlayers,
            'maxPlayers' => $maxPlayers,
            'lastPublishedBy' => $userId > 0 ? $userId : null,
        ]);

        return [
            'publication' => $publication,
            'lobbyPublicUrl' => $this->getLobbyPublicUrl(),
        ];
    }

    /**
     * Remove a previously published sandbox from the TFD Game Lobby.
     */
    public function unpublishFromLobby(BgSandbox $sandbox): void
    {
        if ($sandbox->id === null || $sandbox->id <= 0) {
            throw new ValidationException('Invalid sandbox.');
        }
        if ($this->lobbyClient === null || !$this->lobbyClient->isConfigured()) {
            throw new \RuntimeException('Lobby publish integration is not configured.');
        }

        $this->lobbyClient->unpublishSandbox($sandbox->id);
        $this->repository->deletePublication($sandbox->id);
    }

    /**
     * @param mixed $rawAssets
     * @return list<array<string,string>>
     * @throws ValidationException
     */
    private function sanitizePublishAssets(mixed $rawAssets): array
    {
        if (!is_array($rawAssets)) {
            return [];
        }
        $allowedMimes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp', 'image/svg+xml'];
        $clean = [];
        foreach ($rawAssets as $item) {
            if (count($clean) >= 500) {
                break;
            }
            if (!is_array($item)) {
                continue;
            }
            $key = trim((string)($item['key'] ?? ''));
            $mime = strtolower(trim((string)($item['mimeType'] ?? '')));
            $data = (string)($item['dataBase64'] ?? '');
            if ($key === '' || $data === '' || !in_array($mime, $allowedMimes, true)) {
                continue;
            }
            $clean[] = [
                'key' => $key,
                'mimeType' => $mime,
                'dataBase64' => $data,
            ];
        }
        return $clean;
    }
}
