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

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * xoops_lib/.htaccess must deny web access on Apache 2.4 as well as 2.2.
 * "Order allow,deny / Deny from all" alone is understood by 2.4 only with
 * mod_access_compat loaded; without it Apache rejects the directives as
 * unknown and answers every request under xoops_lib with a 500 configuration
 * error rather than a clean 403. Both forms must be present, each guarded by
 * IfModule, as the xoops_data guards already are. (nginx and IIS ignore
 * .htaccess altogether; there xoops_lib belongs outside the document root.)
 *
 * @category  Xoops
 * @package   Tests
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversNothing]
final class TrustPathHtaccessTest extends TestCase
{
    #[Test]
    public function deniesAccessOnApache24And22(): void
    {
        $path = XOOPS_PATH . '/.htaccess';
        self::assertFileExists($path);
        $htaccess = file_get_contents($path);
        self::assertNotFalse($htaccess);

        self::assertMatchesRegularExpression(
            '/<IfModule mod_authz_core\.c>\s*Require all denied\s*<\/IfModule>/i',
            $htaccess,
            'Apache 2.4 form missing'
        );
        self::assertMatchesRegularExpression(
            '/<IfModule !mod_authz_core\.c>\s*Order allow,deny\s*Deny from all\s*<\/IfModule>/i',
            $htaccess,
            'Apache 2.2 form missing'
        );
    }
}
