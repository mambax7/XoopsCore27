<?php

declare(strict_types=1);

/**
 * SCEditor emoticons as XOOPS smileys: the shipped list, install(), and the
 * sanitizer rendering their codes.
 *
 * @category  Test
 * @package   Tests
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/class/module.textsanitizer.php';
require_once XOOPS_ROOT_PATH . '/class/xoopseditor/sceditor/class/SCEditorEmoticons.php';

#[CoversClass(SCEditorEmoticons::class)]
#[CoversClass(MyTextSanitizer::class)]
final class SCEditorEmoticonsTest extends TestCase
{
    private string $uploads = '';

    /** @var list<string> */
    private array $exec = [];

    protected function tearDown(): void
    {
        if ($this->uploads !== '') {
            array_map('unlink', glob($this->uploads . '/smilies/*') ?: []);
            @rmdir($this->uploads . '/smilies');
            @rmdir($this->uploads);
        }
    }

    #[Test]
    public function everyListedImageShipsAndEveryCodeIsUnique(): void
    {
        $rows  = SCEditorEmoticons::list();
        $codes = array_column($rows, 'code');

        $this->assertCount(32, $rows);
        $this->assertSame($codes, array_unique($codes));
        $this->assertNotContains('8-)', $codes, 'XOOPS ships 8-) already');
        // smiley() replaces codes anywhere in a message: short codes changed Re:Post.
        $this->assertSame([], preg_grep('/\A:[a-z]+:\z/', $codes, PREG_GREP_INVERT));
        foreach ($rows as $row) {
            $this->assertFileExists(XOOPS_ROOT_PATH . '/class/xoopseditor/sceditor/emoticons/' . $row['file']);
            $this->assertSame('smilies/sceditor_' . $row['file'], $row['smile_url']);
        }
    }

    #[Test]
    public function installCopiesImagesAndInsertsOnlyMissingCodes(): void
    {
        $this->uploads = sys_get_temp_dir() . '/sce' . bin2hex(random_bytes(4));
        mkdir($this->uploads . '/smilies', 0777, true);
        $logs = [];

        $this->assertTrue(SCEditorEmoticons::install($this->db([':sick:', ':heart:']), $logs, $this->uploads), implode("\n", $logs));

        $this->assertFileExists($this->uploads . '/smilies/sceditor_wub.png');
        $this->assertCount(30, $this->exec, 'the two existing codes are left alone');
        $this->assertStringContainsString("VALUES (':love:', 'smilies/sceditor_wub.png', 'Love', 0)", implode("\n", $this->exec));
        $this->assertSame([], SCEditorEmoticons::missing($this->db(array_column(SCEditorEmoticons::list(), 'code')), $this->uploads));
    }

    #[Test]
    public function anAdminsOwnSmileyKeepsItsImageAndCountsAsPresent(): void
    {
        $this->uploads = sys_get_temp_dir() . '/sce' . bin2hex(random_bytes(4));
        mkdir($this->uploads . '/smilies', 0777, true);
        $existing            = array_column(SCEditorEmoticons::list(), 'smile_url', 'code');
        $existing[':smile:'] = 'smilies/mine.gif';
        $logs = [];

        $this->assertTrue(SCEditorEmoticons::install($this->db($existing), $logs, $this->uploads), implode("
", $logs));

        $this->assertSame([], $this->exec, 'no row is inserted or changed');
        $this->assertFileDoesNotExist($this->uploads . '/smilies/sceditor_smile.png', 'the bundled image is not needed');
        $this->assertFileExists($this->uploads . '/smilies/sceditor_wub.png');
        $this->assertSame([], SCEditorEmoticons::missing($this->db($existing), $this->uploads));
    }

    #[Test]
    public function unreadableSmilesTableIsReportedNotTreatedAsEmpty(): void
    {
        $logs = [];

        $this->assertFalse(SCEditorEmoticons::install($this->db(null), $logs, sys_get_temp_dir()));
        $this->assertNull(SCEditorEmoticons::missing($this->db(null), sys_get_temp_dir()));
        $this->assertSame([], $this->exec);
    }

    #[Test]
    public function smileyMatchesEscapedCodesInOnePassLongestFirst(): void
    {
        $myts = (new ReflectionClass(MyTextSanitizer::class))->newInstanceWithoutConstructor();
        $myts->smileys = [
            ['code' => ':)', 'smile_url' => 'smilies/a.png'],
            ['code' => ':-)', 'smile_url' => 'smilies/b.png'],
            ['code' => '<3', 'smile_url' => 'smilies/c.png'],
            ['code' => ":'(", 'smile_url' => 'smilies/d.png'],
        ];

        $out = $myts->smiley(htmlspecialchars("hi :-) and :) love <3 cry :'(", ENT_COMPAT));

        $this->assertSame(1, substr_count($out, 'smilies/a.png'));
        $this->assertSame(1, substr_count($out, 'smilies/b.png'));
        $this->assertSame(1, substr_count($out, 'smilies/c.png'));
        $this->assertSame(1, substr_count($out, 'smilies/d.png'));
        $this->assertStringNotContainsString('&lt;3', $out);
    }

    /**
     * @param array<int|string, string>|null $codes existing smiles codes; a list entry uses the
     *                                             bundled image, code => url sets its own; null makes the SELECT fail
     */
    private function db(?array $codes): XoopsMySQLDatabase
    {
        $bundled = array_column(SCEditorEmoticons::list(), 'smile_url', 'code');
        $rows    = [];
        foreach ($codes ?? [] as $key => $value) {
            $rows[] = is_int($key)
                ? ['code' => $value, 'smile_url' => $bundled[$value] ?? 'smilies/' . $value . '.png']
                : ['code' => $key, 'smile_url' => $value];
        }
        $db   = $this->createMock(XoopsMySQLDatabase::class);
        $db->method('prefix')->willReturnCallback(static fn ($table = ''): string => 'xoops_' . $table);
        $db->method('quote')->willReturnCallback(static fn ($value): string => "'" . addslashes((string) $value) . "'");
        $db->method('error')->willReturn('');
        $db->method('query')->willReturn(null === $codes ? false : (new ReflectionClass(\mysqli_result::class))->newInstanceWithoutConstructor());
        $db->method('isResultSet')->willReturnCallback(static fn ($result): bool => $result instanceof \mysqli_result);
        $db->method('fetchArray')->willReturnCallback(static function () use (&$rows) {
            return array_shift($rows) ?? false;
        });
        $db->method('exec')->willReturnCallback(function (string $sql): bool {
            $this->exec[] = $sql;

            return true;
        });

        return $db;
    }
}
