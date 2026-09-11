<?php
/**
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */

declare(strict_types=1);

namespace Xoops\Upgrade\Tests\Upgrade;

use PHPUnit\Framework\TestCase;

/**
 * The installer and the upgrade preflight report a missing sodium extension
 * without blocking, and the installer's labels for it fall back to English.
 *
 * @category  Xoops\Upgrade\Tests
 * @package   Xoops
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class TwofactorPreflightTest extends TestCase
{
    /**
     * A language pack without install/language/<lang>/twofactor.php gets the English labels.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testInstallerLoadsSodiumLabelsForAnOlderLanguagePack(): void
    {
        $root = dirname(__DIR__, 3);
        require_once $root . '/htdocs/install/class/installwizard.php';
        $wizard = new \XoopsInstallWizard();
        $wizard->language = 'older_translation';
        // Run the page's own language-loading call with no translated 2FA file.
        $source = file_get_contents($root . '/htdocs/install/page_modcheck.php');
        self::assertSame(1, preg_match('/\$wizard->loadLangFile\(\x27twofactor\x27\);/', $source, $match));
        eval($match[0]);
        self::assertStringContainsString('Sodium', constant('TWOFACTOR_SODIUM'));
        self::assertStringContainsString('Installation can continue', constant('TWOFACTOR_SODIUM_MSG'));
    }

    /**
     * The capability expression is false when the extension or any one AEAD function is missing.
     *
     * @return void
     */
    public function testInstallerAndUpgradeProbeEveryRequiredSodiumFunction(): void
    {
        $root = dirname(__DIR__, 3);
        foreach (['htdocs/install/page_modcheck.php', 'upgrade/preflight.php'] as $file) {
            $source = file_get_contents($root . '/' . $file);
            self::assertSame(1, preg_match('/\$sodiumAvailable\s*=\s*(.*?);/s', $source, $match), $file);
            $expression = $match[1];
            $symbols = ['sodium_crypto_aead_xchacha20poly1305_ietf_keygen', 'sodium_crypto_aead_xchacha20poly1305_ietf_encrypt', 'sodium_crypto_aead_xchacha20poly1305_ietf_decrypt'];
            // Execute the actual capability expression with each missing symbol,
            // including a partial PHP build with the extension still loaded.
            foreach (array_merge([null, 'extension'], $symbols) as $missing) {
                $probe = preg_replace_callback("/(extension_loaded|function_exists)\('([^']+)'\)/", static function (array $call) use ($missing): string {
                    $available = $call[1] === 'extension_loaded' ? $missing !== 'extension' : $call[2] !== $missing;
                    return $available ? 'true' : 'false';
                }, $expression);
                self::assertSame($missing === null, eval('return ' . $probe . ';'), $file . ': ' . ($missing ?? 'available'));
            }
            self::assertStringContainsString('TWOFACTOR_SODIUM', $source);
            self::assertStringNotContainsString('$blockNext = !$sodiumAvailable', $source);
        }
    }
}
