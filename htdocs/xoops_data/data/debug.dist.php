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
    //
    // MUST be a real boolean, and this applies to every 'enabled' in this file. Anything
    // else -- 1, 'true', 'yes' -- is treated as false and debugging stays OFF, silently.
    // That is deliberate rather than lax: a string is truthy whatever it says, so a
    // lenient test would read 'false' as ON, which is the dangerous direction to be wrong
    // in. It does mean 'enabled' => 1 leaves this file doing nothing at all, and this is
    // the line where that is most likely to be typed.
    'enabled' => true,

    // 'development' | 'staging' | 'production'. Read by xoops_getDebugEnvironment() and
    // published as XOOPS_ENVIRONMENT / XOOPS_ENV, for any component that behaves
    // differently on a developer's machine than on a live site. Anything else falls back
    // to 'production' -- the safe end -- rather than being passed through.
    'environment' => 'development',

    // Sets XOOPS_DEBUG, and PHP's display_errors / error_reporting, as early as
    // the bootstrap allows. E_ALL includes the E_USER_DEPRECATED notices XOOPS
    // raises for legacy module APIs.
    'display_errors'  => true,
    'error_reporting' => E_ALL,

    // Who owns PHP's error and exception handlers.
    //
    // 'auto' -- the default -- means the first error-screen module you installed, which
    // recorded itself in debug-runtime.json at install time. Install one module and it
    // works; install a second and it tells you the seat is taken and leaves it alone.
    // With none installed this is 'core': XoopsLogger keeps both handlers and DebugBar
    // receives everything.
    //
    // Naming a module here instead pins it, and beats whatever was recorded. The token is
    // the provider MODULE'S DIRNAME -- 'xwhoops', and so on -- so this line names the
    // directory to go and look in. Core ships no provider, keeps no list of them, and
    // does not know what any token means; a module may also answer to older spellings of
    // its own choosing.
    //
    //     'error_screen' => 'auto',      first installed provider (default)
    //     'error_screen' => 'core',      never hand the handlers to anything
    //     'error_screen' => 'xwhoops',   pin this one, whatever else is installed
    //
    // A pinned or recorded module that is deactivated does NOT pass the screen to
    // another installed provider: it falls back to core behaviour and reports
    // 'unclaimed'. Ownership only ever changes when somebody asks for it.
    //
    // The error screen is activated by THIS FILE only. Admin -> Preferences -> Debug Mode
    // remains what it has always been -- XoopsLogger rendering its log into the page --
    // and does not hand PHP's handlers to a module: writing this file takes filesystem
    // access, which is the privilege that matches the exposure an error screen risks, and
    // an admin session is not. A provider installed on a site with no debug.php reports
    // 'dormant' rather than failing silently.
    //
    // A provider may read its own settings from this file under its own key -- core
    // passes unknown keys through untouched and never interprets them.
    //
    // Whatever happens, four constants are published on EVERY request, so a site can
    // always find out what is going on without guessing:
    //
    //     XOOPS_ERROR_SCREEN_OWNER     the token that won, or 'core'
    //     XOOPS_ERROR_SCREEN_SOURCE    config | recorded | default -- where it came from
    //     XOOPS_ERROR_SCREEN_STATUS    see below
    //     XOOPS_ERROR_SCREEN_MESSAGE   one sentence explaining that status
    //
    // The statuses core itself can publish:
    //
    //     core        nothing was asked for; XoopsLogger keeps the handlers
    //     dormant     a provider is recorded, but this file is absent or disabled
    //     unclaimed   the owner names a module that did not answer -- deactivated,
    //                 uninstalled, or a token nothing was ever going to answer
    //     error       the provider failed to start; core took the handlers back
    //     suppressed  error_screen_strict is on and this was not a developer request,
    //                 so no provider was offered the seat at all
    //     contested   more than one module registered for the token, so core could not
    //                 tell which one held the handlers and returned them to XoopsLogger
    //
    // A provider reports its own outcome too, and core publishes that verbatim without
    // interpreting it. The shipped vocabulary is active | disabled | missing |
    // incompatible | error.
    'error_screen' => 'auto',

    // Enforce the developer gate instead of advising it. Off by default.
    //
    // Core works out whether diagnostics may be exposed to whoever is making the request
    // -- debugging on, and an authenticated member of the webmaster group -- and passes
    // the answer to the provider, which is expected to refuse when it is false. That is
    // an obligation, not a lock: a provider MAY legitimately render a production-safe
    // page for anonymous visitors, and core cannot tell that apart from a stack trace
    // full of superglobals, so it does not try.
    //
    // Turn this on and core stops dispatching altogether for a non-developer request, so
    // a provider that ignored the flag never gets the chance. The status reads
    // 'suppressed' on those requests, which is deliberately not the same as 'core'.
    //
    // Worth it if you install providers you have not read. Leave it off if you rely on a
    // provider's production-safe error page, because this switches that off too.
    'error_screen_strict' => false,

    'database' => [
        // Sets XOOPS_DB_LEGACY_LOG, which makes the database layer report legacy call
        // patterns (deprecated Criteria forms, queryF() for writes, and similar). Useful
        // when modernising a module, noisy otherwise — it is off in the production
        // mainfile.php default for exactly that reason.
        'legacy_log' => false,
    ],

    // The DebugBar module, as a SECOND activation source alongside the database-backed
    // Admin -> Preferences -> Debug Mode. Either switches the toolbar on; the module's own
    // 'debugbar_enable' preference and the authenticated-administrator requirement still
    // apply, and neither is configurable from here. Useful on a local checkout where you
    // would rather not carry a debug flag in the database.
    //
    // Must be a real boolean. The DebugBar module tests this with a strict comparison,
    // so 'true' as a STRING will NOT switch it on -- and a string is what you get if you
    // copy a value out of an .env file or a form. Core does not normalise this block: it
    // passes the key through untouched, exactly as it does for a provider module's own
    // settings, and the module that owns the setting is the one that validates it.
    'debugbar' => [
        'enabled' => false,
    ],

    /*
     * Persistent log file.
     *
     * Records everything the in-page debug output shows, but to a file, so you
     * still have it when a page dies before rendering — which is exactly when
     * you need it most. Notices and warnings are included, not only errors.
     */
    'core_log' => [
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
