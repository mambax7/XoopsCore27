<?php

// Abstract of each filter classes
/**
 * Class ProtectorFilterAbstract
 */
class ProtectorFilterAbstract
{
    public $protector;

    /**
     * ProtectorFilterAbstract constructor.
     */
    public function __construct()
    {
        $this->protector = Protector::getInstance();
        $lang            = empty($GLOBALS['xoopsConfig']['language']) ? ($this->protector->_conf['default_lang'] ?? '') : $GLOBALS['xoopsConfig']['language'];
        $file_to_include = dirname(__DIR__) . '/language/' . $lang . '/main.php';

        if (file_exists($file_to_include)) {
            include_once $file_to_include;
        } else {
            trigger_error('File Path Error: ' . $file_to_include . ' does not exist.');
            throw new \RuntimeException('File Path Error: ' . $file_to_include . ' does not exist.');
        }

        if (!defined('_MD_PROTECTOR_YOUAREBADIP')) {
            include_once dirname(__DIR__) . '/language/english/main.php';
        }
    }

    /**
     * @return bool
     * @deprecated unused in core, will be removed
     */
    public function isMobile()
    {
        if (class_exists('Wizin_User')) {
            // WizMobile (gusagi)
            $user =& Wizin_User::getSingleton();

            return $user->bIsMobile;
        } elseif (defined('HYP_K_TAI_RENDER') && HYP_K_TAI_RENDER) {
            // hyp_common ktai-renderer (nao-pon)
            return true;
        } else {
            return false;
        }
    }
}

// Filter Handler class (singleton)
/**
 * Class ProtectorFilterHandler
 */
class ProtectorFilterHandler
{
    public $protector;
    public $filters_base = '';

    /**
     * ProtectorFilterHandler constructor.
     */
    protected function __construct()
    {
        $this->protector    = Protector::getInstance();
        $this->filters_base = dirname(__DIR__) . '/filters_enabled';
    }

    /**
     * @return ProtectorFilterHandler
     */
    public static function getInstance()
    {
        static $instance;
        if (!isset($instance)) {
            $instance = new ProtectorFilterHandler();
        }

        return $instance;
    }

    // return: false : execute default action
    /**
     * @param string $type
     *
     * @return int|mixed
     */
    public function execute($type)
    {
        $ret = 0;

        $dh = opendir($this->filters_base);
        if (false === $dh) {
            return $ret;
        }

        // Fix 9.1: resolve the filters_enabled base path once so every candidate
        // filter can be verified to live inside it. The previous loader included
        // every file readdir() returned, which allowed a writable filters_enabled
        // directory to achieve RCE if an attacker could place or symlink a file
        // whose name happened to start with the requested type prefix.
        // If realpath() fails (missing dir, broken symlink), bail out now — every
        // per-file containment check below would fail the same way.
        $baseReal = realpath($this->filters_base);
        if (false === $baseReal) {
            closedir($dh);
            return $ret;
        }

        while (($file = readdir($dh)) !== false) {
            if (strncmp($file, $type . '_', strlen($type) + 1) !== 0) {
                continue;
            }
            // Require .php suffix — blocks .phtml, .inc, .phar and other executable
            // extensions that may be interpreted as PHP by misconfigured servers.
            // Case-insensitive: Windows NTFS and macOS HFS+ resolve case-variant
            // filenames as the same file, and pre-existing custom filters may use
            // .PHP / .Php. strcasecmp lets those continue to load on case-sensitive
            // filesystems too.
            if (0 !== strcasecmp(substr($file, -4), '.php')) {
                continue;
            }
            // Resolve the real path and ensure it stays inside filters_enabled.
            // Blocks symlinks pointing outside the directory and any crafted name
            // whose canonical path escapes the base.
            $realPath = realpath($this->filters_base . '/' . $file);
            if (false === $realPath
                || !str_starts_with($realPath, $baseReal . DIRECTORY_SEPARATOR)) {
                continue;
            }
            // Only include regular, readable files. realpath() succeeds for
            // directories too, so an entry like "precommon_something.php/"
            // (a directory that happens to end in .php) would otherwise reach
            // include_once and emit a "failed to open stream: Is a directory"
            // warning on every request. Same for permission-denied files.
            if (!is_file($realPath) || !is_readable($realPath)) {
                continue;
            }

            include_once $realPath;
            $plugin_name = 'protector_' . substr($file, 0, -4);
            if (function_exists($plugin_name)) {
                // old way
                $ret |= call_user_func($plugin_name);
            } elseif (class_exists($plugin_name)) {
                // newer way
                $plugin_obj = new $plugin_name(); //old code is -> $plugin_obj =& new $plugin_name() ; //hack by Trabis
                $ret |= $plugin_obj->execute();
            }
        }
        closedir($dh);

        return $ret;
    }
}
