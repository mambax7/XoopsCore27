<?php

/**
 * See the enclosed file license.txt for licensing information.
 * If you did not receive this file, get it at https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @copyright    (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license          GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package          installer
 * @since            2.3.0
 * @author           Haruki Setoyama  <haruki@planewave.org>
 * @author           Kazumi Ono <webmaster@myweb.ne.jp>
 * @author           Skalpa Keo <skalpa@xoops.org>
 * @author           Taiwen Jiang <phppp@users.sourceforge.net>
 * @author           DuGris (aka L. JEN) <dugris@frxoops.org>
 **/
class PathController
{
    /**
     * @var array
     */
    public array $xoopsPath = [
        'root' => '',
        'data' => '',
        'lib'  => '',
    ];
    /**
     * @var array
     */
    public array $xoopsPathDefault = [
        'data' => 'xoops_data',
        'lib'  => 'xoops_lib',
    ];
    /**
     * @var array
     */
    public array $dataPath = [
        'caches'    => [
            'smarty_cache',
            'smarty_compile',
            'xoops_cache',
        ],
        'configs'   => [
            'captcha',
            'textsanitizer',
        ],
        'data'      => null,
        'protector' => null,
    ];
    /**
     * @var array
     */
    public array $path_lookup       = [
        'root' => 'ROOT_PATH',
        'data' => 'VAR_PATH',
        'lib'  => 'PATH',
    ];
    public       $xoopsUrl          = '';
    public       $xoopsCookieDomain = '';
    /**
     * @var array
     */
    public array $validPath = [
        'root' => 0,
        'data' => 0,
        'lib'  => 0,
    ];
    /**
     * @var bool
     */
    public bool $validUrl = false;
    /**
     * @var array
     */
    public array $permErrors = [
        'root' => null,
        'data' => null,
    ];

    /**
     * @var string Stores the error message
     */
    public $errorMessage = '';

    /**
     * @param $xoopsPathDefault
     * @param $dataPath
     */
    public function __construct($xoopsPathDefault, $dataPath)
    {
        $this->xoopsPathDefault = $xoopsPathDefault;
        $this->dataPath         = $dataPath;

        if (isset($_SESSION['settings']['ROOT_PATH'])) {
            foreach ($this->path_lookup as $req => $sess) {
                $this->xoopsPath[$req] = $_SESSION['settings'][$sess];
            }
        } else {
            $path = str_replace("\\", '/', realpath(dirname(__DIR__, 2) . '/'));
            if (substr($path, -1) === '/') {
                $path = substr($path, 0, -1);
            }
            if (file_exists("$path/mainfile.dist.php")) {
                $this->xoopsPath['root'] = $path;
            }
            // Firstly, locate XOOPS lib folder out of XOOPS root folder
            $this->xoopsPath['lib'] = dirname($path) . '/' . $this->xoopsPathDefault['lib'];
            // If the folder is not created, re-locate XOOPS lib folder inside XOOPS root folder
            if (!is_dir($this->xoopsPath['lib'] . '/')) {
                $this->xoopsPath['lib'] = $path . '/' . $this->xoopsPathDefault['lib'];
            }
            // Firstly, locate XOOPS data folder out of XOOPS root folder
            $this->xoopsPath['data'] = dirname($path) . '/' . $this->xoopsPathDefault['data'];
            // If the folder is not created, re-locate XOOPS data folder inside XOOPS root folder
            if (!is_dir($this->xoopsPath['data'] . '/')) {
                $this->xoopsPath['data'] = $path . '/' . $this->xoopsPathDefault['data'];
            }
        }
        if (isset($_SESSION['settings']['URL'])) {
            $this->xoopsUrl = $_SESSION['settings']['URL'];
        } else {
            $path           = $GLOBALS['wizard']->baseLocation();
            $this->xoopsUrl = substr($path, 0, strrpos($path, '/'));
        }
        if (isset($_SESSION['settings']['COOKIE_DOMAIN'])) {
            $this->xoopsCookieDomain = $_SESSION['settings']['COOKIE_DOMAIN'];
        } else {
            //            $this->xoopsCookieDomain = xoops_getBaseDomain($this->xoopsUrl);
            $this->xoopsCookieDomain = $this->xoops_getBaseDomain($this->xoopsUrl);
        }
    }

    //=================================================

    /**
     * Determine the base domain name for a URL. The primary use for this is to set the domain
     * used for cookies to represent any subdomains.
     *
     * The registrable domain is determined using the public suffix list. If the domain is not
     * registrable, an empty string is returned. This empty string can be used in setcookie()
     * as the domain, which restricts cookie to just the current host.
     *
     * @param string $url URL or hostname to process
     *
     * @return string the registrable domain or an empty string
     */
    private function xoops_getBaseDomain($url)
    {
        $parts = parse_url($url);
        $host  = '';
        if (!empty($parts['host'])) {
            $host = $parts['host'];
            if (strtolower($host) === 'localhost') {
                return 'localhost';
            }
            // bail if this is an IPv4 address (IPv6 will fail later)
            if (false !== filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return '';
            }
            //            $regdom = new \Xoops\RegDom\RegisteredDomain();
            //            $host = $regdom->getRegisteredDomain($host);

            $host = $this->getRegisteredDomain($host);
        }
        return $host ?? '';
    }

    // Define a simplified getRegisteredDomain function
    function getRegisteredDomain($host)
    {
        $hostParts = explode('.', $host);
        $numParts  = count($hostParts);

        if ($numParts >= 2) {
            // For simplicity, assume the domain is the last two parts
            return $hostParts[$numParts - 2] . '.' . $hostParts[$numParts - 1];
        }

        return $host; // Return as is if it's a top-level domain
    }

    public function updateXoopsTrustPath($newTrustPath)
    {
        // 1. Update the session variable
        $_SESSION['settings']['TRUST_PATH'] = $newTrustPath;

        // 2. Update the defined constant (if not already defined)
        if (!defined('XOOPS_TRUST_PATH')) {
            define('XOOPS_TRUST_PATH', $newTrustPath);
        }

        // Firstly, locate XOOPS lib folder out of XOOPS root folder
        //        $this->xoopsPath['lib'] = dirname($path) . '/' . $this->xoopsPathDefault['lib'];
        $this->xoopsPath['lib'] = $newTrustPath;
        // If the folder is not created, re-locate XOOPS lib folder inside XOOPS root folder
        //        if (!is_dir($this->xoopsPath['lib'] . '/')) {
        //            $this->xoopsPath['lib'] = $path . '/' . $this->xoopsPathDefault['lib'];
        //        }

        // 3. Re-register the autoloader
        try {
            $this->registerAutoloader($newTrustPath);
        } catch (Exception $e) {
            // Log or handle error
            error_log('Failed to register autoloader: ' . $e->getMessage());
            throw new RuntimeException("Could not configure autoloader for the new library path.");
        }
    }

    private function registerAutoloader($trustPath)
    {
        // Composer's autoloader (if it exists)
        $composerAutoloader = $trustPath . '/vendor/autoload.php';
        if (file_exists($composerAutoloader)) {
            include_once $composerAutoloader;
            return;
        }

        // Notify about missing Composer autoloader
        throw new RuntimeException("Autoloader not found in {$trustPath}. Ensure the vendor folder is intact.");
    }

    // install/class/pathcontroller.php

    public function sanitizePath($path)
    {
        // Normalize the path and resolve symbolic links
        $realPath = realpath($path);
        if ($realPath && is_dir($realPath)) {
            // Ensure no trailing slashes for consistency
            return rtrim(str_replace('\\', '/', $realPath), '/');
        }
        return false; // Return false for invalid paths
    }

    /**
     * Determine whether the given PHP source contains an executable
     * XOOPS_VERSION definition.
     *
     * @param string $source
     *
     * @return bool
     */
    private function hasXoopsVersionDefinition($source)
    {
        $tokens = token_get_all($source);
        $count  = count($tokens);
        $depth  = 0;

        for ($i = 0; $i < $count; ++$i) {
            if (is_string($tokens[$i])) {
                if ('{' === $tokens[$i]) {
                    ++$depth;
                } elseif ('}' === $tokens[$i] && $depth > 0) {
                    --$depth;
                }
                continue;
            }
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING || strtolower($tokens[$i][1]) !== 'define') {
                continue;
            }
            if ($depth > 0) {
                continue;
            }
            $previousIndex = $this->nextSignificantTokenIndexReverse($tokens, $i - 1);
            if (null !== $previousIndex && is_array($tokens[$previousIndex]) && in_array($tokens[$previousIndex][0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
                continue;
            }
            $openParenIndex = $this->nextSignificantTokenIndex($tokens, $i + 1);
            if (null === $openParenIndex || '(' !== $tokens[$openParenIndex]) {
                continue;
            }
            $nameIndex = $this->nextSignificantTokenIndex($tokens, $openParenIndex + 1);
            if (null === $nameIndex || !is_array($tokens[$nameIndex]) || $tokens[$nameIndex][0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }
            if ('XOOPS_VERSION' === trim($tokens[$nameIndex][1], "\"'")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find the next non-whitespace/comment token.
     *
     * @param array<int, mixed> $tokens
     * @param int               $startIndex
     *
     * @return int|null
     */
    private function nextSignificantTokenIndex(array $tokens, $startIndex)
    {
        $count = count($tokens);
        for ($i = $startIndex; $i < $count; ++$i) {
            if (!is_array($tokens[$i])) {
                return $i;
            }
            if (!in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Find the previous non-whitespace/comment token.
     *
     * @param array<int, mixed> $tokens
     * @param int               $startIndex
     *
     * @return int|null
     */
    private function nextSignificantTokenIndexReverse(array $tokens, $startIndex)
    {
        for ($i = $startIndex; $i >= 0; --$i) {
            if (!is_array($tokens[$i])) {
                return $i;
            }
            if (!in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $i;
            }
        }

        return null;
    }

    //========================================
    public function execute()
    {
        $this->readRequest();
        $valid = $this->validate();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Always persist submitted paths so the form repopulates on
            // validation failure (redirect back to this page).
            foreach ($this->path_lookup as $req => $sess) {
                $_SESSION['settings'][$sess] = $this->xoopsPath[$req];
            }
            $_SESSION['settings']['URL']           = $this->xoopsUrl;
            $_SESSION['settings']['COOKIE_DOMAIN'] = $this->xoopsCookieDomain;
            if ($valid) {
                foreach ($this->path_lookup as $req => $sess) {
                    $canonicalPath = $this->sanitizePath($this->xoopsPath[$req]);
                    if (false !== $canonicalPath) {
                        $this->xoopsPath[$req] = $canonicalPath;
                        $_SESSION['settings'][$sess] = $canonicalPath;
                    }
                }
                // Sync TRUST_PATH only on valid POST — common.inc.php loads
                // the Composer autoloader from TRUST_PATH, not PATH. A bad
                // lib path must not poison TRUST_PATH. Canonicalize it before
                // storing it so later bootstrap code gets an absolute path.
                $trustPath = $this->sanitizePath($this->xoopsPath['lib']);
                if (false !== $trustPath) {
                    $this->xoopsPath['lib']             = $trustPath;
                    $_SESSION['settings']['PATH']       = $trustPath;
                    $_SESSION['settings']['TRUST_PATH'] = $trustPath;
                }
                $GLOBALS['wizard']->redirectToPage('+1');
            } else {
                $GLOBALS['wizard']->redirectToPage('+0');
            }
            exit;
        }
    }

    public function readRequest()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $request = $_POST;
            foreach ($this->path_lookup as $req => $sess) {
                if (isset($request[$req]) && is_string($request[$req])) {
                    $request[$req] = str_replace("\\", '/', trim($request[$req]));
                    if (substr($request[$req], -1) === '/') {
                        $request[$req] = substr($request[$req], 0, -1);
                    }
                    $this->xoopsPath[$req] = $request[$req];
                }
            }
            if (isset($request['URL']) && is_string($request['URL'])) {
                $request['URL'] = trim($request['URL']);
                if (substr($request['URL'], -1) === '/') {
                    $request['URL'] = substr($request['URL'], 0, -1);
                }
                $this->xoopsUrl = $request['URL'];
            }
            if (isset($request['COOKIE_DOMAIN']) && is_string($request['COOKIE_DOMAIN'])) {
                $tempCookieDomain = trim($request['COOKIE_DOMAIN']);
                $tempParts        = parse_url($tempCookieDomain);
                if (!empty($tempParts['host'])) {
                    $tempCookieDomain = $tempParts['host'];
                }
                $request['COOKIE_DOMAIN'] = $tempCookieDomain;
                $this->xoopsCookieDomain  = $tempCookieDomain;
            }
        }
    }

    /**
     * @return bool
     */
    public function validate()
    {
        foreach (array_keys($this->xoopsPath) as $path) {
            if ($this->checkPath($path)) {
                $this->checkPermissions($path);
            }
        }
        $this->validUrl = !empty($this->xoopsUrl);
        $validPaths     = (array_sum(array_values($this->validPath)) == count(array_keys($this->validPath))) ? 1 : 0;
        $validPerms     = true;
        foreach ($this->permErrors as $key => $errs) {
            if (empty($errs)) {
                continue;
            }
            foreach ($errs as $path => $status) {
                if (empty($status)) {
                    $validPerms = false;
                    break;
                }
            }
        }

        return ($validPaths && $this->validUrl && $validPerms);
    }

    /**
     * @param string $PATH
     *
     * @return int
     */
    public function checkPath($PATH = '')
    {
        $ret = 1;
        if ($PATH === 'root' || empty($PATH)) {
            $path = 'root';
            $this->validPath[$path] = 0;
            if (is_dir($this->xoopsPath[$path]) && is_readable($this->xoopsPath[$path])) {
                $versionFile = "{$this->xoopsPath[$path]}/include/version.php";
                $distFile    = "{$this->xoopsPath[$path]}/mainfile.dist.php";
                if (is_file($versionFile) && is_readable($versionFile)) {
                    $versionContents = file_get_contents($versionFile);
                    if (false !== $versionContents && $this->hasXoopsVersionDefinition($versionContents) && is_file($distFile) && is_readable($distFile)) {
                        $this->validPath[$path] = 1;
                    }
                }
            }
            $ret *= $this->validPath[$path];
        }
        if ($PATH === 'lib' || empty($PATH)) {
            $path = 'lib';
            $autoloader = $this->xoopsPath[$path] . '/vendor/autoload.php';
            $xmfMarker = $this->xoopsPath[$path] . '/vendor/xoops/xmf/src/Request.php';
            if (is_dir($this->xoopsPath[$path])
                && is_readable($this->xoopsPath[$path])
                && is_file($autoloader)
                && is_readable($autoloader)
                && is_file($xmfMarker)
                && is_readable($xmfMarker)
            ) {
                $this->validPath[$path] = 1;
            } else {
                $this->validPath[$path] = 0;
            }
            $ret *= $this->validPath[$path];
        }
        if ($PATH === 'data' || empty($PATH)) {
            $path = 'data';
            if (is_dir($this->xoopsPath[$path]) && is_readable($this->xoopsPath[$path])) {
                $this->validPath[$path] = 1;
            } else {
                $this->validPath[$path] = 0;
            }
            $ret *= $this->validPath[$path];
        }

        return $ret;
    }

    /**
     * @param $parent
     * @param $path
     * @param $error
     * @return null
     */
    public function setPermission($parent, $path, &$error)
    {
        if (is_array($path)) {
            foreach (array_keys($path) as $item) {
                if (is_string($item)) {
                    $error[$parent . '/' . $item] = $this->makeWritable($parent . '/' . $item);
                    if (empty($path[$item])) {
                        continue;
                    }
                    foreach ($path[$item] as $child) {
                        $this->setPermission($parent . '/' . $item, $child, $error);
                    }
                } else {
                    $error[$parent . '/' . $path[$item]] = $this->makeWritable($parent . '/' . $path[$item]);
                }
            }
        } else {
            $error[$parent . '/' . $path] = $this->makeWritable($parent . '/' . $path);
        }

        return null;
    }

    /**
     * @param $path
     *
     * @return bool
     */
    public function checkPermissions($path)
    {
        $paths  = [
            'root' => [
                'mainfile.php',
                'uploads',
            ],
            'data' => $this->dataPath,
        ];
        $errors = [
            'root' => null,
            'data' => null,
        ];

        if (!isset($this->xoopsPath[$path])) {
            return false;
        }
        if (!isset($errors[$path])) {
            return true;
        }
        $this->setPermission($this->xoopsPath[$path], $paths[$path], $errors[$path]);
        if (in_array(false, $errors[$path])) {
            $this->permErrors[$path] = $errors[$path];
        }

        return true;
    }

    /**
     * Write-enable the specified folder
     *
     * @param string $path
     * @param bool   $create
     *
     * @return false on failure, method (u-ser,g-roup,w-orld) on success
     * @internal param bool $recurse
     */
    public function makeWritable($path, $create = true)
    {
        $mode = intval('0777', 8);
        if (!is_dir($path)) {
            if (!$create) {
                return false;
            } else {
                if (!mkdir($path, $mode) && !is_dir($path)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
                }
            }
        }
        if (!is_writable($path)) {
            chmod($path, $mode);
        }
        clearstatcache();
        if (is_writable($path)) {
            $info = stat($path);
            if ($info['mode'] & 0002) {
                return 'w';
            } elseif ($info['mode'] & 0020) {
                return 'g';
            }

            return 'u';
        }

        return false;
    }
}
