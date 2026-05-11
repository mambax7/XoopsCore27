<?php
/**
 * Side-effect-free file-safety helpers.
 *
 * Hosts the three small filesystem helpers used by atomic-write and
 * cleanup paths:
 *
 *  - xoops_file_label()        — root-relative label for warnings
 *  - xoops_chmod_quietly()     — scoped-suppressed chmod() with single warning
 *  - xoops_remove_file_quietly() — scoped-suppressed unlink() with single warning
 *
 * They originally lived in include/cp_functions.php, but that file
 * unconditionally `define()`s XOOPS_CPFUNC_LOADED, which include/
 * functions.php keys off to force redirect_header() into the 'default'
 * theme. Including cp_functions.php from non-CP contexts (notably the
 * upgrade scripts that instantiate SystemMaintenance directly) was
 * silently flipping that flag. This file deliberately has NO module-
 * level side effects — it can be required from anywhere without
 * affecting redirect rendering, theme selection, or any other global
 * state. cp_functions.php now requires this file as well, so existing
 * call sites continue to work unchanged.
 *
 * Each function is wrapped in a function_exists() guard so the file is
 * safe to require_once multiple times across mixed load orders.
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package             kernel
 * @since               2.7.0
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

if (!function_exists('xoops_file_label')) {
    /**
     * Create a short, non-sensitive file label for warnings.
     *
     * Returns the path relative to XOOPS_ROOT_PATH when the file lives
     * under the install root, otherwise the basename only. Used by
     * atomic-write callers that want to surface a bit of context
     * ("which area of the install failed") without leaking the full
     * server-side filesystem layout.
     *
     * @param string $filename
     * @return string
     */
    function xoops_file_label($filename)
    {
        $normalized = str_replace('\\', '/', $filename);
        $rootPrefix = rtrim(str_replace('\\', '/', XOOPS_ROOT_PATH), '/') . '/';

        if (strncmp($normalized, $rootPrefix, strlen($rootPrefix)) === 0) {
            return substr($normalized, strlen($rootPrefix));
        }

        return basename($filename);
    }
}

if (!function_exists('xoops_safe_basename')) {
    /**
     * Strict basename for warning messages emitted by the cleanup
     * helpers. Normalises backslashes to '/' first so a Windows-style
     * path stored in a cross-platform context still collapses to just
     * the filename. Use this where the warning must NEVER disclose any
     * directory structure (e.g. cleanup of orphan/temp files).
     *
     * Defensive shape, mirroring xoops_chmod_quietly() /
     * xoops_remove_file_quietly(): reject null-byte payloads up front
     * and wrap basename() in catch(\Throwable). Empirically basename()
     * does not throw on a "\0"-bearing path in PHP 8.2-8.4 (it returns
     * the byte verbatim), but the helpers it serves are documented as
     * best-effort/non-propagating, so the same guarantee must hold here
     * — a stray null byte in a future PHP version, a userland override,
     * or a throwing error handler must NOT escape the cleanup path. A
     * literal "\0" in the formatted trigger_error() output would also
     * confuse log parsers; returning a fixed placeholder keeps the
     * warning readable.
     *
     * @param string $path
     * @return string
     */
    function xoops_safe_basename($path)
    {
        $normalized = str_replace('\\', '/', (string) $path);

        if (str_contains($normalized, "\0")) {
            return 'invalid-path';
        }

        try {
            return basename($normalized);
        } catch (\Throwable $e) {
            return 'invalid-path';
        }
    }
}

if (!function_exists('xoops_chmod_quietly')) {
    /**
     * Set file permissions, suppressing the native PHP warning on failure
     * via the same scoped error_reporting() toggle used by
     * xoops_remove_file_quietly(). Without this, a chmod() failure
     * produces TWO log lines: the native PHP warning AND the project's
     * own trigger_error(). The helper consolidates them into a single
     * project-standard warning based on the boolean return value.
     *
     * @param string $path    Absolute path to the file.
     * @param int    $perms   Permission bits (octal).
     * @param string $context Short label used in the warning message
     *                        (e.g. 'temp', 'temp guard').
     *
     * @return bool True on success, false on failure (warning already emitted).
     */
    function xoops_chmod_quietly($path, $perms, $context = 'temp')
    {
        // Initialise $ok before the try block: error_reporting(0) does NOT
        // disable user-defined error handlers, only the native warning. A
        // naively written handler that always throws (without checking
        // error_reporting() & $errno) would propagate out of the try block
        // before chmod() returns, leaving $ok unset. Defensive default.
        // Catch \Throwable too: chmod() raises ValueError on PHP 8+ for
        // paths containing a null byte, and a user error handler may throw
        // ErrorException for other filesystem conditions. Both are reported
        // as a single project-standard warning, never propagated out of a
        // best-effort cleanup helper.
        $ok            = false;
        $previousLevel = error_reporting(0);
        try {
            $ok = chmod($path, $perms);
        } catch (\Throwable $e) {
            $ok = false;
        } finally {
            error_reporting($previousLevel);
        }
        if (!$ok) {
            // basename-only label: cleanup-helper warnings never need
            // directory context, and the strict form keeps install
            // layout out of any error log a site operator may share.
            trigger_error(
                sprintf('Failed to set permissions on %s file: %s', $context, xoops_safe_basename($path)),
                E_USER_WARNING
            );
        }

        return $ok;
    }
}

if (!function_exists('xoops_remove_file_quietly')) {
    /**
     * Best-effort file removal used by atomic-write cleanup paths and similar
     * fire-and-forget cleanup. Skips non-existent paths so already-deleted
     * files don't trigger warnings, suppresses the unlink() warning via a
     * scoped error_reporting() toggle (no `@` operator), and re-checks
     * existence after a failed unlink — only logging when the file is still
     * present, so TOCTOU races resolve silently.
     *
     * @param string $path    Absolute path to the file to remove.
     * @param string $context Short label used in the warning message
     *                        (e.g. 'temporary', 'backup').
     *
     * @return void
     */
    function xoops_remove_file_quietly($path, $context = 'temporary')
    {
        // file_exists() returns false for broken symlinks, so a dangling
        // symlink would be skipped here and also bypass the post-unlink
        // existence check below — leaving the orphaned link in place. Treat
        // links as existing too: unlink() can remove broken symlinks just
        // fine, and the targets they point to are not what we care about.
        //
        // file_exists() / is_link() can themselves raise ValueError on PHP
        // 8+ when the path contains a null byte. Treat any throw from the
        // pre-check as "nothing to do" — there is no file we could safely
        // remove, and propagating the exception out of a best-effort
        // cleanup helper would abort the caller's unrelated work.
        try {
            if (!file_exists($path) && !is_link($path)) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }
        // Initialise $ok defensively — see xoops_chmod_quietly() for the
        // rationale (error_reporting(0) does not disable user-defined
        // error handlers). Catch \Throwable around unlink() for the same
        // ValueError-on-null-byte / throwing-error-handler reasons.
        $ok            = false;
        $previousLevel = error_reporting(0);
        try {
            $ok = unlink($path);
        } catch (\Throwable $e) {
            $ok = false;
        } finally {
            error_reporting($previousLevel);
        }
        // Same try/catch shape around the post-unlink probe: if the path
        // contained a null byte we have nothing useful to report anyway.
        try {
            $stillPresent = file_exists($path) || is_link($path);
        } catch (\Throwable $e) {
            $stillPresent = false;
        }
        if (!$ok && $stillPresent) {
            // basename-only label: see xoops_chmod_quietly() rationale.
            trigger_error(
                sprintf('Failed to remove %s file: %s', $context, xoops_safe_basename($path)),
                E_USER_WARNING
            );
        }
    }
}
