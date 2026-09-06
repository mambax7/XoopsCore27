<?php

declare(strict_types=1);

namespace Xoops\Upgrade\Tests\Upgrade;

use DomainException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Xoops\Upgrade\XoopsUpgrade;

/**
 * Contract of {@see XoopsUpgrade::apply()} (issue #183).
 *
 * The wizard rebuilds its queue from the check_ methods on every request, so a
 * patch whose apply_ reports success without satisfying its check_ used to be
 * re-selected forever with no message. apply() now re-runs the checks, names
 * the task that failed, threw, or is still pending, and trusts only the tasks a
 * patch lists in $noRecheck (checks that read state fixed at request start).
 */
final class XoopsUpgradeApplyTest extends TestCase
{
    #[Test]
    public function happyPathAppliesAndPassesRecheck(): void
    {
        $patch = new class () extends XoopsUpgrade {
            public array $tasks = ['a'];
            private bool $done = false;
            public function __construct() {} // no DB needed
            public function check_a(): bool { return $this->done; }
            public function apply_a(): bool { $this->done = true; return true; }
        };

        self::assertTrue($patch->apply());
        self::assertSame('', $patch->message());
    }

    #[Test]
    public function applyThatDoesNotSatisfyItsCheckIsReportedAsPending(): void
    {
        $patch = new class () extends XoopsUpgrade {
            public array $tasks = ['good', 'stuck'];
            private bool $goodDone = false;
            public function __construct() {}
            public function check_good(): bool { return $this->goodDone; }
            public function apply_good(): bool { $this->goodDone = true; return true; }
            public function check_stuck(): bool { return false; }
            public function apply_stuck(): bool { return true; } // the #183 shape
        };

        self::assertFalse($patch->apply());
        self::assertStringContainsString('still pending after apply: stuck', $patch->message());
        self::assertStringNotContainsString('good', $patch->message());
    }

    #[Test]
    public function failingTaskIsNamedAndStopsTheRun(): void
    {
        $patch = new class () extends XoopsUpgrade {
            public array $tasks = ['broken', 'later'];
            public bool $laterRan = false;
            public function __construct() {}
            public function check_broken(): bool { return false; }
            public function apply_broken(): bool { return false; }
            public function check_later(): bool { return false; }
            public function apply_later(): bool { $this->laterRan = true; return true; }
        };

        self::assertFalse($patch->apply());
        self::assertStringContainsString('Task broken failed', $patch->message());
        self::assertFalse($patch->laterRan, 'tasks are ordered; a failure must stop the run');
    }

    #[Test]
    public function throwingTaskIsCaughtNamedAndEscaped(): void
    {
        $patch = new class () extends XoopsUpgrade {
            public array $tasks = ['boom'];
            public function __construct() {}
            public function check_boom(): bool { return false; }
            public function apply_boom(): bool { throw new RuntimeException('<disk on fire>'); }
        };

        self::assertFalse($patch->apply());
        self::assertStringContainsString(
            'Task boom threw RuntimeException: &lt;disk on fire&gt;',
            $patch->message()
        );
    }

    #[Test]
    public function absolutePathsInExceptionMessagesAreReducedToBasenames(): void
    {
        $patch = new class () extends XoopsUpgrade {
            public array $tasks = ['copy'];
            public function __construct() {}
            public function check_copy(): bool { return false; }
            public function apply_copy(): bool
            {
                throw new RuntimeException('copy(/var/www/html/xoops_data/configs/captcha/config.php): failed');
            }
        };

        self::assertFalse($patch->apply());
        self::assertStringContainsString('Task copy threw RuntimeException: copy(config.php): failed', $patch->message());
        self::assertStringNotContainsString('/var/www', $patch->message());
    }

    #[Test]
    public function taskListedInNoRecheckIsTrusted(): void
    {
        $patch = new class () extends XoopsUpgrade {
            public array $tasks = ['mainfile'];
            protected array $noRecheck = ['mainfile'];
            public function __construct() {}
            public function check_mainfile(): bool { return false; } // constant cannot refresh
            public function apply_mainfile(): bool { return true; }
        };

        self::assertTrue($patch->apply());
        self::assertSame('', $patch->message());
    }

    #[Test]
    public function checkThrowingDuringVerificationIsCaughtAndNamed(): void
    {
        $patch = new class () extends XoopsUpgrade {
            public array $tasks = ['x'];
            private int $calls = 0;
            public function __construct() {}
            public function check_x(): bool
            {
                if (++$this->calls > 1) {
                    throw new LogicException('recheck exploded');
                }
                return false;
            }
            public function apply_x(): bool { return true; }
        };

        self::assertFalse($patch->apply());
        self::assertStringContainsString('Verification after apply threw RuntimeException', $patch->message());
        self::assertStringContainsString('check_x() threw LogicException: recheck exploded', $patch->message());
    }

    #[Test]
    public function checkThrowingOnInitialStatusIsCaughtAndNamed(): void
    {
        $patch = new class () extends XoopsUpgrade {
            public array $tasks = ['ok', 'bad'];
            public function __construct() {}
            public function check_ok(): bool { return true; }
            public function apply_ok(): bool { return true; }
            public function check_bad(): bool { throw new DomainException('cannot decide'); }
            public function apply_bad(): bool { return true; }
        };

        self::assertFalse($patch->apply());
        self::assertStringContainsString('check_bad() threw DomainException: cannot decide', $patch->message());

        // The same wrapped exception is what buildUpgradeQueue() lets reach the fatal handler.
        try {
            $patch->isApplied();
            self::fail('isApplied() must rethrow');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('::check_bad() threw DomainException', $e->getMessage());
            self::assertInstanceOf(DomainException::class, $e->getPrevious());
        }
    }
}
