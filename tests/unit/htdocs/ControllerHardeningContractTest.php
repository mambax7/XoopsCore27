<?php
/*
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

declare(strict_types=1);

namespace htdocs;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Page controllers that cannot be run in isolation: pins the input checks,
 * output escaping and authorization rules they must keep.
 *
 * @category  Xoops
 * @package   Core
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversNothing]
final class ControllerHardeningContractTest extends TestCase
{
    private static function src(string $file): string
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/' . $file);
        self::assertNotFalse($src, $file);

        return (string) preg_replace('/\s+/', ' ', $src);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function commentControllers(): array
    {
        return [
            'view'  => ['include/comment_view.php'],
            'new'   => ['include/comment_new.php'],
            'edit'  => ['include/comment_edit.php'],
            'reply' => ['include/comment_reply.php'],
            'post'  => ['include/comment_post.php'],
        ];
    }

    #[Test]
    #[DataProvider('commentControllers')]
    public function commentModeIsLimitedToTheRealModes(string $file): void
    {
        // com_mode reaches links and an inline onclick handler.
        $src   = self::src($file);
        $guard = strpos($src, "if (!in_array(\$com_mode, ['flat', 'thread', 'nest'], true)) {");
        $this->assertNotFalse($guard);
        // The guard precedes the first place the value leaves the controller
        // (a link, a template variable, or the comment form's hidden field).
        $sinks = array_filter([
            strpos($src, '. $com_mode'),
            strpos($src, '$com_mode ==='),
            strpos($src, "assign('comment_mode'"),
            strpos($src, "path('include/comment_form.php')"),
        ], 'is_int');
        $this->assertNotEmpty($sinks, $file);
        $this->assertLessThan(min($sinks), $guard, $file);
    }

    #[Test]
    public function installerOutputIsEscapedForItsContext(): void
    {
        $this->assertStringContainsString(
            'htmlspecialchars(json_encode($wizard->pageURI("+1"), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES)',
            self::src('install/include/install_tpl.php')
        );
        $this->assertStringContainsString('htmlspecialchars((string) $v, ENT_QUOTES)', self::src('install/page_configsave.php'));

        $makedata = self::src('install/include/makedata.php');
        $this->assertStringNotContainsString("'\" . XOOPS_URL . \"", $makedata, 'XOOPS_URL is quoted by the database layer');
        $this->assertStringContainsString('$dbm->db->quote(XOOPS_URL . \'/\')', $makedata);
        $this->assertStringContainsString('$dbm->db->quote($adminname)', $makedata);
    }

    #[Test]
    public function storedOrRemoteValuesAreEscapedInMarkup(): void
    {
        $this->assertStringContainsString(
            'htmlspecialchars((string) $this->multimailer->ErrorInfo, ENT_QUOTES | ENT_SUBSTITUTE, \'UTF-8\')',
            self::src('class/xoopsmailer.php')
        );
        $this->assertStringContainsString(
            'value=\"" . htmlspecialchars((string) $business, ENT_QUOTES | ENT_SUBSTITUTE, \'UTF-8\') . "\\">',
            self::src('Frameworks/moduleclasses/moduleadmin/moduleadmin.php')
        );
        $this->assertStringContainsString(
            "'<a href=\"mailto:' . htmlspecialchars((string) \$userinfo['email'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')",
            self::src('modules/system/blocks/system_blocks.php')
        );
        $this->assertStringContainsString("1 === preg_match('#^https?://#i', (string) \$thisUser->getVar('url', 'n'))", self::src('userinfo.php'));
    }

    #[Test]
    public function originalImagesAreServedAsTheirDecodedType(): void
    {
        $src = self::src('image.php');
        // Image manager: the stored (client-declared) type is read first, then
        // both storage types (db blob, upload-folder file) fall through to one
        // decode of the bytes that replaces it.
        $stored     = strpos($src, "\$imageMimetype = \$image->getVar('image_mimetype');");
        $dbBlob     = strpos($src, "\$imageData = \$image->getVar('image_body');");
        $uploadFile = strpos($src, '$imageData = file_get_contents($imagePath); } // Cap the raw blob');
        $decoded    = strpos($src, "\$imageMimetype = \$srcInfo['mime'];");
        // Local URL: decoded by getimagesize() on the file.
        $localUrl = strpos($src, "\$imageMimetype = \$imagesize['mime'];");
        foreach ([$stored, $dbBlob, $uploadFile, $decoded, $localUrl] as $position) {
            $this->assertNotFalse($position);
        }
        $this->assertLessThan($dbBlob, $stored);
        $this->assertLessThan($decoded, $dbBlob);
        $this->assertLessThan($decoded, $uploadFile);
        // The original is sent with the decoded type, and that header is followed by nosniff.
        $served = strpos($src, 'doConditionalGet($etag, $last_modified_string); header("Content-type: {$imageMimetype}"); header(\'X-Content-Type-Options: nosniff\');');
        $this->assertNotFalse($served);
        $this->assertLessThan($served, max($decoded, $localUrl));
        // A cached edited image is sent with the type it was encoded as (a GIF
        // is cached as PNG), decoded from the cached bytes for older entries.
        $this->assertStringContainsString("\$cached_image['image_mimetype'] = \$imageMimetype;", $src);
        $this->assertStringContainsString("\$cachedMimetype = \$cached_image['image_mimetype'] ?? null;", $src);
        // Every response of the script carries nosniff: cache hit and edited
        // image as well as the original.
        $this->assertStringContainsString('header("Content-type: {$cachedMimetype}"); header(\'X-Content-Type-Options: nosniff\');', $src);
        $this->assertStringContainsString('header("Content-type: {$imageMimetype}"); header(\'X-Content-Type-Options: nosniff\'); header("Content-disposition: filename={$imageFilename}");', $src);
        // The blank placeholder (no source, missing image, missing category) is
        // sent from one place, and that place sends nosniff too.
        $this->assertSame(1, substr_count($src, "header('Content-type: image/gif');"));
        $this->assertSame(1, substr_count($src, 'blank.gif'));
        $this->assertStringContainsString("header('Content-type: image/gif'); header('X-Content-Type-Options: nosniff'); readfile(XOOPS_UPLOAD_PATH . '/blank.gif');", $src);
        $this->assertSame(3, substr_count($src, 'exitBlankImage();'));
    }

    #[Test]
    public function mailRecipientsAreUserIds(): void
    {
        $src = self::src('modules/system/admin/mailusers/main.php');
        $this->assertStringContainsString("array_map('intval', Request::getArray('memberslist_id', [], 'POST'))", $src);
        $this->assertStringContainsString("foreach (array_map('intval', \$mail_to_user) as \$to_user)", $src);
    }

    #[Test]
    public function onlyAWebmasterSavesAWebmasterInProfileAdmin(): void
    {
        $src  = self::src('modules/profile/admin/user.php');
        $save = substr($src, (int) strpos($src, "case 'save':"));
        $load = strpos($save, '$user = $handler->getUser($uid);');
        $rule = strpos($save, 'in_array(XOOPS_GROUP_ADMIN, $user->getGroups()) && !in_array(XOOPS_GROUP_ADMIN, $GLOBALS[\'xoopsUser\']->getGroups())');
        $this->assertNotFalse($load);
        $this->assertNotFalse($rule);
        $this->assertLessThan(strpos($save, '$handler->insertUser($user)'), $rule);
    }

    #[Test]
    public function groupMembershipNeedsGroupsSectionRights(): void
    {
        $this->assertStringContainsString(
            "\$gperm_handler->checkRight('system_admin', XOOPS_SYSTEM_GROUP,",
            self::src('modules/system/admin/users/users.php')
        );
        $main = self::src('modules/system/admin/users/main.php');
        $this->assertStringContainsString("\$groups = system_users_canManageGroups() ? array_map('intval', Request::getArray('groups', [])) : [];", $main);
        // Only a webmaster edits a webmaster (else a delegated admin sets its password).
        $this->assertStringContainsString(
            'return !in_array(XOOPS_GROUP_ADMIN, $target->getGroups())',
            self::src('modules/system/admin/users/users.php')
        );
        $save  = substr($main, (int) strpos($main, "case 'users_save':"));
        $guard = strpos($save, 'if (!system_users_mayEdit($edituser)) {');
        $this->assertNotFalse($guard);
        $this->assertLessThan(strpos($save, "\$edituser->setVar('pass'"), $guard);
        $this->assertStringContainsString('if (is_object($target) && !system_users_mayEdit($target)) {', $main);
        $this->assertStringContainsString("\$groups = system_users_canManageGroups() ? Request::getArray('groups', []) : [XOOPS_GROUP_USERS];", $main);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function standaloneEndpoints(): array
    {
        return [
            'templates' => ['modules/system/admin/tplsets/jquery.php', 'XOOPS_SYSTEM_TPLSET'],
            'users'     => ['modules/system/admin/users/jquery.php', 'XOOPS_SYSTEM_USER'],
        ];
    }

    #[Test]
    #[DataProvider('standaloneEndpoints')]
    public function standaloneEndpointsCheckTheirSection(string $file, string $section): void
    {
        $src   = self::src($file);
        $check = strpos($src, "checkRight('system_admin', {$section}, \$xoopsUser->getGroups(), \$xoopsModule->getVar('mid'))");
        $this->assertNotFalse($check);
        $this->assertLessThan(strpos($src, 'switch ($op)'), $check);
    }

    #[Test]
    public function protectedBlocksSurviveADirectDelete(): void
    {
        $src     = self::src('modules/system/admin/blocksadmin/main.php');
        $final   = substr($src, (int) strpos($src, "case 'delete_ok':"));
        $refusal = strpos($final, '$block_handler->deletionRefusal($block)');
        $this->assertNotFalse($refusal);
        $this->assertLessThan(strpos($final, '$block_handler->deleteGuarded($block)'), $refusal);
        $this->assertStringNotContainsString('$block_handler->delete($block)', $final);
        $this->assertStringContainsString("if (!in_array(\$block_type, ['C', 'D'], true)) {", $src);
        $this->assertStringContainsString("\$block_type = (string) \$block->getVar('block_type');", $src);
    }

    #[Test]
    public function aTwoFactorResetHonoursTheWebmasterGuard(): void
    {
        $main  = self::src('modules/system/admin/users/main.php');
        $reset = strpos($main, "if ('users_2fa_reset' === \$op) {");
        $guard = strpos($main, 'if (is_object($target) && !system_users_mayEdit($target)) {', (int) $reset);
        $this->assertNotFalse($guard);
        $this->assertLessThan(strpos($main, "include XOOPS_ROOT_PATH . '/include/manage2fa.php';"), $guard);
        // Same request source as manage2fa.php, so the guarded uid is the reset uid.
        $this->assertStringContainsString("Request::getInt('uid', 0, 'POST' === Request::getMethod() ? 'POST' : 'GET')", $main);
    }

    #[Test]
    public function theSystemModuleCannotBeDeactivated(): void
    {
        $this->assertStringContainsString(
            "if (!is_object(\$module) || \$module->getVar('dirname', 'n') === 'system') { break; }",
            self::src('modules/system/admin/modulesadmin/main.php')
        );
    }

    #[Test]
    public function profileSelfDeleteChecksTheToken(): void
    {
        $src    = self::src('modules/profile/user.php');
        $delete = substr($src, (int) strpos($src, "if (\$op === 'delete')"));
        $check  = strpos($delete, '$GLOBALS[\'xoopsSecurity\']->check()');
        $this->assertNotFalse($check);
        $this->assertLessThan(strpos($delete, '$member_handler->deleteUser('), $check);
    }

    #[Test]
    public function prefixManagerNeverDropsWorkingTables(): void
    {
        $src  = self::src('xoops_lib/modules/protector/admin/prefix_manager.php');
        // Compared as the server compares names: with lower_case_table_names 0, XOOPS is not xoops.
        $this->assertStringContainsString('if ($fold($prefix) === $fold(XOOPS_DB_PREFIX)) {', $src);
        $this->assertStringContainsString("\$result = \$db->query('SELECT @@lower_case_table_names');", $src);
        // Only a readable 0 compares case-sensitively; 1, 2 and an unreadable setting fold case (skips more).
        $this->assertStringContainsString("\$fold = (false !== \$row && 0 === (int) \$row[0]) ? 'strval' : 'strtolower';", $src);
        $skip = strpos($src, "if (str_starts_with(\$fold(\$table), \$fold(XOOPS_DB_PREFIX . '_'))) { continue; }");
        $this->assertNotFalse($skip);
        $drop = strpos($src, '$drs = $db->exec("DROP TABLE `$table`");');
        $this->assertNotFalse($drop);
        $this->assertLessThan($drop, $skip);
    }

    #[Test]
    public function prefixManagerRefusesPrefixesThatOverlapTheWorkingOne(): void
    {
        // xoops_backup under a working prefix xoops: its tables all pass the
        // working-prefix skip above, so a delete would report success and drop nothing.
        // xoops under a working prefix xoops_test: the delete would drop every
        // xoops_* table except the working ones. Both directions are refused.
        $src = self::src('xoops_lib/modules/protector/admin/prefix_manager.php');
        $this->assertStringContainsString(
            "function prefixOverlapsWorkingPrefix(string \$prefix, string \$fold): bool { \$candidate = \$fold(\$prefix . '_'); \$working = \$fold(XOOPS_DB_PREFIX . '_'); return str_starts_with(\$candidate, \$working) || str_starts_with(\$working, \$candidate); }",
            $src
        );
        $copyGuard = strpos($src, 'if (prefixOverlapsWorkingPrefix($new_prefix, $fold)) { die(');
        $this->assertNotFalse($copyGuard);
        $this->assertLessThan(strpos($src, '$crs = $db->exec($create_sql);'), $copyGuard);
        $deleteGuard = strpos($src, 'if (prefixOverlapsWorkingPrefix($prefix, $fold)) { die(');
        $this->assertNotFalse($deleteGuard);
        $this->assertLessThan(strpos($src, '$drs = $db->exec("DROP TABLE `$table`");'), $deleteGuard);
        $this->assertStringContainsString("if (\$prefix['name'] == XOOPS_DB_PREFIX || prefixOverlapsWorkingPrefix(\$prefix['name'], \$fold)) { \$del_button = '';", $src);
    }

    #[Test]
    public function authenticationRefusesInactiveAccountsAndDoubleEscaping(): void
    {
        $this->assertStringContainsString('if (!is_object($this->user) || !$this->user->isActive()) {', self::src('class/xml/rpc/xmlrpcapi.php'));
        $util = self::src('class/userutility.php');
        $this->assertStringNotContainsString('quote(addslashes(', $util);
    }

    #[Test]
    public function anUnavailableCaptchaModeFallsBackToText(): void
    {
        $this->assertStringContainsString(
            "if (!\$handler->isActive() && 'XoopsCaptchaText' !== \$class) {",
            self::src('class/captcha/xoopscaptcha.php')
        );
    }

    #[Test]
    public function statisticsUseOnlyTheValidatedGroupList(): void
    {
        $src = self::src('class/model/stats.php');
        $this->assertStringNotContainsString('$criteria->groupby', $src);
        $this->assertSame(2, substr_count($src, "preg_replace('/^ GROUP BY /', '', \$criteria->getGroupby())"));
    }

    #[Test]
    public function thePreviewNeverDecodesRenderedOutput(): void
    {
        // PHP decodes the form body once. A second decode, before or after
        // displayTarea(), turns literal %3Cscript%3E text into markup.
        $src  = self::src('include/formdhtmltextarea_preview.php');
        $tail = substr($src, (int) strpos($src, '$myts->displayTarea('));
        $this->assertStringContainsString('$myts->displayTarea(', $src);
        $this->assertDoesNotMatchRegularExpression('/(raw)?urldecode\(/', $src);
        $this->assertDoesNotMatchRegularExpression(
            '/html_entity_decode\(|htmlspecialchars_decode\(|undoHtmlSpecialChars\(/',
            $tail
        );
    }
}
