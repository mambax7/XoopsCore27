<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The error-screen seam in htdocs/include/debugconfig.php.
 *
 * Ownership resolves in three steps -- an explicit token in debug.php, else the owner a
 * provider module recorded at install, else 'core' -- and core triggers
 * core.debug.errorscreen for whichever module that names. Every case below was a defect
 * or a design question raised in review; they are here so that re-running the suite
 * answers them, rather than a reviewer having to take a claim on trust.
 *
 * Each case runs in its OWN PROCESS through fixtures/errorscreen-runner.php. The seam
 * publishes its outcome as constants, and constants are defined once per process; sharing
 * one would let the first case decide every later one. A subprocess is also the only
 * honest way to exercise a truncated include/debugconfig.php, which fails at compile time.
 */
#[CoversFunction('xoops_activateErrorScreen')]
#[CoversFunction('xoops_getErrorScreenOwner')]
#[CoversFunction('xoops_getErrorScreenOwnerSource')]
#[CoversFunction('xoops_getErrorScreenStatus')]
#[CoversFunction('xoops_recordErrorScreenOwner')]
#[CoversFunction('xoops_releaseErrorScreenOwner')]
#[CoversFunction('xoops_writeDebugRuntimeOverride')]
#[CoversFunction('xoops_applyDebugConfig')]
class ErrorScreenSeamTest extends TestCase
{
    private string $varPath = '';

    protected function setUp(): void
    {
        $this->varPath = sys_get_temp_dir() . '/xoops-errorscreen-' . bin2hex(random_bytes(6));
        mkdir($this->varPath . '/data', 0777, true);
    }

    protected function tearDown(): void
    {
        if ('' === $this->varPath || !is_dir($this->varPath)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->varPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($this->varPath);
    }

    /**
     * @param array<string, mixed> $spec
     * @return array<string, mixed>
     */
    private function runCase(array $spec): array
    {
        $spec['var_path'] = $spec['var_path'] ?? $this->varPath;

        $command = escapeshellarg(PHP_BINARY)
            . ' ' . escapeshellarg(__DIR__ . '/fixtures/errorscreen-runner.php')
            . ' ' . escapeshellarg((string) json_encode($spec));

        $output = shell_exec($command);
        $this->assertIsString($output, 'the fixture runner produced no output');

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded, 'the fixture runner did not return JSON: ' . $output);

        return $decoded;
    }

    // ---------------------------------------------------------------- resolution

    #[Test]
    public function withNoProviderTheHandlersStayWithTheCore(): void
    {
        $result = $this->runCase(['debug' => ['enabled' => true]]);

        $this->assertSame('core', $result['owner']);
        $this->assertSame('default', $result['source']);
        $this->assertSame('core', $result['status']);
    }

    #[Test]
    public function aRecordedOwnerIsUsedWhenNothingIsPinned(): void
    {
        $result = $this->runCase([
            'debug'    => ['enabled' => true],
            'record'   => 'xprovider',
            'provider' => 'xprovider',
        ]);

        $this->assertSame('xprovider', $result['owner']);
        $this->assertSame('recorded', $result['source']);
        $this->assertSame('active', $result['status']);
        $this->assertTrue($result['provider_ran']);
    }

    #[Test]
    public function anExplicitTokenBeatsTheRecordedOwner(): void
    {
        $result = $this->runCase([
            'debug'    => ['enabled' => true, 'error_screen' => 'xpinned'],
            'record'   => 'xrecorded',
            'provider' => 'xpinned',
        ]);

        $this->assertSame('xpinned', $result['owner']);
        $this->assertSame('config', $result['source']);
        $this->assertSame('active', $result['status']);
    }

    #[Test]
    public function anExplicitCoreKeepsTheHandlersEvenWithAnOwnerRecorded(): void
    {
        // 'core' is a statement, not an absence: a site that says it wants nothing to take
        // the handlers must outrank a record written by an install months earlier.
        $result = $this->runCase([
            'debug'    => ['enabled' => true, 'error_screen' => 'core'],
            'record'   => 'xrecorded',
            'provider' => 'xrecorded',
        ]);

        $this->assertSame('core', $result['owner']);
        $this->assertSame('config', $result['source']);
        $this->assertFalse($result['provider_ran']);
    }

    #[Test]
    public function anUnansweredTokenIsReportedRatherThanPassedOn(): void
    {
        // A deactivated provider must not hand its seat to whatever else is installed:
        // ownership moving without anybody asking is the surprise this seam exists to end.
        $result = $this->runCase([
            'debug'    => ['enabled' => true],
            'record'   => 'xabsent',
            'provider' => 'xrival',
        ]);

        $this->assertSame('xabsent', $result['owner']);
        $this->assertSame('unclaimed', $result['status']);
        $this->assertFalse($result['provider_ran'], 'another provider took an unclaimed seat');
    }

    // ------------------------------------------------------- file-config-only rule

    #[Test]
    public function aRecordedOwnerLiesDormantWithoutAnEnabledDebugFile(): void
    {
        // The error screen is a debug.php feature by decision, not by accident: writing
        // that file takes filesystem access, which is the privilege matching the exposure
        // an error screen risks. Announced rather than silent, because a provider that is
        // installed, recorded and never running is the invisible state this seam ends.
        $result = $this->runCase([
            'debug'    => false,
            'record'   => 'xprovider',
            'provider' => 'xprovider',
        ]);

        $this->assertSame('core', $result['owner']);
        $this->assertSame('dormant', $result['status']);
        $this->assertFalse($result['provider_ran']);
        $this->assertStringContainsString('xprovider', $result['message']);
        $this->assertStringContainsString('debug.php', $result['message']);
    }

    #[Test]
    public function environmentConstantsExistWithNoDebugFileAtAll(): void
    {
        // Their docblock promises they are defined on every request. Guarding the call
        // meant they were absent on exactly the sites with no debug.php -- every
        // production site -- so a consumer reading them bare fatalled only in production.
        $result = $this->runCase(['debug' => false]);

        $this->assertSame('production', $result['environment']);
        $this->assertFalse($result['ray_enabled']);
    }

    // ------------------------------------------------------------- failure modes

    #[Test]
    public function aProviderThatThrowsLeavesTheBootStanding(): void
    {
        $result = $this->runCase([
            'debug'           => ['enabled' => true, 'error_screen' => 'xbroken'],
            'provider'        => 'xbroken',
            'provider_throws' => true,
        ]);

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('xbroken', $result['message']);
    }

    #[Test]
    public function aProviderThatReportsThenThrowsIsReportedAsFailed(): void
    {
        // The published constant and the read-back function must not disagree. They did:
        // the outcome lived in a first-writer-wins store, so a provider that reported
        // success and then died left the store saying 'active' while the constant said
        // 'error' -- two APIs answering one question differently, in precisely the
        // half-broken-provider case the diagnostics exist for.
        $result = $this->runCase([
            'debug'           => ['enabled' => true, 'error_screen' => 'xbroken'],
            'provider'        => 'xbroken',
            'provider_status' => 'active',
            'provider_throws' => true,
        ]);

        $this->assertSame('error', $result['status']);
        $this->assertSame('error', $result['read_back']['status'], 'constant and function disagree');
        $this->assertSame($result['returned'], $result['read_back']['status']);
    }

    #[Test]
    public function aTruncatedLoaderDoesNotStopTheBoot(): void
    {
        // Every call site in common.php must survive a debugconfig.php that fails to
        // compile. Guarding only the first one made the try/catch decorative: the boot
        // cleared it and fatalled a hundred lines later anyway.
        $result = $this->runCase(['case' => 'truncated-loader']);

        $this->assertContains('guard-caught-ParseError', $result['reached']);
        $this->assertContains('end-of-boot', $result['reached'], 'the boot died before the last line');
    }

    // --------------------------------------------------------------- the record

    #[Test]
    public function theGateAnswerIsHandedToTheProvider(): void
    {
        // Advisory, not enforced: a provider may legitimately render a production-safe
        // page for anonymous visitors, so core passes the answer instead of refusing to
        // dispatch. It must actually arrive.
        $result = $this->runCase([
            'debug'             => ['enabled' => true, 'error_screen' => 'xprovider'],
            'provider'          => 'xprovider',
            'developer_request' => true,
        ]);

        $this->assertTrue($result['provider_saw_gate']);
    }

    #[Test]
    public function aSecondProviderCannotTakeASeatThatIsHeld(): void
    {
        $first = $this->runCase([
            'debug'  => ['enabled' => true],
            'record' => 'xfirst',
        ]);
        $this->assertSame('xfirst', $first['recorded_owner']);

        // Same var_path: the record persists between the two processes, which is the point.
        $second = $this->runCase([
            'debug'  => ['enabled' => true],
            'record' => 'xsecond',
        ]);

        $this->assertFalse($second['record_call'], 'the second claim was accepted');
        $this->assertSame('xfirst', $second['recorded_owner']);
    }

    #[Test]
    public function aTokenIsRecordedInTheSameCaseThatConfigWouldUse(): void
    {
        // Config tokens are lower-cased on the way in and every comparison is strict, so
        // a mixed-case dirname that recorded verbatim worked when recorded and went
        // permanently unclaimed when pinned.
        $result = $this->runCase([
            'debug'  => ['enabled' => true],
            'record' => 'XProvider',
        ]);

        $this->assertSame('xprovider', $result['recorded_owner']);
    }
}
