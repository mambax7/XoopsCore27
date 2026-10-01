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
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use xoopsclass\XoopsKernelThemeRedirectTest;

require_once XOOPS_ROOT_PATH . '/include/file_safety.php';

/**
 * Open-redirect regression: backslash and encoded-separator forms (Snyk CWE-601).
 *
 * Snyk flagged the taint flow request -> header('Location: ...') at
 *   htdocs/user.php:81
 *   htdocs/modules/profile/user.php:81
 * Snyk reports flows, not payloads; the payloads below reproduce that flow.
 * The request value reaches the Location header with its backslash intact,
 * parse_url() sees a host-less path, and browsers treat "\" as "/" in http(s)
 * URLs (WHATWG URL Standard, special schemes), so the target resolves to
 * //evil.test.
 *
 * The same blind spot exists in xoops_isLocalUrl(), which redirect_header()
 * relies on, so the contract is pinned there as well as on the shared validator.
 *
 * Contract pinned here:
 *  - xoops_isLocalUrl()            rejects every backslash / encoded-separator form
 *  - xoops_validateLocalRedirect() exists in include/file_safety.php, returns ''
 *    on rejection (the kernel's existing contract) and matches the kernel's
 *    theme-redirect table row for row
 *  - user.php and modules/profile/user.php use the shared validator instead of
 *    their hand-rolled $pathMatch logic
 *
 * @category  Xoops
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class RedirectBackslashTest extends TestCase
{
    /**
     * Payloads as the validator receives them (PHP has already URL-decoded the
     * query string once, so "/%5Cevil.test" on the wire arrives as "/\evil.test").
     *
     * @return array<string, array{string}>
     */
    public static function backslashPayloads(): array
    {
        return [
            'slash backslash (Snyk user.php:81 repro)' => ['/\\evil.test'],
            'backslash slash'                          => ['\\/evil.test'],
            'double backslash'                         => ['\\\\evil.test'],
            'slash double backslash'                   => ['/\\\\evil.test'],
            'still-encoded backslash'                  => ['/%5Cevil.test'],
            'still-encoded backslash lower'            => ['/%5cevil.test'],
            'still-encoded double backslash'           => ['/%5C%5Cevil.test'],
            'still-encoded double slash'               => ['/%2F%2Fevil.test'],
            // redirect_header() emits a meta refresh, where entities ARE decoded.
            'entity backslash'                         => ['/&#92;evil.test'],
            'entity backslash hex'                     => ['/&#x5c;evil.test'],
            // include/loginsession.php builds scheme://host . $posted_redirect and
            // relies on redirect_header() -> xoops_isLocalUrl() to catch this.
            'authority backslash (loginsession shape)' => ['http://localhost\\@evil.test/'],
        ];
    }

    /**
     * Browsers strip ASCII tab/LF/CR while parsing URLs, so "/<TAB>/x" becomes "//x".
     *
     * @return array<string, array{string}>
     */
    public static function strippedWhitespacePayloads(): array
    {
        return [
            'tab between slashes' => ["/\t/evil.test"],
            'lf between slashes'  => ["/\n/evil.test"],
            'cr between slashes'  => ["/\r/evil.test"],
        ];
    }

    /**
     * Legitimate same-site targets that must keep working after the fix.
     *
     * @return array<string, array{string}>
     */
    public static function legitimateTargets(): array
    {
        return [
            'root relative'              => ['/admin.php?fct=preferences'],
            'backslash in query only'    => ['/search.php?query=a%5Cb'],
            'literal backslash in query' => ['/search.php?query=a\\b'],
            'fragment'                   => ['/modules/news/article.php?storyid=1#comments'],
            'absolute same origin'       => ['http://localhost/user.php'],
        ];
    }

    private function requireSharedValidator(): void
    {
        if (!function_exists('xoops_validateLocalRedirect')) {
            self::fail('xoops_validateLocalRedirect() is missing from include/file_safety.php (fix not applied yet).');
        }
    }

    // ---------------------------------------------------------------------
    // xoops_isLocalUrl() — the helper redirect_header() already depends on
    // ---------------------------------------------------------------------

    #[Test]
    #[DataProvider('backslashPayloads')]
    #[DataProvider('strippedWhitespacePayloads')]
    public function isLocalUrlRejectsBackslashAndSeparatorForms(string $payload): void
    {
        self::assertFalse(xoops_isLocalUrl($payload), var_export($payload, true));
    }

    #[Test]
    #[DataProvider('legitimateTargets')]
    public function isLocalUrlStillAcceptsLegitimateTargets(string $url): void
    {
        self::assertTrue(xoops_isLocalUrl($url), $url);
    }

    // ---------------------------------------------------------------------
    // xoops_validateLocalRedirect() — the shared validator
    // ---------------------------------------------------------------------

    #[Test]
    #[DataProvider('backslashPayloads')]
    #[DataProvider('strippedWhitespacePayloads')]
    public function sharedValidatorRejectsBackslashAndSeparatorForms(string $payload): void
    {
        $this->requireSharedValidator();

        self::assertSame('', xoops_validateLocalRedirect($payload, XOOPS_URL), var_export($payload, true));
    }

    #[Test]
    #[DataProvider('legitimateTargets')]
    public function sharedValidatorKeepsLegitimateTargets(string $url): void
    {
        $this->requireSharedValidator();

        self::assertSame($url, xoops_validateLocalRedirect($url, XOOPS_URL), $url);
    }

    /**
     * Failure paths of the shared validator that the kernel table does not
     * exercise: empty input, unusable base URLs and a scheme without a host.
     *
     * @return array<string, array{string, string}>
     */
    public static function validatorEdgeCases(): array
    {
        return [
            'empty redirect'           => ['', 'http://localhost'],
            'whitespace-only redirect' => ["  \t ", 'http://localhost'],
            'empty base url'           => ['/user.php', ''],
            'base url without scheme'  => ['/user.php', 'localhost'],
            'base url without host'    => ['/user.php', 'http:///xoops'],
            'scheme without host'      => ['http:/evil.test/', 'http://localhost'],
            'non-http scheme'          => ['mailto:someone@example.test', 'http://localhost'],
        ];
    }

    #[Test]
    #[DataProvider('validatorEdgeCases')]
    public function sharedValidatorRejectsEdgeCases(string $redirect, string $baseUrl): void
    {
        $this->requireSharedValidator();

        self::assertSame('', xoops_validateLocalRedirect($redirect, $baseUrl));
    }

    #[Test]
    public function sharedValidatorReturnsTrimmedTarget(): void
    {
        $this->requireSharedValidator();

        self::assertSame('/user.php', xoops_validateLocalRedirect("  /user.php \n", 'http://localhost'));
    }

    /**
     * The kernel's theme-redirect table is the most thorough redirect contract
     * in the tree; the shared validator must agree with it row for row so the
     * kernel can delegate without a behaviour change.
     */
    #[Test]
    #[DataProviderExternal(XoopsKernelThemeRedirectTest::class, 'redirectProvider')]
    public function sharedValidatorMatchesKernelThemeRedirectContract(string $input, string $baseUrl, string $expected): void
    {
        $this->requireSharedValidator();

        self::assertSame($expected, xoops_validateLocalRedirect($input, $baseUrl));
    }

    /**
     * End to end through the exact accessor user.php uses:
     * Request::getUrl('xoops_redirect', '', 'GET'). Whatever it returns must be
     * rejected by the validator; this holds whether the accessor passes the
     * payload through or returns ''.
     */
    #[Test]
    #[DataProvider('backslashPayloads')]
    public function requestGetUrlOutputIsRejectedByValidator(string $payload): void
    {
        $this->requireSharedValidator();
        if (!class_exists(\Xmf\Request::class)) {
            self::markTestSkipped('Xmf\Request is not autoloadable in this bootstrap.');
        }

        $saved = $_GET['xoops_redirect'] ?? null;
        $_GET['xoops_redirect'] = $payload;
        try {
            $received = \Xmf\Request::getUrl('xoops_redirect', '', 'GET');
        } finally {
            if (null === $saved) {
                unset($_GET['xoops_redirect']);
            } else {
                $_GET['xoops_redirect'] = $saved;
            }
        }

        self::assertSame('', xoops_validateLocalRedirect($received, XOOPS_URL), var_export($received, true));
    }

    // ---------------------------------------------------------------------
    // Source pins: the flagged call sites use the shared validator
    // ---------------------------------------------------------------------

    /** @return array<string, array{string}> */
    public static function flaggedRedirectFiles(): array
    {
        return [
            'user.php (Snyk line 81)'                 => ['user.php'],
            'modules/profile/user.php (Snyk line 81)' => ['modules/profile/user.php'],
        ];
    }

    #[Test]
    #[DataProvider('flaggedRedirectFiles')]
    public function flaggedFilesDelegateToSharedValidator(string $relPath): void
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/' . $relPath);
        self::assertNotFalse($src);

        self::assertTrue(
            str_contains($src, 'xoops_validateLocalRedirect('),
            "$relPath must validate xoops_redirect through xoops_validateLocalRedirect()."
        );
        self::assertSame(
            0,
            preg_match('/\$pathMatch\s*=/', $src),
            "$relPath still carries the hand-rolled \$pathMatch check that misses backslash forms."
        );
    }

    #[Test]
    public function kernelThemeRedirectDelegatesToSharedValidator(): void
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/class/xoopskernel.php');
        self::assertNotFalse($src);

        self::assertSame(
            1,
            preg_match('/function\s+validateThemeRedirectUrl\b[^{]*\{[^}]*xoops_validateLocalRedirect\(/s', $src),
            'validateThemeRedirectUrl() should delegate to xoops_validateLocalRedirect() so there is one policy.'
        );
    }

    #[Test]
    public function redirectHeaderGateIsNotBackslashBlind(): void
    {
        // redirect_header() only consults xoops_isLocalUrl() for targets with a
        // scheme or a leading "//". "/\evil.test" has neither, so it skips the
        // check entirely. This exact gate must go (or be fed a normalised probe).
        $src = file_get_contents(XOOPS_ROOT_PATH . '/include/functions.php');
        self::assertNotFalse($src);

        self::assertSame(
            0,
            preg_match('/strncmp\(\s*ltrim\(\s*\$decoded\s*\)\s*,\s*\'\/\/\'\s*,\s*2\s*\)/', $src),
            'redirect_header(): the "//"-only gate lets "/\\host" bypass xoops_isLocalUrl().'
        );
    }
}
