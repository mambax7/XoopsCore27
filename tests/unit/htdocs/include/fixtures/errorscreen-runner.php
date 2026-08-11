<?php
/**
 * Out-of-process fixture runner for the error-screen seam.
 *
 * The seam publishes its outcome as CONSTANTS, which can be defined once per process, so
 * the cases cannot share one. Each is run here in its own PHP process and reports back as
 * JSON on stdout; ErrorScreenSeamTest asserts on that.
 *
 * A subprocess is also the only honest way to exercise a TRUNCATED include/debugconfig.php,
 * which is a compile-time failure of the file under test.
 *
 * Usage: php errorscreen-runner.php '<base64 of the json spec>'
 *
 * Spec keys, all optional:
 *   var_path            directory to use as XOOPS_VAR_PATH (required in practice)
 *   root_path           directory to use as XOOPS_ROOT_PATH; defaults to the real core
 *   debug               array to write as debug.php, or false to leave it absent
 *   record              token to record as the error-screen owner before activating
 *   release             token to release AFTER the record above, before activating
 *   provider            token a fake provider answers to
 *   provider_status     status that provider reports
 *   provider_throws     bool: throw after reporting
 *   provider_registers  bool|'exception': install handlers BEFORE reporting, so the case
 *                       can tell whether core hands them back when activation fails.
 *                       true installs BOTH; 'exception' installs only the exception
 *                       handler and leaves the error handler with the core stand-in --
 *                       the only way to write a case in which NOTHING touches the error
 *                       handler, which is what a detector-3 regression test has to be to
 *                       mean anything.
 *   second_listener     'report' | 'silent' | 'silent-exception': add a SECOND listener
 *                       answering the same token, which registers its own handlers and
 *                       either reports (and is refused) or stays quiet. 'silent-exception'
 *                       takes ONLY the exception handler, which PHP allows and which a
 *                       detector watching only the error handler cannot see.
 *   provider_throws_before_report  bool: register, then throw WITHOUT reporting. Core
 *                       caught this and then mislabelled it 'contested'.
 *   developer_request   bool: what xoops_isDeveloperRequest() should answer
 *                       (the strict switch lives in the 'debug' array as
 *                       'error_screen_strict')
 *   mainfile_debug_constant bool: define XOOPS_DEBUG as this value BEFORE debugconfig.php
 *                       loads, which is what mainfile.php does on a real site. false is
 *                       every site upgraded from 2.7.1 -- its mainfile.php survives the
 *                       upgrade with the constant hard-coded false, defined long before
 *                       any 2.7.3 code can look at it. true is the developer who
 *                       hand-edited it, which is what dev sites did before debug.php
 *                       existed. Omit 'developer_request' alongside this, or the stub
 *                       below replaces the very function under test and the case proves
 *                       nothing.
 *   debug_mode          int: $GLOBALS['xoopsConfig']['debug_mode'], the Admin ->
 *                       Preferences setting. 0 is the value worth testing -- it is the
 *                       term that must NOT be what saves the gate.
 *   webmaster_user      bool: install a $GLOBALS['xoopsUser'] stand-in in the webmaster
 *                       group, so condition 2 of the gate passes and condition 1 is the
 *                       only thing the case is measuring.
 *   case                'truncated-loader' replays common.php's guard chain instead
 *
 * @copyright   (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license     GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package     core
 */

// Nothing may reach STDOUT before the JSON payload: the caller decodes the whole stream.
// A noisy php.ini (a stale extension, a startup deprecation) would otherwise prepend a
// warning and make every case look like a seam failure.
ini_set('display_errors', '0');

// The spec arrives base64-encoded -- see the note in ErrorScreenSeamTest::runCase(). Raw
// JSON through escapeshellarg() is silently mangled on Windows.
$raw  = base64_decode((string) ($argv[1] ?? ''), true);
$spec = json_decode(false === $raw ? '' : $raw, true);
if (!is_array($spec)) {
    fwrite(STDOUT, json_encode(['fatal' => 'bad spec']));
    exit(1);
}

$varPath  = (string) ($spec['var_path'] ?? '');
$rootPath = (string) ($spec['root_path'] ?? dirname(__DIR__, 5) . '/htdocs');

define('XOOPS_ROOT_PATH', $rootPath);
define('XOOPS_VAR_PATH', $varPath);

// Simulates mainfile.php: the constant is already defined before include/debugconfig.php
// has been read, and nothing downstream can change it. Defining it HERE rather than after
// the require is the point -- a define() placed later would be the guarded 2.7.3 shape and
// would not reproduce either site.
//
// array_key_exists, not isset or empty: false is a meaningful value here -- it is the
// entire upgraded-site case -- and the two other forms would silently discard it.
if (array_key_exists('mainfile_debug_constant', $spec)) {
    define('XOOPS_DEBUG', (bool) $spec['mainfile_debug_constant']);
}

@mkdir($varPath . '/data', 0777, true);
if (array_key_exists('debug', $spec) && false !== $spec['debug']) {
    file_put_contents($varPath . '/data/debug.php', '<?php return ' . var_export($spec['debug'], true) . ';');
} else {
    @unlink($varPath . '/data/debug.php');
}

// Pre-defined so the loader's own function_exists() guard leaves it alone. Group
// membership needs a session and a database; the seam only needs the answer.
if (array_key_exists('developer_request', $spec)) {
    $xoopsErrorScreenFixtureDeveloper = (bool) $spec['developer_request'];
    /**
     * @param string|null $dirname
     * @return bool
     */
    function xoops_isDeveloperRequest($dirname = null)
    {
        return $GLOBALS['xoopsErrorScreenFixtureDeveloper'];
    }
    $GLOBALS['xoopsErrorScreenFixtureDeveloper'] = $xoopsErrorScreenFixtureDeveloper;
}

// The two inputs to the REAL gate, for cases that deliberately do not stub it out.
if (array_key_exists('debug_mode', $spec)) {
    $GLOBALS['xoopsConfig']['debug_mode'] = (int) $spec['debug_mode'];
}

/**
 * Stand-in for XoopsUser. getGroups() is the entire surface xoops_isDeveloperRequest()
 * touches when called without a $dirname, and the gate tests it by group membership
 * rather than through isAdmin() -- so this is the whole of what a webmaster is, here.
 */
class XoopsErrorScreenFixtureUser
{
    /** @var int[] */
    private $groups;

    /** @param int[] $groups */
    public function __construct(array $groups)
    {
        $this->groups = $groups;
    }

    /** @return int[] */
    public function getGroups()
    {
        return $this->groups;
    }
}

if (!empty($spec['webmaster_user'])) {
    // 1 is the gate's own fallback for XOOPS_GROUP_ADMIN, and leaving that constant
    // undefined exercises the fallback rather than papering over it.
    $GLOBALS['xoopsUser'] = new XoopsErrorScreenFixtureUser([1]);
}

/**
 * Replays include/common.php's guard chain against a deliberately truncated loader.
 *
 * Every call site in common.php must survive a debugconfig.php that fails to compile --
 * that is the whole point of the try/catch around the include. A bare call anywhere later
 * in the file makes the guard decorative, which is what this case exists to catch.
 */
if ('truncated-loader' === ($spec['case'] ?? '')) {
    $reached = [];
    @mkdir($varPath . '/include', 0777, true);
    file_put_contents(
        $varPath . '/include/debugconfig.php',
        "<?php\nfunction xoops_getDebugConfig() { return []; }\nif (true) {\n"
    );

    $loader = $varPath . '/include/debugconfig.php';
    if (is_readable($loader)) {
        try {
            require_once $loader;
        } catch (\Throwable $e) {
            $reached[] = 'guard-caught-' . (new ReflectionClass($e))->getShortName();
        }
    }

    $config    = function_exists('xoops_getDebugConfig') ? xoops_getDebugConfig() : [];
    $reached[] = 'early-read';
    if (function_exists('xoops_applyDebugConfig')) {
        xoops_applyDebugConfig();
    }
    $reached[] = 'early-apply';
    $config    = function_exists('xoops_getDebugConfig') ? xoops_getDebugConfig() : [];
    $reached[] = 'debug-mode-read';
    if (function_exists('xoops_applyDebugConfig')) {
        xoops_applyDebugConfig();
    }
    $reached[] = 'debug-mode-apply';
    if (function_exists('xoops_activateErrorScreen')) {
        xoops_activateErrorScreen();
    }
    $reached[] = 'end-of-boot';

    fwrite(STDOUT, json_encode(['reached' => $reached]));

    return;
}

require XOOPS_ROOT_PATH . '/include/debugconfig.php';

/** Minimal stand-in with XoopsPreload's triggerEvent contract. */
class XoopsErrorScreenFixturePreload
{
    /** @var array<string, callable[]> */
    public $handlers = [];

    /**
     * @param string $name
     * @param array  $args
     * @return void
     */
    public function triggerEvent($name, $args = [])
    {
        foreach ($this->handlers[$name] ?? [] as $handler) {
            $handler($args);
        }
    }
}

$preload                    = new XoopsErrorScreenFixturePreload();
$GLOBALS['xoopsPreload']    = $preload;
$providerRan                = false;
$providerSawDeveloperFlag   = null;

// Stand-ins for XoopsLogger's handlers, installed before activation so a case can ask
// "who owns them now?" by identity rather than by hope. Only set up when the case cares.
$coreErrorHandler     = null;
$coreExceptionHandler = null;
$fixtureErrorHandler  = null;
$fixtureExcHandler    = null;

$registerMode = $spec['provider_registers'] ?? false;

// The core stand-ins go in whichever way the provider is going to register, including
// 'exception' -- the error handler still needs a known owner for the case to be able to
// say "and this one never moved" by identity rather than by absence.
if (false !== $registerMode && null !== $registerMode) {
    $coreErrorHandler     = static function () { return false; };
    $coreExceptionHandler = static function ($e) { };
    set_error_handler($coreErrorHandler);
    set_exception_handler($coreExceptionHandler);

    $fixtureErrorHandler = static function () { return false; };
    $fixtureExcHandler   = static function ($e) { };
}

if (isset($spec['provider'])) {
    $provider       = (string) $spec['provider'];
    $status         = (string) ($spec['provider_status'] ?? 'active');
    $throws         = (bool) ($spec['provider_throws'] ?? false);
    $registers      = $registerMode;
    $throwsFirst    = (bool) ($spec['provider_throws_before_report'] ?? false);
    $preload->handlers['core.debug.errorscreen'][] = static function ($args) use (
        $provider,
        $status,
        $throws,
        $registers,
        $throwsFirst,
        $fixtureErrorHandler,
        $fixtureExcHandler,
        &$providerRan,
        &$providerSawDeveloperFlag
    ) {
        if ($provider !== ($args['owner'] ?? '')) {
            return;
        }
        $providerRan              = true;
        $providerSawDeveloperFlag = $args['developer_request'] ?? null;

        // Registering BEFORE the report is what makes the rollback case real. The bug
        // this guards was invisible for a round because the old fake provider reported
        // and threw without ever taking the handlers, so there was nothing to hand back.
        if (false !== $registers && null !== $registers) {
            // 'exception' takes only the exception handler. PHP hands the two out
            // separately and a real provider may well want just one of them.
            if ('exception' !== $registers) {
                set_error_handler($fixtureErrorHandler);
            }
            set_exception_handler($fixtureExcHandler);
        }

        if ($throwsFirst) {
            throw new RuntimeException('fixture provider failed before it could report');
        }

        $report = $args['report'] ?? null;
        if (is_callable($report)) {
            $report($status, 'fixture provider reporting ' . $status);
        }
        if ($throws) {
            throw new RuntimeException('fixture provider failed after reporting');
        }
    };
}

// A second module answering one token: the misconfiguration the contested detectors
// exist for. It registers AFTER the first, so it ends up owning PHP's handlers whatever
// the published status says -- which is the whole point.
$secondListenerRan = false;
if (isset($spec['provider']) && isset($spec['second_listener'])) {
    $secondProvider = (string) $spec['provider'];
    $secondMode     = (string) $spec['second_listener'];
    $secondReports  = 'report' === $secondMode;
    $preload->handlers['core.debug.errorscreen'][] = static function ($args) use (
        $secondProvider,
        $secondReports,
        $secondMode,
        &$secondListenerRan
    ) {
        if ($secondProvider !== ($args['owner'] ?? '')) {
            return;
        }
        $secondListenerRan = true;
        if ('silent-exception' !== $secondMode) {
            set_error_handler(static function () { return false; });
        }
        set_exception_handler(static function ($e) { });
        $report = $args['report'] ?? null;
        if ($secondReports && is_callable($report)) {
            $report('active', 'the second listener also thinks it is the owner');
        }
    };
}

if (isset($spec['record'])) {
    $recorded = xoops_recordErrorScreenOwner((string) $spec['record']);
}

// Release AFTER the record above, so a case can exercise both halves of the ownership
// lifecycle in one process: claim a seat, then release it (or fail to, when the token is
// not the holder's).
if (isset($spec['release'])) {
    $released = xoops_releaseErrorScreenOwner((string) $spec['release']);
}

// As include/common.php calls it: unconditionally, behind function_exists() only. The
// environment constants are published before the early return precisely so they exist on
// a site with no debug.php, and a fixture that skipped this call could not observe that.
if (function_exists('xoops_applyDebugConfig')) {
    xoops_applyDebugConfig();
}

$returned = xoops_activateErrorScreen();
$read     = xoops_getErrorScreenStatus();

// The gate's OWN answer, reported separately from the seam's outcome. Necessary because
// the two do not always move together: on a site with no debug.php the token resolves to
// 'core' whatever the gate says, so a case asking "is the gate still shut here?" has
// nothing to observe in the status fields. For cases that stub the gate out this just
// echoes the stub, and nothing asserts on it.
$gate = function_exists('xoops_isDeveloperRequest') ? xoops_isDeveloperRequest() : null;

// Read the effective handlers the same way the seam does: set-then-restore is the only
// way PHP offers to look at the current one.
$liveErrorHandler = set_error_handler(static function () { return false; });
restore_error_handler();
$liveExcHandler = set_exception_handler(static function ($e) { });
restore_exception_handler();

fwrite(STDOUT, json_encode([
    'returned'          => $returned,
    'owner'             => XOOPS_ERROR_SCREEN_OWNER,
    'source'            => XOOPS_ERROR_SCREEN_SOURCE,
    'status'            => XOOPS_ERROR_SCREEN_STATUS,
    'message'           => XOOPS_ERROR_SCREEN_MESSAGE,
    'read_back'         => $read,
    'gate'              => $gate,
    'debug_enabled'     => function_exists('xoops_isDebugEnabled') ? xoops_isDebugEnabled() : null,
    'debug_constant'    => defined('XOOPS_DEBUG') ? XOOPS_DEBUG : null,
    'provider_ran'      => $providerRan,
    'provider_saw_gate' => $providerSawDeveloperFlag,
    'recorded_owner'    => xoops_getRecordedErrorScreenOwner(),
    'environment'       => defined('XOOPS_ENVIRONMENT') ? XOOPS_ENVIRONMENT : null,
    'record_call'       => $recorded ?? null,
    'release_call'      => $released ?? null,
    // The runtime file VERBATIM, so a test can assert on the serialised shape itself --
    // e.g. that a file emptied of its last key is the JSON object {} and not the array [].
    'runtime_raw'       => is_file($varPath . '/data/debug-runtime.json')
        ? trim((string) file_get_contents($varPath . '/data/debug-runtime.json'))
        : null,
    'provider_registered'      => null !== $fixtureErrorHandler,
    'second_listener_ran'      => $secondListenerRan,
    'error_handler_is_core'    => null !== $coreErrorHandler && $liveErrorHandler === $coreErrorHandler,
    'error_handler_is_provider'=> null !== $fixtureErrorHandler && $liveErrorHandler === $fixtureErrorHandler,
    'exc_handler_is_core'      => null !== $coreExceptionHandler && $liveExcHandler === $coreExceptionHandler,
    'exc_handler_is_second'    => $secondListenerRan && $liveExcHandler !== $coreExceptionHandler
                                  && $liveExcHandler !== $fixtureExcHandler,
    'exc_handler_is_provider'  => null !== $fixtureExcHandler && $liveExcHandler === $fixtureExcHandler,
]));
