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

    public function validateRedirectUrl(string $redirect, string $baseUrl): string
    {
        return $this->validateThemeRedirectUrl($redirect, $baseUrl);
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
     * @return array<string, array{string, string, string}>
     */
    public static function redirectProvider(): array
    {
        return [
            'local path' => ['/modules/publisher/item.php?itemid=123', 'http://localhost', '/modules/publisher/item.php?itemid=123'],
            'local path with query and fragment' => ['/modules/newbb/viewtopic.php?topic_id=1#post-3', 'http://localhost', '/modules/newbb/viewtopic.php?topic_id=1#post-3'],
            'same host http' => ['http://localhost/modules/newbb/viewpost.php?status=new', 'http://localhost', 'http://localhost/modules/newbb/viewpost.php?status=new'],
            'same host https' => ['https://localhost/modules/newbb/viewpost.php?status=new', 'http://localhost', ''],
            'external host' => ['https://example.com/modules/newbb/viewpost.php?status=new', 'http://localhost', ''],
            'scheme relative host' => ['//example.com/modules/newbb/viewpost.php?status=new', 'http://localhost', ''],
            'encoded scheme relative host' => ['&#47;&#47;example.com/modules/newbb/viewpost.php?status=new', 'http://localhost', ''],
            'javascript scheme' => ['javascript:alert(1)', 'http://localhost', ''],
            'relative path without leading slash' => ['modules/newbb/viewpost.php?status=new', 'http://localhost', ''],
            'header injection' => ["/modules/newbb/viewpost.php\r\nLocation: https://example.com", 'http://localhost', ''],
            'backslash bypass' => ['/\\evil.com', 'http://localhost', ''],
            'default port equivalence' => ['http://localhost:80/modules/newbb/viewpost.php?status=new', 'http://localhost', 'http://localhost:80/modules/newbb/viewpost.php?status=new'],
            'wrong port' => ['http://localhost:8080/', 'http://localhost', ''],
            'https default port equivalence' => ['https://localhost:443/modules/newbb/viewpost.php?status=new', 'https://localhost', 'https://localhost:443/modules/newbb/viewpost.php?status=new'],
            'http rejected on https site' => ['http://localhost/modules/newbb/viewpost.php?status=new', 'https://localhost', ''],
            'subdirectory base path query' => ['/xoops?x=1', 'http://localhost/xoops', '/xoops?x=1'],
            'subdirectory child path' => ['/xoops/modules/newbb/viewpost.php?status=new', 'http://localhost/xoops', '/xoops/modules/newbb/viewpost.php?status=new'],
            'subdirectory outside base path' => ['/modules/newbb/viewpost.php?status=new', 'http://localhost/xoops', ''],
            'subdirectory dot-dot bypass' => ['/xoops/../admin', 'http://localhost/xoops', ''],
            'absolute subdirectory dot-dot bypass' => ['http://localhost/xoops/../admin', 'http://localhost/xoops', ''],
            'encoded dot-dot bypass' => ['/xoops/%2e%2e/admin', 'http://localhost/xoops', ''],
            'encoded slash traversal lower' => ['/xoops/%2e%2e%2fadmin', 'http://localhost/xoops', ''],
            'encoded slash traversal upper' => ['/xoops/%2E%2E%2FAdmin', 'http://localhost/xoops', ''],
            'encoded backslash traversal' => ['/xoops/%2e%2e%5cadmin', 'http://localhost/xoops', ''],
            'encoded backslash scheme-relative' => ['/%5C%5Cevil.com', 'http://localhost', ''],
            'encoded backslash scheme-relative lower' => ['/%5c%5cevil.com', 'http://localhost', ''],
            'absolute encoded slash traversal' => ['http://localhost/xoops/%2e%2e%2fadmin', 'http://localhost/xoops', ''],
            'query with encoded slash allowed' => ['/news.php?path=a%2Fb', 'http://localhost', '/news.php?path=a%2Fb'],
            'absolute with userinfo' => ['http://user@localhost/modules/newbb/viewpost.php', 'http://localhost', ''],
            // Adversarial fixture: the userinfo here is the security test, not a credential.
            'absolute with user and password' => ['http://user:pass@localhost/modules/newbb/viewpost.php', 'http://localhost', ''], // NOSONAR
            'absolute with empty userinfo' => ['http://@localhost/modules/newbb/viewpost.php', 'http://localhost', ''],
        ];
    }

    #[DataProvider('redirectProvider')]
    public function testThemeRedirectUrlOnlyAllowsLocalTargets(string $input, string $baseUrl, string $expected): void
    {
        $kernel = new TestableThemeRedirectKernel();

        $this->assertSame($expected, $kernel->validateRedirectUrl($input, $baseUrl));
    }

    public function testThemeRedirectUrlReadsPostValue(): void
    {
        $_POST['xoops_theme_redirect'] = '/modules/newbb/viewpost.php?status=new';

        $kernel = new TestableThemeRedirectKernel();

        $this->assertSame('/modules/newbb/viewpost.php?status=new', $kernel->themeRedirectUrl());
    }
}
