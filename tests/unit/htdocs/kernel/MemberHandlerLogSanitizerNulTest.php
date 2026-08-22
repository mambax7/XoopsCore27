<?php

declare(strict_types=1);

namespace kernel;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * PHP 8.6 NUL-byte pins for XoopsMemberHandler::sanitizeRequestUri()
 * (kernel/member.php) - the third first-party parse_str() call site.
 *
 * Unlike XoopsFileLogger::redactSessionId(), this site runs the raw
 * REQUEST_URI through parse_url() FIRST, and parse_url() neutralizes control
 * characters before the query substring ever reaches parse_str(). That makes
 * this site shielded from PHP 8.6's literal-NUL ValueError by construction -
 * a property worth pinning so a refactor that swaps parse_url() for a plain
 * explode('?') shows up as a failing test, not as a production crash on 8.6.
 *
 * The percent-encoded case is the realistic vector: parse_str() decodes %00
 * into a literal NUL in the VALUE, and sanitizeForLog() must strip it before
 * the value reaches a log line.
 */
final class MemberHandlerLogSanitizerNulTest extends TestCase
{
    /** @var \XoopsMemberHandler */
    private $handler;

    /** @var ReflectionMethod */
    private $method;

    /** @var string|null REQUEST_URI as found, so a forged one cannot outlive its test */
    private ?string $savedRequestUri = null;

    private bool $hadRequestUri = false;

    protected function setUp(): void
    {
        require_once XOOPS_ROOT_PATH . '/class/database/mysqldatabase.php';
        require_once XOOPS_ROOT_PATH . '/kernel/user.php';
        require_once XOOPS_ROOT_PATH . '/kernel/group.php';
        require_once XOOPS_ROOT_PATH . '/kernel/member.php';

        // sanitizeRequestUri() touches only $_SERVER and private helpers, so a
        // constructor-less instance (no database, no sub-handlers) is enough.
        $this->handler = (new ReflectionClass(\XoopsMemberHandler::class))->newInstanceWithoutConstructor();
        // No setAccessible(): a no-op since PHP 8.1 and deprecated in 8.5.
        $this->method  = new ReflectionMethod(\XoopsMemberHandler::class, 'sanitizeRequestUri');

        $this->hadRequestUri   = isset($_SERVER['REQUEST_URI']);
        $this->savedRequestUri = $_SERVER['REQUEST_URI'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->hadRequestUri) {
            $_SERVER['REQUEST_URI'] = $this->savedRequestUri;
        } else {
            unset($_SERVER['REQUEST_URI']);
        }
    }

    public function testPercentEncodedNulIsDecodedThenStrippedFromTheLogLine(): void
    {
        $_SERVER['REQUEST_URI'] = '/x.php?pass=secret&note=%00hi';

        $result = (string) $this->method->invoke($this->handler);

        // 'pass' is in SENSITIVE_PARAMS and must be redacted; the decoded NUL
        // in 'note' is a control character and sanitizeForLog() removes it.
        $this->assertSame('/x.php?pass=REDACTED&note=hi', $result);
        $this->assertStringNotContainsString('secret', $result);
        $this->assertStringNotContainsString("\0", $result);
    }

    public function testLiteralNulInRequestUriIsNeutralizedByParseUrl(): void
    {
        $_SERVER['REQUEST_URI'] = "/x.php?note=b\0c";

        // No version-conditional here, deliberately: parse_url() replaces the
        // control character before parse_str() runs, so this site must not
        // throw on ANY version, 8.6 included. If this test ever throws, the
        // parse_url() shield was lost in a refactor.
        $result = (string) $this->method->invoke($this->handler);

        // Pin the shield on PRE-8.6 runtimes too (review catch on the first
        // version of this test): with the shield swapped for a raw
        // explode('?'), pre-8.6 parse_str() silently truncates this fixture
        // to '/x.php?note=b' - which still starts with '/x.php' and contains
        // no NUL, so weaker assertions only caught the regression on 8.6.
        // Derive the expectation THROUGH parse_url() itself: whatever this
        // runtime normalizes the control character to, production must match
        // it exactly - a truncating implementation cannot.
        $parts = parse_url($_SERVER['REQUEST_URI']);
        parse_str($parts['query'] ?? '', $expectedParams);
        // 'note' is not in SENSITIVE_PARAMS, and the parse_url()-normalized
        // value contains no control characters, so sanitizeForLog() passes it
        // through unchanged.
        $expected = ($parts['path'] ?? '/') . '?' . http_build_query($expectedParams);

        $this->assertSame($expected, $result);
        $this->assertNotSame('/x.php?note=b', $result, 'truncation-at-NUL means the parse_url() shield was lost');
        $this->assertStringNotContainsString("\0", $result);
    }

    public function testMissingRequestUriReportsCli(): void
    {
        unset($_SERVER['REQUEST_URI']);

        $this->assertSame('cli', $this->method->invoke($this->handler));
    }
}
