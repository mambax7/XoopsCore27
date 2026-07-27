<?php

declare(strict_types=1);

namespace xoopslogger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use XoopsLogger;

/**
 * Output escaping in the logger's debug panel.
 *
 * The panel renders values that an unauthenticated visitor can influence — a PHP warning
 * quotes the offending request value, a database error quotes the offending column — and
 * it is displayed to an administrator with debug output enabled. Everything reaching the
 * markup therefore has to be escaped.
 *
 * Four of the six channels were not, which is what these tests lock down.
 */
#[CoversClass(XoopsLogger::class)]
class XoopsLoggerRenderEscapingTest extends TestCase
{
    private const PAYLOAD = '<script>alert(1)</script>';

    private const ESCAPED = '&lt;script&gt;alert(1)&lt;/script&gt;';

    public static function setUpBeforeClass(): void
    {
        require_once XOOPS_ROOT_PATH . '/class/logger/xoopslogger.php';

        $constants = [
            '_LANGCODE' => 'en', '_CHARSET' => 'UTF-8', '_CLOSE' => 'Close',
            '_LOGGER_DEBUG' => 'Debug', '_LOGGER_INCLUDED_FILES' => 'Included files',
            '_LOGGER_FILES' => '%d files', '_LOGGER_MEM_ESTIMATED' => 'Mem: %s',
            '_LOGGER_MEM_USAGE' => 'Memory', '_LOGGER_NONE' => 'None', '_LOGGER_ALL' => 'All',
            '_LOGGER_ERRORS' => 'Errors', '_LOGGER_QUERIES' => 'Queries',
            '_LOGGER_BLOCKS' => 'Blocks', '_LOGGER_EXTRA' => 'Extra',
            '_LOGGER_TIMERS' => 'Timers', '_LOGGER_DEPRECATED' => 'Deprecated',
            '_LOGGER_E_USER_NOTICE' => 'User notice', '_LOGGER_E_USER_WARNING' => 'User warning',
            '_LOGGER_E_USER_ERROR' => 'User error', '_LOGGER_E_NOTICE' => 'Notice',
            '_LOGGER_E_WARNING' => 'Warning', '_LOGGER_UNKNOWN' => 'Unknown',
            '_LOGGER_FILELINE' => '%s in file %s line %s', '_LOGGER_TOTAL' => 'Total',
            '_LOGGER_CACHED' => 'Cached (%d)', '_LOGGER_NOT_CACHED' => 'Not cached',
            '_LOGGER_TIMETOLOAD' => '%s took %s',
        ];
        foreach ($constants as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }

    /**
     * render.php asks XoopsDatabaseFactory for the table prefix. No stub is needed here:
     * tests/bootstrap.php loads the factory and registers a preload event that swaps in
     * XoopsTestStubDatabase, so this stays a unit test with no connection.
     */
    private function render(XoopsLogger $logger): string
    {
        ob_start();
        $out = $logger->dump('');
        ob_end_clean();

        return (string) $out;
    }

    public static function channelProvider(): array
    {
        return [
            // channel, marker proving the entry rendered at all
            'error string'          => ['errstr', 'Undefined array key'],
            'error file'            => ['errfile', 'in file'],
            'error line'            => ['errline', 'an error'],
            'database error'        => ['queryerror', 'Unknown column'],
            'query sql'             => ['querysql', 'SELECT'],
            'cached block name'     => ['block', 'a block'],
            // render.php formats cached and uncached blocks in separate branches, so both
            // need covering or a regression in one of them goes unnoticed.
            'uncached block name'   => ['blockuncached', 'a block'],
            'deprecation message'   => ['deprecated', 'Legacy API'],
        ];
    }

    #[Test]
    #[DataProvider('channelProvider')]
    public function anAttackerControlledValueIsEscapedInTheDebugPanel(string $channel, string $marker): void
    {
        $logger            = new XoopsLogger();
        $logger->activated = true;

        switch ($channel) {
            case 'errstr':
                $logger->errors[] = [
                    'errno'   => E_WARNING,
                    'errstr'  => 'Undefined array key "' . self::PAYLOAD . '"',
                    'errfile' => '/some/file.php',
                    'errline' => '42',
                ];
                break;
            case 'errfile':
                $logger->errors[] = [
                    'errno'   => E_WARNING,
                    'errstr'  => 'something',
                    'errfile' => '/some/' . self::PAYLOAD . '.php',
                    'errline' => '42',
                ];
                break;
            case 'errline':
                $logger->errors[] = [
                    'errno'   => E_WARNING,
                    'errstr'  => 'an error',
                    'errfile' => '/some/file.php',
                    'errline' => '42' . self::PAYLOAD,
                ];
                break;
            case 'queryerror':
                $logger->queries[] = [
                    'sql'   => 'SELECT 1',
                    'error' => "Unknown column '" . self::PAYLOAD . "' in field list",
                    'errno' => 1054,
                ];
                break;
            case 'querysql':
                // The statement itself is echoed back; a value interpolated into it reaches the panel.
                $logger->queries[] = ['sql' => "SELECT * FROM t WHERE name = '" . self::PAYLOAD . "'"];
                break;
            case 'block':
                $logger->blocks[] = ['name' => 'a block ' . self::PAYLOAD, 'cached' => true, 'cachetime' => 300];
                break;
            case 'blockuncached':
                $logger->blocks[] = ['name' => 'a block ' . self::PAYLOAD, 'cached' => false, 'cachetime' => 0];
                break;
            case 'deprecated':
                $logger->addDeprecated('Legacy API ' . self::PAYLOAD . ' used');
                break;
        }

        $out = $this->render($logger);

        self::assertStringContainsString($marker, $out, 'the entry should still be rendered');
        self::assertStringNotContainsString(self::PAYLOAD, $out, 'raw markup must never reach the panel');
        self::assertStringContainsString(self::ESCAPED, $out, 'the value should appear, escaped');
    }

    #[Test]
    public function theDeprecationTraceKeepsItsLineBreaks(): void
    {
        // The stored entry is a pre-rendered fragment: escaped caller text followed by a
        // <br>-separated trace. Escaping the finished string would destroy the trace, so
        // this guards against over-correcting the fix above.
        $logger            = new XoopsLogger();
        $logger->activated = true;
        $logger->addDeprecated('plain message');

        $out = $this->render($logger);

        self::assertStringContainsString('trace:', $out);
        self::assertStringContainsString('<br>', $out, 'trace separators must remain real markup');
        self::assertStringNotContainsString('&lt;br&gt;', $out);
    }

    #[Test]
    public function aRawMessageIsStillDispatchedToRegisteredLoggers(): void
    {
        // The panel entry is escaped; a file or PSR-3 logger must still receive the text.
        $logger            = new XoopsLogger();
        $logger->activated = true;

        $collector           = new \stdClass();
        $collector->received = [];
        if (!method_exists($logger, 'addLogger')) {
            self::markTestSkipped('XoopsLogger::addLogger() not available');
        }
        $logger->addLogger(new RenderEscapingCollectorMock($collector));

        $logger->addDeprecated('Legacy API ' . self::PAYLOAD . ' used');

        self::assertNotEmpty($collector->received);
        self::assertStringContainsString(self::PAYLOAD, (string) $collector->received[0]['message']);
    }
}

class RenderEscapingCollectorMock
{
    private \stdClass $collector;

    public function __construct(\stdClass $collector)
    {
        $this->collector = $collector;
    }

    public function log($level, $message, array $context = []): void
    {
        $this->collector->received[] = [
            'level'   => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}
