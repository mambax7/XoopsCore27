<?php

declare(strict_types=1);

namespace kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/kernel/object.php';

/**
 * Covers XoopsObjectHandler::buildOrderBy() — the ORDER BY allowlist helper
 * introduced in the pass-3 hardening (SECURITY.md L-6). Verifies that only
 * allowlisted columns survive, that injection / unknown qualifiers cannot reach
 * the emitted clause, that the criteria order is sanitized, and that the default
 * column is used when nothing valid remains.
 */
#[CoversClass(\XoopsObjectHandler::class)]
class XoopsObjectHandlerBuildOrderByTest extends TestCase
{
    /** @var string[] */
    private const COLS = ['com_id', 'com_created', 'com_modified'];

    #[Test]
    public function singleValidColumnGetsDefaultDirection(): void
    {
        $this->assertSame(
            'com_created DESC',
            \XoopsObjectHandler::buildOrderBy('com_created', 'DESC', self::COLS, 'com_id'),
        );
    }

    #[Test]
    public function perClauseDirectionIsHonoured(): void
    {
        $this->assertSame(
            'com_created ASC, com_modified DESC',
            \XoopsObjectHandler::buildOrderBy('com_created ASC, com_modified DESC', 'ASC', self::COLS, 'com_id'),
        );
    }

    #[Test]
    public function unknownColumnFallsBackToDefault(): void
    {
        $this->assertSame(
            'com_id ASC',
            \XoopsObjectHandler::buildOrderBy('com_secret', 'ASC', self::COLS, 'com_id'),
        );
    }

    #[Test]
    public function unknownTableQualifierIsStripped(): void
    {
        // "bogus.com_id" passes the column allowlist but the bogus alias must not
        // reach the SQL — with the default prefix the emitted clause is bare.
        $this->assertSame(
            'com_id ASC',
            \XoopsObjectHandler::buildOrderBy('bogus.com_id', 'ASC', self::COLS, 'com_created'),
        );
    }

    #[Test]
    public function canonicalPrefixDisambiguatesJoinedColumns(): void
    {
        // Mirrors imagemanager.php's binary (joined) path: image_id exists in both
        // joined tables, so the handler forces the "i." alias. Any caller-supplied
        // qualifier is replaced with the canonical one.
        $cols = ['image_weight', 'image_id'];
        $this->assertSame(
            'i.image_weight ASC, i.image_id DESC',
            \XoopsObjectHandler::buildOrderBy('i.image_weight ASC, i.image_id', 'DESC', $cols, 'image_weight', 'i.'),
        );
        // A bogus qualifier on input is still dropped and replaced with "i.".
        $this->assertSame(
            'i.image_id ASC',
            \XoopsObjectHandler::buildOrderBy('bogus.image_id', 'ASC', $cols, 'image_weight', 'i.'),
        );
        // The default-column fallback also carries the canonical prefix.
        $this->assertSame(
            'i.image_weight ASC',
            \XoopsObjectHandler::buildOrderBy('not_a_column', 'ASC', $cols, 'image_weight', 'i.'),
        );
    }

    #[Test]
    #[DataProvider('injectionProvider')]
    public function injectionAttemptsNeverEmitMaliciousSql(string $sort, string $order): void
    {
        $result = \XoopsObjectHandler::buildOrderBy($sort, $order, self::COLS, 'com_id');
        // Whatever the payload, the result is one or more "col ASC|DESC" clauses.
        $this->assertMatchesRegularExpression(
            '/^[A-Za-z_]\w*\s+(ASC|DESC)(,\s[A-Za-z_]\w*\s+(ASC|DESC))*$/',
            $result,
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function injectionProvider(): array
    {
        return [
            'union in sort'        => ['com_id; DROP TABLE x', 'ASC'],
            'subquery in sort'     => ['(SELECT 1)', 'ASC'],
            'injection in order'   => ['com_id', 'ASC, (SELECT password FROM users)'],
            'sleep in order'       => ['com_created', 'DESC; SELECT SLEEP(5)'],
            'empty sort'           => ['', 'ASC'],
            'backtick in sort'     => ['`com_id`', 'DESC'],
        ];
    }

    #[Test]
    public function malformedColumnPrefixIsNeutralised(): void
    {
        // A public static helper must not trust the prefix: anything that is not
        // '' or a single "alias." qualifier is dropped, so a careless future caller
        // cannot inject through it.
        $this->assertSame(
            'com_id ASC',
            \XoopsObjectHandler::buildOrderBy('com_id', 'ASC', self::COLS, 'com_id', 'evil; DROP TABLE x --'),
        );
        $this->assertSame(
            'com_id ASC',
            \XoopsObjectHandler::buildOrderBy('com_id', 'ASC', self::COLS, 'com_id', 'a.b.'),
        );
    }

    #[Test]
    public function invalidOrderIsCoercedToAsc(): void
    {
        $this->assertSame(
            'com_id ASC',
            \XoopsObjectHandler::buildOrderBy('com_id', 'GARBAGE', self::COLS, 'com_id'),
        );
    }
}
