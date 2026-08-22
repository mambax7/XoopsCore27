<?php

declare(strict_types=1);

namespace xoopsclass;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

require_once XOOPS_ROOT_PATH . '/class/xoopskernel.php';
require_once XOOPS_ROOT_PATH . '/class/logger/filelogger.php';
require_once XOOPS_ROOT_PATH . '/include/file_safety.php';

/**
 * PHP 8.6 NUL-byte boundary pins for the parse_str() call sites.
 *
 * PHP 8.6 throws ValueError when the string PASSED TO parse_str() contains a
 * literal NUL. A percent-encoded "%00" is three ordinary characters at that
 * boundary; parse_str() decodes it into a NUL in the OUTPUT values. These
 * tests pin both halves on every supported PHP version, so the distinction
 * (settled by direct experiment during the 8.6 review) survives refactors:
 *
 *  - "%00" input parses without error everywhere, and the decoded NUL in the
 *    output round-trips through rawurlencode()/http_build_query() re-encoding;
 *  - a literal "\0" input silently TRUNCATES parsing at the NUL before 8.6
 *    (observed: everything from the NUL onward is dropped), and is expected
 *    to throw ValueError from 8.6 on. The version-conditional assertions are
 *    behavior pins, not endorsements: if the final 8.6 UPGRADING changes the
 *    contract, or a guard lands in these methods, the failing pin is the
 *    signal to update this file.
 *
 * The stat-family half of the 8.6 NUL story is already pinned by
 * FileSafetyTest; testDecodedNulValueIsRejectedAtTheFileSafetyBoundary()
 * below adds only the connective tissue: a superglobal-shaped decoded value
 * carrying a NUL is refused before it can reach a filesystem call.
 */
final class Php86NulByteBoundaryTest extends TestCase
{
    // =========================================================================
    // xos_kernel_Xoops2::buildUrl() - class/xoopskernel.php
    // =========================================================================

    public function testBuildUrlRoundTripsPercentEncodedNul(): void
    {
        $kernel = new \xos_kernel_Xoops2();

        // parse_str() decodes %00 into a literal NUL in the value; buildUrl()
        // re-encodes with rawurlencode(), restoring %00. No error on any version.
        $this->assertSame(
            '/index.php?a=%00b&c=d',
            $kernel->buildUrl('/index.php?a=%00b&c=d')
        );
    }

    public function testBuildUrlMergesParamsOverPercentEncodedNulQuery(): void
    {
        $kernel = new \xos_kernel_Xoops2();

        $this->assertSame(
            '/index.php?a=%00b&x=1',
            $kernel->buildUrl('/index.php?a=%00b', ['x' => '1'])
        );
    }

    public function testBuildUrlWithLiteralNulByteInQuery(): void
    {
        $kernel = new \xos_kernel_Xoops2();

        if (PHP_VERSION_ID >= 80600) {
            // PHP 8.6: parse_str() rejects a literal NUL in its input string.
            $this->expectException(\ValueError::class);
        }

        $result = $kernel->buildUrl("/index.php?a=\0b&c=d");

        if (PHP_VERSION_ID < 80600) {
            // Pre-8.6: parse_str() silently truncates at the NUL - 'a' survives
            // with an empty value and 'c=d' is lost entirely.
            $this->assertSame('/index.php?a=', $result);
        }
    }

    // =========================================================================
    // XoopsFileLogger::redactSessionId() - class/logger/filelogger.php
    // (reaches parse_str() with the RAW request uri, not parse_url() output,
    //  so a literal NUL genuinely arrives at the 8.6 boundary here)
    // =========================================================================

    public function testRedactSessionIdHandlesPercentEncodedNulInSessionValue(): void
    {
        $result = $this->redact('/x.php?' . $this->sessionName() . '=abc%00def&q=1');

        $this->assertStringContainsString('sid%23redacted', $result);
        $this->assertStringContainsString('q=1', $result);
        $this->assertStringNotContainsString('abc', $result);
        $this->assertStringNotContainsString("\0", $result);
    }

    public function testRedactSessionIdReencodesPercentEncodedNulInOtherParam(): void
    {
        $result = $this->redact('/x.php?' . $this->sessionName() . '=zzz&note=%00hi');

        // The decoded NUL in 'note' is re-encoded by http_build_query(),
        // never emitted raw into the log line.
        $this->assertStringContainsString('sid%23redacted', $result);
        $this->assertStringContainsString('note=%00hi', $result);
        $this->assertStringNotContainsString("\0", $result);
    }

    public function testRedactSessionIdWithLiteralNulByte(): void
    {
        $name = $this->sessionName();

        if (PHP_VERSION_ID >= 80600) {
            $this->expectException(\ValueError::class);
        }

        $result = $this->redact('/x.php?' . $name . "=abc\0def&q=1");

        if (PHP_VERSION_ID < 80600) {
            // Truncation pin: parse_str() stops at the NUL, so only the session
            // parameter survives (redacted); 'q=1' is lost with the tail.
            $this->assertSame(
                '/x.php?' . http_build_query([$name => 'sid#redacted']),
                $result
            );
        }
    }

    // =========================================================================
    // Decoded superglobal value -> file-safety boundary (stat family)
    // =========================================================================

    public function testDecodedNulValueIsRejectedAtTheFileSafetyBoundary(): void
    {
        // This is exactly how PHP populates $_GET: percent-decoding turns
        // "%00" into a literal NUL inside the VALUE.
        parse_str('f=upload%00.png', $params);
        $this->assertSame("upload\0.png", $params['f']);

        // The guard collapses it before any stat/filesystem call can see it -
        // on 8.6 that call would be an uncaught ValueError.
        $this->assertSame('invalid-path', \xoops_safe_basename($params['f']));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function redact(string $uri): string
    {
        $logger = (new ReflectionClass(\XoopsFileLogger::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(\XoopsFileLogger::class, 'redactSessionId');
        $method->setAccessible(true);

        return (string) $method->invoke($logger, $uri);
    }

    private function sessionName(): string
    {
        $name = (string) session_name();

        return '' !== $name ? $name : 'PHPSESSID';
    }
}
