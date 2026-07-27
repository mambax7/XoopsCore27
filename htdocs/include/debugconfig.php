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
            trigger_error(
                'xoops_data/data/debug.php could not be loaded (' . get_class($e)
                . '); continuing with debugging disabled.',
                E_USER_WARNING
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
        $loaded['log']      = isset($loaded['log']) && is_array($loaded['log']) ? $loaded['log'] : [];
        $loaded['database'] = isset($loaded['database']) && is_array($loaded['database']) ? $loaded['database'] : [];

        $config = $loaded;

        return $config;
    }
}

if (!function_exists('xoops_applyDebugConfig')) {
    /**
     * Apply the PHP-level settings: display_errors and error_reporting.
     *
     * Kept separate from xoops_getDebugConfig() so the configuration can be inspected
     * without side effects — the upgrade tooling and tests need to do exactly that.
     *
     * @return bool true when debugging is enabled and was applied
     */
    function xoops_applyDebugConfig()
    {
        $config = xoops_getDebugConfig();
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
