<?php

declare(strict_types=1);

namespace Xoops\Upgrade\Tests\Smarty5;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Xoops\Upgrade\Smarty5RepairOutput;
use Xoops\Upgrade\Smarty5TemplateRepair;

/**
 * @group smarty5
 */
#[CoversClass(Smarty5TemplateRepair::class)]
#[CoversClass(Smarty5RepairOutput::class)]
final class Smarty5TemplateRepairTest extends TestCase
{
    private function repair(string $tempPath): Smarty5RepairOutput
    {
        $output = new Smarty5RepairOutput();
        (new Smarty5TemplateRepair($output))->inspectFile(new SplFileInfo($tempPath));

        return $output;
    }

    #[Test]
    public function itRewritesBlockParentToCanonicalSpecialVar(): void
    {
        $tmp = s5_fixture_copy('block_parent.tpl');
        $out = $this->repair($tmp);

        self::assertStringContainsString('<{$smarty.block.parent}>', (string) file_get_contents($tmp));
        self::assertStringNotContainsString('<{block_parent}>', (string) file_get_contents($tmp));
        self::assertSame(1, $out->totalFixes());
    }

    #[Test]
    public function itRewritesBlockChild(): void
    {
        $tmp = s5_fixture_copy('block_child.tpl');
        $this->repair($tmp);

        self::assertStringContainsString('<{$smarty.block.child}>', (string) file_get_contents($tmp));
    }

    #[Test]
    public function itWritesAPristineBackupBeforeOverwriting(): void
    {
        $tmp = s5_fixture_copy('block_parent.tpl');
        $this->repair($tmp);

        $backup = $tmp . Smarty5TemplateRepair::BACKUP_SUFFIX;
        self::assertFileExists($backup);
        self::assertSame('<{block_parent}>', trim((string) file_get_contents($backup)));
    }

    #[Test]
    public function itIsIdempotent(): void
    {
        $tmp = s5_fixture_copy('block_parent.tpl');
        $this->repair($tmp);
        $afterFirst = file_get_contents($tmp);

        $out = $this->repair($tmp);
        self::assertSame(0, $out->totalFixes());
        self::assertSame($afterFirst, file_get_contents($tmp));
    }

    #[Test]
    public function itMapsStrftimeTokensIncludingMinutes(): void
    {
        $tmp = s5_fixture_copy('date_format_mapped.tpl');
        $out = $this->repair($tmp);
        $after = (string) file_get_contents($tmp);

        // %M (minutes) must become i, not M (month name).
        self::assertStringContainsString('date_format:"Y-m-d H:i:s"', $after);
        self::assertStringNotContainsString('%', $after);
        self::assertSame(1, $out->totalFixes());
    }

    #[Test]
    public function itLeavesLocaleDateTokensUntouched(): void
    {
        $tmp = s5_fixture_copy('date_format_locale.tpl');
        $before = file_get_contents($tmp);
        $out = $this->repair($tmp);

        self::assertSame($before, file_get_contents($tmp));
        self::assertSame(0, $out->totalFixes());
        self::assertFileDoesNotExist($tmp . Smarty5TemplateRepair::BACKUP_SUFFIX);
    }

    #[Test]
    public function itPreservesFileModeAcrossRepair(): void
    {
        if (0 === stripos(PHP_OS, 'WIN')) {
            self::markTestSkipped('chmod on files is unreliable on Windows');
        }
        $tmp = s5_fixture_copy('block_parent.tpl');
        chmod($tmp, 0640);
        clearstatcache();

        $out = $this->repair($tmp);

        clearstatcache();
        self::assertSame(1, $out->totalFixes(), 'fixture must actually be rewritten');
        self::assertSame('640', substr(sprintf('%o', fileperms($tmp)), -3), 'mode preserved across atomic rewrite');
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function reportOnlyFixtures(): array
    {
        return [
            'php_tag'    => ['php_tag.tpl'],
            'insert'     => ['insert.tpl'],
            'native_mod' => ['native_modifier.tpl'],
            'bare_parent' => ['bare_parent.tpl'],
        ];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('reportOnlyFixtures')]
    public function itNeverRewritesReportOnlyRules(string $fixture): void
    {
        $tmp = s5_fixture_copy($fixture);
        $before = file_get_contents($tmp);
        $out = $this->repair($tmp);

        self::assertSame($before, file_get_contents($tmp));
        self::assertSame(0, $out->totalFixes());
    }
}
