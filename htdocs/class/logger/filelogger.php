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
 * SAFETY RULES, each of which exists because a log file is an attacker's best friend:
 *  - the filename is restricted to a plain "*.log". Anything else, notably any
 *    PHP-executable extension, is refused: log content is written verbatim, so a logged
 *    statement containing PHP tags inside a *.php log would be stored, executable code;
 *  - neither the log file nor its directory may be a symlink, so a planted link cannot
 *    redirect appends onto mainfile.php or any other writable file;
 *  - session ids are replaced with a short hash wherever they appear, including in the
 *    request URI, and including before session_start() has run;
 *  - session-table SQL is never recorded, because serialised session data carries CSRF
 *    token seeds and module state;
 *  - absolute server paths are reduced to installation-relative form;
 *  - backtraces render file, line and function only. Arguments are never emitted.
 */
class XoopsFileLogger
{
    /** Only a plain "name.log" is accepted. Anything else falls back to the default. */
    private const FILENAME_PATTERN = '/^[A-Za-z0-9_-]{1,64}\.log$/';

    private const DEFAULT_FILENAME = 'debug.log';

    /**
     * Windows treats these stems as devices whatever extension follows: "NUL.log" opens
     * the null device and silently swallows every entry, "COM1.log" talks to a serial
     * port. They satisfy FILENAME_PATTERN, so they need rejecting separately.
     */
    private const RESERVED_STEMS = [
        'CON', 'PRN', 'AUX', 'NUL',
        'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9',
        'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9',
    ];

    /** Cap on any single field before sanitising, so a huge value cannot exhaust memory. */
    private const MAX_FIELD = 8192;

    /** Rotation bounds. An unbounded max_files would spin the rotation loop for ever. */
    private const MIN_SIZE  = 65536;        // 64 KB
    private const MAX_SIZE  = 536870912;    // 512 MB
    private const MAX_FILES = 20;

    /** Hard cap for a single entry, so one enormous statement cannot defeat rotation. */
    private const MAX_ENTRY = 65536;

    /** Width of the rule that separates entries. Fits an 80-column terminal. */
    private const DIVIDER_WIDTH = 75;

    /** @var string absolute path of the current log file */
    protected $file;

    /** @var int rotate once the file would exceed this many bytes */
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
        $name = isset($config['file']) && is_string($config['file'])
            ? basename($config['file'])
            : self::DEFAULT_FILENAME;

        // basename() alone is NOT enough. It stops directory traversal but happily returns
        // "debug.php", and this class writes attacker-influenced text verbatim.
        if (1 !== preg_match(self::FILENAME_PATTERN, $name)) {
            $name = self::DEFAULT_FILENAME;
        }
        $stem = strtoupper((string) strstr($name, '.', true));
        if (in_array($stem, self::RESERVED_STEMS, true)) {
            $name = self::DEFAULT_FILENAME;
        }

        $this->file = XOOPS_VAR_PATH . '/logs/' . $name;

        // Fail CLOSED when the log directory is reachable over the web.
        //
        // The shipped .htaccess protects Apache only; on nginx, IIS, or Apache with
        // AllowOverride None it does nothing, and /xoops_data/logs/debug.log is then a
        // plain static file full of SQL, user ids and paths. Documentation alone is not a
        // control, so logging is refused unless the administrator has either moved
        // xoops_data outside the document root -- the right answer -- or explicitly
        // accepted the risk in debug.php.
        if (empty($config['allow_web_accessible_log']) && $this->isBelowDocumentRoot()) {
            $this->writeFailed = true;
        }
        $this->maxSize  = $this->clamp($config['max_size'] ?? 8388608, self::MIN_SIZE, self::MAX_SIZE);
        $this->maxFiles = $this->clamp($config['max_files'] ?? 5, 1, self::MAX_FILES);

        $channels       = $config['channels'] ?? ['messages', 'Queries', 'Deprecated'];
        // Compared case-insensitively: debug.php is hand-edited and the channel names
        // are themselves inconsistent ( messages lowercase, Queries/Blocks/Extra/
        // Deprecated capitalised ), so a reasonable-looking 'queries' would otherwise
        // produce a logger that silently records nothing.
        $channels       = is_array($channels) ? array_filter($channels, 'is_string') : [];
        $this->channels = array_values(array_map('strtolower', $channels));

        $this->queriesWithErrorsOnly = (bool) ($config['queries_with_errors_only'] ?? true);
        $this->backtrace             = (bool) ($config['backtrace'] ?? true);
        $this->backtraceLimit        = $this->clamp($config['backtrace_limit'] ?? 12, 1, 50);
        $this->requestId             = substr(md5(uniqid('', true)), 0, 8);
    }

    /**
     * Cut a single field down to MAX_FIELD before any further processing.
     *
     * @param  string $value
     * @return string
     */
    protected function trim($value)
    {
        return strlen($value) > self::MAX_FIELD
            ? substr($value, 0, self::MAX_FIELD) . '...[' . strlen($value) . ' bytes]'
            : $value;
    }

    /**
     * Does the log directory sit inside the served document root?
     *
     * XOOPS_ROOT_PATH *is* the document root by definition, so a log directory beneath it
     * is web-reachable unless the server is configured to refuse it.
     *
     * @return bool true when the log would be publicly fetchable on an unprotected server
     */
    protected function isBelowDocumentRoot()
    {
        // Fails CLOSED. Every branch that cannot answer the question returns true, i.e.
        // "assume web-reachable", so logging is refused rather than permitted. Returning
        // false here would have been a fail-OPEN default, made worse by write() creating
        // the directory recursively — an unresolvable path would have produced a fresh,
        // unprotected log directory.
        if (!defined('XOOPS_ROOT_PATH')) {
            return true;
        }
        $root = realpath(XOOPS_ROOT_PATH);
        if (false === $root || '' === $root) {
            return true;
        }
        // The log directory may legitimately not exist yet on a first run, so fall back
        // to its parent and then to XOOPS_VAR_PATH before giving up.
        $dir = realpath(dirname($this->file));
        if (false === $dir) {
            $dir = realpath(XOOPS_VAR_PATH);
        }
        if (false === $dir || '' === $dir) {
            return true;
        }
        $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $dir  = rtrim(str_replace('\\', '/', $dir), '/') . '/';

        return 0 === stripos($dir, $root);
    }

    /**
     * Coerce a setting into a sane range rather than trusting the file.
     *
     * @param  mixed $value
     * @param  int   $min
     * @param  int   $max
     * @return int
     */
    protected function clamp($value, $min, $max)
    {
        $value = is_numeric($value) ? (int) $value : $min;

        return max($min, min($max, $value));
    }

    /**
     * Receive one event from XoopsLogger.
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

        // Compared in one normalised form throughout. The membership test below was
        // already case-insensitive, so a producer dispatching 'queries' reached the guards
        // that follow -- and an exact-case comparison there would skip them, including the
        // session-table exclusion, which is a privacy control rather than a volume one.
        $channel    = (string) ($context['channel'] ?? 'messages');
        $normalised = strtolower($channel);
        if (!in_array($normalised, $this->channels, true)) {
            return;
        }
        if ('queries' === $normalised) {
            if ($this->queriesWithErrorsOnly && empty($context['error'])) {
                return;
            }
            // Session rows hold serialised session state, which includes XOOPS security
            // token seeds. Recording that statement would put live CSRF secrets in a file.
            if ($this->touchesSessionTable((string) ($context['sql'] ?? $message))) {
                return;
            }
        }

        $this->write($this->format($level, (string) $message, $channel, $context));
    }

    /**
     * Stop writing for the remainder of the request.
     *
     * @return void
     */
    public function quiet()
    {
        $this->quiet = true;
    }

    /**
     * Does this statement read or write the session table?
     *
     * @param  string $sql
     * @return bool
     */
    protected function touchesSessionTable($sql)
    {
        if (!defined('XOOPS_DB_PREFIX')) {
            return false;
        }

        return false !== stripos($sql, XOOPS_DB_PREFIX . '_session');
    }

    /**
     * What kind of entry is this?
     *
     * Derived rather than passed in, so no producer has to be changed to get a useful
     * label. Only 'exception' is explicit, because an uncaught exception and a triggered
     * error both arrive as E_USER_ERROR and telling them apart from the message text
     * would be guesswork.
     *
     * @param  string $level
     * @param  string $channel
     * @param  array  $context
     * @return string
     */
    protected function entryLabel($level, $channel, array $context)
    {
        if (!empty($context['exception'])) {
            return 'EXCEPTION';
        }

        switch (strtolower((string) $channel)) {
            case 'queries':
                // Worth its own label: a query that failed is a different kind of problem
                // from one merely being recorded, and it is what you scan for.
                return empty($context['error']) ? 'QUERY' : 'QUERY ERROR';
            case 'deprecated':
                return 'DEPRECATED';
            case 'blocks':
                return 'BLOCK';
            case 'extra':
                return 'EXTRA';
        }

        // messages: the PSR-3 level already says it -- ERROR, WARNING, NOTICE.
        return strtoupper((string) $level);
    }

    /**
     * The headline pairs: what went wrong, and where.
     *
     * Keyed so the caller can render them aligned. A failed query leads with the database
     * error rather than the statement -- the statement can be hundreds of characters and
     * is not the thing you are looking for first.
     *
     * @param  string $message already sanitised
     * @param  string $channel
     * @param  array  $context
     * @return array<string, string>
     */
    protected function summary($message, $channel, array $context)
    {
        $pairs = [];

        if ($this->summaryCarriesMessage($channel, $context)) {
            $pairs['errstr'] = $message;
            foreach (['errfile', 'errline'] as $key) {
                if (isset($context[$key]) && '' !== $context[$key] && is_scalar($context[$key])) {
                    $pairs[$key] = $this->sanitize($this->trim((string) $context[$key]));
                }
            }

            return $pairs;
        }

        $pairs['error'] = $this->sanitize($this->trim((string) $context['error']));
        if (isset($context['errno']) && is_scalar($context['errno'])) {
            $pairs['errno'] = (string) $context['errno'];
        }

        return $pairs;
    }

    /**
     * Does the headline block already say what the message says?
     *
     * True for everything except a failed query, where the headline carries the database
     * error and the statement itself belongs in the detail below.
     *
     * @param  string $channel
     * @param  array  $context
     * @return bool
     */
    protected function summaryCarriesMessage($channel, array $context)
    {
        return 'queries' !== strtolower((string) $channel)
            || empty($context['error'])
            || !is_scalar($context['error']);
    }

    /**
     * A fixed-width rule carrying the timestamp and the entry label.
     *
     * The timestamp is part of the rule rather than a line of its own, so the two things
     * you scan for -- when, and what kind -- are on the line that catches the eye.
     *
     * @param  string $label
     * @return string
     */
    protected function divider($label)
    {
        $label = trim((string) $label);
        if ('' === $label) {
            $label = 'LOG';
        }

        $stamp = str_repeat('=', 11) . '[' . date('Y-m-d H:i:s') . ']  ';

        // Label centred in what remains, so the rule keeps one width whatever it says.
        $room = max(9, self::DIVIDER_WIDTH - strlen($stamp));
        $left = intdiv($room - strlen($label) - 2, 2);
        $left = max(3, $left);
        $right = max(3, $room - strlen($label) - 2 - $left);

        return $stamp . str_repeat('=', $left) . ' ' . $label . ' ' . str_repeat('=', $right);
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
        // Truncate BEFORE sanitising. MAX_ENTRY alone was not enough: a 16 MB SQL string
        // was regex-processed and concatenated first, and the peak allocation of that
        // exhausted the memory limit long before the finished entry could be trimmed.
        $message = $this->sanitize($this->trim($message));

        // A labelled rule carrying the timestamp opens every entry. Without a separator
        // the file reads as an unbroken wall of text -- entries run to several lines each
        // -- and the label says what you are looking at before you have read a word.
        $entry = "\n" . $this->divider($this->entryLabel($level, $channel, $context)) . "\n";

        // What went wrong and where, directly under the timestamp. This is the reason you
        // opened the file; everything below it is supporting detail, and on a warning
        // raised inside a compiled Smarty template the location is most of the answer.
        $summary = $this->summary($message, $channel, $context);
        foreach ($summary as $key => $value) {
            $entry .= '  ' . str_pad($key, 7) . '= ' . $value . "\n";
        }
        $entry .= "-------\n";

        // The URI on its own line, not in the header: a XOOPS redirect chain runs to
        // several hundred characters and pushed uid -- the field most often wanted beside
        // the error -- off the right of the screen.
        $uri = $this->sanitize($this->currentUri());
        if ('' !== $uri) {
            $entry .= '  uri: ' . $uri . "\n";
        }

        $entry .= sprintf(
            "  %s.%s req=%s uid=%s\n",
            $channel,
            strtoupper($level),
            $this->requestId,
            $this->currentUid()
        );

        // Repeated below only when the summary did not already carry it -- a query's
        // statement belongs here, but a warning's text would just be printed twice.
        if (!$this->summaryCarriesMessage($channel, $context)) {
            $entry .= '  ' . $message . "\n";
        }

        $detail = [];
        foreach (['errno', 'errfile', 'errline', 'sql', 'error', 'query_time'] as $key) {
            if (!isset($context[$key]) || '' === $context[$key] || !is_scalar($context[$key])) {
                continue;
            }
            // Already stated above. A compiled Smarty template path runs past a hundred
            // characters, so repeating errfile and errline here doubled the bulk of the
            // entry for nothing.
            if (isset($summary[$key])) {
                continue;
            }
            $value = $this->sanitize($this->trim((string) $context[$key]));
            // addQuery() passes the SQL as BOTH the message and context['sql']; writing it
            // twice doubles the file size and the time spent holding the lock.
            if ('sql' === $key && $value === $message) {
                continue;
            }
            $detail[] = $key . '=' . $value;
        }
        if ([] !== $detail) {
            $entry .= '  ' . implode(' ', $detail) . "\n";
        }

        if ($this->backtrace) {
            $trace = $this->renderTrace($context['trace'] ?? null);
            if ('' !== $trace) {
                $entry .= $trace . "\n";
            }
        }

        // One entry must never be able to defeat rotation on its own.
        if (strlen($entry) > self::MAX_ENTRY) {
            $entry = substr($entry, 0, self::MAX_ENTRY) . "\n  ...[truncated]\n";
        }

        return $entry;
    }

    /**
     * Render a backtrace, preferring one supplied by the producer.
     *
     * Only file, line and function are emitted. Arguments are never rendered, and the
     * self-captured trace uses DEBUG_BACKTRACE_IGNORE_ARGS, so a database password
     * passed to a connect call cannot reach the file.
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
            $trace = array_values(array_filter($trace, function ($frame) {
                return !isset($frame['class']) || !in_array($frame['class'], [self::class, 'XoopsLogger'], true);
            }));
        }

        $lines = [];
        foreach (array_slice($trace, 0, $this->backtraceLimit) as $frame) {
            if (!is_array($frame) || !isset($frame['file'])) {
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
        // server path on Windows. realpath() variants cover a symlinked installation.
        // Longest first, so XOOPS_ROOT_PATH cannot truncate a longer XOOPS_TRUST_PATH
        // that lives beneath it.
        $paths = [];
        foreach (['XOOPS_VAR_PATH', 'XOOPS_PATH', 'XOOPS_TRUST_PATH', 'XOOPS_ROOT_PATH'] as $constant) {
            if (!defined($constant)) {
                continue;
            }
            $value = (string) constant($constant);
            if ('' === $value) {
                continue;
            }
            foreach ([$value, realpath($value)] as $candidate) {
                if (is_string($candidate) && '' !== $candidate) {
                    $paths[] = str_replace('\\', '/', $candidate);
                    $paths[] = str_replace('/', '\\', $candidate);
                }
            }
        }
        if ([] !== $paths) {
            $paths = array_unique($paths);
            usort($paths, static function ($a, $b) {
                return strlen($b) <=> strlen($a);
            });
            $text = str_replace($paths, '', $text);
        }

        // Anything still absolute came from outside the installation -- a shared include
        // directory, a temp file, a vendor path outside the tree. Reduce it to its
        // basename rather than publishing the server's directory layout.
        $text = preg_replace('#\b[A-Za-z]:[\\\\/][^\s"\'<>()]*[\\\\/]([^\\\\/\s"\'<>()]+)#', '.../$1', (string) $text);
        $text = preg_replace('#(?<![\w:])/(?:home|usr|var|opt|srv|tmp|etc|root)/[^\s"\'<>()]*/([^/\s"\'<>()]+)#', '.../$1', (string) $text);

        // A session id in a log file is a hijacking primitive. Keep a short hash so
        // entries from one visitor can still be correlated.
        if (function_exists('session_id')) {
            $sid = session_id();
            if (is_string($sid) && strlen($sid) > 7) {
                $text = str_replace($sid, 'sid#' . substr(md5($sid), 0, 8), (string) $text);
            }
        }

        // Every untrusted value reaches the file through here, so this is the one place
        // to strip control characters. A newline inside a message, a URI or an SQL
        // fragment would otherwise let a crafted request forge additional log lines --
        // the entry structure below is the only thing allowed to introduce them.
        return (string) preg_replace('/[\x00-\x08\x0A-\x1F\x7F]/', ' ', (string) $text);
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

        return is_string($uri) ? $this->redactSessionId($uri) : '-';
    }

    /**
     * Remove a URL-borne session id from a request URI.
     *
     * Split out from currentUri() so it can be exercised directly: currentUri() short
     * circuits to 'cli' under a CLI SAPI, and a test suite runs under exactly that, so
     * asserting on its output would confirm nothing at all.
     *
     * This has to work even before session_start() has run, when session_id() is still
     * empty and the hash replacement in sanitize() has nothing to match.
     *
     * Redacting the RAW string was not enough: PHP decodes query keys, so
     * "?PHP%53ESSID=secret" carries a live session id that a literal-name regex walks
     * straight past. The query is parsed and rebuilt from DECODED keys instead, which
     * catches every encoding of the name.
     *
     * @param  string $uri raw request uri
     * @return string
     */
    protected function redactSessionId($uri)
    {
        $name  = function_exists('session_name') ? (string) session_name() : 'PHPSESSID';
        $split = explode('?', $uri, 2);
        if ('' !== $name && isset($split[1]) && '' !== $split[1]) {
            parse_str($split[1], $params);
            $touched = false;
            foreach (array_keys($params) as $key) {
                if (0 === strcasecmp((string) $key, $name)) {
                    $params[$key] = 'sid#redacted';
                    $touched      = true;
                }
            }
            if ($touched) {
                // NOT run through urldecode(). http_build_query() percent-encodes its
                // output, and decoding it again reinstates every raw byte of the original
                // attacker-supplied query -- including %0A, which lets a crafted request
                // forge entire log entries. The encoded form is less pretty and safe.
                $uri = $split[0] . '?' . http_build_query($params);
            }
        }

        return substr((string) $uri, 0, 300);
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
     * Append to the log, rotating first when the entry would push it past max_size.
     *
     * A failure disables this logger for the rest of the request rather than repeating a
     * warning for every subsequent event.
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

        // A symlinked directory or log file would let an append land on any writable file
        // the web user owns -- mainfile.php being the obvious target. basename() does not
        // protect against this at all.
        if (is_link($dir) || is_link($this->file)) {
            $this->writeFailed = true;

            return;
        }

        $this->rotateIfNeeded(strlen($entry));

        // Checked before the append, and after rotateIfNeeded() has possibly renamed the
        // live file away, so a freshly rotated log is treated as new too.
        $isNew  = !is_file($this->file);
        $handle = @fopen($this->file, 'ab');
        if (false === $handle) {
            $this->writeFailed = true;

            return;
        }
        // Owner and group only. The contents are precisely what the redaction above works
        // to keep unreachable, and on shared hosting the file mode is the last control
        // standing once xoops_data has been moved out of the web root as the docs advise.
        // Left best-effort deliberately: the file exists either way by this point, so
        // refusing to write would not make a mode we failed to tighten any safer.
        if ($isNew) {
            @chmod($this->file, 0640);
        }
        // Non-blocking: a lock held by a stalled process must not park this request in a
        // queue behind it. Losing one debug line beats holding a worker.
        if (flock($handle, LOCK_EX | LOCK_NB)) {
            fwrite($handle, $entry);
            flock($handle, LOCK_UN);
        }
        fclose($handle);
    }

    /**
     * Roll debug.log to debug.log.1, shifting existing rotations along and discarding
     * the oldest.
     *
     * @param  int $incoming size of the entry about to be appended
     * @return void
     */
    protected function rotateIfNeeded($incoming = 0)
    {
        if (!is_file($this->file)) {
            return;
        }
        $size = @filesize($this->file);
        // Projected size, so an entry that would push the file past the limit rotates
        // before it is written rather than after.
        if (false === $size || ($size + $incoming) < $this->maxSize) {
            return;
        }

        // Every step of the cascade is checked, because none of them can be shrugged off:
        // once a move fails, the following one renames onto the slot that failed to empty
        // and destroys it. A failure therefore stops rotation and disables the logger for
        // the request, rather than silently discarding data or appending to a file that
        // is already over the limit.
        if (is_file($this->file . '.' . $this->maxFiles) && !@unlink($this->file . '.' . $this->maxFiles)) {
            $this->writeFailed = true;

            return;
        }
        for ($i = $this->maxFiles - 1; $i >= 1; --$i) {
            if (!is_file($this->file . '.' . $i)) {
                continue;
            }
            // Stopping here loses nothing. Carrying on would: the next iteration moves
            // .($i - 1) onto .$i, overwriting the rotation that just failed to move --
            // and that is newer data than the one the cascade set out to discard.
            if (!@rename($this->file . '.' . $i, $this->file . '.' . ($i + 1))) {
                $this->writeFailed = true;

                return;
            }
        }
        if (!@rename($this->file, $this->file . '.1')) {
            $this->writeFailed = true;
        }
    }
}
