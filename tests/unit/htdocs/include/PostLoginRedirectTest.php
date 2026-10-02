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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/include/file_safety.php';

/**
 * The post-login redirect target (include/loginsession.php).
 *
 * xoops_login_establish_session() used to build the URL by hand:
 * scheme://host[:port] + the base path when the decoded value did not start
 * with it + the posted xoops_redirect value, relying on redirect_header() to
 * catch anything off-site. xoops_postLoginRedirectUrl() keeps that shape
 * (decode once, prepend the base path for values sent without it) and then
 * applies the shared same-site policy, xoops_validateLocalRedirect(), so the
 * login redirect follows the same rules as user.php, the profile module and
 * the theme selector: no other host, no backslash or encoded-separator
 * forms, no ".." segments, nothing outside the install's base path.
 *
 * @category  Xoops
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class PostLoginRedirectTest extends TestCase
{
    private function requireHelper(): void
    {
        if (!function_exists('xoops_postLoginRedirectUrl')) {
            self::fail('xoops_postLoginRedirectUrl() is missing from include/file_safety.php.');
        }
    }

    /** @return array<string, array{string, string, string}> posted value, base URL, expected URL */
    public static function acceptedTargets(): array
    {
        return [
            'root-relative path'           => ['/modules/news/article.php?storyid=3', 'http://localhost', 'http://localhost/modules/news/article.php?storyid=3'],
            'url-encoded path'             => ['%2Fmodules%2Fnews%2Farticle.php%3Fstoryid%3D3', 'http://localhost', 'http://localhost/modules/news/article.php?storyid=3'],
            'same-origin absolute URL'     => ['http%3A%2F%2Flocalhost%2Fuser.php', 'http://localhost', 'http://localhost/user.php'],
            'subdir: path with base path'  => ['/xoops/modules/news/', 'http://localhost/xoops', 'http://localhost/xoops/modules/news/'],
            'subdir: path without base'    => ['/modules/news/', 'http://localhost/xoops', 'http://localhost/xoops/modules/news/'],
            'subdir: base path itself'     => ['/xoops', 'http://localhost/xoops', 'http://localhost/xoops'],
            'subdir: base path with query' => ['/xoops?x=1', 'http://localhost/xoops', 'http://localhost/xoops?x=1'],
            'port and https kept'          => ['/user.php', 'https://example.test:8443', 'https://example.test:8443/user.php'],
            'backslash only in query'      => ['/search.php?q=a%5Cb', 'http://localhost', 'http://localhost/search.php?q=a\\b'],
            'register only in the query'   => ['/modules/news/?ref=register', 'http://localhost', 'http://localhost/modules/news/?ref=register'],
            'subdir: base path with fragment' => ['/xoops#top', 'http://localhost/xoops', 'http://localhost/xoops#top'],
            'subdir: fragment without base'   => ['/modules/news/#c', 'http://localhost/xoops', 'http://localhost/xoops/modules/news/#c'],
        ];
    }

    #[Test]
    #[DataProvider('acceptedTargets')]
    public function acceptedTargetBecomesAnAbsoluteUrlOnTheSite(string $posted, string $baseUrl, string $expected): void
    {
        $this->requireHelper();

        self::assertSame($expected, xoops_postLoginRedirectUrl($posted, $baseUrl));
    }

    /** @return array<string, array{string}> */
    public static function refusedTargets(): array
    {
        return [
            'empty'                          => [''],
            'registration page'              => ['/register.php'],
            'encoded registration page'      => ['%2Fregister.php'],
            'encoded letter in register'     => ['%2F%72egister.php'],
            'encoded letter, plain slash'    => ['/%72egister.php'],
            'double-encoded letter'          => ['/%2572egister.php'],
            'upper-case registration page'   => ['/REGISTER.php'],
            'module registration page'       => ['/modules/profile/register.php'],
            'entity-encoded letter (hex)'    => ['/&#x72;egister.php'],
            'entity-encoded letter (decimal)' => ['/&#114;egister.php'],
            'percent-encoded entity'         => ['%2F%26%23x72%3Begister.php'],
            'entity inside percent-encoding' => ['/%26%23114%3B%65gister.php'],
            'slash backslash'                => ['/\\evil.test'],
            'encoded slash backslash'        => ['%2F%5Cevil.test'],
            'double-encoded slash backslash' => ['%252F%255Cevil.test'],
            'scheme-relative'                => ['//evil.test'],
            'encoded scheme-relative'        => ['%2F%2Fevil.test'],
            'tab between slashes'            => ['%2F%09%2Fevil.test'],
            'other host'                     => ['http://evil.test/'],
            'userinfo trick'                 => ['@evil.test'],
            'backslash userinfo trick'       => ['\\@evil.test'],
            'host suffix trick'              => ['.evil.test'],
            'port trick'                     => [':8080/'],
            'parent segments'                => ['/modules/../../etc/passwd'],
            'script scheme'                  => ['javascript:alert(1)'],
            'bare relative path'             => ['modules/news/'],
        ];
    }

    #[Test]
    #[DataProvider('refusedTargets')]
    public function refusedTargetFallsBackToTheIndexPage(string $posted): void
    {
        $this->requireHelper();

        self::assertSame('http://localhost/index.php', xoops_postLoginRedirectUrl($posted, 'http://localhost'));
    }

    #[Test]
    public function subdirectoryInstallFallsBackToItsOwnIndexPage(): void
    {
        $this->requireHelper();

        self::assertSame('http://localhost/xoops/index.php', xoops_postLoginRedirectUrl('/xoops/../admin.php', 'http://localhost/xoops'));
        self::assertSame('http://localhost/xoops/index.php', xoops_postLoginRedirectUrl('/\\evil.test', 'http://localhost/xoops/'));
    }

    #[Test]
    public function establishSessionUsesTheHelper(): void
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/include/loginsession.php');
        self::assertNotFalse($src);

        self::assertSame(
            1,
            preg_match('/\$url\s*=\s*xoops_postLoginRedirectUrl\(\s*\$redirect\s*\)\s*;/', $src),
            'xoops_login_establish_session() must build its redirect with xoops_postLoginRedirectUrl().'
        );
        self::assertSame(
            0,
            preg_match('/strncmp\(\s*\$parsed\[/', $src),
            'The hand-built post-login URL is still present.'
        );
    }
}
