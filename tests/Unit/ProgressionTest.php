<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Progression;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The level curve and rank ladder are the single source of truth for XP, so
 * their boundaries are pinned here rather than only exercised through HTTP.
 */
final class ProgressionTest extends TestCase
{
    #[DataProvider('levelBoundaries')]
    public function testLevelForXpMatchesTheConfiguredCurve(int $xp, int $expected): void
    {
        $this->assertSame($expected, Progression::levelForXp($xp));
    }

    /** @return array<string, array{int, int}> */
    public static function levelBoundaries(): array
    {
        return [
            'zero xp is level one' => [0, 1],
            'negative xp clamps to level one' => [-50, 1],
            'one xp short of level two' => [99, 1],
            'exactly level two' => [100, 2],
            'one short of level three' => [399, 2],
            'exactly level three' => [400, 3],
            'level ten floor' => [8100, 10],
            'absurd xp caps at max level' => [10_000_000, 100],
        ];
    }

    #[DataProvider('levelFloors')]
    public function testXpForLevelIsTheInverseFloor(int $level, int $expected): void
    {
        $this->assertSame($expected, Progression::xpForLevel($level));
    }

    /** @return array<string, array{int, int}> */
    public static function levelFloors(): array
    {
        return [
            'level one starts at zero' => [1, 0],
            'level two needs one hundred' => [2, 100],
            'level three needs four hundred' => [3, 400],
            'level ten needs 8100' => [10, 8100],
            'below level one clamps' => [0, 0],
        ];
    }

    public function testSnapshotReportsProgressTowardTheNextLevel(): void
    {
        $snapshot = Progression::snapshot(['xp_total' => 250]);

        $this->assertSame(2, $snapshot['level']);
        $this->assertSame(250, $snapshot['xp']);
        $this->assertSame('Novice', $snapshot['rank']);
        $this->assertSame('Adventurer', $snapshot['next_rank']);
        $this->assertSame(5, $snapshot['next_rank_level']);
        $this->assertSame(50.0, $snapshot['progress']);
        $this->assertSame(150, $snapshot['xp_to_next']);
        $this->assertSame(3, $snapshot['next_level']);
    }

    public function testSnapshotAtMaxLevelHasNoNextLevel(): void
    {
        $snapshot = Progression::snapshot(['xp_total' => Progression::xpForLevel(100)]);

        $this->assertSame(100, $snapshot['level']);
        $this->assertSame(100.0, $snapshot['progress']);
        $this->assertSame(0, $snapshot['xp_to_next']);
        $this->assertNull($snapshot['next_level']);
        $this->assertSame('Mythic', $snapshot['rank']);
        $this->assertSame('Mythic', $snapshot['next_rank']);
        $this->assertNull($snapshot['next_rank_level']);
    }

    #[DataProvider('ranks')]
    public function testRankUsesTheHighestReachedThreshold(int $level, string $expected): void
    {
        $this->assertSame($expected, Progression::rank($level));
    }

    /** @return array<string, array{int, string}> */
    public static function ranks(): array
    {
        return [
            'level one is a novice' => [1, 'Novice'],
            'level four is still a novice' => [4, 'Novice'],
            'level five is an adventurer' => [5, 'Adventurer'],
            'level nine is still an adventurer' => [9, 'Adventurer'],
            'level ten is a champion' => [10, 'Champion'],
            'level twenty is a legend' => [20, 'Legend'],
            'level thirty-five is mythic' => [35, 'Mythic'],
            'level one hundred stays mythic' => [100, 'Mythic'],
        ];
    }

    public function testNextRankPointsAtTheFirstUnreachedThreshold(): void
    {
        $this->assertSame(['name' => 'Adventurer', 'level' => 5], Progression::nextRank(1));
        $this->assertSame(['name' => 'Champion', 'level' => 10], Progression::nextRank(5));
        $this->assertSame(['name' => 'Mythic', 'level' => 35], Progression::nextRank(34));
        $this->assertSame(['name' => 'Mythic', 'level' => null], Progression::nextRank(35));
    }
}
