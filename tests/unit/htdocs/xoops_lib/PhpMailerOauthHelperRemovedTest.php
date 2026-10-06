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
 * PHPMailer ships get_oauth_token.php, an interactive OAuth2 helper that
 * nothing in XOOPS uses. nginx and IIS ignore xoops_lib/.htaccess, so there
 * the script is reachable whenever xoops_lib sits inside the document root
 * (Apache 2.4 without mod_access_compat answers with a 500 configuration
 * error instead, see TrustPathHtaccessTest). The
 * file is not shipped, and because vendor/ is refreshed from
 * composer.dist.json, a post-install/post-update script removes it again.
 *
 * @category  Xoops
 * @package   Tests
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversNothing]
final class PhpMailerOauthHelperRemovedTest extends TestCase
{
    private const HELPER = 'vendor/phpmailer/phpmailer/get_oauth_token.php';

    #[Test]
    public function helperIsNotInTheShippedTree(): void
    {
        self::assertFileDoesNotExist(XOOPS_PATH . '/' . self::HELPER);
    }

    #[Test]
    public function composerRemovesTheHelperAfterInstallAndUpdate(): void
    {
        $json = file_get_contents(XOOPS_PATH . '/composer.dist.json');
        self::assertNotFalse($json);
        $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $scripts  = $manifest['scripts'] ?? [];

        self::assertArrayHasKey('remove-phpmailer-oauth-helper', $scripts);
        self::assertStringContainsString('unlink(', $scripts['remove-phpmailer-oauth-helper']);
        self::assertStringContainsString(self::HELPER, $scripts['remove-phpmailer-oauth-helper']);
        foreach (['post-install-cmd', 'post-update-cmd'] as $event) {
            self::assertSame('@remove-phpmailer-oauth-helper', $scripts[$event] ?? null, $event);
        }
    }
}
