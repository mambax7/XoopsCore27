<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * browse.php must confine the resolved file to its root with a path-boundary
 * match, not a substring match (finding A2-M-1). A substring match let a sibling
 * directory whose name starts with the root's name (e.g. ".../htdocs-evil") pass.
 */
final class BrowsePathContainmentTest extends TestCase
{
    #[Test]
    public function browseUsesBoundaryContainmentNotSubstring(): void
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/browse.php');
        self::assertNotFalse($src);
        self::assertSame(
            0,
            preg_match('/false\s*===\s*strpos\(\s*\$file\s*,/', $src),
            'browse.php must not use a substring strpos() for path containment (A2-M-1).'
        );
        self::assertStringContainsString('str_starts_with($file', $src);
    }

    #[Test]
    public function boundaryMatchRejectsSiblingPrefixDirectory(): void
    {
        // The exact semantics browse.php now relies on.
        $prefix = '/srv/data/';
        self::assertFalse(str_starts_with('/srv/data-evil/x/', $prefix), 'sibling-prefix dir must not be contained');
        self::assertTrue(str_starts_with('/srv/data/sub/x/', $prefix), 'genuine child must be contained');
        self::assertTrue(str_starts_with('/srv/data/', $prefix), 'the root itself must be contained');
    }
}
