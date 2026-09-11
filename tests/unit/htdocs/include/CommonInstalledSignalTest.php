<?php

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
        $define = strpos($source, "define('XOOPS_2FA_INSTALLED', array_key_exists('twofactor_mode', \$xoopsConfig))");
        $load   = strpos($source, '$xoopsConfig    = $config_handler->getConfigsByCat(XOOPS_CONF);');
        $merge  = strpos($source, "path('var/configs/xoopsconfig.php')");

        $this->assertNotFalse($define, 'the constant is defined');
        $this->assertNotFalse($load, 'the database configs load is where it was');
        $this->assertNotFalse($merge, 'the file configs merge is where it was');
        $this->assertGreaterThan($load, $define, 'the signal is read after the database configs load');
        $this->assertLessThan($merge, $define, 'the signal is read before the file configs merge');
    }
}
