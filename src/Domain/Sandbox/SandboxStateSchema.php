<?php
declare(strict_types=1);

namespace App\Domain\Sandbox;

/**
 * Authoritative validator / normalizer for Playtest Sandbox table state (spec §4.2).
 *
 * - Unknown keys are dropped (whitelist), known keys are type-checked and bounded.
 * - Z-indexes are clamped into each component type's categorical tier.
 * - Identity fields (sandboxId, projectId, name) are NOT stored here; they come from columns.
 *
 * Stateless: register once in the DI container and inject where needed.
 */
final class SandboxStateSchema
{
    public const int MAX_STATE_BYTES = 2_000_000;
    public const int MAX_OBJECTS = 5000;
    public const array MODES = ['SETUP', 'PLAY'];
    public const array SEAT_COLORS = ['#E53935', '#1E88E5', '#43A047', '#FDD835', '#8E24AA', '#FB8C00'];

    private const int MAX_SEATS = 6;
    private const int MAX_ZONES = 200;
    private const int MAX_NOTES = 500;
    private const int MAX_LIST = 1000;
    private const int MAX_RESOURCES = 24;
    private const float COORD_LIMIT = 1_000_000.0;
    private const string ID_PATTERN = '/^[A-Za-z][A-Za-z0-9_\-]{0,63}$/';
    private const string REF_PATTERN = '/^[A-Za-z][A-Za-z0-9_\-:.]{0,95}$/';
    private const string RESOURCE_KEY_PATTERN = '/^\p{L}[\p{L}\p{N} _\-]{0,39}$/u';
    private const string COLOR_PATTERN = '/^#[0-9A-Fa-f]{6}$/';
    private const array ZONE_TYPES = ['MARKET_RIVER', 'DISCARD', 'SNAP_ZONE'];
    private const array DEFAULT_PHASES = ['Draw', 'Action Phase', 'Cleanup'];

    private const array COMMON_FIELDS = [
        'x' => 'coord', 'y' => 'coord', 'rotation' => 'angle', 'isLocked' => 'bool',
        'width' => 'size', 'height' => 'size', 'name' => 'label', 'label' => 'label', 'fill' => 'colorOrNull',
        'imageUrl' => 'urlOrNull', 'lockedByUserId' => 'intOrNull',
    ];

    private const array TYPE_FIELDS = [
        'BOARD' => ['assetId' => 'refOrNull', 'snapGrid' => 'snapGrid', 'snapPoints' => 'snapPoints'],
        'CARD' => [
            'cardAssetId' => 'refOrNull', 'frontImageUrl' => 'urlOrNull', 'backImageUrl' => 'urlOrNull',
            'isTapped' => 'bool', 'faceUp' => 'bool', 'ownerSeat' => 'seatOrNull',
            'parentId' => 'idOrNull', 'tuckedChildren' => 'tuckedChildren',
            'peekingUserIds' => 'intList', 'metadata' => 'metadataMap',
        ],
        'DECK' => [
            'backAssetId' => 'refOrNull', 'backImageUrl' => 'urlOrNull', 'faceUp' => 'bool', 'cardIds' => 'idList',
            'autoShuffleOnReset' => 'bool', 'linkedDiscardId' => 'idOrNull',
        ],
        'TOKEN' => ['assetId' => 'refOrNull', 'color' => 'colorOrNull'],
        'SPAWNER' => ['assetId' => 'refOrNull', 'infinite' => 'bool', 'color' => 'colorOrNull'],
        'DIE' => ['faces' => 'dieFaces', 'value' => 'dieValue', 'customFaces' => 'faceLabels', 'color' => 'colorOrNull'],
    ];

    /** @return array<string,mixed> */
    public function createInitial(int $playerCount): array
    {
        $seats = [];
        for ($i = 1; $i <= $playerCount; $i++) {
            $seats[] = [
                'seatIndex' => $i,
                'color' => self::SEAT_COLORS[$i - 1],
                'assignedUserId' => null,
                'playerName' => 'Player ' . $i,
                'handCardIds' => [],
                'resources' => ['Money' => 10, 'VP' => 0],
            ];
        }
        return [
            'mode' => 'SETUP',
            'turnState' => ['activeSeat' => 1, 'roundNumber' => 1, 'currentPhase' => self::DEFAULT_PHASES[0], 'phases' => self::DEFAULT_PHASES],
            'seats' => $seats,
            'boardObjects' => [],
            'zones' => [],
            'notes' => [],
            'scenarios' => [],
            'eventLog' => [],
        ];
    }

    /**
     * @param array<mixed> $raw
     * @return array<string,mixed>
     * @throws InvalidSandboxStateException
     */
    public function normalize(array $raw): array
    {
        $mode = $raw['mode'] ?? 'SETUP';
        if (!in_array($mode, self::MODES, true)) {
            throw new InvalidSandboxStateException('Invalid sandbox mode.');
        }

        return [
            'mode' => $mode,
            'turnState' => $this->turnState($this->map($raw['turnState'] ?? [], 'turnState')),
            'seats' => $this->seats($this->list($raw['seats'] ?? [], 'seats', self::MAX_SEATS)),
            'boardObjects' => $this->boardObjects($this->map($raw['boardObjects'] ?? [], 'boardObjects')),
            'zones' => array_map(fn (mixed $z): array => $this->zone($this->map($z, 'zone')), $this->list($raw['zones'] ?? [], 'zones', self::MAX_ZONES)),
            'notes' => array_map(fn (mixed $n): array => $this->note($this->map($n, 'note')), $this->list($raw['notes'] ?? [], 'notes', self::MAX_NOTES)),
            'scenarios' => $this->scenarios($this->list($raw['scenarios'] ?? [], 'scenarios', 25)),
            'eventLog' => $this->eventLog($this->list($raw['eventLog'] ?? [], 'eventLog', 200)),
        ];
    }

    /** @param array<mixed> $t */
    private function turnState(array $t): array
    {
        $phases = array_map(
            fn (mixed $p): string => $this->string($p, 'turnState.phases[]', 60, false),
            $this->list($t['phases'] ?? self::DEFAULT_PHASES, 'turnState.phases', 20)
        );
        return [
            'activeSeat' => $this->int($t['activeSeat'] ?? 1, 'turnState.activeSeat', 1, self::MAX_SEATS),
            'roundNumber' => $this->int($t['roundNumber'] ?? 1, 'turnState.roundNumber', 1, 99_999),
            'currentPhase' => $this->string($t['currentPhase'] ?? '', 'turnState.currentPhase', 60),
            'phases' => array_values($phases),
        ];
    }

    /** @param list<mixed> $rawSeats */
    private function seats(array $rawSeats): array
    {
        $seats = [];
        $seen = [];
        foreach ($rawSeats as $rawSeat) {
            $s = $this->map($rawSeat, 'seat');
            $index = $this->int($s['seatIndex'] ?? 0, 'seat.seatIndex', 1, self::MAX_SEATS);
            if (isset($seen[$index])) {
                throw new InvalidSandboxStateException("Duplicate seat index {$index}.");
            }
            $seen[$index] = true;
            $seats[] = [
                'seatIndex' => $index,
                'color' => $this->color($s['color'] ?? self::SEAT_COLORS[$index - 1], 'seat.color'),
                'assignedUserId' => isset($s['assignedUserId']) ? $this->int($s['assignedUserId'], 'seat.assignedUserId', 1, PHP_INT_MAX) : null,
                'playerName' => $this->string($s['playerName'] ?? ('Player ' . $index), 'seat.playerName', 60, false),
                'handCardIds' => $this->idList($s['handCardIds'] ?? [], 'seat.handCardIds'),
                'resources' => $this->resources($this->map($s['resources'] ?? [], 'seat.resources')),
            ];
        }
        return $seats;
    }

    /** @param array<mixed> $raw */
    private function resources(array $raw): array
    {
        if (count($raw) > self::MAX_RESOURCES) {
            throw new InvalidSandboxStateException('Too many resource trackers on a seat.');
        }
        $out = [];
        foreach ($raw as $key => $value) {
            if (!is_string($key) || !preg_match(self::RESOURCE_KEY_PATTERN, $key)) {
                throw new InvalidSandboxStateException('Invalid resource key.');
            }
            $out[$key] = $this->int($value, "resources.{$key}", -1_000_000_000, 1_000_000_000);
        }
        return $out;
    }

    /** @param array<mixed> $raw */
    private function boardObjects(array $raw): array
    {
        if (count($raw) > self::MAX_OBJECTS) {
            throw new InvalidSandboxStateException('Too many components on the table.');
        }
        $out = [];
        foreach ($raw as $key => $rawObj) {
            $obj = $this->boardObject($this->map($rawObj, 'boardObject'));
            if ($obj['id'] !== (string)$key) {
                throw new InvalidSandboxStateException('Board object key does not match its id.');
            }
            $out[$obj['id']] = $obj;
        }
        return $out;
    }

    /** @param array<mixed> $o */
    private function boardObject(array $o): array
    {
        $type = SandboxObjectType::tryFrom(is_string($o['type'] ?? null) ? $o['type'] : '')
            ?? throw new InvalidSandboxStateException('Unknown component type.');
        [$defaultW, $defaultH] = $type->defaultSize();

        $out = [
            'id' => $this->id($o['id'] ?? null, 'boardObject.id'),
            'type' => $type->value,
            'zIndex' => $type->clampZ($this->int($o['zIndex'] ?? 0, 'boardObject.zIndex', -100_000, 100_000)),
            'x' => 0.0, 'y' => 0.0, 'rotation' => 0.0, 'isLocked' => false,
            'width' => (float)$defaultW, 'height' => (float)$defaultH,
        ];
        foreach (self::COMMON_FIELDS + self::TYPE_FIELDS[$type->value] as $field => $kind) {
            if (array_key_exists($field, $o)) {
                $out[$field] = $this->field($kind, $o[$field], "{$type->value}.{$field}");
            }
        }
        return $out;
    }

    /** @param array<mixed> $z */
    private function zone(array $z): array
    {
        $type = $z['type'] ?? null;
        if (!in_array($type, self::ZONE_TYPES, true)) {
            throw new InvalidSandboxStateException('Unknown zone type.');
        }
        return [
            'id' => $this->id($z['id'] ?? null, 'zone.id'),
            'type' => $type,
            'name' => $this->string($z['name'] ?? '', 'zone.name', 150),
            'x' => $this->coord($z['x'] ?? 0, 'zone.x'),
            'y' => $this->coord($z['y'] ?? 0, 'zone.y'),
            'width' => $this->size($z['width'] ?? 200, 'zone.width'),
            'height' => $this->size($z['height'] ?? 200, 'zone.height'),
            'sourceDeckId' => $this->idOrNull($z['sourceDeckId'] ?? null, 'zone.sourceDeckId'),
            'slots' => $this->int($z['slots'] ?? 1, 'zone.slots', 1, 20),
            'autoReplenish' => $this->bool($z['autoReplenish'] ?? false, 'zone.autoReplenish'),
        ];
    }

    /** @param array<mixed> $n */
    private function note(array $n): array
    {
        return [
            'id' => $this->id($n['id'] ?? null, 'note.id'),
            'x' => $this->coord($n['x'] ?? 0, 'note.x'),
            'y' => $this->coord($n['y'] ?? 0, 'note.y'),
            'author' => $this->string($n['author'] ?? '', 'note.author', 60),
            'text' => $this->string($n['text'] ?? '', 'note.text', 2000),
            'targetId' => $this->idOrNull($n['targetId'] ?? null, 'note.targetId'),
        ];
    }

    private function field(string $kind, mixed $v, string $path): mixed
    {
        return match ($kind) {
            'coord' => $this->coord($v, $path),
            'angle' => $this->angle($v, $path),
            'size' => $this->size($v, $path),
            'bool' => $this->bool($v, $path),
            'label' => $this->string($v, $path, 150),
            'colorOrNull' => $v === null ? null : $this->color($v, $path),
            'refOrNull' => $this->refOrNull($v, $path),
            'idOrNull' => $this->idOrNull($v, $path),
            'idList' => $this->idList($v, $path),
            'seatOrNull' => $v === null ? null : $this->int($v, $path, 1, self::MAX_SEATS),
            'dieFaces' => $this->int($v, $path, 2, 100),
            'dieValue' => $this->int($v, $path, 1, 100),
            'snapGrid' => $this->snapGrid($v, $path),
            'snapPoints' => $this->snapPoints($v, $path),
            'tuckedChildren' => $this->tuckedChildren($v, $path),
            'urlOrNull' => $v === null ? null : $this->safeUrl($v, $path),
            'intOrNull' => $v === null ? null : $this->int($v, $path, 1, PHP_INT_MAX),
            'intList' => array_map(fn (mixed $n): int => $this->int($n, "{$path}[]", 1, PHP_INT_MAX), $this->list($v, $path, 50)),
            'metadataMap' => $this->metadataMap($v),
            'faceLabels' => array_map(fn (mixed $s): string => $this->string($s, "{$path}[]", 40), $this->list($v, $path, 100)),
            default => throw new \LogicException("Unhandled field kind {$kind}."),
        };
    }

    /** @param list<mixed> $raw */
    private function scenarios(array $raw): array
    {
        return array_map(function (mixed $item): array {
            $s = $this->map($item, 'scenario');
            $snap = $this->map($s['snapshot'] ?? [], 'scenario.snapshot');
            return [
                'id' => $this->id($s['id'] ?? null, 'scenario.id'),
                'name' => $this->string($s['name'] ?? 'Scenario', 'scenario.name', 120, false),
                'savedAt' => $this->string($s['savedAt'] ?? '', 'scenario.savedAt', 40),
                'snapshot' => [
                    'turnState' => $this->turnState($this->map($snap['turnState'] ?? [], 'scenario.turnState')),
                    'seats' => $this->seats($this->list($snap['seats'] ?? [], 'scenario.seats', self::MAX_SEATS)),
                    'boardObjects' => $this->boardObjects($this->map($snap['boardObjects'] ?? [], 'scenario.boardObjects')),
                    'zones' => array_map(fn (mixed $z): array => $this->zone($this->map($z, 'zone')), $this->list($snap['zones'] ?? [], 'scenario.zones', self::MAX_ZONES)),
                    'notes' => array_map(fn (mixed $n): array => $this->note($this->map($n, 'note')), $this->list($snap['notes'] ?? [], 'scenario.notes', self::MAX_NOTES)),
                ],
            ];
        }, $raw);
    }

    /** @param list<mixed> $raw */
    private function eventLog(array $raw): array
    {
        return array_map(function (mixed $item): array {
            $e = $this->map($item, 'eventLog');
            $sc = $e['seatColor'] ?? null;
            return [
                'id' => $this->id($e['id'] ?? 'evt_1', 'eventLog.id'),
                'type' => $this->string($e['type'] ?? 'ACTION', 'eventLog.type', 30),
                'author' => $this->string($e['author'] ?? 'System', 'eventLog.author', 60),
                'seatColor' => is_string($sc) && preg_match(self::COLOR_PATTERN, $sc) ? strtoupper($sc) : '#94A3B8',
                'text' => $this->string($e['text'] ?? '', 'eventLog.text', 500),
                'ts' => $this->string($e['ts'] ?? '', 'eventLog.ts', 40),
            ];
        }, $raw);
    }

    private function safeUrl(mixed $v, string $path): ?string
    {
        if (!is_string($v)) {
            throw new InvalidSandboxStateException("{$path} must be a URL string.");
        }
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        if (mb_strlen($v) > 500 || preg_match('/^\s*(javascript|vbscript|data:text\/html)/i', $v)) {
            throw new InvalidSandboxStateException("{$path} contains an unsafe URL.");
        }
        return $v;
    }

    private function metadataMap(mixed $v): array
    {
        if (!is_array($v)) {
            return [];
        }
        $out = [];
        $count = 0;
        foreach ($v as $k => $val) {
            if (++$count > 30 || !is_string($k)) {
                break;
            }
            $cleanKey = mb_substr(trim($k), 0, 50);
            if (is_int($val) || is_float($val) || is_bool($val)) {
                $out[$cleanKey] = $val;
            } elseif (is_string($val)) {
                $out[$cleanKey] = mb_substr(trim($val), 0, 200);
            }
        }
        return $out;
    }

    private function snapGrid(mixed $v, string $path): array
    {
        $g = $this->map($v, $path);
        if (($g['type'] ?? 'CARTESIAN') !== 'CARTESIAN') {
            throw new InvalidSandboxStateException("{$path}: unsupported grid type.");
        }
        return [
            'enabled' => $this->bool($g['enabled'] ?? false, "{$path}.enabled"),
            'type' => 'CARTESIAN',
            'sizeX' => $this->number($g['sizeX'] ?? 80, "{$path}.sizeX", 4, 2000),
            'sizeY' => $this->number($g['sizeY'] ?? 80, "{$path}.sizeY", 4, 2000),
        ];
    }

    private function snapPoints(mixed $v, string $path): array
    {
        $accepted = array_map(fn (SandboxObjectType $t): string => $t->value, SandboxObjectType::cases());
        return array_map(function (mixed $raw) use ($path, $accepted): array {
            $p = $this->map($raw, $path);
            $types = array_values(array_filter(
                $this->list($p['acceptedTypes'] ?? [], "{$path}.acceptedTypes", count($accepted)),
                fn (mixed $t): bool => in_array($t, $accepted, true)
            ));
            return [
                'id' => $this->id($p['id'] ?? null, "{$path}.id"),
                'x' => $this->coord($p['x'] ?? 0, "{$path}.x"),
                'y' => $this->coord($p['y'] ?? 0, "{$path}.y"),
                'radius' => $this->number($p['radius'] ?? 40, "{$path}.radius", 2, 500),
                'acceptedTypes' => $types,
            ];
        }, $this->list($v, $path, 500));
    }

    private function tuckedChildren(mixed $v, string $path): array
    {
        return array_map(function (mixed $raw) use ($path): array {
            $c = $this->map($raw, $path);
            return [
                'cardId' => $this->id($c['cardId'] ?? null, "{$path}.cardId"),
                'offsetX' => $this->number($c['offsetX'] ?? 0, "{$path}.offsetX", -2000, 2000),
                'offsetY' => $this->number($c['offsetY'] ?? 0, "{$path}.offsetY", -2000, 2000),
                'zIndexOffset' => $this->int($c['zIndexOffset'] ?? -1, "{$path}.zIndexOffset", -50, 50),
            ];
        }, $this->list($v, $path, 50));
    }

    /** @return array<mixed> */
    private function map(mixed $v, string $path): array
    {
        if (!is_array($v) || ($v !== [] && array_is_list($v))) {
            throw new InvalidSandboxStateException("{$path} must be an object.");
        }
        return $v;
    }

    /** @return list<mixed> */
    private function list(mixed $v, string $path, int $max = self::MAX_LIST): array
    {
        if (!is_array($v) || !array_is_list($v)) {
            throw new InvalidSandboxStateException("{$path} must be a list.");
        }
        if (count($v) > $max) {
            throw new InvalidSandboxStateException("{$path} exceeds {$max} entries.");
        }
        return $v;
    }

    private function id(mixed $v, string $path): string
    {
        if (!is_string($v) || !preg_match(self::ID_PATTERN, $v)) {
            throw new InvalidSandboxStateException("{$path} is not a valid identifier.");
        }
        return $v;
    }

    private function idOrNull(mixed $v, string $path): ?string
    {
        return $v === null ? null : $this->id($v, $path);
    }

    private function refOrNull(mixed $v, string $path): ?string
    {
        if ($v === null) {
            return null;
        }
        if (!is_string($v) || !preg_match(self::REF_PATTERN, $v)) {
            throw new InvalidSandboxStateException("{$path} is not a valid asset reference.");
        }
        return $v;
    }

    /** @return list<string> */
    private function idList(mixed $v, string $path): array
    {
        return array_map(fn (mixed $id): string => $this->id($id, "{$path}[]"), $this->list($v, $path));
    }

    private function string(mixed $v, string $path, int $maxLen, bool $allowEmpty = true): string
    {
        if (!is_string($v)) {
            throw new InvalidSandboxStateException("{$path} must be text.");
        }
        $v = trim((string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v));
        if (mb_strlen($v) > $maxLen) {
            throw new InvalidSandboxStateException("{$path} exceeds {$maxLen} characters.");
        }
        if (!$allowEmpty && $v === '') {
            throw new InvalidSandboxStateException("{$path} cannot be empty.");
        }
        return $v;
    }

    private function color(mixed $v, string $path): string
    {
        if (!is_string($v) || !preg_match(self::COLOR_PATTERN, $v)) {
            throw new InvalidSandboxStateException("{$path} must be a #RRGGBB color.");
        }
        return strtoupper($v);
    }

    private function bool(mixed $v, string $path): bool
    {
        if (!is_bool($v)) {
            throw new InvalidSandboxStateException("{$path} must be true or false.");
        }
        return $v;
    }

    private function int(mixed $v, string $path, int $min, int $max): int
    {
        if (is_float($v) && is_finite($v) && floor($v) === $v) {
            $v = (int)$v;
        }
        if (!is_int($v) || $v < $min || $v > $max) {
            throw new InvalidSandboxStateException("{$path} must be an integer between {$min} and {$max}.");
        }
        return $v;
    }

    private function number(mixed $v, string $path, float $min, float $max): float
    {
        if ((!is_int($v) && !is_float($v)) || !is_finite((float)$v) || $v < $min || $v > $max) {
            throw new InvalidSandboxStateException("{$path} must be a number between {$min} and {$max}.");
        }
        return round((float)$v, 2);
    }

    private function coord(mixed $v, string $path): float
    {
        return $this->number($v, $path, -self::COORD_LIMIT, self::COORD_LIMIT);
    }

    private function size(mixed $v, string $path): float
    {
        return $this->number($v, $path, 1, 20_000);
    }

    private function angle(mixed $v, string $path): float
    {
        $deg = fmod($this->number($v, $path, -36_000, 36_000), 360.0);
        return $deg < 0 ? $deg + 360.0 : $deg;
    }
}
