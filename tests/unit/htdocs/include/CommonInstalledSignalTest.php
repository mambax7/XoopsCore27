<?php
/**
 * Tests for the two-factor installed signal captured in include/common.php
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
 * @since     2.7.4
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The two-factor "installed" signal must come from the configuration rows the
 * database returned, captured before var/configs/xoopsconfig.php merges in,
 * so a file override cannot enable the feature on a site the 2.7.4 patch has
 * not reached. common.php cannot be included in a unit test, so the ordering
 * is pinned on the source text.
 */
final class CommonInstalledSignalTest extends TestCase
{
    #[Test]
    public function theInstalledSignalIsCapturedFromTheDatabaseConfigsBeforeTheFileMerge(): void
    {
        $source = (string) file_get_contents(XOOPS_ROOT_PATH . '/include/common.php');
        $define = strpos($source, "define('XOOPS_2FA_INSTALLED', [] === \$xoopsConfig || array_key_exists('twofactor_mode', \$xoopsConfig))");
        $load   = strpos($source, '$xoopsConfig    = $config_handler->getConfigsByCat(XOOPS_CONF);');
        $merge  = strpos($source, "path('var/configs/xoopsconfig.php')");

        $this->assertNotFalse($define, 'the constant is defined');
        $this->assertNotFalse($load, 'the database configs load is where it was');
        $this->assertNotFalse($merge, 'the file configs merge is where it was');
        $this->assertGreaterThan($load, $define, 'the signal is read after the database configs load');
        $this->assertLessThan($merge, $define, 'the signal is read before the file configs merge');
    }
}
