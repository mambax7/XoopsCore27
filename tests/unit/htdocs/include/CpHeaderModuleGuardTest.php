<?php
/**
 * Tests for the missing-module guard in include/cp_header.php
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package   core
 * @since     2.7.5
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Opening the admin page of a module that is present on disk but not
 * installed made getByDirname() return false, and the permission check then
 * called getVar() on it: "Call to a member function getVar() on false".
 * cp_header.php cannot be included in a unit test, so the guard and its
 * position before the first use of $xoopsModule are pinned on the source text.
 */
final class CpHeaderModuleGuardTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(XOOPS_ROOT_PATH . '/include/cp_header.php');
    }

    #[Test]
    public function aModuleThatIsNotInstalledRedirectsBeforeItIsUsed(): void
    {
        $source = $this->source();
        $lookup = strpos($source, '$xoopsModule    = $module_handler->getByDirname(');
        $guard  = strpos($source, 'if (!$xoopsModule instanceof XoopsModule) {');
        $notice = strpos($source, '_MODULENOEXIST);');
        $use    = strpos($source, "\$xoopsModule->getVar('mid')");

        $this->assertNotFalse($lookup, 'the module lookup is where it was');
        $this->assertNotFalse($guard, 'the lookup result is checked');
        $this->assertNotFalse($notice, 'the redirect says the module does not exist');
        $this->assertNotFalse($use, 'the permission check is where it was');
        $this->assertGreaterThan($lookup, $guard, 'the check follows the lookup');
        $this->assertLessThan($use, $guard, 'the check precedes the first getVar() call');
        $this->assertLessThan($use, $notice, 'the redirect precedes the first getVar() call');
    }

    #[Test]
    public function aUrlWithoutAModuleSegmentDoesNotReadAMissingIndex(): void
    {
        $this->assertStringContainsString('getByDirname($url_arr[2] ?? \'\')', $this->source());
    }
}
