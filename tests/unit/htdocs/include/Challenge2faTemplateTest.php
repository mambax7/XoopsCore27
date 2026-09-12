<?php
/**
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */

declare(strict_types=1);

namespace Tests\Unit\Include;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class Challenge2faTemplateTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testChallengeTextIsEscapedAndErrorsKeepTheirLineBreaks(): void
    {
        $smarty = XOOPS_ROOT_PATH . '/xoops_lib/vendor/smarty/smarty/libs/Smarty.class.php';
        if (!is_file($smarty)) {
            self::markTestSkipped('Smarty is not installed in xoops_lib/vendor');
        }
        require_once $smarty;
        $directory = sys_get_temp_dir() . '/xoops-2fa-challenge-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $GLOBALS['xoops'] = new class {
            public function url(string $url): string
            {
                return XOOPS_URL . '/' . $url;
            }
        };
        $smarty = new \Smarty();
        $smarty->left_delimiter = '<{';
        $smarty->right_delimiter = '}>';
        $smarty->setCompileDir($directory);
        $smarty->addPluginsDir(XOOPS_ROOT_PATH . '/class/smarty3_plugins');
        $smarty->assign([
            'xoops_langcode' => 'en', 'xoops_charset' => 'UTF-8', 'xoops_sitename' => 'Site', 'xoops_themecss' => '/theme.css',
            'title' => '<script>title</script>', 'message' => '<img src=x onerror=alert(1)>',
            'error' => "<img src=x onerror=alert(2)>\nSecond error", 'start_again' => false,
            'login_url' => '/user.php', 'action_url' => '/user.php', 'lang_startagain' => 'Start again',
            'lang_code' => 'Code', 'lang_recovery' => 'Recovery', 'lang_recovery_hint' => 'Recovery hint', 'lang_submit' => 'Submit',
            'token_html' => '<input name="XOOPS_TOKEN" value="token">',
        ]);
        try {
            $html = $smarty->fetch('file:' . XOOPS_ROOT_PATH . '/modules/system/templates/system_user2fa.tpl');
            self::assertStringNotContainsString('<script>', $html);
            self::assertStringNotContainsString('<img', $html);
            self::assertStringContainsString('&lt;script&gt;title&lt;/script&gt;', $html);
            self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
            self::assertStringContainsString('&lt;img src=x onerror=alert(2)&gt;<br', $html);
            self::assertStringContainsString('Second error', $html);
            self::assertStringContainsString('<input name="XOOPS_TOKEN"', $html);
            $smarty->assign('start_again', true);
            $html = $smarty->fetch('file:' . XOOPS_ROOT_PATH . '/modules/system/templates/system_user2fa.tpl');
            self::assertStringNotContainsString('<img', $html);
            self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        } finally {
            foreach (glob($directory . '/*') as $file) { unlink($file); }
            rmdir($directory);
        }
    }
}
