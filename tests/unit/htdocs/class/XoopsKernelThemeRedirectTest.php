<?php

declare(strict_types=1);

namespace xoopsclass;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/class/xoopskernel.php';

class TestableThemeRedirectKernel extends \xos_kernel_Xoops2
{
    public function themeRedirectUrl(): string
    {
        return $this->getThemeRedirectUrl();
    }
}

final class XoopsKernelThemeRedirectTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_POST['xoops_theme_redirect']);
        parent::tearDown();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function redirectProvider(): array
    {
        return [
            'local path' => ['/modules/publisher/item.php?itemid=123', '/modules/publisher/item.php?itemid=123'],
            'local path with query and fragment' => ['/modules/newbb/viewtopic.php?topic_id=1#post-3', '/modules/newbb/viewtopic.php?topic_id=1#post-3'],
            'same host http' => ['http://localhost/modules/newbb/viewpost.php?status=new', 'http://localhost/modules/newbb/viewpost.php?status=new'],
            'same host https' => ['https://localhost/modules/newbb/viewpost.php?status=new', 'https://localhost/modules/newbb/viewpost.php?status=new'],
            'external host' => ['https://example.com/modules/newbb/viewpost.php?status=new', ''],
            'scheme relative host' => ['//example.com/modules/newbb/viewpost.php?status=new', ''],
            'encoded scheme relative host' => ['&#47;&#47;example.com/modules/newbb/viewpost.php?status=new', ''],
            'javascript scheme' => ['javascript:alert(1)', ''],
            'relative path without leading slash' => ['modules/newbb/viewpost.php?status=new', ''],
            'header injection' => ["/modules/newbb/viewpost.php\r\nLocation: https://example.com", ''],
        ];
    }

    #[DataProvider('redirectProvider')]
    public function testThemeRedirectUrlOnlyAllowsLocalTargets(string $input, string $expected): void
    {
        $_POST['xoops_theme_redirect'] = $input;

        $kernel = new TestableThemeRedirectKernel();

        $this->assertSame($expected, $kernel->themeRedirectUrl());
    }
}
