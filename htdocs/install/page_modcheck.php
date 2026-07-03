<?php
/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/
/**
 * Installer configuration check page
 *
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

require_once __DIR__ . '/include/common.inc.php';
defined('XOOPS_INSTALL') || die('XOOPS Installation wizard die');

$pageHasForm = false;

// Mandatory extensions: installation cannot proceed without these (e.g.
// mysqli has no fallback driver and would fatal on the DB-connection step),
// so a missing one blocks the Next button.
$missingRequired = xoInstallerMissingRequired($wizard);
$blockNext       = !empty($missingRequired);

// Keep the MySQLi requirements row consistent with the gate: use the same
// configured symbol list so the row cannot show success while Next is blocked
// (mysqli loaded but mysqli_report()/the mysqli class absent).
$mysqliSymbols   = $wizard->configs['extensions_required']['mysqli'][1] ?? [];
$mysqliAvailable = xoInstallerExtensionAvailable('mysqli', $mysqliSymbols);
$mysqliInfo      = $mysqliAvailable && function_exists('mysqli_get_client_info') ? mysqli_get_client_info() : '';

foreach ($wizard->configs['extensions'] as $ext => $value) {
    if (extension_loaded($ext)) {
        if (is_array($value[0])) {
            $wizard->configs['extensions'][$ext][] = xoDiag(1, implode(',', $value[0]));
        } else {
            $wizard->configs['extensions'][$ext][] = xoDiag(1, $value[0]);
        }
    } else {
        $wizard->configs['extensions'][$ext][] = xoDiag(0, $value[0]);
    }
}
ob_start();
?>
    <?php if ($blockNext): ?>
        <?php echo xoInstallerBlockedHtml(implode(', ', $missingRequired)); ?>
    <?php endif; ?>

    <h3><?php echo REQUIREMENTS; ?></h3>
    <table class="table table-hover">
        <tbody>
        <tr>
            <th><?php echo SERVER_API; ?></th>
            <td><?php echo php_sapi_name(); ?><br> <?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
        </tr>

        <tr>
            <th><?php echo _PHP_VERSION; ?></th>
            <td><?php echo xoPhpVersion(); ?></td>
        </tr>

        <tr>
            <th><?php printf(PHP_EXTENSION, 'MySQLi'); ?></th>
            <td><?php echo xoDiag($mysqliAvailable ? 1 : -1, $mysqliInfo); ?></td>
        </tr>

        <tr>
            <th><?php printf(PHP_EXTENSION, 'Session'); ?></th>
            <td><?php echo xoDiag(extension_loaded('session') ? 1 : -1); ?></td>
        </tr>

        <tr>
            <th><?php printf(PHP_EXTENSION, 'PCRE'); ?></th>
            <td><?php echo xoDiag(extension_loaded('pcre') ? 1 : -1); ?></td>
        </tr>

        <tr>
            <th><?php printf(PHP_EXTENSION, 'filter'); ?></th>
            <td><?php echo xoDiag(extension_loaded('filter') ? 1 : -1); ?></td>
        </tr>

        <tr>
            <th scope="row">file_uploads</th>
            <td><?php echo xoDiagBoolSetting('file_uploads', true); ?></td>
        </tr>

        <tr>
            <th><?php printf(PHP_EXTENSION, 'fileinfo'); ?></th>
            <td><?php echo xoDiag(extension_loaded('fileinfo') ? 1 : -1); ?></td>
        </tr>
        </tbody>
    </table>

    <h3><?php echo RECOMMENDED_EXTENSIONS; ?></h3>
    <table class="table table-hover">
        <caption><?php echo RECOMMENDED_EXTENSIONS_MSG; ?></caption>
        <tbody>
        <?php
        foreach ($wizard->configs['extensions'] as $key => $value) {
            echo '<tr><th>' . $value[2] . '</th><td>' . $value[1] . '</td></tr>';
        }
        ?>

        </tbody>
    </table>
<?php
$content = ob_get_contents();
ob_end_clean();

include __DIR__ . '/include/install_tpl.php';
