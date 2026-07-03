<?php

declare(strict_types=1);

namespace Xoops\Upgrade\Tests\Smarty5;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Xoops\Upgrade\Smarty5ScannerOutput;
use Xoops\Upgrade\Smarty5TemplateChecks;

/**
 * @group smarty5
 */
#[CoversClass(Smarty5TemplateChecks::class)]
#[CoversClass(Smarty5ScannerOutput::class)]
final class Smarty5TemplateChecksTest extends TestCase
{
    private function scan(string $fixture): Smarty5ScannerOutput
    {
        $output = new Smarty5ScannerOutput();
        (new Smarty5TemplateChecks($output))->inspectFile(new SplFileInfo(s5_fixture($fixture)));

        return $output;
    }

    /**
     * @return array<string, array{0:string,1:int,2:int,3:int}>
     */
    public static function fixtureExpectations(): array
    {
        return [
            'block_parent'  => ['block_parent.tpl', 1, 0, 0],
            'block_child'   => ['block_child.tpl', 1, 0, 0],
            'date_mapped'   => ['date_format_mapped.tpl', 1, 0, 0],
            'date_locale'   => ['date_format_locale.tpl', 0, 0, 1],
            'php_tag'       => ['php_tag.tpl', 0, 2, 2],
            'include_php'   => ['include_php.tpl', 0, 1, 1],
            'insert'        => ['insert.tpl', 0, 0, 1],
            'make_nocache'  => ['make_nocache.tpl', 0, 0, 1],
            'native_mod'    => ['native_modifier.tpl', 0, 0, 1],
            'asp'           => ['asp_tag.tpl', 0, 0, 1],
            'config_scope'  => ['config_load_scope.tpl', 0, 0, 1],
            'bare_parent'   => ['bare_parent.tpl', 0, 0, 1],
            'clean'         => ['clean.tpl', 0, 0, 0],
        ];
    }

    #[Test]
    #[DataProvider('fixtureExpectations')]
    public function itClassifiesEachRule(string $fixture, int $auto, int $blockers, int $report): void
    {
        $out = $this->scan($fixture);

        self::assertSame($auto, $out->countAutoFixable(), "{$fixture}: auto-fixable count");
        self::assertSame($blockers, $out->countBlockers(), "{$fixture}: blocker count");
        self::assertCount($report, $out->getReportOnlyIssues(), "{$fixture}: report-only count");
    }

    #[Test]
    public function itDeduplicatesBlockerFiles(): void
    {
        // php_tag.tpl has two blocker matches but is a single file.
        self::assertCount(1, $this->scan('php_tag.tpl')->getBlockerFiles());
    }

    #[Test]
    public function itClassifiesDateFormatTokens(): void
    {
        self::assertTrue(Smarty5TemplateChecks::dateFormatIsAutoFixable('%Y-%m-%d %H:%M:%S'));
        self::assertFalse(Smarty5TemplateChecks::dateFormatIsAutoFixable('%A, %B %e'));
    }
}
