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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The user-rank and avatar delete pages stop on a rank or avatar that does
 * not exist, and the avatar delete reports a failed token check properly.
 *
 * On a stale or forged id, the handler's get() returns no object: the
 * user-rank delete then ran its delete or fatalled on $obj->getVar(), and the
 * avatar confirmation page fatalled on $avatar->getVar(). The smilies delete
 * already redirects in that case. The avatar delete's token check called
 * redirect_header() with an extra argument, so the message shown was "3"
 * instead of the token errors.
 *
 * @category  Xoops
 * @package   System
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class AdminDeleteGuardTest extends TestCase
{
    private static function source(string $page): string
    {
        return (string) file_get_contents(XOOPS_ROOT_PATH . '/modules/system/admin/' . $page . '/main.php');
    }

    #[Test]
    public function theUserRankDeleteStopsOnAMissingRank(): void
    {
        self::assertSame(
            1,
            preg_match(
                '/case \'userrank_delete\':.*?\$obj\s*=\s*\$userrank_Handler->get\(\$rank_id\);'
                . '\s*if\s*\(\s*!is_object\(\$obj\)\s*\)\s*\{\s*redirect_header\(\'admin\.php\?fct=userrank\',\s*2,\s*_AM_SYSTEM_DBERROR\);\s*\}'
                . '\s*if\s*\(\s*Request::getInt\(\'ok\'/s',
                self::source('userrank')
            ),
            'The rank must be checked right after get(), before the delete and the confirmation page use it.'
        );
    }

    #[Test]
    public function theAvatarConfirmationStopsOnAMissingAvatarBeforeAnyOutput(): void
    {
        $src   = self::source('avatars');
        $start = strpos($src, "case 'delfile':");
        self::assertNotFalse($start);
        $block = substr($src, $start, (int) strpos($src, "case 'delfileok':", $start) - $start);

        self::assertSame(
            1,
            preg_match(
                '/\$avatar\s*=\s*\$avatar_id\s*>\s*0\s*\?\s*\$avt_handler->get\(\$avatar_id\)\s*:\s*null;'
                . '\s*if\s*\(\s*!is_object\(\$avatar\)\s*\)\s*\{\s*redirect_header\(\'admin\.php\?fct=avatars\',\s*1,\s*_AM_SYSTEM_DBERROR\);\s*\}/',
                $block
            ),
            'The avatar must be looked up and checked, a non-positive id included.'
        );
        self::assertLessThan(
            strpos($block, '$xoBreadCrumb->render()'),
            strpos($block, 'if (!is_object($avatar))'),
            'The check must come before the page starts rendering.'
        );
    }

    #[Test]
    public function theAvatarDeleteReportsAFailedTokenCheck(): void
    {
        $src = self::source('avatars');

        self::assertStringContainsString(
            "redirect_header('admin.php?fct=avatars', 3, implode('<br>', \$GLOBALS['xoopsSecurity']->getErrors()));",
            $src
        );
        self::assertStringNotContainsString("redirect_header('admin.php?fct=avatars', 1, 3,", $src);
    }
}
