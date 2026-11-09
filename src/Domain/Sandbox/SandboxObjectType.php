<?php
declare(strict_types=1);

namespace App\Domain\Sandbox;

/**
 * Playtest Sandbox component types and their categorical Z-Index tiers.
 *
 * Tier map (spec §3):
 *   Tier 1 Boards & Mats      100-199
 *   Tier 2 Zones & Trays      200-299 (zones live in the separate `zones` collection)
 *   Tier 3 Cards & Decks      300-499
 *   Tier 4 Tokens & Meeples   500-699
 *   Tier 5 Active Drag / UI   900-999 (client-side only, never persisted)
 */
enum SandboxObjectType: string
{
    case Board = 'BOARD';
    case Deck = 'DECK';
    case Card = 'CARD';
    case Token = 'TOKEN';
    case Spawner = 'SPAWNER';
    case Die = 'DIE';

    /** @return array{0:int,1:int} Inclusive [min, max] persisted z-index range. */
    public function zRange(): array
    {
        return match ($this) {
            self::Board => [100, 199],
            self::Deck, self::Card => [300, 499],
            self::Token, self::Spawner, self::Die => [500, 699],
        };
    }

    public function clampZ(int $z): int
    {
        [$min, $max] = $this->zRange();
        return max($min, min($max, $z));
    }

    /** @return array{0:int,1:int} Default [width, height] in world pixels. */
    public function defaultSize(): array
    {
        return match ($this) {
            self::Board => [800, 600],
            self::Deck, self::Card => [140, 200],
            self::Token => [48, 48],
            self::Spawner => [64, 64],
            self::Die => [56, 56],
        };
    }
}
