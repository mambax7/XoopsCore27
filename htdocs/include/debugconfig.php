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
        if (!is_array($loaded) || true !== ($loaded['enabled'] ?? false)) {
            return $config;
        }

        // Normalise the nested shapes now, so nothing downstream has to defend itself
        // against an object or a string where an array belongs.
        $loaded['database'] = isset($loaded['database']) && is_array($loaded['database']) ? $loaded['database'] : [];
        $loaded['ray']      = isset($loaded['ray']) && is_array($loaded['ray']) ? $loaded['ray'] : [];
        $loaded['debugbar'] = isset($loaded['debugbar']) && is_array($loaded['debugbar']) ? $loaded['debugbar'] : [];

        // Any OTHER key is passed through untouched. debug.php is a per-install file, so
        // a site is free to keep a provider's settings in it -- but core does not know
        // what those keys mean and must not pretend to. A provider normalises its own
        // block, in its own repository, on the same terms as every other consumer.

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
        $loaded['ray']['enabled']         = true === ($loaded['ray']['enabled'] ?? false);

        // The DebugBar module reads this as a SECOND activation source, alongside the
        // database-backed Admin -> Preferences -> Debug Mode. Normalised here rather than
        // in the module so it gets the same strict treatment as every other switch: the
        // module reads it with a truthiness test somewhere, and 'false' as a string is
        // truthy, which is the exact trap closed above for the other three.
        $loaded['debugbar']['enabled']    = true === ($loaded['debugbar']['enabled'] ?? false);

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

        // Alias, kept in step with core_log so pre-2.7.3 consumers see the same array.
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

        return is_string($recorded) ? trim($recorded) : '';
    }
}

if (!function_exists('xoops_recordErrorScreenOwner')) {
    /**
     * Claim, or release, the recorded ownership of the error screen.
     *
     * Called by a provider module's install and uninstall routines, not at request time.
     *
     * The rule is first-installed-wins, and it is enforced HERE rather than trusted to
     * each provider: a second provider installing must not be able to take a screen the
     * first one is already showing, however it was written. Pass '' to release, which
     * uninstalling should do -- and which deactivating deliberately should NOT, so that
     * re-activating a module restores the setup the site already had.
     *
     * A released screen does not promote whatever else happens to be installed. Ownership
     * changing without anybody asking for it is the surprise this whole mechanism exists
     * to prevent, so the site falls back to 'core' and the other provider takes over only
     * when it is next installed or updated -- a deliberate act, with a visible result.
     *
     * @param string $token the claiming module's dirname, or '' to release
     * @param bool   $force take ownership even when another provider holds it
     * @return bool true when the record now names $token
     */
    function xoops_recordErrorScreenOwner($token, $force = false)
    {
        $token = is_string($token) ? trim($token) : '';
        $held  = xoops_getRecordedErrorScreenOwner();

        if ('' === $token) {
            // Release only what you hold. An uninstall must not clear somebody else's
            // claim just because it ran later.
            return true;
        }

        if ('' !== $held && $held !== $token && !$force) {
            return false;
        }

        return xoops_writeDebugRuntimeOverride(['error_screen_owner' => $token]);
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
        $token = is_string($token) ? trim($token) : '';
        if ('' === $token || xoops_getRecordedErrorScreenOwner() !== $token) {
            return true;
        }

        return xoops_writeDebugRuntimeOverride(['error_screen_owner' => null]);
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
     *  1. debugging is switched on by EITHER mechanism -- the XOOPS_DEBUG constant from
     *     xoops_data/data/debug.php, or Admin -> Preferences -> Debug Mode. They are
     *     documented as independent and either alone is a legitimate way to work;
     *  2. the request belongs to an authenticated member of the webmaster group.
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
        $debugMode = (int) ($GLOBALS['xoopsConfig']['debug_mode'] ?? 0);
        $debugOn   = (defined('XOOPS_DEBUG') && XOOPS_DEBUG)
            || in_array($debugMode, [1, 2], true);
        if (!$debugOn) {
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

if (!function_exists('xoops_mayRegisterErrorScreen')) {
    /**
     * May this component take over the error screen?
     *
     * The single question every contender asks before calling register()/enable().
     * Callers should guard with function_exists() so they keep working on a core that
     * predates this seam.
     *
     * @param string $component the caller's own owner token
     * @return bool
     */
    function xoops_mayRegisterErrorScreen($component)
    {
        return is_string($component) && $component === xoops_getErrorScreenOwner();
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
     * calling this function, and a second define() would be a fatal redeclaration.
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
        defined('RAY_ENABLED')
            || define('RAY_ENABLED', [] !== $config && true === ($config['ray']['enabled'] ?? false));

        if ([] === $config) {
            return false;
        }

        // Strict booleans only: a string such as 'false' is truthy and would turn
        // display_errors ON against the stated intent.
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
        foreach (['XOOPS_LIB_PATH', 'XOOPS_PATH', 'XOOPS_TRUST_PATH'] as $constantName) {
            if (defined($constantName)) {
                $candidates[] = rtrim((string) constant($constantName), '/\\') . '/vendor';
            }
        }
        $candidates[] = XOOPS_ROOT_PATH . '/xoops_lib/vendor';
        $candidates[] = XOOPS_ROOT_PATH . '/class/libraries/vendor';

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                $found = $candidate;

                break;
            }
        }

        return $found;
    }
}

if (!function_exists('xoops_errorScreenState')) {
    /**
     * Backing store for the error-screen outcome. Internal to this file.
     *
     * A provider runs inside an event, so it cannot return a value to the code that
     * triggered it. This is the channel it reports through instead.
     *
     * @param array|null $set the outcome to record, or null to read the current one
     * @return array ['status' => string, 'message' => string]
     */
    function xoops_errorScreenState($set = null)
    {
        static $state = ['status' => '', 'message' => ''];

        // First writer wins. Two providers claiming the same owner token is a
        // misconfiguration, and the second one silently overwriting the first is exactly
        // the load-order roulette the owner token exists to end.
        if (is_array($set) && '' === $state['status']) {
            $state = [
                'status'  => (string) ($set['status'] ?? ''),
                'message' => (string) ($set['message'] ?? ''),
            ];
        }

        return $state;
    }
}

if (!function_exists('xoops_setErrorScreenStatus')) {
    /**
     * Report the outcome of an error-screen registration. Called BY a provider.
     *
     * A provider handling core.debug.errorscreen must call this exactly once, whatever
     * happened -- including when it decided NOT to register. Reporting "I am installed and
     * I chose to stay dormant, because X" is the entire difference between a diagnosable
     * setup and a silent one, and silence is what this seam replaces.
     *
     * Any short lower-case string is a valid status; core does not interpret it. The
     * vocabulary the shipped providers use is active | disabled | missing | incompatible
     * | error, and a consumer that does not recognise a status should show it verbatim
     * rather than treat it as a failure.
     *
     * @param string $status  short machine-readable outcome
     * @param string $message short human explanation of that outcome
     * @return void
     */
    function xoops_setErrorScreenStatus($status, $message = '')
    {
        xoops_errorScreenState(['status' => $status, 'message' => $message]);
    }
}

if (!function_exists('xoops_getErrorScreenStatus')) {
    /**
     * What became of the error screen this request.
     *
     * Meaningful only after xoops_activateErrorScreen() has run; before that the status
     * is an empty string. The same values are published as XOOPS_ERROR_SCREEN_STATUS and
     * XOOPS_ERROR_SCREEN_MESSAGE for consumers that would rather read a constant.
     *
     * @return array ['status' => string, 'message' => string]
     */
    function xoops_getErrorScreenStatus()
    {
        return xoops_errorScreenState();
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
     * declared owner from xoops_data/data/debug.php, triggers core.debug.errorscreen, and
     * records whatever the provider reports back through xoops_setErrorScreenStatus().
     * A provider is an ordinary module preload -- the same mechanism the xwhoops module
     * has always used -- so a library falling out of use, or a new one arriving, is a
     * module being uninstalled or installed and never a change to core.
     *
     * Always defines, whatever the outcome:
     *
     *   XOOPS_ERROR_SCREEN_OWNER    the owner token, 'core' when none applies
     *   XOOPS_ERROR_SCREEN_SOURCE   config | recorded | default -- where that came from
     *   XOOPS_ERROR_SCREEN_STATUS   core | active | disabled | unclaimed | error, or
     *                               whatever else the provider reported
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
        } else {
            // One boundary around the whole dispatch. An error screen is a debugging
            // convenience; a broken or half-installed provider must not be able to take
            // the site down on the last line of the bootstrap.
            try {
                $preload = $GLOBALS['xoopsPreload'] ?? null;
                if (is_object($preload) && method_exists($preload, 'triggerEvent')) {
                    $preload->triggerEvent('core.debug.errorscreen', ['owner' => $owner]);
                }
                $state   = xoops_errorScreenState();
                $status  = $state['status'];
                $message = $state['message'];
            } catch (\Throwable $e) {
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
                // prevent; the other module takes over when it is next installed or
                // updated, which is a deliberate act with a visible result.
                $status  = 'unclaimed';
                $message = 'No active module claims the error screen "' . $owner
                    . '"; the handlers stay with XoopsLogger.';
            }
        }

        xoops_errorScreenState(['status' => $status, 'message' => $message]);

        define('XOOPS_ERROR_SCREEN_OWNER', $owner);
        define('XOOPS_ERROR_SCREEN_SOURCE', xoops_getErrorScreenOwnerSource());
        define('XOOPS_ERROR_SCREEN_STATUS', $status);
        define('XOOPS_ERROR_SCREEN_MESSAGE', $message);

        return $status;
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
     * off -- cannot erase each other's settings.
     *
     * Written to a temporary file and renamed, so a reader never sees a half-written
     * file: on every platform this matters on, rename over an existing path is atomic.
     *
     * @param array $changes keys to set, or set to null to remove
     * @return bool true when the file now reflects $changes
     */
    function xoops_writeDebugRuntimeOverride($changes)
    {
        if (!is_array($changes) || [] === $changes || !defined('XOOPS_VAR_PATH')) {
            return false;
        }

        $directory = XOOPS_VAR_PATH . '/data';
        if (!is_dir($directory) || !is_writable($directory)) {
            return false;
        }

        $current = xoops_readDebugRuntimeOverride(true);
        foreach ($changes as $key => $value) {
            if (null === $value) {
                unset($current[$key]);
                continue;
            }
            $current[$key] = $value;
        }

        $json = json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return false;
        }

        $file      = $directory . '/debug-runtime.json';
        $temporary = $file . '.' . getmypid() . '.tmp';
        if (false === file_put_contents($temporary, $json . "\n")) {
            return false;
        }
        if (!rename($temporary, $file)) {
            @unlink($temporary);

            return false;
        }

        // The static cache in the reader is now stale, and callers in this same request
        // -- an install routine reading back what it just claimed -- would otherwise see
        // the previous contents.
        xoops_readDebugRuntimeOverride(true);

        return true;
    }
}
