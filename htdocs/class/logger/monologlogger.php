<?php
/**
 * XOOPS Monolog Logger Adapter
 *
 * Wraps Monolog 2.x/3.x as a PSR-3 compatible logger that integrates with
 * XoopsLogger via the addLogger() composite pattern (ported from XOOPS 2.6).
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package             kernel
 * @subpackage          logger
 * @since               2.7.0
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Psr\Log\LogLevel;

/**
 * Monolog adapter for XoopsLogger composite pattern.
 *
 * Usage:
 *   $monolog = new XoopsMonologLogger('xoops');
 *   XoopsLogger::getInstance()->addLogger($monolog);
 *
 * @package kernel
 */
class XoopsMonologLogger
{
    /**
     * @var Logger|null Monolog logger instance
     */
    private $monolog;

    /**
     * @var bool Whether this logger is active
     */
    private $activated = true;

    /**
     * Constructor — creates a Monolog logger with sensible defaults.
     *
     * @param string $channelName  Monolog channel name (default: 'xoops')
     * @param array<int, \Monolog\Handler\HandlerInterface> $handlers Optional handlers
     * @param array<int, callable> $processors Optional processors
     * @param string $minimumLevel Minimum level for the default file handler
     */
    public function __construct($channelName = 'xoops', array $handlers = [], array $processors = [], $minimumLevel = LogLevel::WARNING)
    {
        if (!class_exists('Monolog\Logger')) {
            $this->activated = false;
            return;
        }

        try {
            $this->monolog = new Logger($channelName);

            if (empty($handlers)) {
                // Default: rotating file handler in XOOPS_VAR_PATH/logs/
                // Require XOOPS_VAR_PATH (outside webroot); never fall back
                // to XOOPS_ROOT_PATH which would expose logs publicly.
                if (!defined('XOOPS_VAR_PATH')) {
                    $this->activated = false;
                    return;
                }
                $logDir = XOOPS_VAR_PATH . '/logs';
                if (!is_dir($logDir)) {
                    if (!mkdir($logDir, 0755, true) && !is_dir($logDir)) {
                        $this->activated = false;
                        return;
                    }
                }
                $logFile = $logDir . '/xoops.log';
                $handler = new RotatingFileHandler($logFile, 30, $this->normalizeLevel($minimumLevel));
                $this->monolog->pushHandler($handler);
            } else {
                foreach ($handlers as $handler) {
                    $this->monolog->pushHandler($handler);
                }
            }

            foreach ($processors as $processor) {
                $this->monolog->pushProcessor($processor);
            }
        } catch (\Throwable $e) {
            $this->activated = false;
        }
    }

    /**
     * PSR-3 compatible log method (v1 untyped signature for broad compat).
     *
     * @param mixed  $level    PSR-3 log level string or Monolog integer level
     * @param string $message  log message
     * @param array<array-key, mixed> $context context array
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        if (!$this->activated || null === $this->monolog) {
            return;
        }

        // Strip the 'channel' key used for DebugBar routing — not relevant for file logging
        $logContext = $this->sanitizeContext($context);
        unset($logContext['channel']);

        try {
            $this->monolog->log($this->normalizeLevel($level), (string) $message, $logContext);
        } catch (\Throwable $e) {
            // Silently ignore to prevent cascading failures
        }
    }

    /**
     * Quiet mode — no-op for file-based logger (nothing to suppress).
     *
     * @return void
     */
    public function quiet()
    {
        // File logger has no page output to suppress
    }

    /**
     * Get the underlying Monolog Logger instance for advanced configuration.
     *
     * @return Logger|null
     */
    public function getMonolog()
    {
        return $this->monolog;
    }

    /** Whether Monolog initialized successfully. */
    public function isActive(): bool
    {
        return $this->activated && null !== $this->monolog;
    }

    /**
     * Normalize levels to PSR-3 names accepted by both Monolog 2 and 3.
     *
     * @param mixed $level  PSR-3 level string or integer
     * @return 'emergency'|'alert'|'critical'|'error'|'warning'|'notice'|'info'|'debug'
     */
    private function normalizeLevel($level)
    {
        $map = [
            Logger::EMERGENCY => LogLevel::EMERGENCY,
            Logger::ALERT     => LogLevel::ALERT,
            Logger::CRITICAL  => LogLevel::CRITICAL,
            Logger::ERROR     => LogLevel::ERROR,
            Logger::WARNING   => LogLevel::WARNING,
            Logger::NOTICE    => LogLevel::NOTICE,
            Logger::INFO      => LogLevel::INFO,
            Logger::DEBUG     => LogLevel::DEBUG,
        ];
        if (is_int($level)) {
            return isset($map[$level]) ? $map[$level] : LogLevel::DEBUG;
        }
        $level = strtolower((string) $level);
        return in_array($level, $map, true) ? $level : LogLevel::DEBUG;
    }

    /**
     * Bound legacy context and prevent credentials or object graphs reaching disk.
     * @param array<array-key, mixed> $context
     * @return array<array-key, mixed>
     */
    private function sanitizeContext(array $context, int $depth = 0): array
    {
        if ($depth >= 4) {
            return ['_truncated' => 'maximum context depth reached'];
        }
        $safe = [];
        $count = 0;
        foreach ($context as $key => $value) {
            if (++$count > 25) {
                $safe['_truncated'] = 'additional context items omitted';
                break;
            }
            $name = (string) $key;
            if (preg_match('/(?:password|passwd|token|secret|cookie|authorization|api[_-]?key)/i', $name)) {
                $safe[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $safe[$key] = $this->sanitizeContext($value, $depth + 1);
            } elseif ($value instanceof \Throwable) {
                $safe[$key] = get_class($value) . ': ' . substr($value->getMessage(), 0, 2000);
            } elseif (is_object($value)) {
                $safe[$key] = '[object ' . get_class($value) . ']';
            } elseif (is_resource($value)) {
                $safe[$key] = '[resource ' . get_resource_type($value) . ']';
            } elseif (is_string($value) && strlen($value) > 2000) {
                $safe[$key] = substr($value, 0, 2000) . '… [truncated]';
            } else {
                $safe[$key] = $value;
            }
        }
        return $safe;
    }
}
