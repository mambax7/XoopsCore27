<?php
/*
 * No-dependency test runner for the Smarty 4 -> 5 readiness scanner family.
 *
 * This site has no PHPUnit/composer wired up (see htdocs/CLAUDE.md), so this
 * runner exercises the same assertions as the PHPUnit suite against the WAMP PHP
 * binary. Run:  php htdocs/upgrade/tests/run-smarty5-tests.php
 *
 * Exit code 0 = all assertions passed, 1 = at least one failed.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Xoops\Upgrade\Smarty5ScannerOutput;
use Xoops\Upgrade\Smarty5TemplateChecks;
use Xoops\Upgrade\Smarty5RepairOutput;
use Xoops\Upgrade\Smarty5TemplateRepair;

$GLOBALS['__pass'] = 0;
$GLOBALS['__fail'] = 0;

function check(bool $cond, string $label): void
{
    if ($cond) {
        $GLOBALS['__pass']++;
        echo "  PASS  {$label}\n";
    } else {
        $GLOBALS['__fail']++;
        echo "  FAIL  {$label}\n";
    }
}

function section(string $title): void
{
    echo "\n== {$title} ==\n";
}

/** Run the report-only checks over one fixture and return its output. */
function runChecks(string $fixtureName): Smarty5ScannerOutput
{
    $output = new Smarty5ScannerOutput();
    $checks = new Smarty5TemplateChecks($output);
    $checks->inspectFile(new SplFileInfo(s5_fixture($fixtureName)));

    return $output;
}

/** Repair one fixture copy and return the repair output. */
function runRepair(string $tempPath): Smarty5RepairOutput
{
    $output = new Smarty5RepairOutput();
    $repair = new Smarty5TemplateRepair($output);
    $repair->inspectFile(new SplFileInfo($tempPath));

    return $output;
}

// =============================================================================
// 1. Detection / classification (Smarty5TemplateChecks)
// =============================================================================
section('Smarty5TemplateChecks — detection & classification');

// [fixture => [autoFixable, blockers, reportOnly]]
$expected = [
    'block_parent.tpl'       => [1, 0, 0],
    'block_child.tpl'        => [1, 0, 0],
    'date_format_mapped.tpl' => [1, 0, 0],
    'date_format_locale.tpl' => [0, 0, 1],
    'php_tag.tpl'            => [0, 2, 2], // <{php}> and <{/php}> both match
    'include_php.tpl'        => [0, 1, 1],
    'insert.tpl'             => [0, 0, 1],
    'make_nocache.tpl'       => [0, 0, 1],
    'native_modifier.tpl'    => [0, 0, 1],
    'asp_tag.tpl'            => [0, 0, 1], // warn, not a gate blocker
    'config_load_scope.tpl'  => [0, 0, 1],
    'bare_parent.tpl'        => [0, 0, 1],
    'clean.tpl'              => [0, 0, 0],
];

foreach ($expected as $fixture => [$auto, $block, $report]) {
    $out = runChecks($fixture);
    check(
        $auto === $out->countAutoFixable()
        && $block === $out->countBlockers()
        && $report === count($out->getReportOnlyIssues()),
        sprintf(
            '%-24s auto=%d/%d block=%d/%d report=%d/%d',
            $fixture,
            $out->countAutoFixable(), $auto,
            $out->countBlockers(), $block,
            count($out->getReportOnlyIssues()), $report
        )
    );
}

// Blocker file list is populated and deduplicated.
$out = runChecks('php_tag.tpl');
check(1 === count($out->getBlockerFiles()), 'php_tag blocker file list deduped to 1 entry');

// date_format token classification helper
check(Smarty5TemplateChecks::dateFormatIsAutoFixable('%Y-%m-%d %H:%M:%S'), 'date "%Y..%M..%S" is auto-fixable');
check(!Smarty5TemplateChecks::dateFormatIsAutoFixable('%A, %B'), 'date "%A, %B" (locale) is NOT auto-fixable');

// =============================================================================
// 2. Repair (Smarty5TemplateRepair) — forward-compatible only
// =============================================================================
section('Smarty5TemplateRepair — forward-compatible rewrites');

// block_parent -> $smarty.block.parent, with backup + idempotency
$tmp = s5_fixture_copy('block_parent.tpl');
$out = runRepair($tmp);
$after = file_get_contents($tmp);
check(str_contains($after, '<{$smarty.block.parent}>'), 'block_parent rewritten to $smarty.block.parent');
check(!str_contains($after, '<{block_parent}>'), 'old block_parent shorthand removed');
check(1 === $out->totalFixes(), 'block_parent: exactly 1 fix counted');
check(is_file($tmp . Smarty5TemplateRepair::BACKUP_SUFFIX), 'block_parent: .preflight-bak written');
check('<{block_parent}>' === trim((string) file_get_contents($tmp . Smarty5TemplateRepair::BACKUP_SUFFIX)), 'backup holds pristine original');
// idempotent re-run
$out2 = runRepair($tmp);
check(0 === $out2->totalFixes(), 'block_parent: re-run is a no-op (idempotent)');

// block_child
$tmp = s5_fixture_copy('block_child.tpl');
runRepair($tmp);
check(str_contains((string) file_get_contents($tmp), '<{$smarty.block.child}>'), 'block_child rewritten to $smarty.block.child');

// date_format mapped (incl. %M -> i minutes gotcha)
$tmp = s5_fixture_copy('date_format_mapped.tpl');
$out = runRepair($tmp);
$after = file_get_contents($tmp);
check(str_contains($after, 'date_format:"Y-m-d H:i:s"'), 'date_format %Y-%m-%d %H:%M:%S -> Y-m-d H:i:s (%M->i)');
check(!str_contains($after, '%'), 'no strftime % tokens remain');
check(1 === $out->totalFixes(), 'date_format: exactly 1 fix counted');

// date_format locale -> left untouched (manual review)
$tmp = s5_fixture_copy('date_format_locale.tpl');
$before = file_get_contents($tmp);
$out = runRepair($tmp);
check($before === file_get_contents($tmp), 'date_format locale tokens left untouched');
check(0 === $out->totalFixes(), 'date_format locale: 0 fixes');
check(!is_file($tmp . Smarty5TemplateRepair::BACKUP_SUFFIX), 'no backup when nothing changed');

// blockers / report-only rules are never rewritten by repair
foreach (['php_tag.tpl', 'insert.tpl', 'native_modifier.tpl', 'bare_parent.tpl'] as $fixture) {
    $tmp = s5_fixture_copy($fixture);
    $before = file_get_contents($tmp);
    $out = runRepair($tmp);
    check($before === file_get_contents($tmp) && 0 === $out->totalFixes(), "repair leaves {$fixture} untouched");
}

// =============================================================================
// 3. Upgrade_271 task contract (check_/apply_ agreement)
// =============================================================================
section('Upgrade_271 — task contract & skip-with-warning');

require_once dirname(__DIR__) . '/upd_2.7.0-to-2.7.1/index.php';

/** Test double: bypasses the DB-bound constructor and stubs the scan helpers. */
class _S5UpgradeStub extends Upgrade_271
{
    public int $stubAuto = 0;
    /** @var array<int,array{rule:string,file:string,match:string,tier:string}> */
    public array $stubReport = [];

    public function __construct() {} // skip parent (no DB needed for contract tests)

    protected function countAutoFixable(): int
    {
        return $this->stubAuto;
    }

    protected function scanReportOnly(): array
    {
        return $this->stubReport;
    }
}

// -- smartytemplates: clean tree --
$_SESSION = [];
$u = new _S5UpgradeStub();
$u->stubAuto = 0;
check(true === $u->check_smartytemplates(), 'smartytemplates: clean tree -> check true');

// -- smartytemplates: pending issues -> skip-with-warning --
$_SESSION = [];
$u = new _S5UpgradeStub();
$u->stubAuto = 2;
check(false === $u->check_smartytemplates(), 'smartytemplates: 2 pending -> check false (first pass)');
check(true === $u->apply_smartytemplates(), 'smartytemplates: apply returns true (never aborts)');
check(!empty($_SESSION['smarty5-templates-warned-271']), 'smartytemplates: warn flag set by apply');
check(true === $u->check_smartytemplates(), 'smartytemplates: check true after warn (queue can finish)');
check(str_contains($u->message(), '2 template(s)'), 'smartytemplates: log mentions pending count');

// -- log escaping --
$_SESSION = [];
$u = new _S5UpgradeStub();
$u->stubAuto = 0;
$u->stubReport = [['rule' => 'php_tag', 'file' => '/themes/x<script>.tpl', 'match' => '<{php}>', 'tier' => 'S4-BLOCK']];
$u->apply_smartytemplates();
check(str_contains($u->message(), '&lt;script&gt;'), 'smartytemplates: report file path is HTML-escaped');
check(!str_contains($u->message(), '<script>'), 'smartytemplates: no raw <script> in log');

// -- smartycache: durable one-shot --
$_SESSION = [];
$s5CacheMarker = XOOPS_VAR_PATH . '/data/smarty5_cache_purged_271.marker';
if (is_file($s5CacheMarker)) {
    @unlink($s5CacheMarker);
}
$u = new _S5UpgradeStub();
check(false === $u->check_smartycache(), 'smartycache: false before run');
check(true === $u->apply_smartycache(), 'smartycache: apply true');
check(true === $u->check_smartycache(), 'smartycache: true after run (durable marker)');
$_SESSION = [];
check(true === $u->check_smartycache(), 'smartycache: stays true after session reset');

// -- smartyextensions: guard (package absent in test env) --
$_SESSION = [];
$u = new _S5UpgradeStub();
$present = class_exists(\Xoops\SmartyExtensions\ExtensionRegistry::class);
check($present === $u->check_smartyextensions(), 'smartyextensions: check reflects class presence');
check(true === $u->apply_smartyextensions(), 'smartyextensions: apply true (never wedges)');
check(true === $u->check_smartyextensions(), 'smartyextensions: true after apply (flag or present)');
if (!$present) {
    check(str_contains($u->message(), 'composer require'), 'smartyextensions: missing package logs actionable advice');
}

// =============================================================================
// Summary
// =============================================================================
echo "\n----------------------------------------\n";
printf("Smarty5 readiness tests: %d passed, %d failed\n", $GLOBALS['__pass'], $GLOBALS['__fail']);

// Best-effort cleanup of temp working files.
$work = __DIR__ . '/tmp';
if (is_dir($work)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($work, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($work);
}

exit($GLOBALS['__fail'] === 0 ? 0 : 1);
