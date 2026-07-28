<?php
/**
 * XOOPS debug settings
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
 * @package             core
 * @since               2.7.3
 */

/*
 * ---------------------------------------------------------------------------
 * HOW TO USE
 * ---------------------------------------------------------------------------
 * Copy this file to debug.php in the same directory and edit it. While
 * debug.php does not exist, XOOPS behaves exactly as before and nothing here
 * has any effect — this is an opt-in development aid.
 *
 *     xoops_data/data/debug.dist.php  ->  xoops_data/data/debug.php
 *
 * It replaces hand-editing XOOPS_DEBUG in mainfile.php, which is awkward
 * because mainfile.php also holds your database credentials and is not
 * replaced on upgrade.
 *
 * NEVER enable this on a production site that serves real visitors:
 * display_errors reveals paths and query fragments to anyone who triggers an
 * error.
 *
 * ---------------------------------------------------------------------------
 * READ THIS FIRST: is your log directory reachable over the web?
 * ---------------------------------------------------------------------------
 * The log records SQL, user ids, backtraces and file paths. XOOPS ships
 * .htaccess files denying web access to this file and to xoops_data/logs, but
 * .htaccess works on APACHE ONLY, and only where AllowOverride permits it. On
 * nginx, IIS, or Apache with AllowOverride None, those files do nothing at all.
 *
 * The reliable fix is to keep xoops_data OUTSIDE the document root, which many
 * installations already do. If it sits under the web root, add the equivalent
 * rule yourself, then verify by requesting the log URL in a browser:
 *
 *   nginx:
 *     location ^~ /xoops_data/ { deny all; return 404; }
 *
 *   IIS (web.config): add "xoops_data" to requestFiltering/hiddenSegments.
 *
 * The log filename is restricted to a plain "*.log" name, and any other value
 * silently falls back to debug.log. That restriction is deliberate: log content
 * is written verbatim, so a *.php log could be made to hold executable code.
 * ---------------------------------------------------------------------------
 *
 * This is INDEPENDENT of Admin -> Preferences -> Debug Mode, which controls the
 * in-page debug output shown to administrators. Either can be used alone.
 * ---------------------------------------------------------------------------
 */

if (!defined('XOOPS_ROOT_PATH')) {
    // Not reachable through the web server, and not useful standalone.
    http_response_code(404);
    exit();
}

return [
    // Master switch. false leaves XOOPS in its normal production behaviour even
    // if the rest of this file says otherwise.
    'enabled' => true,

    // Sets XOOPS_DEBUG, and PHP's display_errors / error_reporting, as early as
    // the bootstrap allows. E_ALL includes the E_USER_DEPRECATED notices XOOPS
    // raises for legacy module APIs.
    'display_errors'  => true,
    'error_reporting' => E_ALL,

    'database' => [
        // Sets XOOPS_DB_LEGACY_LOG, which makes the database layer report legacy call
        // patterns (deprecated Criteria forms, queryF() for writes, and similar). Useful
        // when modernising a module, noisy otherwise — it is off in the production
        // mainfile.php default for exactly that reason.
        'legacy_log' => false,
    ],

    /*
     * Persistent log file.
     *
     * Records everything the in-page debug output shows, but to a file, so you
     * still have it when a page dies before rendering — which is exactly when
     * you need it most. Notices and warnings are included, not only errors.
     */
    'log' => [
        'enabled' => true,

        // Logging REFUSES to run while xoops_data sits inside the document root, because
        // the log would then be a plain fetchable file on any server where .htaccess is
        // ignored — nginx, IIS, or Apache with AllowOverride None. Documentation is not a
        // control, so this fails closed.
        //
        // The right fix is to move xoops_data outside the web root. If instead you have
        // added a server rule yourself AND verified it by requesting the log URL, set
        // this to true to accept the remaining risk.
        'allow_web_accessible_log' => false,

        // Written to xoops_data/logs/. Rotated files get .1, .2 … suffixes.
        // Must be a plain "*.log" name; anything else falls back to debug.log.
        'file' => 'debug.log',

        // Rotate once the file passes this size, keeping this many older files.
        // Left unbounded, a busy site can produce hundreds of MB in a day.
        'max_size'  => 8388608, // 8 MB
        'max_files' => 5,

        // Drop the middle of any single value longer than this, keeping the head, the
        // tail and a count of what was removed. Aimed at the pathological rather than
        // the merely long: a runaway redirect chain reaches a thousand characters of
        // the same fragment repeated and buries the entry around it, while ordinary
        // SQL, error text and file paths sit well under the limit and pass through
        // untouched. Set to 0 to keep every value whole.
        'max_value' => 512,

        // Which channels to record. 'messages' carries PHP notices, warnings and
        // errors; 'Queries' carries SQL including failures. Blocks and Extra are
        // verbose and off by default.
        'channels' => ['messages', 'Queries', 'Deprecated'],

        // Only record queries that failed. Turn off to log every statement —
        // useful for N+1 hunting, but very large on a busy site.
        'queries_with_errors_only' => true,

        // Append a backtrace to each entry. This is what makes an entry
        // actionable rather than merely informative.
        'backtrace'       => true,
        'backtrace_limit' => 12,
    ],
];
