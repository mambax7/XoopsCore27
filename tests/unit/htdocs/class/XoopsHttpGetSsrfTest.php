<?php

declare(strict_types=1);

namespace xoopsclass;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use XoopsHttpGet;

require_once XOOPS_ROOT_PATH . '/class/xoopshttpget.php';

/**
 * Exposes the protected SSRF guard and bypasses the constructor's curl/fopen
 * requirement (isAllowedUrl() is pure: parse_url + filter_var + DNS).
 */
final class HttpGetSsrfProbe extends XoopsHttpGet
{
    public function __construct() {} // intentionally skip parent (no transport needed)

    public function allowed(string $url): bool
    {
        return $this->isAllowedUrl($url);
    }
}

/**
 * Regression tests for the XoopsHttpGet SSRF guard (SECURITY.md M-5).
 *
 * Only rejection cases are asserted — they use literal IPs / schemes and need no
 * network. The "allowed public host" path depends on live DNS and is not unit-tested.
 *
 * @see \XoopsHttpGet
 */
final class XoopsHttpGetSsrfTest extends TestCase
{
    /**
     * @return array<string, array{0:string}>
     */
    public static function rejectedUrls(): array
    {
        return [
            'file scheme'         => ['file:///etc/passwd'],
            'php wrapper'         => ['php://filter/resource=/etc/passwd'],
            'gopher scheme'       => ['gopher://127.0.0.1:6379/'],
            'loopback ipv4'       => ['http://127.0.0.1/'],
            'loopback name-ish'   => ['http://127.1/'],
            'link-local metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'private 10/8'        => ['http://10.0.0.5/'],
            'private 172.16/12'   => ['http://172.16.0.1/'],
            'private 192.168/16'  => ['http://192.168.1.1/'],
            'userinfo form'       => ['http://user:pass@example.com/'],
            'scheme-relative'     => ['//example.com/'],
            'no scheme/host'      => ['/relative/path'],
            // IPv6 literals: parse_url keeps the [..] brackets, which fail FILTER_VALIDATE_IP
            // and DNS resolution, so these are rejected without relying on the IPv6 coverage
            // of FILTER_FLAG_NO_PRIV_RANGE / NO_RES_RANGE.
            'ipv6 loopback'       => ['http://[::1]/'],
            'ipv6 mapped loopback'=> ['http://[::ffff:127.0.0.1]/'],
            'ipv6 mapped metadata'=> ['http://[::ffff:169.254.169.254]/'],
            'ipv6 link-local'     => ['http://[fe80::1]/'],
            'ipv6 ula private'    => ['http://[fd00::1]/'],
        ];
    }

    #[Test]
    #[DataProvider('rejectedUrls')]
    public function rejectsUnsafeTargets(string $url): void
    {
        $probe = new HttpGetSsrfProbe();
        self::assertFalse($probe->allowed($url), $url . ' must be rejected by the SSRF guard');
    }
}
