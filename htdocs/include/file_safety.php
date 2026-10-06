<?php
/**
 * Side-effect-free file-safety helpers.
 *
 * Hosts small, dependency-light safety helpers used by atomic-write, cleanup
 * and redirect paths:
 *
 *  - xoops_file_label()        — root-relative label for warnings
 *  - xoops_safe_basename()     — null-byte-safe basename() with placeholder
 *  - xoops_chmod_quietly()     — scoped-suppressed chmod() with single warning
 *  - xoops_remove_file_quietly() — scoped-suppressed unlink() with single warning
 *  - xoops_resolveFileWithin() — canonical path of a stored file name, only inside a root
 *  - xoops_isLocalUrl()        — strict same-origin check (scheme/host/port) for redirects
 *  - xoops_validateLocalRedirect() — full same-site redirect policy (origin + base path)
 *  - xoops_postLoginRedirectUrl()  — the absolute URL to send a user to after login
 *  - xoops_rebuildQueryString() — parse-and-re-emit a query string for safe reflection
 *  - xoops_groupCacheKey()     — unguessable cache-id segment for a group set
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

if (!function_exists('xoops_resolveFileWithin')) {
    /**
     * Resolve a stored file name against the directory it must live in.
     *
     * Use before any file operation driven by a name read from the database
     * or a request (an image, smiley or rank file under XOOPS_UPLOAD_PATH,
     * say), so a name such as "../mainfile.php" or a symlink pointing out of
     * the directory can never reach a file outside it.
     *
     * @param string $root     directory the file must be inside
     * @param string $relative stored file name, relative to $root
     * @return string the canonical path when it names a regular file strictly
     *                inside $root, '' otherwise (missing root or file, a
     *                directory, a path outside $root, a null byte)
     *
     * Containment is verified at resolution time; it is not a lock. A rename or
     * symlink swap inside $root between this call and the caller's file
     * operation could change what the path points to, which requires write
     * access inside $root.
     */
    function xoops_resolveFileWithin($root, $relative)
    {
        try {
            $rootReal = realpath((string) $root);
            $fileReal = realpath(rtrim((string) $root, '/\\') . '/' . ltrim((string) $relative, '/\\'));
        } catch (\Throwable $e) {
            return ''; // realpath() throws a ValueError on a null byte
        }
        // Normalised so a filesystem root ("/" or "C:\") does not become a
        // doubled separator that no child path can start with.
        if (false === $rootReal || false === $fileReal
            || !str_starts_with($fileReal, rtrim($rootReal, '/\\') . DIRECTORY_SEPARATOR)
            || !is_file($fileReal)
        ) {
            return '';
        }

        return $fileReal;
    }
}

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
        // error_reporting(0) does NOT disable user-defined error handlers,
        // only the native warning. Catch \Throwable too: chmod() raises
        // ValueError on PHP 8+ for
        // paths containing a null byte, and a user error handler may throw
        // ErrorException for other filesystem conditions. Both are reported
        // as a single project-standard warning, never propagated out of a
        // best-effort cleanup helper.
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

if (!function_exists('xoops_path_confirmed_absent')) {
    /**
     * Whether nothing exists at $path, confirmed by listing the nearest
     * existing ancestor. file_exists() is also false when a parent cannot be
     * read, so a missing file is trusted only when its directory can be
     * listed; an unreadable directory confirms nothing.
     *
     * @param string $path absolute filesystem path
     *
     * @return bool
     */
    function xoops_path_confirmed_absent(string $path): bool
    {
        // scandir() on an unreadable directory warns with the full path; the
        // scoped handler keeps that off the page (the caller reports with a
        // basename), and a throwing handler or ValueError confirms nothing.
        set_error_handler(static fn (): bool => true);
        try {
            // The direct probe first: on a case-insensitive filesystem the
            // listing holds the stored casing, so a path spelt differently
            // would otherwise read as absent while the file is still there.
            if (file_exists($path) || is_link($path)) {
                return false;
            }
            $name = basename($path);
            $dir  = dirname($path);
            while (!is_dir($dir)) {
                if (dirname($dir) === $dir) {
                    return false;
                }
                $name = basename($dir);
                $dir  = dirname($dir);
            }
            $listing = scandir($dir);

            return false !== $listing && !in_array($name, $listing, true);
        } catch (\Throwable $e) {
            return false;
        } finally {
            restore_error_handler();
        }
    }
}
if (!function_exists('xoops_remove_file_quietly')) {
    /**
     * Best-effort file removal used by atomic-write cleanup paths and similar
     * fire-and-forget cleanup. Skips paths confirmed absent so already-deleted
     * files don't trigger warnings, suppresses the unlink() warning via a
     * scoped error_reporting() toggle (no `@` operator), and re-checks
     * absence after a failed unlink — logging when the file is still present
     * or cannot be confirmed gone (an unreadable parent), so TOCTOU races
     * resolve silently and an inaccessible path is not mistaken for a removed one.
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
                if (!xoops_path_confirmed_absent($path)) {
                    trigger_error(
                        sprintf('Could not confirm removal of %s file: %s', $context, xoops_safe_basename($path)),
                        E_USER_WARNING
                    );
                }

                return;
            }
        } catch (\Throwable $e) {
            return;
        }
        // See xoops_chmod_quietly() for the rationale: error_reporting(0)
        // does not disable user-defined error handlers. Catch \Throwable
        // around unlink() for the same ValueError-on-null-byte /
        // throwing-error-handler reasons.
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
            $stillPresent = !$ok && !xoops_path_confirmed_absent($path); // the listing only when unlink() failed
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

if (!function_exists('xoops_normalizeUrlSeparators')) {
    /**
     * Collapse escaped-ampersand layers in a URL back to a single separator.
     *
     * A URL is not HTML, so "&amp;" in a query string is already wrong: to anything that
     * parses the URL rather than renders it, the following parameter is named
     * "amp;name". It went unnoticed because a browser decodes the entity before
     * requesting, which hides the fault for exactly one hop.
     *
     * It stops hiding once a semicolon has been percent-encoded somewhere along the
     * chain. "&amp%3B" is not a complete entity, so the browser can no longer collapse
     * it, and every later escape adds another layer. xoops.org was logging
     *
     *     ?start=0&amp%3Bamp%3Bamp%3Bforum=0
     *
     * for a URL that should read "?start=0&forum=0" -- three rounds of escaping deep and
     * growing by one on each pass through the login redirect.
     *
     * Both spellings are collapsed, so a URI that already carries the damage is healed
     * rather than carried forward. A parameter genuinely named "amp" is untouched: the
     * pattern matches only "amp;" or "amp%3B" directly following an ampersand.
     *
     * @param string $url candidate URL
     * @return string the URL with "&amp;" / "&amp%3B" runs reduced to "&"
     */
    function xoops_normalizeUrlSeparators($url)
    {
        return (string) preg_replace('/&(?:amp;|amp%3B)+/i', '&', (string) $url);
    }
}

if (!function_exists('xoops_isLocalUrl')) {
    /**
     * Decide whether an absolute or scheme-relative URL points at this site.
     *
     * Decodes HTML entities first, rejects control characters, then compares
     * scheme, host and port against XOOPS_URL exactly. A look-alike host such as
     * `localhost.example.org`, a userinfo trick such as `localhost@evil.test`, or
     * a scheme-relative `//evil.test` therefore does not match. A single
     * leading-slash, root-relative path (e.g. `/index.php`) is treated as local;
     * `//` is not. Bare relative paths (e.g. `user.php`) are the caller's
     * responsibility and are intentionally not the target of this helper.
     *
     * @param string $url candidate URL
     * @return bool true when the target is same-origin or a root-relative path
     */
    function xoops_isLocalUrl($url)
    {
        $decoded = html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (preg_match('/[\x00-\x1F\x7F]/', $decoded)) {
            // control characters / CR-LF; browsers also drop TAB/LF/CR
            // while parsing, so "/<TAB>/host" would become "//host"
            return false;
        }

        if (xoops_urlHeadIsAmbiguous($decoded)) {
            return false;
        }

        $parts = parse_url($decoded);
        if ($parts === false) {
            return false;
        }

        if (!isset($parts['host'])) {
            // A scheme with no host (e.g. "http:/evil.test/") is malformed and a
            // browser may normalise it to an external absolute URL — reject it. A
            // genuine root-relative path carries no scheme: allow a single-slash
            // path, reject `//host`.
            return !isset($parts['scheme'])
                && isset($parts['path'])
                && strncmp($parts['path'], '/', 1) === 0
                && strncmp($decoded, '//', 2) !== 0;
        }

        $base = parse_url((string) XOOPS_URL);
        if (!isset($base['host'])) {
            return false;
        }

        $sameHost = strcasecmp($parts['host'], $base['host']) === 0;

        // A scheme-relative target (//host/...) inherits the base scheme, so
        // compare against the base rather than defaulting to http.
        $baseScheme   = strtolower($base['scheme'] ?? 'http');
        $targetScheme = strtolower($parts['scheme'] ?? $baseScheme);
        $sameScheme   = $targetScheme === $baseScheme;

        // Normalise default ports so http://host and http://host:80 (and the
        // https/443 pair) compare as the same origin.
        $defaultPorts = ['http' => 80, 'https' => 443, 'ftp' => 21];
        $targetPort = $parts['port'] ?? ($defaultPorts[$targetScheme] ?? null);
        $basePort   = $base['port'] ?? ($defaultPorts[$baseScheme] ?? null);
        $samePort   = $targetPort === $basePort;

        return $sameHost && $sameScheme && $samePort;
    }
}

if (!function_exists('xoops_urlHeadIsAmbiguous')) {
    /**
     * Detect URL forms that parse_url() and browsers read differently.
     *
     * Browsers treat "\" as "/" in the scheme, authority and path of http(s)
     * URLs (WHATWG URL Standard, special schemes), so "/\host" or
     * "http://site\@host" can leave the site while parse_url() reports a
     * local path or a user name. Percent-encoded "/" and "\" in that same
     * part can be normalised by clients or proxies into the same shapes.
     * The query and fragment are data, so separators there stay allowed.
     *
     * @param string $url candidate URL, already entity-decoded
     * @return bool true when the scheme/authority/path part is ambiguous
     */
    function xoops_urlHeadIsAmbiguous($url)
    {
        $head = preg_split('/[?#]/', (string) $url, 2)[0];

        return str_contains($head, '\\') || 1 === preg_match('/%(?:2f|5c)/i', $head);
    }
}

if (!function_exists('xoops_validateLocalRedirect')) {
    /**
     * Validate an untrusted redirect target against the XOOPS base URL.
     *
     * The single same-site redirect policy shared by user.php,
     * modules/profile/user.php and the theme selector. A target is accepted
     * only when it is either
     *  - a root-relative path ("/...") inside the base path, or
     *  - an absolute http(s) URL with the base scheme, host and effective port,
     *    no userinfo, and a path inside the base path.
     * Rejected outright: control characters, scheme-relative "//host",
     * backslash and encoded-separator forms (see xoops_urlHeadIsAmbiguous()),
     * ".." path segments (literal or encoded) and bare relative paths.
     *
     * The checks run on the entity-decoded form, because redirect_header()
     * and other HTML sinks decode entities before the browser acts on the URL.
     *
     * @param string      $redirect untrusted redirect target
     * @param string|null $baseUrl  authoritative base URL; defaults to XOOPS_URL
     * @return string the trimmed target when it is safe, '' otherwise
     */
    function xoops_validateLocalRedirect($redirect, $baseUrl = null)
    {
        $redirect = trim((string) $redirect);
        $baseUrl  = (string) ($baseUrl ?? (defined('XOOPS_URL') ? XOOPS_URL : ''));
        if ('' === $redirect || '' === $baseUrl) {
            return '';
        }

        $probe = html_entity_decode($redirect, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (1 === preg_match('/[\x00-\x1F\x7F]/', $probe)
            || str_starts_with($probe, '//')
            || xoops_urlHeadIsAmbiguous($probe)
        ) {
            return '';
        }

        $parts = parse_url($probe);
        if (false === $parts || isset($parts['user']) || isset($parts['pass'])) {
            return '';
        }

        $base       = parse_url($baseUrl) ?: [];
        $baseScheme = strtolower((string) ($base['scheme'] ?? ''));
        $baseHost   = (string) ($base['host'] ?? '');
        $basePath   = rtrim((string) ($base['path'] ?? ''), '/');
        if ('' === $baseScheme || '' === $baseHost) {
            return '';
        }

        $effectivePort = static function (string $scheme, $port): ?int {
            if (null !== $port) {
                return (int) $port;
            }

            return ['http' => 80, 'https' => 443][$scheme] ?? null;
        };

        if (isset($parts['scheme']) || isset($parts['host'])) {
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            $host   = (string) ($parts['host'] ?? '');
            if ($scheme !== $baseScheme
                || '' === $host
                || 0 !== strcasecmp($host, $baseHost)
                || $effectivePort($scheme, $parts['port'] ?? null) !== $effectivePort($baseScheme, $base['port'] ?? null)
            ) {
                return '';
            }
            $path = (string) ($parts['path'] ?? '/');
        } elseif (str_starts_with($probe, '/')) {
            $path = (string) ($parts['path'] ?? '/');
        } else {
            return ''; // bare relative paths resolve against the current page, not the site
        }

        // Decode before splitting so "%2e%2e" is seen as a ".." segment.
        if (in_array('..', explode('/', rawurldecode($path)), true)) {
            return '';
        }
        if ('' !== $basePath && $path !== $basePath && !str_starts_with($path, $basePath . '/')) {
            return '';
        }

        return $redirect;
    }
}

if (!function_exists('xoops_postLoginRedirectUrl')) {
    /**
     * Build the absolute URL to send a user to after login.
     *
     * $redirect is the posted xoops_redirect value, normally the URL-encoded
     * REQUEST_URI of the page that showed the login form. It is decoded once;
     * a root-relative path given without the base path of a subdirectory
     * install gets the base path prepended, as before; the result must then
     * pass xoops_validateLocalRedirect(). An empty value, a registration page
     * (judged on the path with HTML entities and percent-encoding removed,
     * case-insensitively) or anything the validator refuses falls back to
     * the site's index.php.
     *
     * @param string      $redirect posted xoops_redirect value, URL-encoded
     * @param string|null $baseUrl  authoritative base URL; defaults to XOOPS_URL
     * @return string absolute URL on this site
     */
    function xoops_postLoginRedirectUrl($redirect, $baseUrl = null)
    {
        $baseUrl  = rtrim((string) ($baseUrl ?? (defined('XOOPS_URL') ? XOOPS_URL : '')), '/');
        $fallback = $baseUrl . '/index.php';
        $redirect = (string) $redirect;
        if ('' === $redirect) {
            return $fallback;
        }

        $target = rawurldecode($redirect);
        $path   = preg_split('/[?#]/', $target, 2)[0];

        // Never send a user who just logged in back to a registration page.
        // Judge the path the browser will end up requesting: up to three
        // layers of HTML entities and percent-encoding are removed first
        // (redirect_header() emits the URL into HTML, so "/&#x72;egister.php"
        // reaches the browser as /register.php, and "/%2572egister.php" as
        // "/%72egister.php", which the web server serves as /register.php),
        // and the path is split off only afterwards, because an entity such
        // as "&#x72;" itself contains a "#".
        $probe = $target;
        for ($i = 0; $i < 3; $i++) {
            $next = rawurldecode(html_entity_decode($probe, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($next === $probe) {
                break;
            }
            $probe = $next;
        }
        if (str_contains(strtolower(preg_split('/[?#]/', $probe, 2)[0]), 'register')) {
            return $fallback;
        }

        $base     = parse_url($baseUrl) ?: [];
        $basePath = rtrim((string) ($base['path'] ?? ''), '/');
        if ('' !== $basePath
            && str_starts_with($path, '/')
            && !str_starts_with($path, '//')
            && $path !== $basePath
            && !str_starts_with($path, $basePath . '/')
        ) {
            $target = $basePath . $target;
        }

        $target = xoops_validateLocalRedirect($target, $baseUrl);
        if ('' === $target) {
            return $fallback;
        }
        if (!str_starts_with($target, '/')) {
            return $target; // already an absolute URL on this site
        }

        return strtolower((string) ($base['scheme'] ?? 'http')) . '://' . (string) ($base['host'] ?? '')
            . (isset($base['port']) ? ':' . (int) $base['port'] : '') . $target;
    }
}

if (!function_exists('xoops_rebuildQueryString')) {
    /**
     * Rebuild a query string so it is safe to reflect into a redirect
     * Location header, '?' included — or return '' when there is nothing
     * usable. The raw QUERY_STRING is attacker-controlled: appended verbatim
     * it invites cache-poisoning and phishing parameter injection (CRLF
     * itself is already blocked by PHP's header()). Instead of gating on a
     * character allowlist — which reflects malformed percent-escapes
     * verbatim and costs a long urlencoded xoops_redirect its whole query —
     * the string is parsed with parse_str(), which mirrors how the redirect
     * target itself will read it, and re-emitted with http_build_query(), so
     * every reflected byte is RFC 3986-safe or a valid escape by
     * construction. The length cap is header-size sanity only.
     *
     * @param string $queryString raw query string (e.g. $_SERVER['QUERY_STRING'])
     * @return string '?' plus the rebuilt query string, or '' when empty, oversized or unparseable
     */
    function xoops_rebuildQueryString($queryString)
    {
        $queryString = (string) $queryString;
        if ('' === $queryString || strlen($queryString) > 2000) {
            return '';
        }
        parse_str($queryString, $params);
        $rebuilt = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        return ('' === $rebuilt) ? '' : ('?' . $rebuilt);
    }
}

if (!function_exists('xoops_groupCacheKey')) {
    /**
     * Cache-id segment for a set of group ids.
     *
     * Smarty cache ids become file names under xoops_data/caches. Content cached
     * for one group set must not be addressable by guessing its name, so the
     * segment is an HMAC of the sorted ids under the site's stored 'cacheid' key
     * (created on first use in xoops_data/data, like the 'rememberme' key). The
     * former derivation hashed the database credentials into the name instead.
     *
     * Without a usable key the segment is random for the request, so group
     * content is written but never served from the cache, and a warning names
     * the cause; a guessable segment is never produced. The one-time repair of a
     * malformed key file is not atomic, so two first requests repairing at once
     * can hold different secrets for that request: one more cache miss, nothing
     * guessable.
     *
     * @param int[] $groups group ids, in any order
     *
     * @return string 16 hex characters; the same for the same group set
     */
    function xoops_groupCacheKey(array $groups): string
    {
        static $secret = null;
        if (null === $secret) {
            // Xmf\Random::generateKey() is a sha512 hex digest. Anything else is a
            // broken key file: an include of the empty file a concurrent first
            // request is still writing returns 1, which getSigning() casts to '1',
            // and an interrupted write leaves a file KeyFactory never replaces.
            // One kill-and-rebuild repairs the latter instead of warning forever.
            $secret = '';
            try {
                $key = \Xmf\Jwt\KeyFactory::build('cacheid');
                try {
                    $secret = (string) $key->getSigning();
                } catch (\Throwable $e) {
                    $secret = ''; // a syntactically broken key file throws on include
                }
                if (!preg_match('/^[0-9a-f]{128}\z/', $secret)) {
                    $key->kill();
                    $key->create();
                    $secret = (string) $key->getSigning();
                }
            } catch (\Throwable $e) {
                $secret = '';
            }
            if (!preg_match('/^[0-9a-f]{128}\z/', $secret)) {
                trigger_error('xoops_groupCacheKey(): no usable cacheid key in key storage; group content is not cached for this request', E_USER_WARNING);
                $secret = bin2hex(random_bytes(16));
            }
        }
        $groups = array_map('intval', $groups);
        sort($groups);

        return substr(hash_hmac('sha256', implode('-', $groups), $secret), 0, 16);
    }
}
