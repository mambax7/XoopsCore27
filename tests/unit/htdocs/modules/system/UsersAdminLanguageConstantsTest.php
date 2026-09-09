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

namespace Tests\Unit\System;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/SourceFileTestTrait.php';

/**
 * Every _AM_SYSTEM_USERS_* constant the users admin page uses must be
 * defined in its language file. On PHP 8 an undefined constant is an Error,
 * so a missing define turns an error redirect into a fatal page.
 * _AM_SYSTEM_USERS_NO_SUCH_USER was used on three paths and defined nowhere.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class UsersAdminLanguageConstantsTest extends TestCase
{
    use SourceFileTestTrait;

    #[Test]
    public function everyUsersAdminConstantUsedByThePageIsDefined(): void
    {
        $this->loadSourceFile('htdocs/modules/system/admin/users/main.php');
        // Comments are stripped so a constant mentioned in a comment does not count as a use.
        $code = php_strip_whitespace($this->filePath);
        preg_match_all('/\b_AM_SYSTEM_USERS_[A-Z0-9_]+\b/', $code, $m);
        $used = array_unique($m[0]);
        self::assertContains('_AM_SYSTEM_USERS_NO_SUCH_USER', $used);

        $this->loadSourceFile('htdocs/modules/system/language/english/admin/users.php');
        preg_match_all("/define\\('(_AM_SYSTEM_USERS_[A-Z0-9_]+)'/", $this->sourceContent, $d);
        $defined = $d[1];

        $missing = array_values(array_diff($used, $defined));
        self::assertSame([], $missing, 'constants used by admin/users/main.php but not defined in its language file');
    }
}
