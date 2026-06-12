<?php

declare(strict_types=1);

namespace hardening;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Each state-changing admin action must validate a XOOPS token before it mutates
 * (findings H-2, H-3, H-4, M-1). These procedural handlers cannot be executed in
 * isolation, so the guard is asserted at the source level: the token check must
 * be present AND appear before the first mutating call in that branch.
 */
final class CsrfTokenGuardTest extends TestCase
{
    private function extractCaseBlock(string $source, string $caseLabel): string
    {
        $pattern = "/case\\s+'" . preg_quote($caseLabel, '/') . "'\\s*:/";
        if (!preg_match($pattern, $source, $m, PREG_OFFSET_CAPTURE)) {
            self::fail("case '{$caseLabel}' not found");
        }
        $start = (int) $m[0][1];
        if (preg_match("/\\n\\s*(case\\s+'|default\\s*:)/", $source, $n, PREG_OFFSET_CAPTURE, $start + 1)) {
            return substr($source, $start, ((int) $n[0][1]) - $start);
        }
        return substr($source, $start);
    }

    private function assertGuardBeforeMutation(string $block, string $mutation, string $label): void
    {
        $guardPos = strpos($block, "xoopsSecurity']->check(");
        $mutPos   = strpos($block, $mutation);
        self::assertNotFalse($guardPos, "{$label}: missing token check");
        self::assertNotFalse($mutPos, "{$label}: mutation needle '{$mutation}' not found");
        self::assertLessThan($mutPos, $guardPos, "{$label}: token check must precede the mutation");
    }

    /** @return array<string, array{string, string, string}> */
    public static function guardedCases(): array
    {
        $base = XOOPS_ROOT_PATH;
        return [
            'groups action_group' => [$base . '/modules/system/admin/groups/main.php',   'action_group', 'addUserToGroup'],
            'mailusers send'      => [$base . '/modules/system/admin/mailusers/main.php', 'send',         '->send('],
            'comment delete_one'  => [$base . '/include/comment_delete.php',              'delete_one',   '->delete('],
            'comment delete_all'  => [$base . '/include/comment_delete.php',              'delete_all',   '->delete('],
            'users_active'        => [$base . '/modules/system/admin/users/main.php',     'users_active', 'insertUser'],
            'users_synchronize'   => [$base . '/modules/system/admin/users/main.php',     'users_synchronize', 'synchronize('],
            'profile step toggle' => [$base . '/modules/profile/admin/step.php',          'toggle',       'profile_stepsave_toggle'],
        ];
    }

    #[Test]
    #[DataProvider('guardedCases')]
    public function tokenCheckPrecedesMutation(string $file, string $case, string $mutation): void
    {
        $src = file_get_contents($file);
        self::assertNotFalse($src, $file);
        $this->assertGuardBeforeMutation($this->extractCaseBlock($src, $case), $mutation, $case);
    }

    #[Test]
    public function commentDeleteRedirectsUseCanonicalItemParam(): void
    {
        // $redirect_page already ends with the item parameter name, so redirects
        // must append "=<id>" (as the success path does), not "?itemName=<id>"
        // which produces a malformed double-? URL on a failed check.
        $src = file_get_contents(XOOPS_ROOT_PATH . '/include/comment_delete.php');
        self::assertNotFalse($src);
        self::assertStringNotContainsString(
            "redirect_page . '?' . \$comment_config['itemName']",
            $src,
            'comment_delete redirects must not build a malformed double-? URL'
        );
    }

    #[Test]
    public function userSelfDeleteChecksTokenBeforeDeleteUser(): void
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/user.php');
        self::assertNotFalse($src);
        $guardPos = strpos($src, "xoopsSecurity']->check(");
        $mutPos   = strpos($src, 'deleteUser(');
        self::assertNotFalse($guardPos, 'user.php: missing token check');
        self::assertNotFalse($mutPos, 'user.php: deleteUser() not found');
        self::assertLessThan($mutPos, $guardPos, 'self-delete must validate the token before deleteUser()');
    }

    /** @return array<string, array{string, string}> */
    public static function ajaxToggleHandlers(): array
    {
        return [
            'avatars display'   => ['/modules/system/admin/avatars/main.php',  "'display' === \$op"],
            'smilies display'   => ['/modules/system/admin/smilies/main.php',  "'smilies_update_display' === \$op"],
            'userrank special'  => ['/modules/system/admin/userrank/main.php', "'userrank_update_special' === \$op"],
        ];
    }

    #[Test]
    #[DataProvider('ajaxToggleHandlers')]
    public function ajaxToggleValidatesTokenBeforeOutput(string $relPath, string $opGuard): void
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . $relPath);
        self::assertNotFalse($src, $relPath);
        $guardPos  = strpos($src, $opGuard);
        $headerPos = strpos($src, 'xoops_cp_header(');
        self::assertNotFalse($guardPos, "{$relPath}: missing op guard");
        self::assertNotFalse($headerPos, "{$relPath}: xoops_cp_header() not found");
        self::assertLessThan($headerPos, $guardPos, "{$relPath}: token guard must precede page output");
        $region = substr($src, $guardPos, 200);
        self::assertMatchesRegularExpression('/->\s*check\(\s*false\s*\)/', $region, "{$relPath}: toggle must validate the token");
    }

    #[Test]
    public function controlPanelFooterEmitsRequestToken(): void
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/include/cp_functions.php');
        self::assertNotFalse($src);
        self::assertStringContainsString('getTokenHTML()', $src);
    }

    #[Test]
    public function blocksAdminAjaxOpsValidateTokenBeforeOutput(): void
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/modules/system/admin/blocksadmin/main.php');
        self::assertNotFalse($src);
        $guardPos  = strpos($src, "in_array(\$op, ['display', 'drag', 'order']");
        $headerPos = strpos($src, 'xoops_cp_header(');
        self::assertNotFalse($guardPos, 'blocksadmin: missing AJAX op guard');
        self::assertNotFalse($headerPos, 'blocksadmin: xoops_cp_header() not found');
        self::assertLessThan($headerPos, $guardPos, 'blocksadmin: token guard must precede page output');
        self::assertMatchesRegularExpression('/->\s*check\(\s*false\s*\)/', substr($src, $guardPos, 200));
    }

    #[Test]
    public function blocksJsSubmitsRequestToken(): void
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/modules/system/js/blocks.js');
        self::assertNotFalse($src);
        self::assertStringContainsString('XOOPS_TOKEN_REQUEST', $src);
    }

    #[Test]
    public function adminJsDisplayPostSubmitsRequestToken(): void
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/modules/system/js/admin.js');
        self::assertNotFalse($src);
        self::assertMatchesRegularExpression(
            '/op=display_post.*XOOPS_TOKEN_REQUEST/s',
            $src,
            'admin.js display_post must submit the request token'
        );
    }

    #[Test]
    public function imagesDisplayTogglesValidateTokenBeforeOutput(): void
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/modules/system/admin/images/main.php');
        self::assertNotFalse($src);
        $guardPos  = strpos($src, "in_array(\$op, ['display_cat', 'display_img']");
        $headerPos = strpos($src, 'xoops_cp_header(');
        self::assertNotFalse($guardPos, 'images: missing toggle guard');
        self::assertNotFalse($headerPos, 'images: xoops_cp_header() not found');
        self::assertLessThan($headerPos, $guardPos, 'images: token guard must precede page output');
        self::assertMatchesRegularExpression('/->\s*check\(\s*false\s*\)/', substr($src, $guardPos, 200));
    }

    #[Test]
    public function getLinkTogglesEmbedTokenInTemplates(): void
    {
        $checks = [
            '/modules/system/templates/admin/system_users.tpl'                       => 'XOOPS_TOKEN_REQUEST=<{$users_csrf}>',
            '/modules/system/themes/dark/modules/system/admin/system_users.tpl'      => 'XOOPS_TOKEN_REQUEST=<{$users_csrf}>',
            '/modules/profile/templates/profile_admin_steplist.tpl'                  => 'XOOPS_TOKEN_REQUEST=<{$steps_csrf}>',
        ];
        foreach ($checks as $rel => $needle) {
            $src = file_get_contents(XOOPS_ROOT_PATH . $rel);
            self::assertNotFalse($src, $rel);
            self::assertStringContainsString($needle, $src, "{$rel}: GET toggle link must carry the request token");
        }
    }

    #[Test]
    public function usersJqueryPostCountValidatesTokenBeforeUpdate(): void
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/modules/system/admin/users/jquery.php');
        self::assertNotFalse($src);
        $guardPos = strpos($src, "xoopsSecurity']->check(");
        $mutPos   = strpos($src, '->exec(');
        self::assertNotFalse($guardPos, 'users/jquery: missing token check');
        self::assertNotFalse($mutPos, 'users/jquery: exec() not found');
        self::assertLessThan($mutPos, $guardPos, 'post-count update must validate the token first');
    }
}
