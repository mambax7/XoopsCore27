<?php

declare(strict_types=1);

namespace Xoops\Upgrade\Tests\Smarty5;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Upgrade_271;

require_once dirname(__DIR__, 2) . '/upd_2.7.0-to-2.7.1/index.php';

/**
 * Test double for {@see Upgrade_271}: bypasses the DB-bound constructor and stubs
 * the filesystem scan so the check_/apply_ contract can be tested in isolation.
 */
final class Upgrade271Stub extends Upgrade_271
{
    public int $stubAuto = 0;

    /** @var array<int,array{rule:string,file:string,match:string,tier:string}> */
    public array $stubReport = [];

    public function __construct() {} // intentionally skip parent (no DB needed)

    protected function countAutoFixable(): int
    {
        return $this->stubAuto;
    }

    protected function scanReportOnly(): array
    {
        return $this->stubReport;
    }
}

/**
 * @group smarty5
 */
final class Upgrade271Test extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $marker = XOOPS_VAR_PATH . '/data/smarty5_cache_purged_271.marker';
        if (is_file($marker)) {
            @unlink($marker);
        }
    }

    #[Test]
    public function smartytemplatesPassesWhenClean(): void
    {
        $u = new Upgrade271Stub();
        $u->stubAuto = 0;

        self::assertTrue($u->check_smartytemplates());
    }

    #[Test]
    public function smartytemplatesSkipsWithWarningWhenIssuesRemain(): void
    {
        $u = new Upgrade271Stub();
        $u->stubAuto = 2;

        // First encounter: not yet warned -> pending.
        self::assertFalse($u->check_smartytemplates());

        // apply never aborts and warns once.
        self::assertTrue($u->apply_smartytemplates());
        self::assertNotEmpty($_SESSION['smarty5-templates-warned-271'] ?? null);
        self::assertStringContainsString('2 template(s)', $u->message());

        // After warning, the queue can complete even though issues remain.
        self::assertTrue($u->check_smartytemplates());
    }

    #[Test]
    public function smartytemplatesEscapesLoggedPaths(): void
    {
        $u = new Upgrade271Stub();
        $u->stubReport = [['rule' => 'php_tag', 'file' => '/themes/x<script>.tpl', 'match' => '<{php}>', 'tier' => 'S4-BLOCK']];
        $u->apply_smartytemplates();

        self::assertStringContainsString('&lt;script&gt;', $u->message());
        self::assertStringNotContainsString('<script>', $u->message());
    }

    #[Test]
    public function smartytemplatesWaitsForReportOnlyWarnings(): void
    {
        $u = new Upgrade271Stub();
        $u->stubAuto   = 0;
        $u->stubReport = [['rule' => 'insert', 'file' => '/themes/x.tpl', 'match' => '{insert}', 'tier' => 'S4-MANUAL']];

        // Report-only items remain even though autofixable is clean: not "verified
        // clean" until apply_ has logged the manual-review warnings on the first pass.
        self::assertFalse($u->check_smartytemplates());
        self::assertTrue($u->apply_smartytemplates());
        self::assertTrue($u->check_smartytemplates());
    }

    #[Test]
    public function smartycacheCompletionIsDurableAcrossSessions(): void
    {
        $u = new Upgrade271Stub();

        self::assertFalse($u->check_smartycache());
        self::assertTrue($u->apply_smartycache());
        self::assertTrue($u->check_smartycache());

        // A new or expired session must NOT make the 2.7.1 patch pending again:
        // completion is recorded with a durable marker file, not $_SESSION.
        $_SESSION = [];
        self::assertTrue($u->check_smartycache(), 'smartycache must stay applied after a session reset');
    }

    #[Test]
    public function smartyextensionsGuardNeverWedgesQueue(): void
    {
        $u = new Upgrade271Stub();
        $present = class_exists(\Xoops\SmartyExtensions\ExtensionRegistry::class);

        self::assertSame($present, $u->check_smartyextensions());
        self::assertTrue($u->apply_smartyextensions());
        self::assertTrue($u->check_smartyextensions());
    }
}
