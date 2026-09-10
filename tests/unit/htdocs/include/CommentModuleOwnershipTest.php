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

namespace Tests\Unit\Include;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\System\SourceFileTestTrait;

require_once dirname(__DIR__) . '/modules/system/SourceFileTestTrait.php';

/**
 * comment_post.php and comment_delete.php, when included by a module, take the
 * module id from the page that received the request while the comment handler
 * loads any comment by id, so an administrator of one module could save or
 * delete another module's comment through their own module's endpoint (#192).
 * Both files now require the loaded comment to belong to the requesting module
 * before any moderation right is evaluated. The check is sliced from each
 * file and executed with a stub comment; redirect_header() throws in the test
 * bootstrap.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class CommentModuleOwnershipTest extends TestCase
{
    use SourceFileTestTrait;

    private const MARKER = 'if (!is_object($comment) || (int) $comment->getVar(\'com_modid\') !== (int) $com_modid) {';

    /**
     * Rows: [file, what must follow the check, stub comment, refused].
     *
     * In comment_post.php the check precedes the moderation rights that use
     * the module id; in comment_delete.php those rights are evaluated before
     * the comment exists, so the check precedes every operation instead.
     *
     * @return array<string, array{string, string, mixed, bool}>
     */
    public static function cases(): array
    {
        $rows = [];
        $files = [
            'htdocs/include/comment_post.php'   => 'isAdmin($com_modid)',
            'htdocs/include/comment_delete.php' => 'switch ($op) {',
        ];
        foreach ($files as $file => $mustPrecede) {
            $name          = basename($file);
            $rows["$name: comment of this module"]    = [$file, $mustPrecede, self::comment(modid: 3), false];
            $rows["$name: comment of another module"] = [$file, $mustPrecede, self::comment(modid: 4), true];
            $rows["$name: module id as string"]       = [$file, $mustPrecede, self::comment(modid: '3'), false];
            $rows["$name: unknown comment id"]        = [$file, $mustPrecede, false, true];
        }

        return $rows;
    }

    #[Test]
    #[DataProvider('cases')]
    public function aCommentFromAnotherModuleIsRefusedBeforeItIsActedOn(string $file, string $mustPrecede, mixed $comment, bool $refused): void
    {
        $this->loadSourceFile($file);
        $start = strpos($this->sourceContent, self::MARKER);
        self::assertNotFalse($start, "$file has no module ownership check");
        $noperm = strpos($this->sourceContent, '_NOPERM);', $start);
        self::assertNotFalse($noperm);
        $close = strpos($this->sourceContent, '}', $noperm);
        self::assertNotFalse($close);
        $check = substr($this->sourceContent, $start, $close - $start + 1);
        // exit() after the redirect must never be reached in the test: the
        // bootstrap's redirect_header() throws first.
        self::assertStringContainsString('redirect_header(', $check);

        // The check runs where the module id is already the requesting
        // module's, and before the code that acts on the comment.
        self::assertNotFalse(strpos($this->sourceContent, $mustPrecede, $start), "$mustPrecede must follow the check");
        // and it is not skipped for a zero or negative id: every operation acts
        // on the id, and the handler returns a fresh object for 0, whose module
        // id of 0 must be refused like any other mismatch.
        self::assertStringNotContainsString('if ($com_id > 0) {', $this->sourceContent);

        $com_modid     = 3;
        $com_itemid    = 9;
        $com_id        = 42;
        $com_mode      = 'flat';
        $com_order     = 0;
        $redirect_page = 'index.php?item';
        if ($refused) {
            $this->expectException(\RedirectHeaderException::class);
        }
        // Evaluates the check exactly as written in the file; the locals above
        // are the variables it reads.
        eval('namespace ' . __NAMESPACE__ . "\\CommentOwnership;\n" . $check);
        self::assertFalse($refused);
    }

    private static function comment(int|string $modid): object
    {
        return new class($modid) {
            public function __construct(private int|string $modid)
            {
            }

            public function getVar(string $key): mixed
            {
                return 'com_modid' === $key ? $this->modid : null;
            }
        };
    }
}
