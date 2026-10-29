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
        $this->assertStringContainsString("if (!in_array(\$com_mode, ['flat', 'thread', 'nest'], true)) {", self::src($file));
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
        $this->assertStringContainsString("\$imageMimetype = \$srcInfo['mime'];", $src);
        $this->assertStringContainsString("header('X-Content-Type-Options: nosniff');", $src);
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
        $this->assertLessThan(strpos($final, '$block_handler->delete($block)'), $refusal);
        $this->assertStringContainsString("in_array(\$storedType, ['S', 'M'], true) ? \$storedType : Request::getString('block_type', '')", $src);
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
        $this->assertStringContainsString('if (0 === strcasecmp($prefix, XOOPS_DB_PREFIX)) {', $src);
        $this->assertStringContainsString("\$result = \$db->query('SELECT @@lower_case_table_names');", $src);
        $skip = strpos($src, "if (str_starts_with(\$fold(\$table), \$fold(XOOPS_DB_PREFIX . '_'))) { continue; }");
        $this->assertNotFalse($skip);
        $drop = strpos($src, '$drs = $db->exec("DROP TABLE `$table`");');
        $this->assertNotFalse($drop);
        $this->assertLessThan($drop, $skip);
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
}
