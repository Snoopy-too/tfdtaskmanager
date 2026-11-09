<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\ResourceLockedException;
use App\Application\Exceptions\ValidationException;
use App\Domain\Entities\BgSandbox;
use App\Domain\Repositories\BgSandboxRepositoryInterface;
use App\Domain\Sandbox\InvalidSandboxStateException;
use App\Domain\Sandbox\SandboxStateSchema;

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
        if (mb_strlen($name) > BgSandbox::MAX_NAME_LENGTH) {
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
        if (!json_validate($rawJson)) {
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
            if (strlen($rawJson) > SandboxStateSchema::MAX_STATE_BYTES || !json_validate($rawJson)) {
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
            }
        }

        return $state;
    }

    /**
     * Hosts (or resets) a live multiplayer room initialized from `startingSnapshot` (or `tableState`).
     *
     * @return array<string,mixed>
     */
    public function hostLiveSession(BgSandbox $sandbox, int $hostUserId): array
    {
        $initial = $sandbox->startingSnapshot ?? $sandbox->tableState;
        $initial['mode'] = 'PLAY';
        return $this->repository->createOrResetRoom((int)$sandbox->id, $hostUserId, $initial);
    }

    /**
     * Synchronizes a live multiplayer room action (seat claim, soft-lock, cursor move, ping, or state commit).
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function syncLiveRoom(BgSandbox $sandbox, int $userId, string $userName, array $payload): array
    {
        $room = $this->repository->findRoomBySandboxId((int)$sandbox->id);
        if ($room === null) {
            $room = $this->hostLiveSession($sandbox, $userId);
        }

        $roomState = is_array($room['roomState']) ? $room['roomState'] : $sandbox->tableState;
        $presence = is_array($room['presenceState']) ? $room['presenceState'] : ['cursors' => [], 'pings' => [], 'seats' => []];
        $now = time();
        $bumpVersion = false;

        // Clean stale cursors (>12s) and pings (>4s)
        foreach (($presence['cursors'] ?? []) as $uid => $c) {
            if (($now - (int)($c['ts'] ?? 0)) > 12) {
                unset($presence['cursors'][$uid]);
            }
        }
        $presence['pings'] = array_values(array_filter(
            is_array($presence['pings'] ?? null) ? $presence['pings'] : [],
            fn (mixed $p): bool => is_array($p) && (($now - (int)($p['ts'] ?? 0)) <= 4)
        ));

        $subAction = (string)($payload['subAction'] ?? 'POLL');

        if ($subAction === 'CLAIM_SEAT') {
            $seatIdx = isset($payload['seatIndex']) ? (int)$payload['seatIndex'] : null;
            foreach (($roomState['seats'] ?? []) as $i => $s) {
                if (($s['assignedUserId'] ?? null) === $userId) {
                    $roomState['seats'][$i]['assignedUserId'] = null;
                }
                if ($seatIdx !== null && (int)($s['seatIndex'] ?? 0) === $seatIdx) {
                    $roomState['seats'][$i]['assignedUserId'] = $userId;
                    $roomState['seats'][$i]['playerName'] = $userName !== '' ? $userName : ('Player ' . $seatIdx);
                }
            }
            $presence['seats'][(string)$userId] = $seatIdx;
            $bumpVersion = true;
        } elseif ($subAction === 'LOCK_REQUEST') {
            $objId = (string)($payload['objectId'] ?? '');
            if ($objId !== '' && isset($roomState['boardObjects'][$objId])) {
                $curLock = $roomState['boardObjects'][$objId]['lockedByUserId'] ?? null;
                if ($curLock === null || (int)$curLock === $userId) {
                    $roomState['boardObjects'][$objId]['lockedByUserId'] = $userId;
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
                    $bumpVersion = true;
                }
            }
        } elseif ($subAction === 'OBJECT_DROP') {
            $objId = (string)($payload['objectId'] ?? '');
            if ($objId !== '' && isset($roomState['boardObjects'][$objId])) {
                if (isset($payload['x'])) {
                    $roomState['boardObjects'][$objId]['x'] = round((float)$payload['x'], 2);
                }
                if (isset($payload['y'])) {
                    $roomState['boardObjects'][$objId]['y'] = round((float)$payload['y'], 2);
                }
                $roomState['boardObjects'][$objId]['lockedByUserId'] = null;
                $bumpVersion = true;
            }
        } elseif ($subAction === 'COMMIT_STATE' && isset($payload['state']) && is_string($payload['state']) && json_validate($payload['state'])) {
            $decoded = json_decode($payload['state'], true);
            if (is_array($decoded)) {
                try {
                    $roomState = $this->schema->normalize($decoded);
                    $bumpVersion = true;
                } catch (InvalidSandboxStateException) {
                    // Ignore malformed state mutation
                }
            }
        }

        if (isset($payload['cursor']) && is_array($payload['cursor'])) {
            $presence['cursors'][(string)$userId] = [
                'userId' => $userId,
                'name' => mb_substr($userName !== '' ? $userName : 'Tester', 0, 40),
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
                'name' => mb_substr($userName !== '' ? $userName : 'Tester', 0, 40),
                'color' => (string)($payload['ping']['color'] ?? '#F43F5E'),
                'x' => round((float)($payload['ping']['x'] ?? 0), 1),
                'y' => round((float)($payload['ping']['y'] ?? 0), 1),
                'ts' => $now,
            ];
        }

        $this->repository->updateRoom((int)$room['id'], $roomState, $presence, $bumpVersion);
        $newVersion = $bumpVersion ? ((int)$room['version'] + 1) : (int)$room['version'];

        $viewerSeat = isset($payload['viewerSeat']) && $payload['viewerSeat'] !== '' ? (int)$payload['viewerSeat'] : null;
        $godMode = !empty($payload['godMode']);

        return [
            'roomCode' => $room['roomCode'],
            'version' => $newVersion,
            'state' => $this->redactForSeat($roomState, $viewerSeat, $userId, $godMode),
            'presence' => $presence,
        ];
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
}
