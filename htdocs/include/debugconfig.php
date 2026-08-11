<?php
/**
 * XOOPS debug configuration loader
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category            Debug
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package             core
 * @link                https://xoops.org
 * @since               2.7.3
 * @author              XOOPS Team
 */

if (!defined('XOOPS_ROOT_PATH')) {
    die('Restricted access');
}

if (!function_exists('xoops_setDebugConfigError')) {
    /**
     * Record why debug.php could not be used, for later display.
     *
     * Split into a setter and a getter, both function_exists-guarded like everything else
     * in this file, so the store survives whatever order the bootstrap loads things in.
     * A static inside the getter is the whole implementation: this is per-request state,
     * written at most once, read at most once, and it must not require a class to exist.
     *
     * @param string|null $message the reason, or null to read the current one
     *
     * @return string the recorded reason, '' when there is none
     */
    function xoops_setDebugConfigError($message = null)
    {
        static $stored = '';

        if (null !== $message) {
            $stored = (string) $message;
        }

        return $stored;
    }
}

if (!function_exists('xoops_getDebugConfigError')) {
    /**
     * Why debug.php was ignored this request, or '' when it was not.
     *
     * Consumed by DebugBar's Diagnostics page. Deliberately NOT consumed during
     * bootstrap: see the catch in xoops_getDebugConfig() for why saying this early is
     * worse than saying it late.
     *
     * @return string
     */
    function xoops_getDebugConfigError()
    {
        return xoops_setDebugConfigError();
    }
}

if (!function_exists('xoops_getDebugConfig')) {
    /**
     * Read xoops_data/data/debug.php, once per request.
     *
     * Returns an empty array when the file is absent, unreadable, does not return an
     * array, or has 'enabled' set to false — in every one of those cases XOOPS behaves
     * exactly as it did before this file existed. That is the point: the whole feature
     * is opt-in and its absence is the normal state.
     *
     * Loaded from two places, whichever comes first:
     *  - mainfile.php (new installs), early enough to define XOOPS_DEBUG itself;
     *  - include/common.php, which every install reaches, so a site whose mainfile.php
     *    predates 2.7.3 still gets the logging even though its XOOPS_DEBUG constant was
     *    already fixed by then.
     *
     * @return array the settings, or [] when debugging is not configured
     */
    function xoops_getDebugConfig()
    {
        static $config = null;

        if (null !== $config) {
            return $config;
        }
        $config = [];

        if (!defined('XOOPS_VAR_PATH')) {
            return $config;
        }

        $file = XOOPS_VAR_PATH . '/data/debug.php';
        if (!is_file($file) || !is_readable($file)) {
            return $config;
        }

        // A hand-edited file WILL eventually contain a syntax error or throw. Without this
        // boundary that becomes an uncaught ParseError during bootstrap -- a debugging aid
        // taking the whole site down. Failing closed, to production behaviour, is the only
        // acceptable outcome.
        try {
            $loaded = include $file;
        } catch (\Throwable $e) {
            // RECORDED, not announced. This runs during bootstrap, before this very
            // function has told PHP what to do with errors, so a trigger_error() here is
            // emitted under php.ini's rules -- on a host with display_errors on, that is
            // a file path printed to whoever happened to load the page. Silence is not
            // the answer either: a developer whose debug.php has a typo would get no
            // signal at all and conclude the feature is broken.
            //
            // So the reason is kept and published through xoops_getDebugConfigError(),
            // which DebugBar's Diagnostics page reads once the request is known to belong
            // to an authenticated administrator. The information survives; only the
            // moment of delivery changes.
            xoops_setDebugConfigError(
                'xoops_data/data/debug.php could not be loaded (' . get_class($e) . ': '
                . $e->getMessage() . '); continuing with debugging disabled.'
            );

            return $config;
        }

        if (!is_array($loaded)) {
            xoops_setDebugConfigError(
                'xoops_data/data/debug.php did not return an array; continuing with debugging disabled.'
            );

            return $config;
        }

        // 'enabled' must be a real boolean. A string from an environment-backed config is
        // truthy whatever it happens to say, so 'false' would switch debugging ON.
        if (true !== ($loaded['enabled'] ?? false)) {
            return $config;
        }

        // Normalise the nested shapes now, so nothing downstream has to defend itself
        // against an object or a string where an array belongs.
        $loaded['database'] = isset($loaded['database']) && is_array($loaded['database']) ? $loaded['database'] : [];

        // Any OTHER key is passed through untouched -- with no exceptions, which is a
        // stronger statement than this file used to be able to make. debug.php is a
        // per-install file, so a site is free to keep a module's settings in it, but core
        // does not know what those keys mean and must not pretend to. Every module
        // normalises its own block, in its own repository, on the same terms as every
        // other consumer -- including the modules the XOOPS project ships itself, which
        // get no shortcut here for being ours.

        // The core file log. Named 'core_log' from 2.7.3 on, matching the "core" source
        // the DebugBar module lists on its Logs page and keeping it clearly distinct from
        // database.legacy_log, which is a different switch entirely. The pre-2.7.3 spelling
        // 'log' is still accepted so an existing debug.php keeps working; both keys are
        // published below and point at the SAME normalised array, so a consumer written
        // against either name reads identical settings.
        $coreLog = null;
        if (isset($loaded['core_log']) && is_array($loaded['core_log'])) {
            $coreLog = $loaded['core_log'];
        } elseif (isset($loaded['log']) && is_array($loaded['log'])) {
            $coreLog = $loaded['log'];
        }
        $loaded['core_log'] = is_array($coreLog) ? $coreLog : [];

        // The nested switches need the same strict treatment as the top-level one.
        // Downstream reads them with !empty(), and the string 'false' is not empty, so
        // 'enabled' => 'false' switched logging ON -- precisely the trap closed above,
        // one level further down.
        $loaded['core_log']['enabled']    = true === ($loaded['core_log']['enabled'] ?? false);
        $loaded['database']['legacy_log'] = true === ($loaded['database']['legacy_log'] ?? false);

        // Exactly one component may own PHP's error and exception handlers. The value is
        // a free-form token naming that component, resolved by xoops_getErrorScreenOwner()
        // and answered by whichever module claims it -- deliberately NOT an allowlist,
        // because a closed list would mean no error screen could exist without a core
        // release naming it first. Shape is validated, meaning is not: anything that is
        // not a non-empty string falls back to 'core' rather than guessing, and a token
        // no module answers for is reported as 'unclaimed' by xoops_activateErrorScreen()
        // rather than silently handing the handlers to whichever module loads last.
        // Absent means 'auto', NOT 'core': a site that installed exactly one error-screen
        // module and never opened this file should get that module. An explicit 'core'
        // still pins core, which is a different statement from saying nothing at all.
        $screen = $loaded['error_screen'] ?? 'auto';
        $loaded['error_screen'] = (is_string($screen) && '' !== trim($screen))
            ? strtolower(trim($screen))
            : 'auto';

        // Opt-in hard gate. Off by default, and that default is the whole design: core
        // passes 'developer_request' to the provider and lets it decide, because a
        // provider may legitimately render a PRODUCTION-SAFE page for anonymous visitors
        // and core cannot tell that apart from a stack trace full of superglobals.
        //
        // A site that does not want to extend that trust to every provider it might ever
        // install can say so here, and then core does not dispatch at all for a
        // non-developer request. Same strict-boolean treatment as every other switch.
        $loaded['error_screen_strict'] = true === ($loaded['error_screen_strict'] ?? false);

        // Alias, kept in step with core_log so pre-2.7.3 consumers see the same array.
        //
        // @deprecated 2.7.3 Read 'core_log'. The 'log' spelling is accepted as INPUT and
        // published as output through the 2.7.x line, and comes out in 2.8.0 -- named now,
        // while it costs one line, because an alias with no expiry is a permanent second
        // vocabulary that nobody ever feels entitled to remove.
        $loaded['log'] = $loaded['core_log'];

        $config = $loaded;

        return $config;
    }
}

if (!function_exists('xoops_getDebugEnvironment')) {
    /**
     * The declared deployment environment: development, staging or production.
     *
     * Anything unrecognised, and anything at all while debugging is off, resolves to
     * 'production'. Failing towards the safe value matters because callers use this to
     * decide whether to expose diagnostics.
     *
     * @return string
     */
    function xoops_getDebugEnvironment()
    {
        $config = xoops_getDebugConfig();
        if ([] === $config) {
            return 'production';
        }

        $environment = $config['environment'] ?? 'development';
        if (!is_string($environment)) {
            return 'production';
        }

        return in_array($environment, ['development', 'staging', 'production'], true)
            ? $environment
            : 'production';
    }
}

if (!function_exists('xoops_getErrorScreenOwner')) {
    /**
     * Which component owns PHP's error and exception handlers this request.
     *
     * PHP has exactly one error handler and one exception handler, and set_error_handler()
     * gives them to whoever calls last. XOOPS has more than one contender: XoopsLogger,
     * registered unconditionally in its getInstance() and the source that feeds the
     * DebugBar module, plus whatever error-screen modules a site has installed. Before
     * this key existed the winner was decided by module weight and mid -- reinstalling an
     * unrelated module could silently disable your error screen, or silently empty
     * DebugBar's Messages tab, with no diagnostic anywhere pointing at the cause.
     *
     * Resolved in three steps, first answer wins:
     *
     *  1. 'error_screen' in debug.php, when it names something other than 'auto'. An
     *     explicit choice is never overridden by anything below.
     *  2. the owner recorded in debug-runtime.json, which the first provider module to be
     *     installed writes for itself. This is what makes installing one module enough.
     *  3. 'core' -- XoopsLogger keeps both handlers and DebugBar receives everything.
     *
     * The token is by convention the provider MODULE'S DIRNAME, so 'error_screen' names
     * the directory to go and look in and no separate vocabulary has to be documented or
     * kept unique. Core neither validates it against a list nor knows what any particular
     * token means; a provider may additionally answer to legacy names of its own choosing,
     * which is where a spelling like 'whoops' or 'tracy' is honoured -- in the module that
     * owns the name, not here.
     *
     * Always 'core' when debugging is not enabled at all.
     *
     * @return string the owner token, 'core' when none applies
     */
    function xoops_getErrorScreenOwner()
    {
        $config = xoops_getDebugConfig();
        if ([] === $config) {
            return 'core';
        }

        $declared = (string) ($config['error_screen'] ?? 'auto');
        if ('' !== $declared && 'auto' !== $declared) {
            return $declared;
        }

        $recorded = xoops_getRecordedErrorScreenOwner();

        return '' !== $recorded ? $recorded : 'core';
    }
}

if (!function_exists('xoops_getErrorScreenOwnerSource')) {
    /**
     * WHERE the owner came from: config | recorded | default.
     *
     * Worth publishing because the recorded owner lives in a file nobody reads by hand.
     * "Why is this module the owner?" must be answerable without knowing that
     * debug-runtime.json exists, so an admin screen can say "recorded at install" rather
     * than leaving a value with no visible cause.
     *
     * @return string config|recorded|default
     */
    function xoops_getErrorScreenOwnerSource()
    {
        $config = xoops_getDebugConfig();
        if ([] === $config) {
            return 'default';
        }

        $declared = (string) ($config['error_screen'] ?? 'auto');
        if ('' !== $declared && 'auto' !== $declared) {
            return 'config';
        }

        return '' !== xoops_getRecordedErrorScreenOwner() ? 'recorded' : 'default';
    }
}

if (!function_exists('xoops_getRecordedErrorScreenOwner')) {
    /**
     * The owner recorded in debug-runtime.json, or '' when none is.
     *
     * @return string
     */
    function xoops_getRecordedErrorScreenOwner()
    {
        $override = xoops_readDebugRuntimeOverride();
        $recorded = $override['error_screen_owner'] ?? '';

        return is_string($recorded) ? strtolower(trim($recorded)) : '';
    }
}

if (!function_exists('xoops_recordErrorScreenOwner')) {
    /**
     * Claim, or release, the recorded ownership of the error screen.
     *
     * Called by a provider module's install and uninstall routines, not at request time.
     *
     * The rule is first-installed-wins, enforced HERE against ordinary claims rather than
     * trusted to each provider: a second provider installing must not take a screen the
     * first one is already showing, however carelessly it was written.
     *
     * Enforced against ORDINARY claims -- the precise wording matters. $force skips the
     * precondition, and is how a deliberate handover happens once the holder is gone or
     * inactive. Module code is trusted code and could take the seat regardless, so this is
     * a correctness boundary rather than a security one: it stops an accident, not an
     * attacker. An earlier version of this docblock said "cannot take a seat another
     * module is sitting in even if it asks", which promised more than the parameter list.
     *
     * This function only CLAIMS. Release through xoops_releaseErrorScreenOwner($dirname),
     * which checks that you are the holder first -- there is no caller identity here, so
     * an anonymous "release" could only ever mean "clear whatever anybody else holds",
     * and uninstalling one module must not free another module's seat.
     *
     * A released screen does not promote whatever else happens to be installed. Ownership
     * changing without anybody asking for it is the surprise this whole mechanism exists
     * to prevent, so the site falls back to 'core'.
     *
     * Handing the seat to a provider that is ALREADY installed therefore takes a
     * deliberate act, and there are three: pin 'error_screen' in debug.php; uninstall the
     * holder and reinstall the one you want; or deactivate the holder and update the one
     * you want. The third works through $force -- an update hook establishes that the
     * holder is gone or inactive, which is a question about the module table and so cannot
     * be answered from this file, and then claims with $force = true. See xtracy's
     * xoops_module_update_*() for the reference implementation.
     *
     * An update hook that calls this WITHOUT $force is declined while another token is
     * recorded, which is correct: an ordinary update must not quietly take a seat from a
     * provider that is still running.
     *
     * @param string $token the claiming module's dirname; '' is refused
     * @param bool   $force take ownership even when another provider holds it. The hook a
     *                      deliberate handover uses, after verifying the holder is gone or
     *                      inactive -- never on an ordinary install.
     * @return bool true when the record now names $token
     */
    function xoops_recordErrorScreenOwner($token, $force = false)
    {
        // Lower-cased to match how a token written in debug.php is normalised. Without
        // this a mixed-case dirname works when recorded and goes permanently 'unclaimed'
        // when pinned, since every comparison is strict.
        $token = is_string($token) ? strtolower(trim($token)) : '';
        $held  = xoops_getRecordedErrorScreenOwner();

        if ('' === $token) {
            // Refused, and reported as refused. Returning true here would let a caller
            // that meant to release believe it had.
            return false;
        }

        // Fail fast on the obvious refusal, so a caller that is plainly not entitled to
        // the seat does not touch the file at all. This is a courtesy, NOT the guard --
        // $held was read outside the lock and may already be stale by the time we act on
        // it. The guard that counts is the $expect below, evaluated inside the lock.
        if ('' !== $held && $held !== $token && !$force) {
            return false;
        }

        // Compare-and-set: write only if the seat is still free or already ours.
        //
        // Without this, two modules installing at the same moment can both read an empty
        // owner, both pass the check above, and both write -- so "the first provider
        // installed wins" quietly becomes "whichever finished last wins". A narrow race,
        // but the rule it breaks is the one this whole mechanism exists to state.
        //
        // $force skips the precondition, which is what makes it a deliberate handover
        // rather than a louder claim: a provider's update hook uses it after establishing
        // that the holder is gone or inactive, a question core's bootstrap cannot answer.
        $expect = $force ? null : ['error_screen_owner' => ['', $token]];

        return xoops_writeDebugRuntimeOverride(['error_screen_owner' => $token], $expect);
    }
}

if (!function_exists('xoops_releaseErrorScreenOwner')) {
    /**
     * Release the recorded ownership, but only if $token currently holds it.
     *
     * @param string $token the releasing module's dirname
     * @return bool true when the record no longer names $token
     */
    function xoops_releaseErrorScreenOwner($token)
    {
        $token = is_string($token) ? strtolower(trim($token)) : '';
        if ('' === $token) {
            return true;
        }
        if (xoops_getRecordedErrorScreenOwner() !== $token) {
            // Not ours to release, and that is a success: the caller wanted the record to
            // stop naming it, and it does not.
            return true;
        }

        // ...but re-checked inside the lock, because between the read above and the write
        // below a forced transfer can have handed the seat to somebody else. An
        // unconditional delete here would erase the new owner's record on the way out.
        $done = xoops_writeDebugRuntimeOverride(
            ['error_screen_owner' => null],
            ['error_screen_owner' => [$token]]
        );

        // A refusal means the seat changed hands while we were uninstalling. The record no
        // longer names us either way, which is what the caller asked for.
        return $done || xoops_getRecordedErrorScreenOwner() !== $token;
    }
}


if (!function_exists('xoops_isDebugEnabled')) {
    /**
     * Is debugging switched on for this site, by any mechanism?
     *
     * THIS IS NOT THE SAME QUESTION AS xoops_isDeveloperRequest(), and the difference is
     * the whole reason both exist:
     *
     *  - this one asks about the SITE. It has no opinion about who is making the request,
     *    and it is the right question for anything written to a log, emitted as a
     *    deprecation notice, or computed for later reading. A cron run and a CLI script
     *    have no user at all, and refusing them diagnostics because nobody is logged in
     *    would be nonsense;
     *  - xoops_isDeveloperRequest() asks about the REQUESTER, and gates what is shown on
     *    screen to a person. It is this predicate AND webmaster-group membership.
     *
     * Conflating them is how this went wrong before: three components each answered "may I
     * show diagnostics" differently, and the loosest one set the site's real exposure.
     * Splitting the site question out means the strict answer is built from the loose one
     * rather than beside it, so they cannot drift.
     *
     * Three terms, monotone. Each may widen, none may narrow -- see the note on
     * xoops_isDeveloperRequest() for why the constant is read ALONGSIDE the config and not
     * replaced by it, and why the Debug Mode term is any non-zero value.
     *
     * Safe at any point in the bootstrap, and side-effect free: xoops_getDebugConfig()
     * caches statically and reads one file at most once per request.
     *
     * @return bool
     */
    function xoops_isDebugEnabled()
    {
        $debugMode = (int) ($GLOBALS['xoopsConfig']['debug_mode'] ?? 0);

        return (defined('XOOPS_DEBUG') && XOOPS_DEBUG)
            || [] !== xoops_getDebugConfig()
            || 0 !== $debugMode;
    }
}

if (!function_exists('xoops_isDeveloperRequest')) {
    /**
     * May diagnostics be exposed to whoever is making THIS request?
     *
     * One answer to a question three components were each answering differently:
     * DebugBar checked $xoopsUserIsAdmin plus the debug_mode preference, xwhoops checked
     * the XOOPS_DEBUG constant plus isAdmin() plus its own group permission, and Tracy
     * checked XOOPS_DEBUG and nothing else -- so the loosest of the three silently set the
     * real exposure for the whole site. Tracy's bar and BlueScreen reveal source, paths
     * and request data, and were rendering for anonymous visitors as a result.
     *
     * Two conditions, both required:
     *  1. debugging is switched on by ANY of three mechanisms -- the XOOPS_DEBUG constant,
     *     the presence of a usable xoops_data/data/debug.php, or Admin -> Preferences ->
     *     Debug Mode set to anything but Off. They are documented as independent and any
     *     one alone is a legitimate way to work. That equality is about EXPOSURE, which is
     *     what this function gates.
     *     It does not extend to ACTIVATION of the error screen: xoops_activateErrorScreen()
     *     honours the file configuration only, deliberately, and reports 'dormant' on a
     *     site running Debug Mode alone;
     *  2. the request belongs to an authenticated member of the webmaster group.
     *
     * The config is read DIRECTLY, in addition to the constant rather than instead of it,
     * and that is the whole reason this is three terms and not two. On a current install
     * XOOPS_DEBUG is defined as `[] !== $xoopsDebugConfig`, so terms one and two are the
     * same predicate and the behaviour is unchanged. They come apart only on a site
     * upgraded from 2.7.1, whose mainfile.php survives the upgrade untouched and has
     * therefore already frozen XOOPS_DEBUG at its hard-coded false before any 2.7.3 code
     * runs -- no code here can undo that, so the documented promise in condition 1 was
     * simply false on every upgraded site until the config term was added.
     *
     * Reading the config INSTEAD of the constant would have been shorter and is wrong: it
     * silently drops the developer who hand-edited XOOPS_DEBUG to true in mainfile.php,
     * which is exactly what dev sites did before debug.php existed, and who has no
     * debug.php at all. Each term may only ever widen; none may narrow.
     *
     * Group membership is the test rather than XoopsUser::isAdmin(), which resolves
     * against $GLOBALS['xoopsModule'] when called with no argument and therefore answers
     * "admin of whatever module we happen to be inside" -- context-dependence being the
     * exact property this function exists to remove.
     *
     * Safe to call at any point in the bootstrap: before the auth stage has run there is
     * no user, and the answer is false.
     *
     * @param string|null $dirname optionally also require admin rights on this module
     * @return bool
     */
    function xoops_isDeveloperRequest($dirname = null)
    {
        // Condition 1, delegated. It is the SITE question and it has its own function --
        // criteria.php and anything else writing to a log needs the same answer without
        // the webmaster test, and a copy of the predicate there would be a copy that
        // drifts. Building the strict answer out of the loose one is what keeps them
        // in step.
        if (!xoops_isDebugEnabled()) {
            return false;
        }

        $user = $GLOBALS['xoopsUser'] ?? null;
        if (!is_object($user) || !method_exists($user, 'getGroups')) {
            return false;
        }

        $adminGroup = defined('XOOPS_GROUP_ADMIN') ? (int) constant('XOOPS_GROUP_ADMIN') : 1;
        $groups     = $user->getGroups();
        $groups     = is_array($groups) ? array_map('intval', $groups) : [];
        if (!in_array($adminGroup, $groups, true)) {
            return false;
        }

        if (null === $dirname || '' === $dirname) {
            return true;
        }

        // Module-scoped rights. Resolved through xoops_getHandler() rather than
        // \XoopsModule::getByDirname(), because 2.7.3 autoloads no kernel class and the
        // static form only resolves when some earlier caller happened to load
        // kernel/module.php first.
        if (!function_exists('xoops_getHandler') || !method_exists($user, 'isAdmin')) {
            return false;
        }
        $handler = xoops_getHandler('module');
        if (!is_object($handler) || !method_exists($handler, 'getByDirname')) {
            return false;
        }
        $module = $handler->getByDirname($dirname);

        return is_object($module) && $user->isAdmin((int) $module->getVar('mid'));
    }
}

if (!function_exists('xoops_applyDebugConfig')) {
    /**
     * Apply the PHP-level settings: display_errors and error_reporting.
     *
     * Kept separate from xoops_getDebugConfig() so the configuration can be inspected
     * without side effects — the upgrade tooling and tests need to do exactly that.
     *
     * Also publishes the constants that describe the environment. They are defined here
     * rather than in mainfile.php because mainfile.php survives upgrades untouched, so
     * anything placed there never reaches an existing site. Every define() is guarded:
     * this function is called more than once per request by design, and mainfile.php may
     * legitimately have set some of them already.
     *
     * XOOPS_DEBUG is NOT defined here. mainfile.php defines it unguarded straight after
     * calling this function, so defining it here too would mean a second define() on an
     * already-defined constant: PHP 8 emits `Warning: Constant XOOPS_DEBUG already
     * defined`, keeps the FIRST value and carries on. Not fatal -- an earlier version of
     * this comment said it was -- but a warning on every request is no more acceptable
     * than a fatal, and the value that survives is the one from whichever file ran first,
     * which is not a thing to leave to ordering. Hence: defined in exactly one place.
     *
     * The cost of that is paid in xoops_isDeveloperRequest(), which cannot trust the
     * constant on an upgraded site and reads xoops_getDebugConfig() alongside it.
     *
     * @return bool true when debugging is enabled and was applied
     */
    function xoops_applyDebugConfig()
    {
        $config = xoops_getDebugConfig();

        // Defined on EVERY request, enabled or not. A constant that exists only in
        // development is worse than no constant: consuming code then has to branch on
        // defined() and pick a fallback, and the fallbacks drift apart.
        defined('XOOPS_ENVIRONMENT') || define('XOOPS_ENVIRONMENT', xoops_getDebugEnvironment());
        defined('XOOPS_ENV') || define('XOOPS_ENV', XOOPS_ENVIRONMENT);

        if ([] === $config) {
            return false;
        }

        // Strict booleans only: a string such as 'false' is truthy and would turn
        // display_errors ON against the stated intent.
        // Only when the key is present, unlike error_reporting which has a documented
        // default. Deliberate: the absence of a display_errors setting means "leave
        // php.ini alone", and php.ini's answer on a server is more likely to be right for
        // that server than a default invented here. The asymmetry is worth a line because
        // it reads like an oversight.
        if (array_key_exists('display_errors', $config)) {
            ini_set('display_errors', true === $config['display_errors'] ? '1' : '0');
        }

        // error_reporting defaults to E_ALL when debugging is enabled but the key is
        // absent or not an integer. Leaving php.ini's value in place would silently
        // produce an enabled logger that records nothing -- if error_reporting is 0,
        // XoopsLogger::handleError() discards every error before it reaches a logger.
        // A string like 'E_ALL' casts to 0, which is exactly that trap, so only a real
        // integer is honoured.
        $reporting = $config['error_reporting'] ?? null;
        error_reporting(is_int($reporting) ? $reporting : E_ALL);

        return true;
    }
}

if (!function_exists('xoops_findVendorDirectory')) {
    /**
     * Locate the Composer vendor directory for optional developer libraries.
     *
     * Installations disagree about where this lives — xoops_lib on a modern install,
     * class/libraries on an older one, and XOOPS_PATH may or may not be defined by a
     * mainfile.php written years ago. Each candidate is tested rather than assumed.
     *
     * @return string absolute path without trailing slash, or '' when none exists
     */
    function xoops_findVendorDirectory()
    {
        static $found = null;

        if (null !== $found) {
            return $found;
        }
        $found = '';

        $candidates = [];
        // XOOPS_PATH and XOOPS_TRUST_PATH only: both are defined by mainfile.php. An
        // earlier revision also probed XOOPS_LIB_PATH, which no core release defines --
        // harmless behind defined(), but it advertised an API that does not exist.
        foreach (['XOOPS_PATH', 'XOOPS_TRUST_PATH'] as $constantName) {
            if (defined($constantName)) {
                $candidates[] = rtrim((string) constant($constantName), '/\\') . '/vendor';
            }
        }
        // Guarded like the two above: XOOPS_ROOT_PATH is defined by mainfile.php on any
        // booted request, but this helper is also reachable from a provider module's preload
        // and from tests, where a bare reference to an undefined constant is a fatal in
        // PHP 8 -- not the empty-string "none found" this function promises.
        if (defined('XOOPS_ROOT_PATH')) {
            $candidates[] = XOOPS_ROOT_PATH . '/xoops_lib/vendor';
            $candidates[] = XOOPS_ROOT_PATH . '/class/libraries/vendor';
        }

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                $found = $candidate;

                break;
            }
        }

        return $found;
    }
}

if (!function_exists('xoops_getErrorScreenStatus')) {
    /**
     * What became of the error screen this request.
     *
     * Reads the published constants rather than keeping a second copy of the answer.
     * There is deliberately no store behind this: a function and a constant that both
     * describe the same event are two things that can disagree, and they did -- a
     * provider that reported success and then threw left the constant saying 'error'
     * while the function still said 'active'. One source, no drift.
     *
     * Meaningful only after xoops_activateErrorScreen() has run; before that, and on a
     * core too old to have the seam, every value is an empty string.
     *
     * The message is PLAIN TEXT and interpolates the owner token verbatim. Anything
     * rendering it into HTML must escape it. The token is a lowercased dirname or a value
     * an admin wrote into debug.php -- both privileged -- so this is a note for consumers
     * rather than a hole, but it is not pre-sanitised and nobody should assume it is.
     *
     * @return array ['owner' => string, 'source' => string, 'status' => string, 'message' => string]
     */
    function xoops_getErrorScreenStatus()
    {
        return [
            'owner'   => defined('XOOPS_ERROR_SCREEN_OWNER') ? (string) constant('XOOPS_ERROR_SCREEN_OWNER') : '',
            'source'  => defined('XOOPS_ERROR_SCREEN_SOURCE') ? (string) constant('XOOPS_ERROR_SCREEN_SOURCE') : '',
            'status'  => defined('XOOPS_ERROR_SCREEN_STATUS') ? (string) constant('XOOPS_ERROR_SCREEN_STATUS') : '',
            'message' => defined('XOOPS_ERROR_SCREEN_MESSAGE') ? (string) constant('XOOPS_ERROR_SCREEN_MESSAGE') : '',
        ];
    }
}


if (!function_exists('xoops_readDebugRuntimeOverride')) {
    /**
     * Read xoops_data/data/debug-runtime.json.
     *
     * Small, machine-written companion to debug.php holding only the switches an admin
     * screen may flip at runtime. Separate on purpose: the file an admin button writes is
     * JSON, so a compromised or buggy toggle cannot introduce executable code into the
     * bootstrap.
     *
     * @param bool $refresh re-read from disk instead of returning the cached copy
     * @return array decoded settings, or [] when absent, unreadable or malformed
     */
    function xoops_readDebugRuntimeOverride($refresh = false)
    {
        static $override = null;

        if (null !== $override && !$refresh) {
            return $override;
        }
        $override = [];

        if (!defined('XOOPS_VAR_PATH')) {
            return $override;
        }

        $file = XOOPS_VAR_PATH . '/data/debug-runtime.json';
        if (!is_file($file) || !is_readable($file)) {
            return $override;
        }

        $json = file_get_contents($file);
        if (!is_string($json) || '' === trim($json)) {
            return $override;
        }

        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            $override = $decoded;
        }

        return $override;
    }
}

if (!function_exists('xoops_writeDebugRuntimeOverride')) {
    /**
     * Merge changes into xoops_data/data/debug-runtime.json.
     *
     * The machine-written companion to debug.php. JSON on purpose: the file an admin
     * button or an install routine edits must not be able to introduce executable code
     * into the bootstrap, however it is written and whatever ends up in it.
     *
     * Merges rather than replaces, and a null value removes its key, so two independent
     * writers -- a module install recording ownership, an admin toggle switching a bar
     * off -- do not erase each other's settings.
     *
     * The whole read-modify-write runs under an exclusive lock on a sidecar file. The
     * merge alone is not enough: two writers can both read the same JSON and then write
     * back, and the second silently drops the first one's key. Atomic rename prevents a
     * reader seeing a torn file; it does nothing about a lost update, and an earlier
     * revision of this docblock claimed otherwise.
     *
     * The sidecar rather than the file itself, because the write is a rename: locking a
     * path that is about to be replaced protects nothing.
     *
     * Written to a temporary file and renamed, so a reader never sees a half-written
     * file: on every platform this matters on, rename over an existing path is atomic.
     *
     * $expect makes the whole thing a compare-and-set. Pass ['key' => [allowed, values]]
     * and the write only happens if the file's current value for that key is one of them
     * -- checked INSIDE the lock, which is the only place a check can mean anything. A
     * caller that reads first and then writes has already lost: between its read and its
     * write, another request can have changed the value it was relying on.
     *
     * @param array      $changes keys to set, or set to null to remove
     * @param array|null $expect  key => list of acceptable current values, or null for
     *                            an unconditional write. A key absent from the file
     *                            reads as ''.
     * @return bool true when the file now reflects $changes
     */
    function xoops_writeDebugRuntimeOverride($changes, $expect = null)
    {
        if (!is_array($changes) || [] === $changes || !defined('XOOPS_VAR_PATH')) {
            return false;
        }

        $directory = XOOPS_VAR_PATH . '/data';
        if (!is_dir($directory) || !is_writable($directory)) {
            return false;
        }

        $lockFile   = $directory . '/debug-runtime.lock';
        $lockHandle = @fopen($lockFile, 'c');
        if (false === $lockHandle) {
            return false;
        }
        if (!flock($lockHandle, LOCK_EX)) {
            fclose($lockHandle);

            return false;
        }

        $current = xoops_readDebugRuntimeOverride(true);

        // The precondition, re-read under the lock. Anything the caller checked before
        // calling is stale by definition, however carefully it looked.
        if (is_array($expect)) {
            foreach ($expect as $key => $acceptable) {
                $held = $current[$key] ?? '';
                $held = is_string($held) ? $held : '';
                if (!in_array($held, (array) $acceptable, true)) {
                    flock($lockHandle, LOCK_UN);
                    fclose($lockHandle);

                    return false;
                }
            }
        }

        foreach ($changes as $key => $value) {
            if (null === $value) {
                unset($current[$key]);
                continue;
            }
            $current[$key] = $value;
        }

        // The empty document is the only one that needs forcing: a file emptied of every
        // key would otherwise encode as [], because that is what an empty PHP array is.
        // Both decode to an empty array here, but a consumer in another language reading
        // [] where it expected an object has been handed a different type by an
        // implementation detail.
        //
        // JSON_FORCE_OBJECT would do it -- and would also quietly turn any nested LIST a
        // future writer stores into {"0":...}. Nothing stores one today, which is exactly
        // why it would be found late and by somebody who did not put it there.
        $json = [] === $current
            ? '{}'
            : json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $file      = $directory . '/debug-runtime.json';
        $temporary = $file . '.' . getmypid() . '.tmp';
        $written   = false;

        if (is_string($json) && false !== file_put_contents($temporary, $json . "\n")) {
            $written = rename($temporary, $file);
            if (!$written) {
                @unlink($temporary);
            }
        }

        // The static cache in the reader is now stale, and callers in this same request
        // -- an install routine reading back what it just claimed -- would otherwise see
        // the previous contents. Refreshed inside the lock so the next reader in this
        // process cannot observe the pre-write state.
        xoops_readDebugRuntimeOverride(true);

        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);

        return $written;
    }
}

if (!function_exists('xoops_activateErrorScreen')) {
    /**
     * Hand PHP's error and exception handlers to whichever component the site declared.
     *
     * CALL THIS LAST, at the very end of include/common.php. An error screen takes over
     * set_error_handler() and set_exception_handler(), and so did XoopsLogger earlier in
     * the boot; whichever runs last owns them. Firing this any earlier produces a screen
     * that is quietly displaced before the first error ever occurs -- which looks like the
     * screen being broken rather than being overwritten.
     *
     * Core itself registers nothing here and knows no provider by name. It reads the
     * declared owner, triggers core.debug.errorscreen, and records whatever the provider
     * reports back. A provider is an ordinary module preload -- the same mechanism the
     * xwhoops module has always used -- so a library falling out of use, or a new one
     * arriving, is a module being uninstalled or installed and never a change to core.
     *
     * The event receives three arguments:
     *
     *   'owner'              the token being offered. Answer only to your own.
     *   'developer_request'  whether xoops_isDeveloperRequest() is true for this request.
     *                        ADVISORY, and passed rather than enforced because a provider
     *                        may legitimately register a production-safe error page for
     *                        anonymous visitors. A provider that exposes source, file
     *                        paths, request data or superglobals MUST refuse when this is
     *                        false -- the one obligation core cannot check for you.
     *                        A site that would rather not take that on trust can set
     *                        'error_screen_strict' => true in debug.php, and then core
     *                        does not dispatch at all when this would be false. Off by
     *                        default, because enforcing it for everybody would forbid a
     *                        provider from rendering a production-safe page.
     *   'report'             callable(string $status, string $message = ''): bool
     *                        Call it exactly once, whatever happened -- INCLUDING when you
     *                        decide not to register. "I am installed and chose to stay
     *                        dormant, because X" is the whole difference between a
     *                        diagnosable setup and a silent one. First caller wins; later
     *                        calls return false. Any short lower-case status is valid and
     *                        core does not interpret it; the shipped vocabulary is
     *                        active | disabled | missing | incompatible | error.
     *                        ONE exception: reporting 'error' tells core the activation
     *                        failed, and core restores the error and exception handlers it
     *                        held before the dispatch. Report 'error' only when you mean
     *                        it, and register your handlers LAST -- after everything that
     *                        can throw -- because a shutdown function you have already
     *                        registered cannot be taken back by core or by anyone.
     *                        Register BEFORE you report, never after: core compares who
     *                        holds the handlers at your report against who holds them at
     *                        the end of the dispatch, to detect a second module
     *                        registering on top of you. Reporting first makes your own
     *                        registration look like exactly that.
     *
     * Passing the reporter in the event rather than exposing a global setter keeps the
     * outcome in this function's own scope: the catch below can finalise a status without
     * competing with a store a half-failed provider already wrote to.
     *
     * Always defines, whatever the outcome:
     *
     *   XOOPS_ERROR_SCREEN_OWNER    the owner token, 'core' when none applies
     *   XOOPS_ERROR_SCREEN_SOURCE   config | recorded | default -- where that came from
     *   XOOPS_ERROR_SCREEN_STATUS   core | dormant | active | disabled | unclaimed |
     *                               error | contested | suppressed, or whatever else the
     *                               provider reported. 'suppressed' means the site set
     *                               error_screen_strict and this was not a developer
     *                               request, so nothing was dispatched at all.
     *                               'contested' is core's own: more than one
     *                               listener registered for one token, so the handlers
     *                               went back to XoopsLogger rather than staying with
     *                               whichever module happened to run last
     *   XOOPS_ERROR_SCREEN_MESSAGE  a short human explanation of that status
     *
     * Defining them unconditionally is what lets an admin screen tell "no provider is
     * installed" apart from "a provider is installed and currently off" -- an absent
     * constant means the core is older than this seam, which is a third, different
     * statement.
     *
     * @return string the resulting status
     */
    function xoops_activateErrorScreen()
    {
        if (defined('XOOPS_ERROR_SCREEN_STATUS')) {
            return (string) constant('XOOPS_ERROR_SCREEN_STATUS');
        }

        $owner = xoops_getErrorScreenOwner();

        if ('core' === $owner) {
            $status  = 'core';
            $message = 'XoopsLogger keeps the error and exception handlers.';

            // Recorded, but the file config that would activate it is not there.
            //
            // The error screen is a debug.php feature, deliberately and not merely as a
            // side effect of this early return. Writing xoops_data/data/debug.php takes
            // filesystem access -- the same privilege as the exposure an error screen
            // risks -- while Admin -> Preferences -> Debug Mode is reachable from a web
            // form by anyone holding an admin session. Handing PHP's handlers to
            // third-party code asks for the stronger credential. Debug Mode has meant
            // "XoopsLogger renders its log into the page" for twenty years, and quietly
            // widening it to "a module takes over set_error_handler()" would change the
            // meaning of an existing control on every site with a provider installed.
            // The pin and the opt-out live in debug.php too, so a Debug Mode site would
            // have no vocabulary to say "stop" without creating the file anyway.
            //
            // Said out loud, because a provider that is installed, recorded, and silently
            // never running is exactly the invisible state this seam exists to end.
            $recorded = xoops_getRecordedErrorScreenOwner();
            if ('' !== $recorded && [] === xoops_getDebugConfig()) {
                $status  = 'dormant';
                $message = 'Provider "' . $recorded . '" is recorded as the error-screen owner, but'
                    . ' xoops_data/data/debug.php is absent or disabled. The error screen activates'
                    . ' only under the file-based debug configuration -- Admin -> Preferences ->'
                    . ' Debug Mode does not activate it.';
            }
        } elseif (true === (xoops_getDebugConfig()['error_screen_strict'] ?? false)
                  && !(function_exists('xoops_isDeveloperRequest') && xoops_isDeveloperRequest())) {
            // The site asked core to enforce the gate rather than advise it, and this
            // request is not a developer's. Nothing is dispatched, so no provider gets the
            // chance to decide for itself.
            //
            // Reported under its own status rather than as 'core': "the screen you
            // configured was suppressed for this request" and "you configured no screen"
            // are different situations, and a provider author staring at a page with no
            // BlueScreen needs to be able to tell which one they are looking at.
            $status  = 'suppressed';
            $message = 'The error screen "' . $owner . '" was not offered this request:'
                . ' error_screen_strict is on and this is not a developer request.';
        } else {
            $outcome = ['status' => '', 'message' => ''];

            // Read the handlers we are about to lend out, without disturbing them.
            //
            // set_*_handler() returns the previous handler and installs the new one, so
            // the restore() immediately after puts the stack back exactly as it was. This
            // is the only way to READ the current handler in PHP; there is no getter.
            //
            // Kept so that a provider which fails AFTER registering can be undone -- see
            // the rollback below the dispatch. Snapshotted here rather than inside the
            // try, because a failure is exactly the case where the try's scope is the
            // wrong place to be keeping the thing that repairs it.
            $priorErrorHandler     = set_error_handler(null);
            restore_error_handler();
            $priorExceptionHandler = set_exception_handler(null);
            restore_exception_handler();

            // First caller wins. Two providers answering one token is a misconfiguration,
            // and the second silently overwriting the first is the load-order roulette the
            // owner token exists to end.
            //
            // Stated exactly, because the honest version is narrower than "exactly one
            // module owns the error screen": the preload dispatcher runs every listener,
            // so core cannot stop a second one registering. What core does is DETECT that
            // it happened -- by the refusal count below, and by comparing who held the
            // handlers when the winning report arrived against who holds them at the end
            // -- publish it as 'contested', and put the handlers back where they were. A
            // registry that invoked exactly one provider would PREVENT it instead; that is
            // the 2.8 direction, not this one.
            $refusedReports          = 0;
            $handlerAtFirstReport    = null;
            $exceptionAtFirstReport  = null;

            $report = static function ($status, $message = '') use (
                &$outcome,
                &$refusedReports,
                &$handlerAtFirstReport,
                &$exceptionAtFirstReport
            ) {
                if ('' !== $outcome['status']) {
                    // Refused -- but remembered. A second module answering one token is
                    // the misconfiguration this whole mechanism exists to surface, and
                    // core knew about it all along and threw the knowledge away.
                    ++$refusedReports;

                    return false;
                }
                $status = is_string($status) ? trim($status) : '';
                if ('' === $status) {
                    return false;
                }
                $outcome = [
                    'status'  => $status,
                    'message' => is_string($message) ? $message : '',
                ];

                // Whoever holds the handlers at the moment the winning report is accepted.
                // If that is not who holds them when the dispatch ends, a later listener
                // registered on top -- the case the published status would otherwise
                // describe wrongly. Assumes a provider registers BEFORE it reports, which
                // the contract above now requires.
                $handlerAtFirstReport = set_error_handler(null);
                restore_error_handler();
                $exceptionAtFirstReport = set_exception_handler(null);
                restore_exception_handler();

                return true;
            };

            $dispatchThrew = false;

            // One boundary around the whole dispatch. An error screen is a debugging
            // convenience; a broken or half-installed provider must not be able to take
            // the site down on the last line of the bootstrap.
            try {
                $preload = $GLOBALS['xoopsPreload'] ?? null;
                if (is_object($preload) && method_exists($preload, 'triggerEvent')) {
                    $preload->triggerEvent('core.debug.errorscreen', [
                        'owner'             => $owner,
                        'developer_request' => function_exists('xoops_isDeveloperRequest')
                            && xoops_isDeveloperRequest(),
                        'report'            => $report,
                    ]);
                }
                $status  = $outcome['status'];
                $message = $outcome['message'];
            } catch (\Throwable $e) {
                // Authoritative. A provider that reported success and then threw is a
                // provider that failed, and the outcome it wrote is stale by the time we
                // are here -- which is exactly why the outcome lives in this scope and not
                // in a store that would have refused this correction.
                $dispatchThrew = true;
                $status  = 'error';
                $message = 'Error screen "' . $owner . '" failed to start (' . get_class($e) . ').';
            }

            if ('' === $status) {
                // The owner names a provider that no installed module answers for --
                // deactivated, uninstalled, or a token nobody was ever going to answer.
                // Behaviour falls back to 'core': XoopsLogger keeps the handlers, which is
                // exactly what happens when nothing registers. Reported under its own
                // status rather than as 'core', because "the screen you configured is not
                // running" and "you configured no screen" are different situations and
                // only one of them wants looking at.
                //
                // Deliberately does NOT promote some other installed provider. Ownership
                // changing without anybody asking is the surprise this mechanism exists to
                // prevent.
                $status  = 'unclaimed';
                $message = 'No active module claims the error screen "' . $owner
                    . '"; the handlers stay with XoopsLogger.';
            }

            // A failed provider does not get to keep the handlers.
            //
            // Core promises above that a broken or half-installed provider cannot take the
            // site down. Without this it can: a provider that calls set_error_handler()
            // and only then fails still owns PHP's handlers for the rest of the request,
            // while the constants below say the activation failed. The site's error path
            // then belongs to something core has already declared broken.
            //
            // Keyed on the OUTCOME, not on the catch. Both reference providers wrap their
            // own activation in a try/catch and report 'error' without ever throwing at
            // core -- xtracy around Debugger::enable(), xwhoops around registerWhoops() --
            // so a restore that lived in the catch above would miss the two implementations
            // shipped as the worked examples.
            //
            // 'error' is therefore the one status core interprets. Every other status is
            // still opaque to core and passed through untouched.
            //
            // Two honest limits. set_*_handler() pushes a frame rather than popping one,
            // so this restores the EFFECTIVE handler at one extra stack level -- correct,
            // and cheap on the last line of a bootstrap. And a shutdown function the
            // provider registered CANNOT be removed by anybody; providers close that
            // residue by doing everything that can throw before they register, not after.
            // Who ended up with the handlers, whatever anybody said about it.
            //
            // BOTH of them. An earlier version of this compared only the error handler,
            // which missed a listener that takes just the exception handler -- and PHP
            // will hand you one without the other perfectly happily. The prose says
            // "handlers", plural, so the check has to mean it.
            $finalErrorHandler = set_error_handler(null);
            restore_error_handler();
            $finalExceptionHandler = set_exception_handler(null);
            restore_exception_handler();

            // Registration is not mediated; only reporting is.
            //
            // The preload dispatcher runs every listener. The reporter closure decides
            // which OUTCOME is published, but nothing stops a second listener calling
            // set_error_handler() after the first, and that loser owns the handlers while
            // the constants describe the winner. Core cannot prevent this without
            // replacing the broadcast with a registry -- but it can notice, say so, and
            // refuse to leave a contested seat occupied.
            //
            // Three ways the published outcome can be a lie. Core has the evidence for all
            // three and used to discard it.
            $contested = '';
            if ($refusedReports > 0) {
                // Two modules answered one token and both reported. Unambiguous.
                $contested = $refusedReports . ' further module(s) answered the error screen "'
                    . $owner . '" after the first; core cannot tell which one holds the handlers.';
            } elseif ('' !== $outcome['status']
                      && ($handlerAtFirstReport !== $finalErrorHandler
                          || $exceptionAtFirstReport !== $finalExceptionHandler)) {
                // Somebody registered after the module whose report was accepted.
                $contested = 'A second listener registered handlers after "' . $owner
                    . '" reported, so the published status may not describe the module that holds them.';
            } elseif (!$dispatchThrew && '' === $outcome['status']
                      && ($priorErrorHandler !== $finalErrorHandler
                          || $priorExceptionHandler !== $finalExceptionHandler)) {
                // Nobody reported, yet the handlers moved. Without this the status below
                // reads 'unclaimed' -- "the handlers stay with XoopsLogger" -- which in
                // this case is flatly false.
                //
                // Not when the dispatch THREW, though. A provider that registers and then
                // throws before it can report looks identical to a silent second listener
                // from the handlers alone -- but it is not identical, and core knows the
                // difference, because it caught the exception. 'error' is the more
                // specific and more useful answer, so it wins; overwriting it with
                // 'contested' would report a multi-listener problem that did not happen.
                $contested = 'A module registered handlers for the error screen "' . $owner
                    . '" without reporting an outcome.';
            }

            // Hand the handlers back on failure, and on a contested seat.
            //
            // Reporting the problem while leaving the last registrant installed would keep
            // the guarantee false and merely annotate it. Falling back to XoopsLogger is
            // the direction the rest of this design fails in: a surprise toward core is
            // recoverable, a surprise toward a module is not.
            if ('error' === $status || '' !== $contested) {
                set_error_handler($priorErrorHandler);
                set_exception_handler($priorExceptionHandler);
            }

            if ('' !== $contested) {
                $status  = 'contested';
                $message = $contested . ' The handlers were returned to XoopsLogger.';
            }
        }

        define('XOOPS_ERROR_SCREEN_OWNER', $owner);
        define('XOOPS_ERROR_SCREEN_SOURCE', xoops_getErrorScreenOwnerSource());
        define('XOOPS_ERROR_SCREEN_STATUS', $status);
        define('XOOPS_ERROR_SCREEN_MESSAGE', $message);

        return $status;
    }
}
