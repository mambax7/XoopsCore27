<?php
/**
 * XOOPS file logger
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category            Logger
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package             kernel
 * @link                https://xoops.org
 * @since               2.7.3
 * @author              XOOPS Team
 */

if (!defined('XOOPS_ROOT_PATH')) {
    die('Restricted access');
}

/**
 * Writes XoopsLogger events to a rotating file under XOOPS_VAR_PATH/logs.
 *
 * Registers with XoopsLogger::addLogger(), the same seat DebugBar occupies, so it
 * receives every event the in-page debug output receives — notices, warnings, errors,
 * deprecations and SQL — without any change to the producers.
 *
 * It exists because the in-page output is only visible to an administrator on a page
 * that actually renders. When a request dies before output, or fails for a guest, or
 * only misbehaves under real traffic, the file is the only record.
 *
 * Two things are deliberately not written:
 *  - the session id, which would let anyone reading the log hijack a session;
 *  - absolute server paths, which are reduced to installation-relative form.
 */
class XoopsFileLogger
{
    /** @var string absolute path of the current log file */
    protected $file;

    /** @var int rotate once the file exceeds this many bytes */
    protected $maxSize;

    /** @var int how many rotated files to keep */
    protected $maxFiles;

    /** @var string[] channels to record */
    protected $channels;

    /** @var bool record only failing SQL rather than every statement */
    protected $queriesWithErrorsOnly;

    /** @var bool append a backtrace to each entry */
    protected $backtrace;

    /** @var int frames to keep in the backtrace */
    protected $backtraceLimit;

    /** @var bool set by quiet(); suppresses all further writes */
    protected $quiet = false;

    /** @var bool one-shot guard so a broken log destination cannot spam */
    protected $writeFailed = false;

    /** @var string stable per-request id, so interleaved requests can be separated */
    protected $requestId;

    /**
     * @param array $config the 'log' section of xoops_data/data/debug.php
     */
    public function __construct(array $config = [])
    {
        $dir = XOOPS_VAR_PATH . '/logs';
        $name = isset($config['file']) ? basename((string) $config['file']) : 'debug.log';
        if ('' === $name || '.' === $name[0]) {
            $name = 'debug.log';
        }

        $this->file                  = $dir . '/' . $name;
        $this->maxSize               = max(0, (int) ($config['max_size'] ?? 8388608));
        $this->maxFiles              = max(0, (int) ($config['max_files'] ?? 5));
        $this->channels              = (array) ($config['channels'] ?? ['messages', 'Queries', 'Deprecated']);
        $this->queriesWithErrorsOnly = (bool) ($config['queries_with_errors_only'] ?? true);
        $this->backtrace             = (bool) ($config['backtrace'] ?? true);
        $this->backtraceLimit        = max(1, (int) ($config['backtrace_limit'] ?? 12));
        $this->requestId             = substr(md5(uniqid('', true)), 0, 8);
    }

    /**
     * Receive one event from XoopsLogger.
     *
     * Signature matches the PSR-3 shape XoopsLogger dispatches with.
     *
     * @param  string $level   psr-3 level
     * @param  string $message
     * @param  array  $context dispatch context; 'channel' selects the collector
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        if ($this->quiet || $this->writeFailed) {
            return;
        }

        $channel = (string) ($context['channel'] ?? 'messages');
        if (!in_array($channel, $this->channels, true)) {
            return;
        }
        if ('Queries' === $channel && $this->queriesWithErrorsOnly && empty($context['error'])) {
            return;
        }

        $this->write($this->format($level, (string) $message, $channel, $context));
    }

    /**
     * Stop writing for the remainder of the request.
     *
     * XoopsLogger calls this for output-sensitive requests such as AJAX. The file is not
     * output, but honouring it keeps behaviour consistent with the other loggers.
     *
     * @return void
     */
    public function quiet()
    {
        $this->quiet = true;
    }

    /**
     * Build one log entry.
     *
     * @param  string $level
     * @param  string $message
     * @param  string $channel
     * @param  array  $context
     * @return string
     */
    protected function format($level, $message, $channel, array $context)
    {
        $head = sprintf(
            "[%s] %s.%s req=%s uri=%s uid=%s",
            date('Y-m-d H:i:s'),
            $channel,
            strtoupper($level),
            $this->requestId,
            $this->currentUri(),
            $this->currentUid()
        );

        $body = '  ' . $this->sanitize($message);

        $detail = [];
        foreach (['errno', 'errfile', 'errline', 'sql', 'error', 'query_time'] as $key) {
            if (isset($context[$key]) && '' !== $context[$key] && is_scalar($context[$key])) {
                $detail[] = $key . '=' . $this->sanitize((string) $context[$key]);
            }
        }
        if ([] !== $detail) {
            $body .= "\n  " . implode(' ', $detail);
        }

        if ($this->backtrace) {
            $trace = $this->renderTrace($context['trace'] ?? null);
            if ('' !== $trace) {
                $body .= "\n" . $trace;
            }
        }

        return $head . "\n" . $body . "\n";
    }

    /**
     * Render a backtrace, preferring one supplied by the producer.
     *
     * @param  array|null $trace
     * @return string
     */
    protected function renderTrace($trace)
    {
        if (!is_array($trace)) {
            if (!function_exists('debug_backtrace')) {
                return '';
            }
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $this->backtraceLimit + 6);
            // Drop the frames belonging to the logger itself.
            $trace = array_values(array_filter($trace, function ($frame) {
                return !isset($frame['class']) || !in_array($frame['class'], [self::class, 'XoopsLogger'], true);
            }));
        }

        $lines = [];
        foreach (array_slice($trace, 0, $this->backtraceLimit) as $frame) {
            if (!isset($frame['file'])) {
                continue;
            }
            $lines[] = '    ' . $this->sanitize((string) $frame['file'])
                . ':' . ($frame['line'] ?? '?')
                . (isset($frame['function']) ? ' ' . $frame['function'] . '()' : '');
        }

        return [] === $lines ? '' : implode("\n", $lines);
    }

    /**
     * Strip absolute server paths and anything session-identifying.
     *
     * @param  string $text
     * @return string
     */
    protected function sanitize($text)
    {
        // Both separator styles must be replaced. The constants normally hold forward
        // slashes while PHP reports __FILE__ and backtrace frames with the platform
        // separator, so matching only the constant's own form silently leaks the full
        // server path on Windows -- and in backtraces on any platform where the two
        // disagree. Longest first, so XOOPS_ROOT_PATH cannot truncate a longer
        // XOOPS_TRUST_PATH that lives beneath it.
        $paths = [];
        foreach (['XOOPS_VAR_PATH', 'XOOPS_PATH', 'XOOPS_TRUST_PATH', 'XOOPS_ROOT_PATH'] as $constant) {
            if (!defined($constant)) {
                continue;
            }
            $value = (string) constant($constant);
            if ('' === $value) {
                continue;
            }
            $paths[] = str_replace('\\', '/', $value);
            $paths[] = str_replace('/', '\\', $value);
        }
        if ([] !== $paths) {
            $paths = array_unique($paths);
            usort($paths, static function ($a, $b) {
                return strlen($b) <=> strlen($a);
            });
            $text = str_replace($paths, '', $text);
        }

        // A session id in a log file is a hijacking primitive. Keep a short hash so
        // entries from one visitor can still be correlated.
        if (function_exists('session_id')) {
            $sid = session_id();
            if (is_string($sid) && strlen($sid) > 7) {
                $text = str_replace($sid, 'sid#' . substr(md5($sid), 0, 8), $text);
            }
        }

        return $text;
    }

    /**
     * @return string request uri, or 'cli'
     */
    protected function currentUri()
    {
        if ('cli' === PHP_SAPI || 'phpdbg' === PHP_SAPI) {
            return 'cli';
        }
        $uri = $_SERVER['REQUEST_URI'] ?? '-';

        return is_string($uri) ? substr($uri, 0, 300) : '-';
    }

    /**
     * @return int current uid, 0 for a guest
     */
    protected function currentUid()
    {
        $user = $GLOBALS['xoopsUser'] ?? null;

        return is_object($user) && method_exists($user, 'getVar') ? (int) $user->getVar('uid') : 0;
    }

    /**
     * Append to the log, rotating first when it has grown past max_size.
     *
     * A failure here disables this logger for the rest of the request rather than
     * repeating a warning for every subsequent event.
     *
     * @param  string $entry
     * @return void
     */
    protected function write($entry)
    {
        $dir = dirname($this->file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->writeFailed = true;

            return;
        }

        $this->rotateIfNeeded();

        if (false === @file_put_contents($this->file, $entry, FILE_APPEND | LOCK_EX)) {
            $this->writeFailed = true;
        }
    }

    /**
     * Roll debug.log to debug.log.1, shifting existing rotations along and
     * discarding the oldest.
     *
     * @return void
     */
    protected function rotateIfNeeded()
    {
        if ($this->maxSize <= 0 || !is_file($this->file)) {
            return;
        }
        $size = @filesize($this->file);
        if (false === $size || $size < $this->maxSize) {
            return;
        }

        if ($this->maxFiles < 1) {
            @unlink($this->file);

            return;
        }

        @unlink($this->file . '.' . $this->maxFiles);
        for ($i = $this->maxFiles - 1; $i >= 1; --$i) {
            if (is_file($this->file . '.' . $i)) {
                @rename($this->file . '.' . $i, $this->file . '.' . ($i + 1));
            }
        }
        @rename($this->file, $this->file . '.1');
    }
}
