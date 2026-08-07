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
 * Usage: php errorscreen-runner.php '<json spec>'
 *
 * Spec keys, all optional:
 *   var_path            directory to use as XOOPS_VAR_PATH (required in practice)
 *   root_path           directory to use as XOOPS_ROOT_PATH; defaults to the real core
 *   debug               array to write as debug.php, or false to leave it absent
 *   record              token to record as the error-screen owner before activating
 *   provider            token a fake provider answers to
 *   provider_status     status that provider reports
 *   provider_throws     bool: throw after reporting
 *   developer_request   bool: what xoops_isDeveloperRequest() should answer
 *   case                'truncated-loader' replays common.php's guard chain instead
 *
 * @copyright   (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license     GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package     core
 */

$spec = json_decode($argv[1] ?? '{}', true);
if (!is_array($spec)) {
    fwrite(STDOUT, json_encode(['fatal' => 'bad spec']));
    exit(1);
}

$varPath  = (string) ($spec['var_path'] ?? '');
$rootPath = (string) ($spec['root_path'] ?? dirname(__DIR__, 5) . '/htdocs');

define('XOOPS_ROOT_PATH', $rootPath);
define('XOOPS_VAR_PATH', $varPath);

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

if (isset($spec['provider'])) {
    $provider       = (string) $spec['provider'];
    $status         = (string) ($spec['provider_status'] ?? 'active');
    $throws         = (bool) ($spec['provider_throws'] ?? false);
    $preload->handlers['core.debug.errorscreen'][] = static function ($args) use (
        $provider,
        $status,
        $throws,
        &$providerRan,
        &$providerSawDeveloperFlag
    ) {
        if ($provider !== ($args['owner'] ?? '')) {
            return;
        }
        $providerRan              = true;
        $providerSawDeveloperFlag = $args['developer_request'] ?? null;
        $report                   = $args['report'] ?? null;
        if (is_callable($report)) {
            $report($status, 'fixture provider reporting ' . $status);
        }
        if ($throws) {
            throw new RuntimeException('fixture provider failed after reporting');
        }
    };
}

if (isset($spec['record'])) {
    $recorded = xoops_recordErrorScreenOwner((string) $spec['record']);
}

// As include/common.php calls it: unconditionally, behind function_exists() only. The
// environment constants are published before the early return precisely so they exist on
// a site with no debug.php, and a fixture that skipped this call could not observe that.
if (function_exists('xoops_applyDebugConfig')) {
    xoops_applyDebugConfig();
}

$returned = xoops_activateErrorScreen();
$read     = xoops_getErrorScreenStatus();

fwrite(STDOUT, json_encode([
    'returned'          => $returned,
    'owner'             => XOOPS_ERROR_SCREEN_OWNER,
    'source'            => XOOPS_ERROR_SCREEN_SOURCE,
    'status'            => XOOPS_ERROR_SCREEN_STATUS,
    'message'           => XOOPS_ERROR_SCREEN_MESSAGE,
    'read_back'         => $read,
    'provider_ran'      => $providerRan,
    'provider_saw_gate' => $providerSawDeveloperFlag,
    'recorded_owner'    => xoops_getRecordedErrorScreenOwner(),
    'environment'       => defined('XOOPS_ENVIRONMENT') ? XOOPS_ENVIRONMENT : null,
    'ray_enabled'       => defined('RAY_ENABLED') ? RAY_ENABLED : null,
    'record_call'       => $recorded ?? null,
]));
