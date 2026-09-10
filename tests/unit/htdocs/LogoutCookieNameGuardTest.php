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

namespace Tests\Unit\Htdocs;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\System\SourceFileTestTrait;

require_once __DIR__ . '/modules/system/SourceFileTestTrait.php';

/**
 * user.php's logout cleared the remember-me cookie with the configured name
 * unconditionally. That name is a site setting and is empty when remember-me
 * is disabled, and setcookie() rejects an empty name on PHP 8, so every logout
 * on such a site failed after the session had already been cleared (#191).
 * The login path a few lines earlier and the restore path in common.php both
 * guard the same setting; logout must too.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class LogoutCookieNameGuardTest extends TestCase
{
    use SourceFileTestTrait;

    /**
     * Both logout entry points: the core one and the profile module's own,
     * which is reached directly at modules/profile/user.php?op=logout.
     *
     * @return array<string, array{string}>
     */
    public static function logoutPages(): array
    {
        return [
            'core user.php'           => ['htdocs/user.php'],
            'profile module user.php' => ['htdocs/modules/profile/user.php'],
        ];
    }

    #[Test]
    #[DataProvider('logoutPages')]
    public function logoutClearsTheRememberMeCookieOnlyWhenANameIsConfigured(string $file): void
    {
        $this->loadSourceFile($file);
        $start = strpos($this->sourceContent, "if (\$op === 'logout') {");
        self::assertNotFalse($start);
        $end = strpos($this->sourceContent, '// clear entry from online users table', $start);
        self::assertNotFalse($end);
        $logout = substr($this->sourceContent, $start, $end - $start);

        $guard = strpos($logout, "if (!empty(\$GLOBALS['xoopsConfig']['usercookie'])) {");
        self::assertNotFalse($guard, 'the cookie name must be checked before it is used');
        $calls = substr_count($logout, "xoops_setcookie(\$GLOBALS['xoopsConfig']['usercookie'], null, time() - 3600");
        self::assertSame(2, $calls, 'both cookie forms are still cleared');
        self::assertLessThan(strpos($logout, 'xoops_setcookie('), $guard);
        // both calls sit inside the guard: no call after its closing brace
        $close = strpos($logout, "\n    }\n", $guard);
        self::assertNotFalse($close);
        self::assertStringNotContainsString('xoops_setcookie(', substr($logout, $close));
    }
}
