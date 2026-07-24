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
 */

/**
 * call htmlspecialchars with standard arguments
 * @param string $value
 * @return string
 */
function installerHtmlSpecialChars($value = '')
{
    return htmlspecialchars($value, ENT_QUOTES, _INSTALL_CHARSET, true);
}

function install_acceptUser()
{
    $GLOBALS['xoopsUser'] = null;
    $assertClaims = [
        'sub' => 'xoopsinstall',
    ];
    $claims = \Xmf\Jwt\TokenReader::fromCookie('install', 'xo_install_user', $assertClaims);
    if (false === $claims || empty($claims->uname)) {
        return false;
    }
    $uname = $claims->uname;
    /** @var XoopsMemberHandler $memberHandler */
    $memberHandler = xoops_getHandler('member');
    $users = $memberHandler->getUsers(new Criteria('uname', $uname));
    $user = array_pop($users);

    // The install token only ever names the administrator created during
    // installation. Accept it only when it still resolves to an existing
    // administrator, so a token can never authenticate a non-admin identity.
    if (!is_object($user) || !$user->isAdmin()) {
        return false;
    }

    if (is_object($GLOBALS['xoops']) && method_exists($GLOBALS['xoops'], 'acceptUser')) {
        $res = $GLOBALS['xoops']->acceptUser($uname, true, '');

        return $res;
    }

    $GLOBALS['xoopsUser']        = $user;
    $_SESSION['xoopsUserId']     = $GLOBALS['xoopsUser']->getVar('uid');
    $_SESSION['xoopsUserGroups'] = $GLOBALS['xoopsUser']->getGroups();

    return true;
}

/**
 * @param $installer_modified
 */
function install_finalize($installer_modified)
{
    // Set mainfile.php readonly
    @chmod(XOOPS_ROOT_PATH . '/mainfile.php', 0444);
    // Set Secure file readonly
    @chmod(XOOPS_VAR_PATH . '/data/secure.php', 0444);

    // Close session to release file locks before renaming.
    // This is the final installation step so no further session data is needed.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    clearstatcache(true);

    $source = XOOPS_ROOT_PATH . '/install';
    $dest   = XOOPS_ROOT_PATH . '/' . $installer_modified;

    // Rename installer folder
    if (!@rename($source, $dest) && is_dir($source)) {
        // On Windows, rename() may fail when called from within the directory.
        // Retry at shutdown when more file handles may have been released.
        register_shutdown_function(static function () use ($source, $dest) {
            clearstatcache(true);
            if (!@rename($source, $dest) && is_dir($source)) {
                trigger_error('Failed to rename install directory — manual removal may be required.', E_USER_WARNING);
            }
        });
    }
}

/**
 * Detect whether XOOPS is already installed on this host.
 *
 * "Installed" means the on-disk mainfile.php has been included (so the DB
 * connection constants are defined) AND the users table for that database
 * already holds at least one account. A half-written mainfile, an unreachable
 * database, or an empty users table all read as "not installed" so a genuine
 * (re)install can still proceed. The check fails open (returns false) on any
 * uncertainty — the lock that consumes it only bites on a confirmed install.
 *
 * @return bool
 */
function install_isInstalled(): bool
{
    if (!defined('XOOPS_ROOT_PATH')
        || !defined('XOOPS_DB_HOST') || !defined('XOOPS_DB_USER')
        || !defined('XOOPS_DB_NAME') || !defined('XOOPS_DB_PREFIX')
        || !function_exists('mysqli_init')) {
        return false;
    }

    $host    = (string) XOOPS_DB_HOST;
    $cprefix = (defined('XOOPS_DB_PCONNECT') && XOOPS_DB_PCONNECT) ? 'p:' : '';
    $port    = 0;
    // Split an explicit host:port; leave IPv6 literals and socket paths alone.
    if (substr_count($host, ':') === 1) {
        [$hostPart, $portPart] = explode(':', $host, 2);
        if (ctype_digit($portPart)) {
            $host = $hostPart;
            $port = (int) $portPart;
        }
    }

    $mysqli = @mysqli_init();
    if (!$mysqli) {
        return false;
    }
    // This probe runs BEFORE the XOOPS database handler is bootstrapped, so it
    // must talk to mysqli directly (a parameterized handler does not exist this
    // early). Two hardening rules apply:
    //
    //  1. Fail closed. The state defaults to "installed" and is only cleared on
    //     a positive "users table absent" result (ER_NO_SUCH_TABLE, 1146). A
    //     connection, authentication, permission or timeout failure must NEVER
    //     unlock the installer on a live site.
    //  2. PHP 8.1+ defaults mysqli to exception mode (MYSQLI_REPORT_ERROR |
    //     MYSQLI_REPORT_STRICT) and the '@' operator does NOT suppress those
    //     exceptions, so the missing-table probe on a fresh install is caught
    //     below (rule 1) instead of fatalling.
    //
    // XOOPS_DB_PREFIX is a trusted, install-validated identifier; it is
    // re-validated as a bare identifier and its backticks are doubled before it
    // is concatenated into this unavoidable pre-bootstrap raw query.
    $installed = true;
    $prefix    = (string) XOOPS_DB_PREFIX;
    try {
        @mysqli_options($mysqli, MYSQLI_OPT_CONNECT_TIMEOUT, 3);
        $pass      = defined('XOOPS_DB_PASS') ? (string) XOOPS_DB_PASS : '';
        $connected = @mysqli_real_connect($mysqli, $cprefix . $host, (string) XOOPS_DB_USER, $pass, (string) XOOPS_DB_NAME, $port);
        if ($connected && ('' === $prefix || preg_match('/^[A-Za-z0-9_]+$/', $prefix))) {
            $table  = $prefix . '_users';
            $result = mysqli_query($mysqli, 'SELECT COUNT(*) FROM `' . str_replace('`', '``', $table) . '`');
            if ($result instanceof \mysqli_result) {
                $row       = mysqli_fetch_row($result);
                $installed = !empty($row) && (int) $row[0] > 0;
                mysqli_free_result($result);
            } else {
                // Non-exception mode: query returned false. Only a missing
                // users table means "not installed"; anything else stays locked.
                $installed = (1146 !== mysqli_errno($mysqli));
            }
        }
    } catch (\mysqli_sql_exception $e) {
        $installed = (1146 !== $e->getCode());
    } finally {
        @mysqli_close($mysqli);
    }

    return $installed;
}

/**
 * Positive "already installed" lock for the installer bootstrap.
 *
 * Called once from common.inc.php so every installer page inherits the gate.
 * A completed install is walkable only by an authorized, in-progress wizard
 * session (the run that just wrote mainfile.php, before the users table
 * exists); a fresh or anonymous session on an already-installed site is
 * refused. This does not rely on the install/ directory being removed or on
 * mainfile.php being chmod-ed read-only.
 *
 * @return void
 */
function install_denyIfInstalled(): void
{
    if (!install_isInstalled()) {
        return;
    }
    // Allow an authorized, in-progress install run to finish. The users table
    // only becomes populated after page_configsave has set these flags, so a
    // legitimate first-time install passes through; a fresh session on an
    // already-installed site has neither flag and is blocked before it can
    // reach the destructive pages.
    if (!empty($_SESSION['UserLogin']) || !empty($_SESSION['settings']['authorized'])) {
        return;
    }

    // The admin-gated wizard pages (configsite, theme, moduleinstaller) set
    // $xoopsOption['hascommon'], so common.inc.php boots full XOOPS instead of
    // starting the installer's own PHP session — which swaps the session store
    // and hides the flags checked above. Those pages authenticate the
    // in-progress admin via the signed one-time xo_install_user JWT
    // (XoopsInstallWizard::xoInit -> checkAccess -> install_acceptUser), which
    // runs before this gate. Recognise that SAME in-progress marker here: a
    // *valid* install token PLUS an authenticated administrator is a legitimate
    // mid-install run and is allowed through. An ordinary logged-in
    // administrator WITHOUT an install token must NOT bypass the lock, so the
    // installed-site guard still holds on a live site.
    // TokenReader::fromCookie() reads and verifies the xo_install_user cookie
    // itself (returning false when it is absent, expired, or fails signature /
    // claim checks), so the in-progress marker is confirmed without touching
    // $_COOKIE directly.
    if (class_exists(\Xmf\Jwt\TokenReader::class)
        && false !== \Xmf\Jwt\TokenReader::fromCookie('install', 'xo_install_user', ['sub' => 'xoopsinstall'])
        && isset($GLOBALS['xoopsUser']) && is_object($GLOBALS['xoopsUser'])
        && method_exists($GLOBALS['xoopsUser'], 'isAdmin') && $GLOBALS['xoopsUser']->isAdmin()) {
        return;
    }

    if (!headers_sent()) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
    }
    $title   = 'XOOPS is already installed';
    $message = 'This site is already installed, so the installer is locked for security. '
             . 'To reinstall, first remove mainfile.php (and, if you intend a clean install, drop the '
             . 'existing database tables), then run the installer again.';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>'
        . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</title></head><body><h1>'
        . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1><p>'
        . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p></body></html>';
    exit;
}

/**
 * @param string $name
 * @param string $value
 * @param string $label
 * @param string $help
 */
function xoFormField($name, $value, $label, $help = '')
{
    $label = installerHtmlSpecialChars($label);
    $name  = installerHtmlSpecialChars($name);
    $value = installerHtmlSpecialChars($value);
    echo '<div class="form-group">';
    echo '<label class="xolabel" for="' . $name . '">' . $label . '</label>';
    if ($help) {
        echo '<div class="xoform-help alert alert-info">' . $help . '</div>';
    }
    echo '<input type="text" class="form-control" name="'.$name.'" id="'.$name.'" value="'.$value.'">';
    echo '</div>';
}

/**
 * @param        $name
 * @param        $value
 * @param        $label
 * @param string $help
 */
function xoPassField($name, $value, $label, $help = '')
{
    $label = installerHtmlSpecialChars($label);
    $name  = installerHtmlSpecialChars($name);
    $value = installerHtmlSpecialChars($value);
    echo '<div class="form-group">';
    echo '<label class="xolabel" for="' . $name . '">' . $label . '</label>';
    if ($help) {
        echo '<div class="xoform-help alert alert-info">' . $help . '</div>';
    }
    if ($name === 'adminpass') {
        echo '<input type="password" class="form-control" name="'.$name.'" id="'.$name.'" value="'.$value.'"  onkeyup="passwordStrength(this.value)">';
    } else {
        echo '<input type="password" class="form-control" name="'.$name.'" id="'.$name.'" value="'.$value.'">';
    }
    echo '</div>';
}

/**
 * @param        $name
 * @param        $value
 * @param        $label
 * @param array  $options
 * @param string $help
 * @param        $extra
 */
function xoFormSelect($name, $value, $label, $options, $help = '', $extra='')
{
    $label = installerHtmlSpecialChars($label);
    $name  = installerHtmlSpecialChars($name);
    $value = installerHtmlSpecialChars($value);
    echo '<div class="form-group">';
    echo '<label class="xolabel" for="' . $name . '">' . $label . '</label>';
    if ($help) {
        echo '<div class="xoform-help alert alert-info">' . $help . '</div>';
    }
    echo '<select class="form-control" name="'.$name.'" id="'.$name.'" value="'.$value.'" '.$extra.'>';
    foreach ($options as $optionValue => $optionReadable) {
        $selected = ($value === $optionValue) ? ' selected' : '';
        echo '<option value="'.$optionValue . '"' . $selected . '>' . $optionReadable . '</option>';
    }
    echo '</select>';
    echo '</div>';
}

/*
 * gets list of name of directories inside a directory
 */
/**
 * @param $dirname
 *
 * @return array
 */
function getDirList($dirname)
{
    $dirlist = [];
    if ($handle = opendir($dirname)) {
        while ($file = readdir($handle)) {
            if ($file[0] !== '.' && is_dir($dirname . $file)) {
                $dirlist[] = $file;
            }
        }
        closedir($handle);
        asort($dirlist);
        reset($dirlist);
    }

    return $dirlist;
}

/**
 * @param        $status
 * @param string $str
 *
 * @return string
 */
function xoDiag($status = -1, $str = '')
{
    if ($status == -1) {
        $GLOBALS['error'] = true;
    }
    $classes = [-1 => 'fa-solid fa-ban text-danger', 0 => 'fa-solid fa-square text-warning', 1 => 'fa-solid fa-check text-success'];
    $strings = [-1 => FAILED, 0 => WARNING, 1 => SUCCESS];
    if (empty($str)) {
        $str = $strings[$status];
    }

    return '<span class="' . $classes[$status] . '"></span>' . $str;
}

/**
 * @param      $name
 * @param bool $wanted
 * @param bool $severe
 *
 * @return string
 */
function xoDiagBoolSetting($name, $wanted = false, $severe = false)
{
    $setting = (bool) ini_get($name);
    if ($setting === (bool) $wanted) {
        return xoDiag(1, $setting ? 'ON' : 'OFF');
    } else {
        return xoDiag($severe ? -1 : 0, $setting ? 'ON' : 'OFF');
    }
}

/**
 * seems to only be used for license file?
 * @param string $path dir or file path
 *
 * @return string
 */
function xoDiagIfWritable($path)
{
    $path  = '../' . $path;
    $error = true;
    if (!is_dir($path)) {
        if (file_exists($path) && !is_writable($path)) {
            @chmod($path, 0664);
            $error = !is_writable($path);
        }
    } else {
        if (!is_writable($path)) {
            @chmod($path, 0775);
            $error = !is_writable($path);
        }
    }

    return xoDiag($error ? -1 : 1, $error ? ' ' : ' ');
}

/**
 * @return string
 */
function xoPhpVersion()
{
    if (version_compare(phpversion(), '8.2.0', '>=')) {
        return xoDiag(1, phpversion());
    } else {
        return xoDiag(-1, phpversion());
    }
}

/**
 * Whether a mandatory extension is usable.
 *
 * For extensions with no fallback driver (e.g. mysqli) a plain
 * extension_loaded() is enough, but a partial/unusual build can report the
 * extension loaded while a specific symbol the caller needs (e.g.
 * mysqli_report) is absent — so optional functions/classes are verified too.
 *
 * @param string   $ext     extension name (e.g. 'mysqli')
 * @param string[] $symbols functions/classes that must also exist
 *
 * @return bool
 */
function xoInstallerExtensionAvailable(string $ext, array $symbols = []): bool
{
    if (!extension_loaded($ext)) {
        return false;
    }
    foreach ($symbols as $symbol) {
        // class_exists(.., false): don't trigger the autoloader during the
        // install bootstrap just to probe for an internal extension class.
        if (!function_exists($symbol) && !class_exists($symbol, false)) {
            return false;
        }
    }
    return true;
}

/**
 * Labels of the mandatory extensions that are not usable.
 *
 * Single source of truth shared by the requirements-page gate and the
 * server-side guards on later pages, so every entry point applies the same
 * rule against $configs['extensions_required'] (label + required symbols).
 *
 * @param XoopsInstallWizard $wizard
 *
 * @return string[] human-readable labels of missing extensions (empty = ok)
 */
function xoInstallerMissingRequired(XoopsInstallWizard $wizard): array
{
    $missing = [];
    foreach ($wizard->configs['extensions_required'] as $ext => $info) {
        [$label, $symbols] = $info;
        if (!xoInstallerExtensionAvailable($ext, $symbols)) {
            $missing[] = $label;
        }
    }
    return $missing;
}

/**
 * Build the "mandatory extension missing" alert markup.
 *
 * Returned (not echoed) so the caller can assign it to $content and render it
 * through the standard installer chrome at file scope.
 *
 * @param string $labels human-readable extension label or comma-separated
 *                       list of labels (callers pass the joined missing set)
 *
 * @return string
 */
function xoInstallerBlockedHtml(string $labels): string
{
    return '<div class="alert alert-danger" role="alert">'
        . '<h4 class="alert-heading"><span class="fa-solid fa-ban"></span> ' . MISSING_REQUIRED_EXTENSIONS . '</h4>'
        . '<p class="mb-0">'
        . installerHtmlSpecialChars(sprintf(MISSING_REQUIRED_EXTENSIONS_MSG, $labels))
        . '</p></div>';
}

/**
 * @param $path
 * @param $valid
 *
 * @return string
 */
function genPathCheckHtml($path, $valid)
{
    if ($valid) {
        switch ($path) {
            case 'root':
                $msg = sprintf(XOOPS_FOUND, XOOPS_VERSION);
                break;

            case 'lib':
            case 'data':
            default:
                $msg = XOOPS_PATH_FOUND;
                break;
        }

        return '<span class="pathmessage"><span class="fa-solid fa-check text-success"></span> ' . $msg . '</span>';
    } else {
        switch ($path) {
            case 'root':
                $msg = ERR_NO_XOOPS_FOUND;
                break;

            case 'lib':
            case 'data':
            default:
                $msg = ERR_COULD_NOT_ACCESS;
                break;
        }
        $GLOBALS['error'] = true;
        return '<div class="alert alert-danger"><span class="fa-solid fa-ban text-danger"></span> ' . $msg . '</div>';
    }
}

/**
 * @param $link
 *
 * @return mixed
 */
function getDbCharsets($link)
{
    static $charsets = [];
    if ($charsets) {
        return $charsets;
    }

    if ($result = mysqli_query($link, 'SHOW CHARSET')) {
        while ($row = mysqli_fetch_assoc($result)) {
            $charsets[$row['Charset']] = $row['Description'];
        }
    }

    return $charsets;
}

/**
 * @param $link
 * @param $charset
 *
 * @return mixed
 */
function getDbCollations($link, $charset)
{
    static $collations = [];
    if (!empty($collations[$charset])) {
        return $collations[$charset];
    }

    if ($result = mysqli_query($link, "SHOW COLLATION WHERE CHARSET = '" . mysqli_real_escape_string($link, $charset) . "'")) {
        while ($row = mysqli_fetch_assoc($result)) {
            $collations[$charset][$row['Collation']] = $row['Default'] ? 1 : 0;
        }
    }

    return $collations[$charset];
}

/**
 * @param $link
 * @param $charset
 * @param $collation
 *
 * @return null|string
 */
function validateDbCharset($link, $charset, &$collation)
{
    $error = null;

    if (empty($charset)) {
        $collation = '';
    }
    if (empty($charset) && empty($collation)) {
        return $error;
    }

    $charsets = getDbCharsets($link);
    if (!isset($charsets[$charset])) {
        $error = sprintf(ERR_INVALID_DBCHARSET, $charset);
    } elseif (!empty($collation)) {
        $collations = getDbCollations($link, $charset);
        if (!isset($collations[$collation])) {
            $error = sprintf(ERR_INVALID_DBCOLLATION, $collation);
        }
    }

    return $error;
}

/**
 * @param $name
 * @param $value
 * @param $label
 * @param $help
 * @param $link
 * @param $charset
 *
 * @return string
 */
function xoFormFieldCollation($name, $value, $label, $help, $link, $charset)
{
    if (empty($charset) || !$collations = getDbCollations($link, $charset)) {
        return '';
    }

    $options           = [];
    foreach ($collations as $key => $isDefault) {
        if ($isDefault) {  // 'Yes' or ''
            $options = [$key => $key . ' (Default)'] + $options;
        } else {
            $options[$key] = $key;
        }
    }

    return xoFormSelect($name, $value, $label, $options, $help);
}

/**
 * @param $name
 * @param $value
 * @param $label
 * @param $help
 * @param $link
 * @param $charset
 *
 * @return string
 */
function xoFormBlockCollation($name, $value, $label, $help, $link, $charset)
{
    return xoFormFieldCollation($name, $value, $label, $help, $link, $charset);
}

/**
 * @param        $name
 * @param        $value
 * @param        $label
 * @param string $help
 * @param        $link
 *
 * @return string
 */
function xoFormFieldCharset($name, $value, $label, $help, $link)
{
    if (!$charsets = getDbCharsets($link)) {
        return '';
    }
    foreach ($charsets as $k => $v) {
        $charsets[$k] = $v . ' (' . $k . ')';
    }
    asort($charsets);
    $label = installerHtmlSpecialChars($label);
    $name  = installerHtmlSpecialChars($name);
    $value = installerHtmlSpecialChars($value);
    $extra = 'onchange="setFormFieldCollation(\'DB_COLLATION\', this.value)"';
    return xoFormSelect($name, $value, $label, $charsets, $help, $extra);
}

/**
 * *#@+
 * Xoops Write Licence System Key
 * @param        $system_key
 * @param        $licensefile
 * @param string $license_file_dist
 * @return string
 */
function xoPutLicenseKey($system_key, $licensefile, $license_file_dist = 'license.dist.php')
{
    // If file exists, ensure it's writable first
    if (file_exists($licensefile)) {
        if (!is_writable($licensefile)) {
            // Try to make it writable
            if (!chmod($licensefile, 0666)) {
                return 'Error: Unable to make license file writable';
            }
        }
    } else {
        // Check if directory is writable
        $dir = dirname($licensefile);
        if (!is_writable($dir)) {
            return 'Error: Directory is not writable';
        }
    }

    // Open file with error checking
    $fver     = fopen($licensefile, 'w');
    if ($fver === false) {
        return 'Error: Unable to open license file for writing';
    }

    // Read distribution file with error checking
    if (!is_readable($license_file_dist)) {
        fclose($fver);
        return 'Error: Distribution license file is not readable';
    }

    $fver_buf = file($license_file_dist);
    if ($fver_buf === false) {
        fclose($fver);
        return 'Error: Unable to read distribution license file';
    }


    // Write the contents
    foreach ($fver_buf as $line => $value) {
        $ret = $value;
        if (strpos($value, 'XOOPS_LICENSE_KEY') > 0) {
            $ret = 'define(\'XOOPS_LICENSE_KEY\', \'' . $system_key . "');\n";
        }
        if (fwrite($fver, $ret) === false) {
            fclose($fver);
            return 'Error: Failed to write to license file';
        }
    }
    fclose($fver);

    // Set final permissions
    chmod($licensefile, 0444);

    return sprintf(WRITTEN_LICENSE, XOOPS_LICENSE_CODE, $system_key);
}

/**
 * *#@+
 * Xoops Build Licence System Key
 * @throws \Random\RandomException
 */
function xoBuildLicenceKey()
{
    $xoops_serdat = [];
    $checksums = [1 => 'md5', 2 => 'sha1'];
    $type      = random_int(1, 2);
    $func      = $checksums[$type];

    error_reporting(0);

    // Public Key
    if ($xoops_serdat['version'] = $func(XOOPS_VERSION)) {
        $xoops_serdat['version'] = substr($xoops_serdat['version'], 0, 6);
    }
    if ($xoops_serdat['licence'] = $func(XOOPS_LICENSE_CODE)) {
        $xoops_serdat['licence'] = substr($xoops_serdat['licence'], 0, 2);
    }
    if ($xoops_serdat['license_text'] = $func(XOOPS_LICENSE_TEXT)) {
        $xoops_serdat['license_text'] = substr($xoops_serdat['license_text'], 0, 2);
    }

    if ($xoops_serdat['domain_host'] = $func($_SERVER['HTTP_HOST'])) {
        $xoops_serdat['domain_host'] = substr($xoops_serdat['domain_host'], 0, 2);
    }

    // Private Key
    $xoops_serdat['file']     = $func(__FILE__);
    $xoops_serdat['basename'] = $func(basename(__FILE__));
    $xoops_serdat['path']     = $func(__DIR__);

    foreach ($_SERVER as $key => $data) {
        $xoops_serdat[$key] = substr($func(serialize($data)), 0, 4);
    }

    $xoops_key = '';
    foreach ($xoops_serdat as $key => $data) {
        $xoops_key .= $data;
    }
    while (strlen($xoops_key) > 40) {
        $lpos      = random_int(18, strlen($xoops_key));
        $xoops_key = substr($xoops_key, 0, $lpos) . substr($xoops_key, $lpos + 1, strlen($xoops_key) - ($lpos + 1));
    }

    return xoStripeKey($xoops_key);
}

/**
 * *#@+
 * Xoops Stripe Licence System Key
 * @param $xoops_key
 * @return mixed|string
 */
function xoStripeKey($xoops_key)
{
    $uu     = 0;
    $num    = 6;
    $length = 30;
    $strip  = floor(strlen($xoops_key) / 6);
    $strlen = strlen($xoops_key);
    $ret = '';
    for ($i = 0; $i < $strlen; ++$i) {
        if ($i < $length) {
            ++$uu;
            if ($uu == $strip) {
                $ret .= substr($xoops_key, $i, 1) . '-';
                $uu = 0;
            } else {
                if (substr($xoops_key, $i, 1) != '-') {
                    $ret .= substr($xoops_key, $i, 1);
                } else {
                    $uu--;
                }
            }
        }
    }
    $ret = str_replace('--', '-', $ret);
    if (substr($ret, 0, 1) == '-') {
        $ret = substr($ret, 2, strlen($ret));
    }
    if (substr($ret, strlen($ret) - 1, 1) == '-') {
        $ret = substr($ret, 0, strlen($ret) - 1);
    }

    return $ret;
}


/**
 * @return string
 */
function writeLicenseKey()
{
    return xoPutLicenseKey(xoBuildLicenceKey(), XOOPS_VAR_PATH . '/data/license.php', __DIR__ . '/license.dist.php');
}
