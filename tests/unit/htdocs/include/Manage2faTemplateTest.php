<?php

declare(strict_types=1);

namespace Tests\Unit\Include;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;

/** Compile and render the shipped template through the vendored Smarty engine. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class Manage2faTemplateTest extends TestCase
{
    #[Test]
    public function realSmartyRendersEveryFormAndEscapesUntrustedValues(): void
    {
        require_once XOOPS_ROOT_PATH . '/xoops_lib/vendor/smarty/smarty/libs/Smarty.class.php';
        $directory = sys_get_temp_dir() . '/xoops-2fa-template-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $GLOBALS['xoops'] = new class { public function url(string $url): string { return XOOPS_URL . '/' . $url; } };
        $smarty = new \Smarty();
        $smarty->left_delimiter = '<{';
        $smarty->right_delimiter = '}>';
        $smarty->setCompileDir($directory);
        $smarty->addPluginsDir(XOOPS_ROOT_PATH . '/class/smarty3_plugins');
        $smarty->caching = 0;
        $labels = [];
        foreach (['title', 'paused', 'codes', 'codes_help', 'reset_help', 'enabled', 'http', 'scan', 'manual', 'password', 'reset', 'regenerate', 'disable', 'confirm', 'enable', 'back'] as $name) {
            $labels[$name] = 'Label ' . $name;
        }
        $base = ['labels' => $labels, 'langcode' => 'en', 'charset' => 'UTF-8', 'account' => '<script>alert(1)</script>',
            'message' => '', 'error' => '', 'paused' => false, 'codes' => [], 'installed' => true, 'admin_reset' => false,
            'enrolled' => false, 'http_warning' => true, 'secret' => '', 'qr' => '', 'action_url' => XOOPS_URL . '/user.php',
            'token_html' => '<input type="hidden" name="XOOPS_TOKEN" value="csrf">', 'uid' => 9, 'confirm_setup' => false,
            'lang_code' => 'Code', 'lang_recovery' => 'Recovery', 'back_url' => XOOPS_URL . '/userinfo.php?uid=9'];
        try {
            foreach ([
                'begin' => [],
                'confirm' => ['confirm_setup' => true, 'secret' => '<img src=x onerror=alert(1)>'],
                'regenerate' => ['enrolled' => true],
                'reset' => ['admin_reset' => true],
            ] as $action => $overrides) {
                $smarty->assign($overrides + $base);
                $html = $smarty->fetch('file:' . XOOPS_ROOT_PATH . '/modules/system/templates/system_user2fa_manage.tpl');
                self::assertStringContainsString('<title>Label title</title>', $html);
                self::assertStringNotContainsString('<script>', $html);
                self::assertStringContainsString('&lt;script&gt;', $html);
                self::assertStringContainsString('name="XOOPS_TOKEN"', $html);
                self::assertStringContainsString('name="action" value="' . $action . '"', $html);
                if ($action === 'confirm') {
                    self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
                    self::assertStringNotContainsString('name="password"', $html);
                    self::assertStringContainsString('name="code"', $html);
                } else {
                    self::assertStringContainsString('name="password"', $html);
                }
                if ($action === 'regenerate') {
                    self::assertStringContainsString('name="recovery"', $html);
                    self::assertStringContainsString('value="disable"', $html);
                }
                if ($action === 'reset') {
                    self::assertStringContainsString('name="uid" value="9"', $html);
                    self::assertStringNotContainsString('name="code"', $html);
                }
            }
            $smarty->assign(['codes' => ['<unsafe>']] + $base);
            $html = $smarty->fetch('file:' . XOOPS_ROOT_PATH . '/modules/system/templates/system_user2fa_manage.tpl');
            self::assertStringContainsString('&lt;unsafe&gt;', $html);
            self::assertStringNotContainsString('<form', $html);
        } finally {
            foreach (glob($directory . '/*') as $compiled) { unlink($compiled); }
            rmdir($directory);
        }
    }
}
