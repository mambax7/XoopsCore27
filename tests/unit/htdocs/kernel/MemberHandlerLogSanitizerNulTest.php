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
        $this->method  = new ReflectionMethod(\XoopsMemberHandler::class, 'sanitizeRequestUri');
        $this->method->setAccessible(true);

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

        $this->assertStringStartsWith('/x.php', $result);
        $this->assertStringNotContainsString("\0", $result);
    }

    public function testMissingRequestUriReportsCli(): void
    {
        unset($_SERVER['REQUEST_URI']);

        $this->assertSame('cli', $this->method->invoke($this->handler));
    }
}
