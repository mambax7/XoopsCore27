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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The smilies, user-rank and avatar admin pages remove an uploaded file only
 * through xoops_resolveFileWithin() and xoops_remove_file_quietly().
 *
 * Each page used to carry its own realpath()/str_starts_with() containment
 * check around a raw unlink(): three copies of logic the helper provides.
 * Smilies and user ranks also read the stored name in the HTML-escaped 's'
 * format (a name with & or a quote then resolved to nothing and the file was
 * left behind) and chmod()ed the file to 0777 just before an unchecked
 * unlink() whose warning names the full path.
 *
 * @category  Xoops
 * @package   System
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class AdminFileContainmentTest extends TestCase
{
    /**
     * The source of one delete case: from its label to the next case label.
     */
    private static function deleteBlock(string $page, string $case): string
    {
        $src   = (string) file_get_contents(XOOPS_ROOT_PATH . '/modules/system/admin/' . $page . '/main.php');
        $start = strpos($src, "case '" . $case . "':");
        self::assertNotFalse($start, $page . ': case ' . $case . ' not found');
        $end = strpos($src, "\n    case '", $start + 1);

        return substr($src, $start, false === $end ? null : $end - $start);
    }

    /** @return array<string, array{string, string, string, string}> page, case, stored-name expression, context */
    public static function deletePages(): array
    {
        return [
            'smilies'   => ['smilies', 'smilies_delete', "(string) \$obj->getVar('smile_url', 'n')", 'smiley image'],
            'user rank' => ['userrank', 'userrank_delete', "(string) \$obj->getVar('rank_image', 'n')", 'rank image'],
            'avatar'    => ['avatars', 'delfileok', '$file', 'avatar image'],
        ];
    }

    #[Test]
    #[DataProvider('deletePages')]
    public function theDeleteRemovesOnlyAContainedFile(string $page, string $case, string $storedName, string $context): void
    {
        $block = self::deleteBlock($page, $case);

        self::assertStringContainsString("require_once XOOPS_ROOT_PATH . '/include/file_safety.php';", $block);
        self::assertSame(
            1,
            preg_match(
                '/\$(\w+)\s*=\s*xoops_resolveFileWithin\(\s*XOOPS_UPLOAD_PATH\s*,\s*' . preg_quote($storedName, '/') . '\s*\);'
                . '\s*if\s*\(\s*\'\'\s*!==\s*\$\1\s*\)\s*\{\s*xoops_remove_file_quietly\(\s*\$\1\s*,\s*\'' . preg_quote($context, '/') . '\'\s*\)/',
                $block
            ),
            $page . ': the raw stored name must be resolved inside XOOPS_UPLOAD_PATH, and only that path removed.'
        );
    }

    #[Test]
    #[DataProvider('deletePages')]
    public function noHandRolledContainmentOrRawFileCallsRemain(string $page, string $case): void
    {
        $block = self::deleteBlock($page, $case);

        foreach (['realpath(', 'str_starts_with(', 'unlink(', 'chmod('] as $call) {
            self::assertStringNotContainsString($call, $block, $page . ': ' . $call . ' must not remain in the delete.');
        }
    }
}
