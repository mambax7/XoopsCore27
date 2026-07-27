<?php

declare(strict_types=1);

namespace xoopslogger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use XoopsFileLogger;

/**
 * Tests for XoopsFileLogger.
 *
 * The emphasis is on the properties that make a log file safe to have, because every one
 * of them was a real defect at some point during review: a filename that could be made
 * PHP-executable, a guard that failed open, a URI rebuild that reinstated raw newlines,
 * and session data reaching the file.
 *
 * Note that the test bootstrap places XOOPS_VAR_PATH *inside* XOOPS_ROOT_PATH, so the log
 * directory is "web accessible" as far as the logger is concerned. That is deliberate here:
 * it exercises the fail-closed path, and every test that wants a write must opt in with
 * allow_web_accessible_log.
 */
#[CoversClass(XoopsFileLogger::class)]
class XoopsFileLoggerTest extends TestCase
{
    private string $logDir;

    /** @var string|null REQUEST_URI as found, so a forged one cannot outlive its test */
    private ?string $originalRequestUri = null;

    protected function setUp(): void
    {
        require_once XOOPS_ROOT_PATH . '/class/logger/filelogger.php';
        $this->logDir = XOOPS_VAR_PATH . '/logs';
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0775, true);
        }
        $this->originalRequestUri = $_SERVER['REQUEST_URI'] ?? null;
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        if (null === $this->originalRequestUri) {
            unset($_SERVER['REQUEST_URI']);
        } else {
            $_SERVER['REQUEST_URI'] = $this->originalRequestUri;
        }
        $this->cleanUp();
    }

    /** Remove only the files these tests create. */
    private function cleanUp(): void
    {
        foreach (glob($this->logDir . '/phpunit*.log*') ?: [] as $file) {
            @unlink($file);
        }
    }

    /** Build a logger that is allowed to write, so behaviour can be observed. */
    private function makeLogger(array $overrides = []): XoopsFileLogger
    {
        return new XoopsFileLogger(array_merge([
            'file'                     => 'phpunit.log',
            'channels'                 => ['messages'],
            'backtrace'                => false,
            'allow_web_accessible_log' => true,
        ], $overrides));
    }

    private function readLog(string $name = 'phpunit.log'): string
    {
        $path = $this->logDir . '/' . $name;

        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    private function property(XoopsFileLogger $logger, string $name): mixed
    {
        $property = new ReflectionProperty(XoopsFileLogger::class, $name);
        $property->setAccessible(true);

        return $property->getValue($logger);
    }

    // -----------------------------------------------------------------
    // Filename: nothing PHP-executable, no Windows device stems
    // -----------------------------------------------------------------

    public static function unsafeFilenameProvider(): array
    {
        return [
            'php extension'      => ['debug.php'],
            'uppercase php'      => ['shell.PHP'],
            'phtml'              => ['x.phtml'],
            'double extension'   => ['debug.log.php'],
            'traversal'          => ['../../../mainfile.php'],
            'dotfile'            => ['.htaccess'],
            'windows nul device' => ['NUL.log'],
            'windows com port'   => ['COM1.log'],
            'lowercase device'   => ['nul.log'],
            'empty'              => [''],
        ];
    }

    #[Test]
    #[DataProvider('unsafeFilenameProvider')]
    public function unsafeFilenamesFallBackToTheDefault(string $candidate): void
    {
        $logger = $this->makeLogger(['file' => $candidate]);

        self::assertSame('debug.log', basename((string) $this->property($logger, 'file')));
    }

    public static function safeFilenameProvider(): array
    {
        return [
            ['debug.log'],
            ['phpunit.log'],
            ['my-app_2.log'],
            ['console.log'],
        ];
    }

    #[Test]
    #[DataProvider('safeFilenameProvider')]
    public function plainLogFilenamesAreAccepted(string $candidate): void
    {
        $logger = $this->makeLogger(['file' => $candidate]);

        self::assertSame($candidate, basename((string) $this->property($logger, 'file')));
    }

    // -----------------------------------------------------------------
    // Fail closed when the log directory is reachable over the web
    // -----------------------------------------------------------------

    #[Test]
    public function refusesToWriteWhenTheLogDirectoryIsBelowTheDocumentRoot(): void
    {
        // No allow_web_accessible_log, and the bootstrap puts xoops_data under htdocs.
        $logger = new XoopsFileLogger(['file' => 'phpunit.log', 'channels' => ['messages']]);

        self::assertTrue((bool) $this->property($logger, 'writeFailed'));

        $logger->log('error', 'must not be written', ['channel' => 'messages']);
        self::assertSame('', $this->readLog());
    }

    #[Test]
    public function writesWhenTheRiskIsExplicitlyAccepted(): void
    {
        $logger = $this->makeLogger();
        $logger->log('error', 'written after opt-in', ['channel' => 'messages']);

        self::assertStringContainsString('written after opt-in', $this->readLog());
    }

    // -----------------------------------------------------------------
    // Log injection
    // -----------------------------------------------------------------

    #[Test]
    public function newlinesInAMessageCannotForgeAnEntry(): void
    {
        $logger = $this->makeLogger();
        $logger->log('error', "first line\n[2026-01-01 00:00:00] messages.ERROR FORGED", ['channel' => 'messages']);

        $body = $this->readLog();
        self::assertStringNotContainsString("\n[2026-01-01", $body);
        self::assertStringContainsString('FORGED', $body, 'the text survives, only the newline is neutralised');
    }

    /**
     * Call redactSessionId() directly.
     *
     * currentUri() short circuits to 'cli' under a CLI SAPI, which is exactly how this
     * suite runs — asserting on a log entry's uri= field would pass without testing
     * anything at all.
     */
    private function redact(XoopsFileLogger $logger, string $uri): string
    {
        $method = new \ReflectionMethod(XoopsFileLogger::class, 'redactSessionId');
        $method->setAccessible(true);

        return (string) $method->invoke($logger, $uri);
    }

    #[Test]
    public function theRebuiltUriIsNotUrlDecoded(): void
    {
        $out = $this->redact($this->makeLogger(), '/index.php?PHPSESSID=abcdef0123456789abcdef01&x=%0A%5Bforged%5D');

        // http_build_query() percent-encodes; decoding it again would put a real newline
        // back and let the request forge a second log line.
        self::assertStringNotContainsString("\n", $out);
        self::assertStringNotContainsString('[forged]', $out);
    }

    #[Test]
    public function aNewlineInTheUriCannotForgeAnEntry(): void
    {
        $forged                 = "/index.php?x=1\n[2026-01-01 00:00:00] messages.ERROR FORGED";
        $_SERVER['REQUEST_URI'] = $forged;
        $logger                 = $this->makeLogger();

        // Asserted through sanitize() rather than the finished entry. currentUri() returns
        // 'cli' under this SAPI, so the URI never reaches the file here and a count of the
        // entry's newlines would hold even with the control-character strip removed.
        $sanitize = new ReflectionMethod(XoopsFileLogger::class, 'sanitize');
        $sanitize->setAccessible(true);
        $clean = (string) $sanitize->invoke($logger, $forged);

        self::assertStringNotContainsString("\n", $clean, 'a newline would open a second log line');
        // The payload text is not censored -- it is disarmed. The newline becomes a space,
        // so the forged header stays inside the line it was injected into.
        self::assertStringContainsString('/index.php?x=1 [2026-01-01', $clean);

        // The entry itself is still a header plus one body line and nothing more.
        $logger->log('warning', 'a message', ['channel' => 'messages']);
        self::assertSame(2, substr_count($this->readLog(), "\n"));
    }

    // -----------------------------------------------------------------
    // Session data must never reach the file
    // -----------------------------------------------------------------

    #[Test]
    public function aSessionIdInTheUriIsRedacted(): void
    {
        $out = $this->redact($this->makeLogger(), '/index.php?PHPSESSID=abcdef0123456789abcdef01');

        self::assertStringNotContainsString('abcdef0123456789', $out);
        self::assertStringContainsString('redacted', $out);
    }

    #[Test]
    public function aSessionIdIsRedactedEvenWhenTheKeyIsPercentEncoded(): void
    {
        // PHP decodes query keys, so this IS a live session id — a literal-name regex
        // over the raw string walks straight past it.
        $out = $this->redact($this->makeLogger(), '/index.php?PHP%53ESSID=abcdef0123456789abcdef01');

        self::assertStringNotContainsString('abcdef0123456789', $out);
    }

    #[Test]
    public function anUnrelatedQueryStringIsLeftAlone(): void
    {
        $out = $this->redact($this->makeLogger(), '/index.php?page=2&sort=name');

        self::assertSame('/index.php?page=2&sort=name', $out);
    }

    #[Test]
    public function sessionTableSqlIsNeverRecorded(): void
    {
        if (!defined('XOOPS_DB_PREFIX')) {
            self::markTestSkipped('XOOPS_DB_PREFIX is not defined in this bootstrap');
        }

        $logger = $this->makeLogger([
            'channels'                 => ['Queries'],
            'queries_with_errors_only' => false,
        ]);
        $sessionSql = 'INSERT INTO ' . XOOPS_DB_PREFIX . "_session (sess_id, sess_data) VALUES ('a', 'secret')";
        $logger->log('debug', $sessionSql, ['channel' => 'Queries', 'sql' => $sessionSql]);
        $logger->log('debug', 'SELECT 1 FROM somewhere_else', ['channel' => 'Queries', 'sql' => 'SELECT 1 FROM somewhere_else']);

        $body = $this->readLog();
        self::assertStringNotContainsString('sess_data', $body);
        self::assertStringContainsString('somewhere_else', $body);
    }

    #[Test]
    public function aLowercaseQueriesChannelStillSkipsSessionRows(): void
    {
        if (!defined('XOOPS_DB_PREFIX')) {
            self::markTestSkipped('XOOPS_DB_PREFIX is not defined in this bootstrap');
        }

        // The channel filter has always been case-insensitive, so 'queries' reaches the
        // guards that follow. When those compared exact-case, a producer spelling the
        // channel this way walked straight past the session exclusion -- which protects
        // CSRF token seeds, so it must not depend on how the channel was capitalised.
        $logger = $this->makeLogger([
            'channels'                 => ['Queries'],
            'queries_with_errors_only' => false,
        ]);
        $sessionSql = 'INSERT INTO ' . XOOPS_DB_PREFIX . "_session (sess_id, sess_data) VALUES ('a', 'secret')";
        $logger->log('debug', $sessionSql, ['channel' => 'queries', 'sql' => $sessionSql]);
        $logger->log('debug', 'SELECT 1 FROM somewhere_else', ['channel' => 'queries', 'sql' => 'SELECT 1 FROM somewhere_else']);

        $body = $this->readLog();
        self::assertStringNotContainsString('sess_data', $body);
        self::assertStringContainsString('somewhere_else', $body, 'ordinary SQL must still be recorded');
    }

    // -----------------------------------------------------------------
    // Rotation must not destroy what it is shifting
    // -----------------------------------------------------------------

    #[Test]
    public function aFailedRotationStopsInsteadOfOverwritingTheNextSlot(): void
    {
        // .2 cannot move to .3 because .3 is a non-empty directory. Carrying on would
        // rename .1 onto .2 and destroy it -- newer data than the rotation the cascade
        // set out to discard -- so the cascade has to stop at the first failure.
        $live    = $this->logDir . '/phpunit.log';
        $blocked = $live . '.3';
        file_put_contents($live, str_repeat('L', 70000));
        file_put_contents($live . '.1', 'ROTATION-1');
        file_put_contents($live . '.2', 'ROTATION-2');
        if (!is_dir($blocked)) {
            mkdir($blocked);
        }
        file_put_contents($blocked . '/blocker', 'x');

        try {
            $logger = $this->makeLogger(['max_size' => 65536, 'max_files' => 3]);
            $rotate = new ReflectionMethod(XoopsFileLogger::class, 'rotateIfNeeded');
            $rotate->setAccessible(true);
            $rotate->invoke($logger, 1000);

            $survived = false;
            foreach (glob($live . '.*') ?: [] as $candidate) {
                if (is_file($candidate) && file_get_contents($candidate) === 'ROTATION-2') {
                    $survived = true;
                }
            }
            self::assertTrue($survived, 'the rotation that could not move must not be overwritten');
            self::assertTrue($this->property($logger, 'writeFailed'), 'a stalled rotation disables the logger');
        } finally {
            @unlink($blocked . '/blocker');
            @rmdir($blocked);
        }
    }

    #[Test]
    public function aHealthyRotationStillShiftsEverythingAlong(): void
    {
        $live = $this->logDir . '/phpunit.log';
        file_put_contents($live, str_repeat('L', 70000));
        file_put_contents($live . '.1', 'ROTATION-1');

        $logger = $this->makeLogger(['max_size' => 65536, 'max_files' => 3]);
        $rotate = new ReflectionMethod(XoopsFileLogger::class, 'rotateIfNeeded');
        $rotate->setAccessible(true);
        $rotate->invoke($logger, 1000);

        self::assertSame('ROTATION-1', file_get_contents($live . '.2'));
        self::assertStringStartsWith('LLL', (string) file_get_contents($live . '.1'));
        self::assertFalse($this->property($logger, 'writeFailed'));
    }

    #[Test]
    public function aFreshLogFileIsNotWorldReadable(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('POSIX file modes only; Windows reports 0666 regardless');
        }

        $logger = $this->makeLogger();
        $logger->log('error', 'first entry', ['channel' => 'messages']);

        clearstatcache(true, $this->logDir . '/phpunit.log');

        // This also pins where the is_file() check that drives the chmod sits: below
        // rotateIfNeeded(), so a log that was just rotated away counts as new and is
        // tightened too. Moving it back above would leave that file at the umask default.
        self::assertSame(0640, fileperms($this->logDir . '/phpunit.log') & 0777);
    }

    // -----------------------------------------------------------------
    // Configuration is clamped, not trusted
    // -----------------------------------------------------------------

    #[Test]
    public function absurdRotationSettingsAreClamped(): void
    {
        $logger = $this->makeLogger([
            'max_files'       => 1000000000,
            'max_size'        => -1,
            'backtrace_limit' => 99999,
        ]);

        self::assertLessThanOrEqual(20, (int) $this->property($logger, 'maxFiles'));
        self::assertGreaterThanOrEqual(65536, (int) $this->property($logger, 'maxSize'));
        self::assertLessThanOrEqual(50, (int) $this->property($logger, 'backtraceLimit'));
    }

    #[Test]
    public function aNonArrayChannelListYieldsNoChannels(): void
    {
        $logger = $this->makeLogger(['channels' => 'not-an-array']);

        self::assertSame([], $this->property($logger, 'channels'));
    }

    #[Test]
    public function channelNamesMatchRegardlessOfCase(): void
    {
        $logger = $this->makeLogger([
            'channels'                 => ['queries', 'MESSAGES'],
            'queries_with_errors_only' => false,
        ]);
        $logger->log('debug', 'SELECT marker_one', ['channel' => 'Queries', 'sql' => 'SELECT marker_one']);
        $logger->log('warning', 'marker_two', ['channel' => 'messages']);

        $body = $this->readLog();
        self::assertStringContainsString('marker_one', $body);
        self::assertStringContainsString('marker_two', $body);
    }

    #[Test]
    public function anUnlistedChannelIsIgnored(): void
    {
        $logger = $this->makeLogger(['channels' => ['messages']]);
        $logger->log('debug', 'block_marker', ['channel' => 'Blocks', 'name' => 'block_marker']);

        self::assertStringNotContainsString('block_marker', $this->readLog());
    }

    // -----------------------------------------------------------------
    // Bounds and quiet()
    // -----------------------------------------------------------------

    #[Test]
    public function anEnormousMessageIsTruncatedRatherThanWrittenWhole(): void
    {
        $logger = $this->makeLogger();
        $logger->log('error', str_repeat('A', 2 * 1024 * 1024), ['channel' => 'messages']);

        // Far below the 2 MB input: capped by MAX_FIELD, then MAX_ENTRY.
        self::assertLessThan(131072, strlen($this->readLog()));
    }

    #[Test]
    public function quietStopsFurtherWrites(): void
    {
        $logger = $this->makeLogger();
        $logger->log('error', 'before quiet', ['channel' => 'messages']);
        $sizeBefore = strlen($this->readLog());

        $logger->quiet();
        $logger->log('error', 'after quiet', ['channel' => 'messages']);

        self::assertSame($sizeBefore, strlen($this->readLog()));
        self::assertStringNotContainsString('after quiet', $this->readLog());
    }

    // -----------------------------------------------------------------
    // Entry shape
    // -----------------------------------------------------------------

    #[Test]
    public function anEntryCarriesTheChannelLevelMessageAndContext(): void
    {
        $logger = $this->makeLogger();
        $logger->log('warning', 'the message', ['channel' => 'messages', 'errno' => 2]);

        $body = $this->readLog();
        self::assertStringContainsString('messages.WARNING', $body);
        self::assertStringContainsString('the message', $body);
        self::assertStringContainsString('errno=2', $body);
        // Under a CLI SAPI — which is how this suite runs — the uri field is 'cli'.
        self::assertStringContainsString('uri=cli', $body);
    }
}
