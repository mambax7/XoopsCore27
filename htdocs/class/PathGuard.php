<?php
/**
 * XOOPS request-path containment guard
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package   core
 * @since     2.7.3
 */

declare(strict_types=1);

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * PathGuard - resolve a request-supplied relative path against a root
 * directory and refuse everything that escapes it.
 *
 * This is the containment contract the tplsets endpoints converged on
 * across review (PR #176), extracted so every caller enforces the same
 * rules and a truth-table test can pin them:
 *
 * - NUL bytes anywhere in either input are refused outright; realpath()
 *   runs inside a try with \ValueError caught as the backstop (PHP 8
 *   throws for NULs in path-taking functions).
 * - A false realpath() (non-existent path, dangling component) is
 *   refused, never passed on.
 * - Containment is boundary-aware: the candidate must be the root
 *   itself (directories only) or start with root PLUS separator - a
 *   bare prefix check would accept a SIBLING whose name merely begins
 *   with the root ("/themes" matching "/themes2").
 * - Directory mode requires is_dir(); file mode requires is_file(), so
 *   a directory named like "x.css" (or the root itself via "/") never
 *   reaches a file handler.
 * - The optional extension allowlist is matched case-insensitively
 *   against the resolved name's extension.
 * - On success the CANONICAL path (realpath output, native separators)
 *   is returned - callers must use it, not the raw request string,
 *   or they reopen the symlink time-of-check/time-of-use gap.
 *
 * @category  Xoops
 * @package   Core
 * @author    XOOPS Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class PathGuard
{
    /**
     * Resolve a relative path to a real DIRECTORY inside $root.
     *
     * @param string $root     absolute root directory the result must stay inside
     * @param string $relative request-supplied path fragment, appended to $root
     *
     * @return string|false canonical directory path, or false when refused
     *
     * @throws \TypeError when a caller passes non-string arguments (strict_types)
     */
    public static function resolveDir(string $root, string $relative): string|false
    {
        return self::resolve($root, $relative, true, []);
    }

    /**
     * Resolve a relative path to a real FILE strictly inside $root.
     *
     * @param string   $root              absolute root directory the result must stay inside
     * @param string   $relative          request-supplied path fragment, appended to $root
     * @param string[] $allowedExtensions lowercase extensions without dots (e.g. ['css','tpl']);
     *                                    empty accepts any extension
     *
     * @return string|false canonical file path, or false when refused
     *
     * @throws \TypeError when a caller passes non-string arguments (strict_types)
     */
    public static function resolveFile(string $root, string $relative, array $allowedExtensions = []): string|false
    {
        return self::resolve($root, $relative, false, $allowedExtensions);
    }

    /**
     * @param string   $root              root directory
     * @param string   $relative          request-supplied fragment
     * @param bool     $wantDir           true for directory mode, false for file mode
     * @param string[] $allowedExtensions file mode only; empty accepts any
     *
     * @return string|false
     */
    private static function resolve(string $root, string $relative, bool $wantDir, array $allowedExtensions): string|false
    {
        // A NUL cannot occur in a legitimate path - refuse it before any
        // filesystem call sees it.
        if (str_contains($root, "\0") || str_contains($relative, "\0")) {
            return false;
        }
        // Join explicitly when neither side supplies the separator:
        // "themes" + "default" must probe themes/default, not the sibling
        // "themesdefault" (review catch - the concatenation failed CLOSED,
        // but a shared API should not refuse legitimate callers).
        $joiner = ('' === $relative
            || str_starts_with($relative, '/')
            || str_starts_with($relative, '\\')
            || str_ends_with($root, '/')
            || str_ends_with($root, '\\'))
            ? ''
            : DIRECTORY_SEPARATOR;
        try {
            // realpath() belongs INSIDE the try: PHP 8 throws \ValueError
            // for NUL bytes, the backstop should the check above regress.
            $rootReal  = realpath($root);
            $candidate = realpath($root . $joiner . $relative);
        } catch (\ValueError $e) {
            return false;
        }
        if (!is_string($rootReal) || !is_dir($rootReal) || !is_string($candidate)) {
            return false;
        }
        // Boundary prefix built from a right-trimmed root: when the root IS
        // a filesystem root ("/", "C:\"), the naive $rootReal . SEPARATOR
        // would double the separator and refuse every child (review catch).
        $boundary = rtrim($rootReal, '/\\') . DIRECTORY_SEPARATOR;
        if ($wantDir) {
            if (!is_dir($candidate)) {
                return false;
            }
            // Exact root, or root plus separator - never a bare prefix.
            if ($candidate !== $rootReal && !str_starts_with($candidate, $boundary)) {
                return false;
            }

            return $candidate;
        }
        if (!is_file($candidate)) {
            return false;
        }
        // A file is never the root itself; it must sit strictly inside.
        if (!str_starts_with($candidate, $boundary)) {
            return false;
        }
        if ([] !== $allowedExtensions) {
            $ext = strtolower((string) pathinfo($candidate, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExtensions, true)) {
                return false;
            }
        }

        return $candidate;
    }
}
