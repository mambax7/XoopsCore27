<?php
/*
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

declare(strict_types=1);

namespace Tests\Unit\Include;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\System\SourceFileTestTrait;

require_once dirname(__DIR__) . '/modules/system/SourceFileTestTrait.php';

/**
 * comment_edit.php loaded any comment id from the query string and rendered
 * its text, email and URL into the edit form with no check on who was
 * asking, while the save and delete paths enforce owner-or-module-admin. The
 * edit form must apply the same rule before any field is read.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class CommentEditOwnershipTest extends TestCase
{
    use SourceFileTestTrait;

    #[Test]
    public function editFormRequiresOwnerOrModuleAdminBeforeReadingTheComment(): void
    {
        $this->loadSourceFile('htdocs/include/comment_edit.php');

        $load = strpos($this->sourceContent, '$comment         = $comment_handler->get($com_id);');
        self::assertNotFalse($load);
        $firstRead = strpos($this->sourceContent, "\$comment->getVar('dohtml')", $load);
        self::assertNotFalse($firstRead);
        $guard = substr($this->sourceContent, $load, $firstRead - $load);

        self::assertStringContainsString('is_object($comment) && is_object($xoopsUser)', $guard);
        // Authorisation is against the module the comment belongs to, not the
        // module whose page carries the request, and the system comment
        // moderator right the save path honours is accepted here too.
        self::assertStringContainsString("\$xoopsUser->isAdmin((int) \$comment->getVar('com_modid'))", $guard);
        self::assertStringNotContainsString("isAdmin(\$xoopsModule->getVar('mid'))", $guard);
        self::assertStringContainsString("checkRight('system_admin', XOOPS_SYSTEM_COMMENT, \$xoopsUser->getGroups())", $guard);
        self::assertStringContainsString("(int) \$comment->getVar('com_uid') === (int) \$xoopsUser->getVar('uid')", $guard);
        self::assertStringContainsString("(int) \$xoopsUser->getVar('uid') > 0", $guard, 'anonymous comments have no owner');
        self::assertStringContainsString('redirect_header(XOOPS_URL', $guard);
        self::assertStringContainsString('_NOPERM', $guard);
    }

    /**
     * @return array<string, array{?object, object|false, bool}>
     */
    public static function guardCases(): array
    {
        $comment = self::comment(uid: 7, modid: 3);

        return [
            'anonymous visitor'                       => [null, $comment, false],
            'missing comment'                         => [self::user(uid: 7), false, false],
            'unrelated member'                        => [self::user(uid: 8), $comment, false],
            'uid 0 never owns an anonymous comment'   => [self::user(uid: 0), self::comment(uid: 0, modid: 3), false],
            'administrator of another module'         => [self::user(uid: 8, adminOf: [4]), $comment, false],
            'comment author'                          => [self::user(uid: 7), $comment, true],
            "administrator of the comment's module"   => [self::user(uid: 8, adminOf: [3]), $comment, true],
            'system comment moderator'                => [self::user(uid: 8, moderator: true), $comment, true],
        ];
    }

    #[Test]
    #[DataProvider('guardCases')]
    public function guardExecutesTheAuthorModuleAdminOrModeratorRule(?object $xoopsUser, object|false $comment, bool $allowed): void
    {
        if (!$allowed) {
            $this->expectException(\RedirectHeaderException::class);
        }

        self::assertSame($allowed, $this->runGuard($xoopsUser, $comment));
    }

    /**
     * Executes the guard block of comment_edit.php, which runs before header.php
     * is included, in a namespace where xoops_getHandler() resolves to a stub.
     * The bootstrap's redirect_header() throws RedirectHeaderException.
     */
    private function runGuard(?object $xoopsUser, object|false $comment): bool
    {
        $this->loadSourceFile('htdocs/include/comment_edit.php');
        $start = strpos($this->sourceContent, '$canEdit = false;');
        self::assertNotFalse($start);
        $end = strpos($this->sourceContent, '$dohtml', $start);
        self::assertNotFalse($end);
        $guard = substr($this->sourceContent, $start, $end - $start);

        // The guard pulls in modules/system/constants.php for XOOPS_SYSTEM_COMMENT;
        // that file also loads notification constants the bootstrap defines
        // differently, so supply the one constant here instead.
        self::assertStringContainsString("include_once \$GLOBALS['xoops']->path('modules/system/constants.php');", $guard);
        $guard = str_replace("include_once \$GLOBALS['xoops']->path('modules/system/constants.php');", '', $guard);
        if (!defined('XOOPS_SYSTEM_COMMENT')) {
            define('XOOPS_SYSTEM_COMMENT', 14);
        }

        $namespace = __NAMESPACE__ . '\CommentEditGuard';
        $GLOBALS['commentEditGuardPermHandler'] = new class {
            /** @param int[] $groups */
            public function checkRight(string $name, int $id, array $groups): bool
            {
                return 'system_admin' === $name && XOOPS_SYSTEM_COMMENT === $id && in_array(99, $groups, true);
            }
        };
        if (!function_exists($namespace . '\xoops_getHandler')) {
            eval('namespace ' . $namespace . '; function xoops_getHandler($name) { return $GLOBALS["commentEditGuardPermHandler"]; }');
        }

        $canEdit = null;
        eval('namespace ' . $namespace . ";\n" . $guard);

        return (bool) $canEdit;
    }

    /**
     * @param int[] $adminOf module ids the user administers
     */
    private static function user(int $uid, array $adminOf = [], bool $moderator = false): object
    {
        return new class($uid, $adminOf, $moderator) {
            /** @param int[] $adminOf */
            public function __construct(private int $uid, private array $adminOf, private bool $moderator)
            {
            }

            public function getVar(string $key): mixed
            {
                return 'uid' === $key ? $this->uid : null;
            }

            public function isAdmin(?int $mid = null): bool
            {
                return in_array((int) $mid, $this->adminOf, true);
            }

            /** @return int[] */
            public function getGroups(): array
            {
                return $this->moderator ? [99] : [2];
            }
        };
    }

    private static function comment(int $uid, int $modid): object
    {
        return new class($uid, $modid) {
            public function __construct(private int $uid, private int $modid)
            {
            }

            public function getVar(string $key): mixed
            {
                return ['com_uid' => $this->uid, 'com_modid' => $this->modid][$key] ?? null;
            }
        };
    }
}
