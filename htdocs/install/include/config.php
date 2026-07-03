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

if (!defined('XOOPS_INSTALL')) {
    die('XOOPS Custom Installation die');
}

$configs = [];

// setup config site info
$configs['db_types'] = ['mysql' => 'mysql'];

// setup config site info
$configs['conf_names'] = [
    'sitename',
    'slogan',
    'allow_register',
    'meta_keywords',
    'meta_description',
    'meta_author',
    'meta_copyright',
    'closesite',
    'debug_mode',
];

// languages config files
$configs['language_files'] = [
    'global',
];

// extension_loaded
$configs['extensions'] = [
    'mbstring' => [
        'MBString',
        sprintf(PHP_EXTENSION, CHAR_ENCODING),
    ],
    'intl'     => [
        'Intl',
        sprintf(PHP_EXTENSION, INTL_SUPPORT),
    ],
    'iconv'    => [
        'Iconv',
        sprintf(PHP_EXTENSION, ICONV_CONVERSION),
    ],
    'xml'      => [
        'XML',
        sprintf(PHP_EXTENSION, XML_PARSING),
    ],
    'zlib'     => [
        'Zlib',
        sprintf(PHP_EXTENSION, ZLIB_COMPRESSION),
    ],
    'gd'       => [
        (function_exists('gd_info') && $gdlib = @gd_info()) ? 'GD ' . $gdlib['GD Version'] : '',
        sprintf(PHP_EXTENSION, IMAGE_FUNCTIONS),
    ],
    'exif'     => [
        'Exif',
        sprintf(PHP_EXTENSION, IMAGE_METAS),
    ],
    'curl'     => [
        'Curl',
        sprintf(PHP_EXTENSION, CURL_HTTP),
    ],
];

// Mandatory extensions — installation cannot proceed without these.
// Keyed by extension name; each value is a tuple
// [human-readable label, [symbols that must also exist]]. mysqli has no
// fallback driver, so a missing entry here would otherwise fatal later in
// the DB-connection step; the others are core extensions with no polyfill.
// mbstring is intentionally NOT listed: the installer autoloads
// symfony/polyfill-mbstring (via common.inc.php), so the mb_* functions
// remain available even without the extension — gating on
// extension_loaded('mbstring') would wrongly block a working install. It
// stays in the recommended-extensions list below.
//
// The symbol list is the single source of truth shared by the
// requirements-page gate and the server-side guards, so both apply
// identical rules: a partial build that reports mysqli loaded but lacks
// mysqli_report()/the mysqli class is treated as missing in both places.
$configs['extensions_required'] = [
    'mysqli'   => ['MySQLi', ['mysqli_report', 'mysqli']],
    'session'  => ['Session', []],
    'pcre'     => ['PCRE', []],
    'filter'   => ['filter', []],
    'fileinfo' => ['fileinfo', []],
];

// Writable files and directories
$configs['writable'] = [
    'uploads/',
    'uploads/avatars/',
    'uploads/files/',
    'uploads/images/',
    'uploads/ranks/',
    'uploads/smilies/',
];

// Modules to be installed by default
$configs['modules'] = [];

// xoops_lib, xoops_data directories
$configs['xoopsPathDefault'] = [
    'data' => 'xoops_data',
    'lib'  => 'xoops_lib',
];

// writable xoops_lib, xoops_data directories
$configs['dataPath'] = [
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

return $configs;
