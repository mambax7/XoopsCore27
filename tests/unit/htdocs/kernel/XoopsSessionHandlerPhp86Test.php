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

    /** @var string|null REMOTE_ADDR as found, so a forged one cannot outlive its test */
    private ?string $savedRemoteAddr = null;

    private bool $hadRemoteAddr = false;

    protected function setUp(): void
    {
        $this->hadRemoteAddr   = isset($_SERVER['REMOTE_ADDR']);
        $this->savedRemoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
        $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '192.168.1.100';

        $this->db = $this->createMockDatabase();

        $ref = new ReflectionClass(\XoopsSessionHandler::class);
        $this->handler = $ref->newInstanceWithoutConstructor();
        $this->setProtectedProperty($this->handler, 'db', $this->db);
    }

    protected function tearDown(): void
    {
        if ($this->hadRemoteAddr) {
            $_SERVER['REMOTE_ADDR'] = $this->savedRemoteAddr;
        } else {
            unset($_SERVER['REMOTE_ADDR']);
        }
    }

    // =========================================================================
    // SessionIdInterface / create_sid()
    // =========================================================================

    /** PHP 8.6 checks for the method; the interface is declared as documentation. */
    public function testImplementsSessionIdInterface(): void
    {
        $this->assertInstanceOf(\SessionIdInterface::class, $this->handler);
    }

    /** Fixed 32-hex format, independent of session.sid_* settings. */
    public function testCreateSidReturns32LowercaseHexCharacters(): void
    {
        $sid = $this->handler->create_sid();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $sid);
    }

    /** IDs must pass include/common.php's SSL-bridge regex to be forwardable. */
    public function testCreateSidMatchesSslBridgePattern(): void
    {
        // include/common.php only forwards a POSTed session ID matching
        // this pattern; generated IDs must be forwardable.
        $sid = $this->handler->create_sid();

        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9,-]{26,128}$/', $sid);
    }

    /** 128 bits of randomness: 100 draws must not collide. */
    public function testCreateSidReturnsUniqueIds(): void
    {
        $seen = [];
        for ($i = 0; $i < 100; $i++) {
            $seen[$this->handler->create_sid()] = true;
        }

        $this->assertCount(100, $seen);
    }

    /** ID generation is pure; validation happens later in validateId(). */
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

    /** Probe-first: assert the pin where possible, the honest failure report where not. */
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
                if ('1' === ini_get('session.use_strict_mode')) {
                    // Unchangeable but already strict: the short-circuit answers
                    // true and no notice is owed.
                    $this->assertTrue($this->handler->enforceStrictMode());
                    $this->markTestSkipped('environment forbids session ini changes (already strict)');
                }
                // Capture the diagnostic instead of suppressing it: the contract
                // is false PLUS an E_USER_WARNING naming the directive.
                $captured = [];
                set_error_handler(static function (int $errno, string $errstr) use (&$captured): bool {
                    $captured[] = [$errno, $errstr];

                    return true;
                });
                try {
                    $result = $this->handler->enforceStrictMode();
                } finally {
                    restore_error_handler();
                }
                $this->assertFalse(
                    $result,
                    'when the directive cannot be changed, the helper must report failure, not pretend'
                );
                // ini_set() itself emits a native E_WARNING here ("Session ini
                // settings cannot be changed after headers have already been
                // sent") BEFORE the helper's diagnostic, so captured[0] is the
                // engine's entry, not ours (review catch on the first version
                // of this branch). Filter to the promised E_USER_WARNING.
                $userWarnings = array_values(array_filter(
                    $captured,
                    static fn (array $error): bool => E_USER_WARNING === $error[0]
                ));
                $this->assertNotEmpty($userWarnings, 'the refusal must be accompanied by an E_USER_WARNING diagnostic');
                $this->assertStringContainsString('use_strict_mode', $userWarnings[0][1]);
                $this->markTestSkipped('environment forbids session ini changes (headers already sent)');
            }

            $this->assertTrue($this->handler->enforceStrictMode());
            $this->assertSame('1', ini_get('session.use_strict_mode'));
        } finally {
            @ini_set('session.use_strict_mode', (string) $original);
        }
    }

    /** A refusal while a session is active must warn, never fail silently. */
    public function testEnforceStrictModeRefusesMidSessionWithAWarning(): void
    {
        if (PHP_SESSION_ACTIVE === session_status()) {
            $this->markTestSkipped('a session is already active in this runner');
        }
        if (!@session_start()) {
            $this->markTestSkipped('cannot start a session in this environment');
        }
        try {
            $captured = [];
            set_error_handler(static function (int $errno, string $errstr) use (&$captured): bool {
                $captured[] = [$errno, $errstr];

                return true;
            });
            try {
                $result = $this->handler->enforceStrictMode();
            } finally {
                restore_error_handler();
            }

            if ('1' === ini_get('session.use_strict_mode')) {
                // Already-strict short-circuits before the mid-session check.
                $this->assertTrue($result);

                return;
            }
            $this->assertFalse($result);
            $this->assertNotEmpty($captured, 'the mid-session refusal must be accompanied by a diagnostic');
            $this->assertSame(E_USER_WARNING, $captured[0][0]);
            $this->assertStringContainsString('mid-session', $captured[0][1]);
        } finally {
            session_abort();
        }
    }

    /** Already '1' (php.ini, earlier call, or the 8.6 default): success without changing anything. */
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

    /** PHP 8.6 lazy-write routes new empty sessions here; a plain UPDATE would lose them. */
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

    /** The insert half of the upsert must carry IP and data. */
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

    /** The duplicate half must never rewrite sess_data or sess_ip. */
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

    /** A failed exec() must surface as false, not success. */
    public function testUpdateTimestampReturnsFalseWhenExecFails(): void
    {
        $this->db->method('exec')->willReturn(false);

        $this->assertFalse($this->handler->updateTimestamp('sess_abc', 'payload'));
    }
}
