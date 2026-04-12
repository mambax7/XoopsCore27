<?php
/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/

use Xoops\Upgrade\XoopsUpgrade;
use Xoops\Upgrade\UpgradeControl;

/**
 * Upgrader from 2.4.0 to 2.4.1
 *
 * See the enclosed file license.txt for licensing information.
 * If you did not receive this file, get it at https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @copyright    (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license          GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package          upgrader
 * @since            2.4.0
 * @author           Taiwen Jiang <phppp@users.sourceforge.net>
 * @author           trabis <lusopoemas@gmail.com>
 */
class Upgrade_241 extends XoopsUpgrade
{
    /**
     * @return bool
     */
    public function check_license(): bool
    {
        if (!defined('XOOPS_LICENSE_KEY')) {
            return false;
        }

        $licenseKey = trim((string) constant('XOOPS_LICENSE_KEY'));

        return '' !== $licenseKey && '000000-000000-000000-000000-000000' !== $licenseKey;
    }

    /**
     * @return bool|string
     */
    public function apply_license(): bool|string
    {
        set_time_limit(120);
        $licenseFile = XOOPS_ROOT_PATH . '/include/license.php';
        $licenseDir  = dirname($licenseFile);
        if (!is_writable($licenseFile) || !is_writable($licenseDir)) {
            echo "<p><span style='color:#ff0000;'>&nbsp;include/license.php and its parent directory must be writable by the web server.</span></p>";

            return false;
        }
        if (XOOPS_LICENSE_KEY == '000000-000000-000000-000000-000000') {
            $result = $this->xoops_putLicenseKey($this->xoops_buildLicenceKey(), $licenseFile, __DIR__ . '/license.dist.php');
        } else {
            $result = $this->xoops_upgradeLicenseKey($this->xoops_getPublicLicenceKey(), $licenseFile, __DIR__ . '/license.dist.php');
        }
        if (false === $result) {
            $this->logs[] = 'License key write failed';
            return false;
        }
        return (bool) $result;
    }

    /**
     * *#@+
     * Xoops Write Licence System Key
     */
    public function xoops_upgradeLicenseKey($public_key, $licensefile, $license_file_dist = 'license.dist.php')
    {
        $fver_buf = file($license_file_dist);
        if (false === $fver_buf) {
            return false;
        }
        $license_key = $public_key . substr(XOOPS_LICENSE_KEY, 13, strlen(XOOPS_LICENSE_KEY) - 13);
        $content = '';
        foreach ($fver_buf as $line => $value) {
            if (strpos($value, 'XOOPS_LICENSE_KEY') > 0) {
                $content .= 'define(\'XOOPS_LICENSE_KEY\', \'' . $license_key . "');";
            } else {
                $content .= $value;
            }
        }

        $tmpFile = tempnam(dirname($licensefile), 'tmp_license_');
        if (false === $tmpFile) {
            return false;
        }

        $fver = fopen($tmpFile, 'wb');
        if (false === $fver) {
            @unlink($tmpFile);

            return false;
        }

        $written = fwrite($fver, $content);
        if ($written !== strlen($content)) {
            fclose($fver);
            @unlink($tmpFile);

            return false;
        }
        fflush($fver);
        fclose($fver);

        if (!@rename($tmpFile, $licensefile)) {
            @unlink($tmpFile);

            return false;
        }

        chmod($licensefile, 0444);

        return 'Written License Key: ' . $license_key;
    }

    /**
     * *#@+
     * Xoops Write Licence System Key
     */
    public function xoops_putLicenseKey($system_key, $licensefile, $license_file_dist = 'license.dist.php')
    {
        $fver_buf = file($license_file_dist);
        if (false === $fver_buf) {
            return false;
        }
        $content = '';
        foreach ($fver_buf as $line => $value) {
            if (strpos($value, 'XOOPS_LICENSE_KEY') > 0) {
                $content .= 'define(\'XOOPS_LICENSE_KEY\', \'' . $system_key . "');";
            } else {
                $content .= $value;
            }
        }

        $tmpFile = tempnam(dirname($licensefile), 'tmp_license_');
        if (false === $tmpFile) {
            return false;
        }

        $fver = fopen($tmpFile, 'wb');
        if (false === $fver) {
            @unlink($tmpFile);

            return false;
        }

        $written = fwrite($fver, $content);
        if ($written !== strlen($content)) {
            fclose($fver);
            @unlink($tmpFile);

            return false;
        }
        fflush($fver);
        fclose($fver);

        if (!@rename($tmpFile, $licensefile)) {
            @unlink($tmpFile);

            return false;
        }

        chmod($licensefile, 0444);

        return 'Written License Key: ' . $system_key;
    }

    /**
     * *#@+
     * Xoops Get Public Checkbit from Licence System Key
     */
    public function xoops_getPublicLicenceKey()
    {
        $xoops_key    = '';
        $xoops_serdat = [];
        $checksums    = [1 => 'md5', 2 => 'sha1'];

        // Remember to upgrade versions string with each release there after.
        $versions = ['XOOPS 2.4.0', 'XOOPS 2.4.1'];

        error_reporting(E_ALL);
        foreach ($checksums as $funcid => $func) {
            foreach ($versions as $versionid => $version) {
                if ($xoops_serdat['version'] = $func($version) && substr(XOOPS_LICENSE_KEY, 0, 6) === substr($func($version), 0, 6)) {
                    $xoops_serdat['version'] = substr($xoops_serdat['version'], 0, 6);
                    $checkbit                = $func;
                }
            }
        }
        if (isset($checkbit)) {
            if ($xoops_serdat['licence'] = $checkbit(XOOPS_LICENSE_CODE)) {
                $xoops_serdat['licence'] = substr($xoops_serdat['licence'], 0, 2);
            }
            if ($xoops_serdat['license_text'] = $checkbit(XOOPS_LICENSE_TEXT)) {
                $xoops_serdat['license_text'] = substr($xoops_serdat['license_text'], 0, 2);
            }

            if ($xoops_serdat['domain_host'] = $checkbit($_SERVER['HTTP_HOST'])) {
                $xoops_serdat['domain_host'] = substr($xoops_serdat['domain_host'], 0, 2);
            }
        }
        foreach ($xoops_serdat as $key => $data) {
            $xoops_key .= $data;
        }

        return $this->xoops_stripeKey($xoops_key, 6, 13, 0);
    }

    /**
     * *#@+
     * Xoops Build Licence System Key
     */
    public function xoops_buildLicenceKey()
    {
        $xoops_serdat = [];
        $checksums = [1 => 'md5', 2 => 'sha1'];
        $type      = mt_rand(1, 2);
        $func      = $checksums[$type];
        $xoops_key = '';

        error_reporting(E_ALL);

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

        foreach ($xoops_serdat as $key => $data) {
            $xoops_key .= $data;
        }
        while (strlen($xoops_key) > 40) {
            $lpos      = mt_rand(18, strlen($xoops_key));
            $xoops_key = substr($xoops_key, 0, $lpos) . substr($xoops_key, $lpos + 1, strlen($xoops_key) - ($lpos + 1));
        }

        return $this->xoops_stripeKey($xoops_key);
    }

    /**
     * *#@+
     * Xoops Stripe Licence System Key
     */
    public function xoops_stripeKey($xoops_key, $num = 6, $length = 30, $uu = 0)
    {
        $strip = floor(strlen($xoops_key) / 6);
        $ret   = '';
        for ($i = 0; $i < strlen($xoops_key); ++$i) {
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

    public function __construct(XoopsMySQLDatabase $db, UpgradeControl $control)
    {
        parent::__construct($db, $control, basename(__DIR__));
        $this->tasks = ['license'];
    }
}

return Upgrade_241::class;
