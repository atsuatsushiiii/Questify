<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the sidebar's active-item rule.
 *
 * A prefix match keeps `/quests/12` on the My Quests item, while `$exact`
 * stops `/admin/users` from leaving the admin Overview item highlighted.
 */
final class NavActiveTest extends TestCase
{
    private ?string $previous = null;

    protected function setUp(): void
    {
        $this->previous = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : null;
    }

    protected function tearDown(): void
    {
        if ($this->previous === null) {
            unset($_SERVER['REQUEST_URI']);

            return;
        }

        $_SERVER['REQUEST_URI'] = $this->previous;
    }

    private function at(string $uri): void
    {
        $_SERVER['REQUEST_URI'] = $uri;
    }

    public function testPrefixMatchingKeepsParentSectionsActive(): void
    {
        $this->at('/quests/42');

        $this->assertTrue(nav_active('/quests'));
        $this->assertTrue(nav_active('/quests', false));
    }

    public function testExactMatchingStopsChildrenFromHighlightingTheParent(): void
    {
        $this->at('/admin/users');

        $this->assertTrue(nav_active('/admin'));
        $this->assertFalse(nav_active('/admin', true));
    }

    public function testExactMatchingStillHighlightsTheParentItself(): void
    {
        $this->at('/admin');

        $this->assertTrue(nav_active('/admin', true));
    }

    public function testTrailingSlashesAndQueryStringsDoNotBreakExactMatching(): void
    {
        $this->at('/admin/?window=14');

        $this->assertTrue(nav_active('/admin', true));
        $this->assertFalse(nav_active('/admin/users', true));
    }

    public function testUnrelatedRoutesAreNeverActive(): void
    {
        $this->at('/dashboard');

        $this->assertFalse(nav_active('/admin'));
        $this->assertFalse(nav_active('/admin', true));
    }
}
