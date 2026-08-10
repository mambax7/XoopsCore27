<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversNothing;
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
 *
 * ---------------------------------------------------------------------------------------
 * IF YOU CHANGE ONE OF THE THREE CONTESTED DETECTORS, OR ONE OF THEIR CASES: break the
 * detector by hand, re-run its case, and require RED before you put it back.
 *
 * This is not ceremony. One of these cases was once green against the exact regression it
 * was named for -- its fixture took BOTH handlers, so detector 3 fired on the error handler
 * and the exception-handler comparison the case existed to protect was never reached.
 * Reverting detector 3 to the one-eyed version left the whole suite green. Nothing in a
 * passing run can tell you that; only the mutant can.
 *
 * | Detector | The case that must kill it |
 * |---|---|
 * | 1 refused reports         | two listeners report; the second is refused |
 * | 2 report-time vs final    | exception-only registration AFTER the accepted report |
 * | 3 pre-dispatch vs final   | exception-only SILENT registration, with the first
 * |                           | provider taking only the exception handler |
 *
 * The cases also carry self-guards (detector 3's asserts error_handler_is_provider is
 * false) so that a later well-meaning fixture change fails loudly instead of quietly
 * going vacuous again. ADR-0001 Appendix A is the long version.
 * ---------------------------------------------------------------------------------------
 */
// CoversNothing, deliberately. Every case executes the seam in a SUBPROCESS (see above),
// so the parent PHPUnit process never runs -- and cannot collect coverage for -- the
// functions under test: xoops_activateErrorScreen, xoops_getErrorScreenOwner,
// xoops_getErrorScreenOwnerSource, xoops_getErrorScreenStatus, xoops_recordErrorScreenOwner,
// xoops_releaseErrorScreenOwner, xoops_writeDebugRuntimeOverride, xoops_applyDebugConfig.
// Declaring those as CoversFunction targets made the coverage job fail with "not a valid
// target" (the functions are never defined in this process), and loading the file here just
// to validate the metadata would record a misleading 0%.
#[CoversNothing]
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

        // base64, not raw JSON.
        //
        // escapeshellarg() on Windows wraps the argument in double quotes and REPLACES
        // every embedded double quote with a space -- so a JSON spec arrives at the
        // runner as { var_path : ... }, which is not JSON, and every case here failed on
        // the exact host this seam is developed on while passing on Linux CI. Base64 has
        // no characters any shell cares about, on any platform, so the quoting question
        // stops existing rather than being answered per-OS.
        $command = escapeshellarg(PHP_BINARY)
            . ' ' . escapeshellarg(__DIR__ . '/fixtures/errorscreen-runner.php')
            . ' ' . escapeshellarg(base64_encode((string) json_encode($spec)));

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
    }

    // --------------------------------------------------- the boot's own guard chain

    /**
     * Every call site in the REAL common.php, not a copy of it.
     *
     * The subprocess 'truncated-loader' case below proves the boot survives a
     * debugconfig.php that fails to compile -- but it proves it against a hand-written
     * replay of common.php's guard sequence, so a bare xoops_applyDebugConfig() added to
     * the real file tomorrow would leave that case green. It guards a copy.
     *
     * This reads the shipped file and audits it directly. The two are not redundant: one
     * asks "does the boot survive?", this one asks "is every call still guarded?", and it
     * is the second question that rots.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function guardedBootFiles(): array
    {
        $root = dirname(__DIR__, 4) . '/htdocs';

        return [
            ['include/common.php', $root . '/include/common.php'],
            ['mainfile.dist.php', $root . '/mainfile.dist.php'],
        ];
    }

    #[Test]
    public function everyDebugCallInTheBootIsGuardedByFunctionExists(): void
    {
        $guarded = ['xoops_getDebugConfig', 'xoops_applyDebugConfig', 'xoops_activateErrorScreen'];

        foreach (self::guardedBootFiles() as [$label, $path]) {
            $this->assertFileExists($path, $label . ' is missing');
            $lines = file($path, FILE_IGNORE_NEW_LINES);
            $this->assertIsArray($lines);

            foreach ($lines as $number => $line) {
                // Comments and docblocks name these functions constantly; only code counts.
                $code = trim($line);
                if ('' === $code || str_starts_with($code, '//') || str_starts_with($code, '*')
                    || str_starts_with($code, '/*')) {
                    continue;
                }

                foreach ($guarded as $function) {
                    if (!str_contains($line, $function . '(')) {
                        continue;
                    }
                    // The guard is either on this line (the ternary form) or on an
                    // enclosing if (the block form). Walk back over the three nearest
                    // lines of CODE rather than three raw lines: mainfile.dist.php puts a
                    // four-line comment between its guard and the guarded call, and a raw
                    // window narrow enough to be useful would read that as a violation.
                    $window  = $line;
                    $seen    = 0;
                    for ($back = $number - 1; $back >= 0 && $seen < 3; --$back) {
                        $previous = trim($lines[$back]);
                        if ('' === $previous || str_starts_with($previous, '//')
                            || str_starts_with($previous, '*') || str_starts_with($previous, '/*')) {
                            continue;
                        }
                        $window .= "\n" . $lines[$back];
                        ++$seen;
                    }
                    $this->assertStringContainsString(
                        "function_exists('" . $function . "')",
                        $window,
                        sprintf(
                            '%s line %d calls %s() with no function_exists() guard within'
                            . ' three lines. Every call site must survive a debugconfig.php'
                            . ' that fails to compile; one bare call makes the try/catch'
                            . ' around the loader decorative.',
                            $label,
                            $number + 1,
                            $function
                        )
                    );
                }
            }
        }
    }

    // ---------------------------------------------------- the opt-in strict gate

    #[Test]
    public function theGateIsAdvisoryUntilASiteAsksForItToBeEnforced(): void
    {
        // The default, and the argument for it: a provider MAY render a production-safe
        // page for an anonymous visitor, and core cannot tell that apart from a stack
        // trace full of superglobals. So core passes the answer and lets the provider
        // decide.
        $result = $this->runCase([
            'debug'             => ['enabled' => true, 'error_screen' => 'xprovider'],
            'provider'          => 'xprovider',
            'developer_request' => false,
        ]);

        $this->assertSame('active', $result['status']);
        $this->assertTrue($result['provider_ran'], 'the provider must still be offered the seat');
        $this->assertFalse($result['provider_saw_gate'], 'and must be told this is not a developer');
    }

    #[Test]
    public function strictModeStopsTheDispatchForANonDeveloperRequest(): void
    {
        // A site that would rather not extend that trust to every provider it might
        // install can say so, and then a provider that ignores the flag never gets the
        // chance to ignore it.
        $result = $this->runCase([
            'debug' => [
                'enabled'             => true,
                'error_screen'        => 'xprovider',
                'error_screen_strict' => true,
            ],
            'provider'          => 'xprovider',
            'developer_request' => false,
        ]);

        $this->assertSame('suppressed', $result['status']);
        $this->assertFalse($result['provider_ran'], 'strict mode must not dispatch at all');
        $this->assertStringContainsString('error_screen_strict', $result['message']);
    }

    #[Test]
    public function strictModeIsInvisibleToADeveloperRequest(): void
    {
        $result = $this->runCase([
            'debug' => [
                'enabled'             => true,
                'error_screen'        => 'xprovider',
                'error_screen_strict' => true,
            ],
            'provider'          => 'xprovider',
            'developer_request' => true,
        ]);

        $this->assertSame('active', $result['status']);
        $this->assertTrue($result['provider_ran']);
    }

    #[Test]
    public function strictModeNeedsARealBooleanLikeEveryOtherSwitch(): void
    {
        // 'true' as a STRING leaves it off. That is the same trap, and the same answer,
        // as every other switch in debug.php -- and here failing closed means failing
        // toward the documented default rather than toward a stricter one nobody asked
        // for, which would silently stop providers running.
        $result = $this->runCase([
            'debug' => [
                'enabled'             => true,
                'error_screen'        => 'xprovider',
                'error_screen_strict' => 'true',
            ],
            'provider'          => 'xprovider',
            'developer_request' => false,
        ]);

        $this->assertSame('active', $result['status']);
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
    public function aProviderThatRegistersAndThenThrowsGetsTheHandlersTakenBack(): void
    {
        // The guarantee the seam is built on: a broken provider must not be able to take
        // the site down. Without the rollback it could -- a provider that calls
        // set_error_handler() and only THEN fails keeps PHP's handlers for the rest of
        // the request, while the constants say the activation failed. The site's error
        // path then belongs to something core has already declared broken.
        //
        // aProviderThatThrowsLeavesTheBootStanding() cannot catch this: its provider
        // never registers, so there is nothing to hand back.
        $result = $this->runCase([
            'debug'              => ['enabled' => true, 'error_screen' => 'xbroken'],
            'provider'           => 'xbroken',
            'provider_registers' => true,
            'provider_throws'    => true,
        ]);

        $this->assertSame('error', $result['status']);
        $this->assertTrue($result['provider_registered'], 'the fixture provider must take the handlers first');
        $this->assertTrue($result['error_handler_is_core'], 'the error handler must be handed back');
        $this->assertTrue($result['exc_handler_is_core'], 'the exception handler must be handed back');
        $this->assertFalse($result['error_handler_is_provider']);
        $this->assertFalse($result['exc_handler_is_provider']);
    }

    #[Test]
    public function aProviderThatRegistersAndSelfCatchesGetsTheHandlersTakenBack(): void
    {
        // The path a restore living in core's catch would miss, and the one BOTH shipped
        // providers take: the provider wraps its own activation, catches its own failure,
        // reports 'error' and returns normally. Core's catch never runs -- so the
        // rollback is keyed on the outcome, not on the exception.
        $result = $this->runCase([
            'debug'              => ['enabled' => true, 'error_screen' => 'xbroken'],
            'provider'           => 'xbroken',
            'provider_registers' => true,
            'provider_status'    => 'error',
            'provider_throws'    => false,
        ]);

        $this->assertSame('error', $result['status']);
        $this->assertSame('error', $result['read_back']['status'], 'constant and read-back must agree');
        $this->assertTrue($result['provider_registered']);
        $this->assertTrue($result['error_handler_is_core'], 'the error handler must be handed back');
        $this->assertTrue($result['exc_handler_is_core'], 'the exception handler must be handed back');
    }

    #[Test]
    public function aProviderThatSucceedsKeepsTheHandlersItRegistered(): void
    {
        // The other half of the contract, and the reason the rollback is keyed on 'error'
        // rather than on "did the handlers change": a provider that works must keep them.
        $result = $this->runCase([
            'debug'              => ['enabled' => true, 'error_screen' => 'xworks'],
            'provider'           => 'xworks',
            'provider_registers' => true,
            'provider_status'    => 'active',
        ]);

        $this->assertSame('active', $result['status']);
        $this->assertTrue($result['error_handler_is_provider'], 'a working provider keeps the error handler');
        $this->assertTrue($result['exc_handler_is_provider'], 'a working provider keeps the exception handler');
        $this->assertFalse($result['error_handler_is_core']);
    }

    #[Test]
    public function twoModulesAnsweringOneTokenAreReportedAndTheSeatIsVacated(): void
    {
        // Detector 1. The reporter closure has always refused the second report; it used
        // to throw that knowledge away and publish the first module's status as though
        // nothing else had happened -- while the second module's handlers were the ones
        // actually installed.
        $result = $this->runCase([
            'debug'              => ['enabled' => true, 'error_screen' => 'xshared'],
            'provider'           => 'xshared',
            'provider_registers' => true,
            'second_listener'    => 'report',
        ]);

        $this->assertTrue($result['second_listener_ran']);
        $this->assertSame('contested', $result['status']);
        $this->assertStringContainsString('xshared', $result['message']);
        $this->assertTrue($result['error_handler_is_core'], 'a contested seat goes back to XoopsLogger');
        $this->assertFalse($result['error_handler_is_provider']);
    }

    #[Test]
    public function aSecondListenerThatRegistersWithoutReportingIsDetected(): void
    {
        // Detector 2. The dangerous half of the same problem: the second module says
        // nothing, so the refusal count is zero and the published status looks perfectly
        // healthy. Core catches it by comparing who held the handlers when the winning
        // report arrived against who holds them at the end.
        $result = $this->runCase([
            'debug'              => ['enabled' => true, 'error_screen' => 'xshared'],
            'provider'           => 'xshared',
            'provider_registers' => true,
            'second_listener'    => 'silent',
        ]);

        $this->assertTrue($result['second_listener_ran']);
        $this->assertSame('contested', $result['status']);
        $this->assertTrue($result['error_handler_is_core']);
    }

    #[Test]
    public function aListenerThatRegistersAndNeverReportsIsNotCalledUnclaimed(): void
    {
        // Detector 3. Nobody reports at all, so the old code published 'unclaimed' --
        // "no active module claims the error screen; the handlers stay with XoopsLogger"
        // -- while a module had quietly taken them. That message was not merely unhelpful,
        // it was false.
        $result = $this->runCase([
            'debug'              => ['enabled' => true, 'error_screen' => 'xsilent'],
            'provider'           => 'xsilent',
            'provider_registers' => true,
            'second_listener'    => 'silent',
            'provider_status'    => '',
        ]);

        $this->assertNotSame('unclaimed', $result['status']);
        $this->assertSame('contested', $result['status']);
        $this->assertTrue($result['error_handler_is_core']);
    }

    #[Test]
    public function aListenerThatTakesOnlyTheExceptionHandlerIsStillDetected(): void
    {
        // PHP hands out the two handlers separately, and a listener is free to take one
        // without the other. The detectors used to compare only the error handler, so a
        // listener that quietly took just the exception handler was published as
        // 'unclaimed' -- "the handlers stay with XoopsLogger" -- while the exception path
        // was already somebody else's. The message was not merely unhelpful, it was false
        // for half of what it described.
        //
        // 'exception', not true, and that is the whole test. With `provider_registers =>
        // true` the FIRST listener takes the error handler as well, so detector 3 sees a
        // moved error handler and publishes 'contested' whether or not it ever looks at
        // the exception handler -- the case would stay green against the exact regression
        // it is named for. Nothing here may touch the error handler, so the only evidence
        // available to core is the exception handler, and the assertion means something.
        $result = $this->runCase([
            'debug'              => ['enabled' => true, 'error_screen' => 'xshared'],
            'provider'           => 'xshared',
            'provider_registers' => 'exception',
            'second_listener'    => 'silent-exception',
            'provider_status'    => '',
        ]);

        $this->assertSame('contested', $result['status']);
        $this->assertTrue(
            $result['error_handler_is_core'],
            'no listener in this case touches the error handler; it must still be the core stand-in'
        );
        $this->assertFalse(
            $result['error_handler_is_provider'],
            'if this is ever true the case has stopped being exception-only and proves nothing'
        );
        $this->assertTrue($result['exc_handler_is_core'], 'the exception handler must come back too');
    }

    #[Test]
    public function anExceptionOnlyListenerAfterTheWinningReportIsDetected(): void
    {
        // The same blind spot on detector 2: a provider reports and is accepted, then a
        // later listener takes only the exception handler. Comparing error handlers alone
        // saw nothing and published 'active'.
        $result = $this->runCase([
            'debug'              => ['enabled' => true, 'error_screen' => 'xshared'],
            'provider'           => 'xshared',
            'provider_registers' => true,
            'second_listener'    => 'silent-exception',
        ]);

        $this->assertSame('contested', $result['status']);
        $this->assertTrue($result['exc_handler_is_core']);
    }

    #[Test]
    public function aProviderThatThrowsBeforeReportingIsCalledErrorNotContested(): void
    {
        // Core caught the exception, so it KNOWS this was a failure rather than a second
        // listener — 'error' is the more specific answer and must win. Detector 3 used to
        // overwrite it with 'contested', reporting a multi-listener problem that never
        // happened. The handlers go back either way; only the diagnosis was wrong.
        $result = $this->runCase([
            'debug'                         => ['enabled' => true, 'error_screen' => 'xbroken'],
            'provider'                      => 'xbroken',
            'provider_registers'            => true,
            'provider_throws_before_report' => true,
        ]);

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('xbroken', $result['message']);
        $this->assertTrue($result['error_handler_is_core']);
        $this->assertTrue($result['exc_handler_is_core']);
    }

    #[Test]
    public function aSingleWellBehavedProviderIsNotReportedAsContested(): void
    {
        // The false-positive guard. All three detectors compare handler identity, so a
        // lone provider doing everything right must come through untouched -- otherwise
        // the mitigation would disable every working error screen and nothing would fail.
        $result = $this->runCase([
            'debug'              => ['enabled' => true, 'error_screen' => 'xworks'],
            'provider'           => 'xworks',
            'provider_registers' => true,
            'provider_status'    => 'active',
        ]);

        $this->assertSame('active', $result['status']);
        $this->assertFalse($result['second_listener_ran']);
        $this->assertTrue($result['error_handler_is_provider']);
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

    #[Test]
    public function theHolderCanReleaseItsSeatAndTheFileStaysAnObject(): void
    {
        // The release half of the ownership lifecycle, exercised end to end: claim, then
        // release the same token. The seat must be free afterwards, and the runtime file
        // -- emptied of its last key -- must serialise as the JSON OBJECT {}, not the
        // array [] an empty PHP array would encode to. A consumer in another language
        // reading [] where it expected an object has been handed a different type.
        $result = $this->runCase([
            'debug'   => ['enabled' => true],
            'record'  => 'xprovider',
            'release' => 'xprovider',
        ]);

        $this->assertTrue($result['record_call']);
        $this->assertTrue($result['release_call']);
        $this->assertSame('', $result['recorded_owner'], 'the released seat must be free');
        $this->assertSame('{}', $result['runtime_raw'], 'the emptied file must stay a JSON object');
    }

    #[Test]
    public function aNonHolderCannotReleaseAnotherModulesSeat(): void
    {
        // Release checks identity: uninstalling one module must not free another module's
        // seat just because it ran later. The call still returns true -- the caller asked
        // for "the record no longer names me", and it does not -- but the holder's record
        // survives untouched.
        $result = $this->runCase([
            'debug'   => ['enabled' => true],
            'record'  => 'xfirst',
            'release' => 'xother',
        ]);

        $this->assertTrue($result['release_call']);
        $this->assertSame('xfirst', $result['recorded_owner'], "another module's seat must survive a foreign release");
    }
}
