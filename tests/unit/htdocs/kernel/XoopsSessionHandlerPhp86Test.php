<?php

declare(strict_types=1);

namespace kernel;

use ReflectionClass;
use XoopsMySQLDatabase;

require_once XOOPS_ROOT_PATH . '/kernel/session.php';

/**
 * PHP 8.6 compatibility tests for XoopsSessionHandler.
 *
 * Covers the three PHP 8.6 session changes:
 *  - session_set_save_handler() deprecates object handlers without
 *    create_sid() + validateId()  -> SessionIdInterface / create_sid()
 *  - session.use_strict_mode default flips 0 -> 1 -> enforceStrictMode()
 *  - unchanged (incl. new-and-empty) sessions route to updateTimestamp()
 *    instead of write() under lazy_write -> upsert behavior
 *
 * Uses reflection to bypass the constructor (which depends on globals and
 * session_set_cookie_params) and injects a mock database, matching
 * XoopsSessionHandlerTest.
 */
class XoopsSessionHandlerPhp86Test extends KernelTestCase
{
    /** @var XoopsMySQLDatabase|\PHPUnit\Framework\MockObject\MockObject */
    private $db;

    /** @var \XoopsSessionHandler */
    private $handler;

    protected function setUp(): void
    {
        $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '192.168.1.100';

        $this->db = $this->createMockDatabase();

        $ref = new ReflectionClass(\XoopsSessionHandler::class);
        $this->handler = $ref->newInstanceWithoutConstructor();
        $this->setProtectedProperty($this->handler, 'db', $this->db);
    }

    // =========================================================================
    // SessionIdInterface / create_sid()
    // =========================================================================

    public function testImplementsSessionIdInterface(): void
    {
        $this->assertInstanceOf(\SessionIdInterface::class, $this->handler);
    }

    public function testCreateSidReturns32LowercaseHexCharacters(): void
    {
        $sid = $this->handler->create_sid();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $sid);
    }

    public function testCreateSidMatchesSslBridgePattern(): void
    {
        // include/common.php only forwards a POSTed session ID matching
        // this pattern; generated IDs must be forwardable.
        $sid = $this->handler->create_sid();

        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9,-]{26,128}$/', $sid);
    }

    public function testCreateSidReturnsUniqueIds(): void
    {
        $seen = [];
        for ($i = 0; $i < 100; $i++) {
            $seen[$this->handler->create_sid()] = true;
        }

        $this->assertCount(100, $seen);
    }

    public function testCreateSidDoesNotTouchDatabase(): void
    {
        $this->db->expects($this->never())->method('query');
        $this->db->expects($this->never())->method('queryF');
        $this->db->expects($this->never())->method('exec');

        $this->handler->create_sid();
    }

    // =========================================================================
    // enforceStrictMode()
    // =========================================================================

    public function testEnforceStrictModePinsIniToOneWhenTheEnvironmentAllows(): void
    {
        // PHP refuses ALL session.* ini changes once headers are sent - and
        // whether a PHPUnit run has "sent headers" depends on PHP version and
        // printer (peer review reproduced a deterministic failure of the naive
        // form of this test on PHP 8.2.33). Probe first: if this environment
        // cannot change the directive at all, the helper's honest answer is
        // false, and asserting '1' would test the runner, not the code.
        $original = ini_get('session.use_strict_mode');
        $probe    = @ini_set('session.use_strict_mode', '0');
        try {
            if (false === $probe) {
                $this->assertFalse(
                    @$this->handler->enforceStrictMode(),
                    'when the directive cannot be changed, the helper must report failure, not pretend'
                );
                $this->markTestSkipped('environment forbids session ini changes (headers already sent)');
            }

            $this->assertTrue($this->handler->enforceStrictMode());
            $this->assertSame('1', ini_get('session.use_strict_mode'));
        } finally {
            @ini_set('session.use_strict_mode', (string) $original);
        }
    }

    public function testEnforceStrictModeShortCircuitsWhenAlreadyPinned(): void
    {
        $original = ini_get('session.use_strict_mode');
        if (false === @ini_set('session.use_strict_mode', '1')) {
            $this->markTestSkipped('environment forbids session ini changes (headers already sent)');
        }
        try {
            // Already '1': must report success without changing anything.
            $this->assertTrue($this->handler->enforceStrictMode());
            $this->assertSame('1', ini_get('session.use_strict_mode'));
        } finally {
            @ini_set('session.use_strict_mode', (string) $original);
        }
    }

    // =========================================================================
    // updateTimestamp() upsert
    // =========================================================================

    public function testUpdateTimestampUsesUpsert(): void
    {
        // PHP 8.6 routes a new-and-still-empty session here instead of
        // write(); a plain UPDATE would never create the row and strict
        // mode would reject the ID on the next request.
        $sqlCaptured = null;
        $this->db->expects($this->once())
            ->method('exec')
            ->willReturnCallback(function (string $sql) use (&$sqlCaptured) {
                $sqlCaptured = $sql;
                return true;
            });

        $result = $this->handler->updateTimestamp('new_empty_session', '');

        $this->assertTrue($result);
        $this->assertStringContainsString('INSERT INTO xoops_session', $sqlCaptured);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $sqlCaptured);
        $this->assertStringContainsString('new_empty_session', $sqlCaptured);
    }

    public function testUpdateTimestampInsertsIpAndDataForNewRow(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        $sqlCaptured = null;
        $this->db->method('exec')
            ->willReturnCallback(function (string $sql) use (&$sqlCaptured) {
                $sqlCaptured = $sql;
                return true;
            });

        $this->handler->updateTimestamp('sess_abc', 'the_session_payload');

        $this->assertStringContainsString('sess_ip', $sqlCaptured);
        $this->assertStringContainsString('sess_data', $sqlCaptured);
        $this->assertStringContainsString('the_session_payload', $sqlCaptured);
    }

    public function testUpdateTimestampDuplicateClauseTouchesOnlyTimestamp(): void
    {
        // An existing session must keep its stored data: only
        // sess_updated may appear after ON DUPLICATE KEY UPDATE.
        $sqlCaptured = null;
        $this->db->method('exec')
            ->willReturnCallback(function (string $sql) use (&$sqlCaptured) {
                $sqlCaptured = $sql;
                return true;
            });

        $this->handler->updateTimestamp('sess_abc', 'payload');

        $duplicateClause = substr($sqlCaptured, (int) strpos($sqlCaptured, 'ON DUPLICATE KEY UPDATE'));
        $this->assertStringContainsString('sess_updated', $duplicateClause);
        $this->assertStringNotContainsString('sess_data', $duplicateClause);
        $this->assertStringNotContainsString('sess_ip', $duplicateClause);
    }

    public function testUpdateTimestampReturnsFalseWhenExecFails(): void
    {
        $this->db->method('exec')->willReturn(false);

        $this->assertFalse($this->handler->updateTimestamp('sess_abc', 'payload'));
    }
}
