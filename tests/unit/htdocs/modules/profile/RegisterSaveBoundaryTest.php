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

namespace Tests\Unit\Profile;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\System\SourceFileTestTrait;

require_once dirname(__DIR__) . '/system/SourceFileTestTrait.php';

/**
 * The registration step number comes from the client. Identity validation
 * (uniqueness, password rules, agreement, captcha) ran only inside the
 * step-1 branch, while the save branch inserted for any later step, so a
 * request claiming step 2 created an account without any of it. The save
 * branch must now require that step 1 passed in this session and validate
 * the identity values again before inserting.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class RegisterSaveBoundaryTest extends TestCase
{
    use SourceFileTestTrait;

    protected function setUp(): void
    {
        $this->loadSourceFile('htdocs/modules/profile/register.php');
    }

    #[Test]
    public function stepOneRecordsItsOutcomeAndStepZeroResetsIt(): void
    {
        $stepOne = $this->between("if (\$current_step == 1) {", '// If the last step required SAVE');
        self::assertStringContainsString("\$_SESSION['profile_register_validated'] = ('' === \$stop);", $stepOne);

        $stepZero = $this->between("if (\$current_step == 0) {", '} else {');
        self::assertStringContainsString("\$_SESSION['profile_register_validated'] = false;", $stepZero);
    }

    #[Test]
    public function newUserIsValidatedAgainAtTheSaveBoundary(): void
    {
        $save = $this->between("\$isNew = \$newuser->isNew();", 'insertUser($newuser)');

        self::assertStringContainsString("empty(\$_SESSION['profile_register_validated'])", $save);
        self::assertStringContainsString('XoopsUserUtility::validate($newuser, $pass, $vpass)', $save);
    }

    #[Test]
    public function insertIsSkippedWhenTheSaveBoundaryValidationFailed(): void
    {
        $save = $this->between("\$isNew = \$newuser->isNew();", '$profile_handler->insert($profile)');

        self::assertMatchesRegularExpression(
            "/if \\('' !== \\\$stop\\) \\{.*?\\} elseif \\(!\\\$member_handler->insertUser\\(\\\$newuser\\)\\)/s",
            $save
        );
    }

    #[Test]
    public function stepOneRecordIsConsumedByASuccessfulInsert(): void
    {
        // A record left behind by a finished or abandoned flow must not
        // authorise a second insert from the same session.
        $afterInsert = $this->between("\$_SESSION['profile_register_uid'] = \$newuser->getVar('uid');", 'if (!empty($stop) || isset($steps[$current_step])) {');

        self::assertStringContainsString("\$_SESSION['profile_register_validated'] = false;", $afterInsert);
    }

    #[Test]
    public function passwordIsCarriedBetweenStepsUnfiltered(): void
    {
        // Other fields are tag-stripped and trimmed on the way into the
        // session; the password is hashed and validated as typed at the save
        // step, so a filtered copy would be rejected or hashed wrongly.
        $merge = $this->between('$postfields = [];', "if (\$current_step == 0) {");

        self::assertStringContainsString("('pass' === \$fieldname || 'vpass' === \$fieldname)", $merge);
        self::assertStringContainsString(
            "Request::getVar(\$fieldname, '', 'POST', 'string', Request::MASK_ALLOW_RAW | Request::MASK_NO_TRIM)",
            $merge
        );
    }

    #[Test]
    public function laterStepMergeToleratesAMissingOrFinishedSessionCopy(): void
    {
        // The session copy is set to null when a flow finishes; a later-step
        // request in the same session must not fail in array_merge().
        $merge = $this->between('// Merge current $_POST', '$_POST                    = array_merge(');

        self::assertStringContainsString("array_merge(\$_SESSION['profile_post'] ?? [], \$postfields)", $merge);
    }

    /**
     * @return array<string, array{bool, string, int, bool}>
     */
    public static function saveBoundaryCases(): array
    {
        // [step-1 record present, revalidation result, expected insertUser() calls, expect _US_REGISTERNG]
        return [
            'forged later step without a step-1 record' => [false, '', 0, true],
            'record present but revalidation fails'     => [true, 'duplicate', 0, false],
            'record present and revalidation passes'    => [true, '', 1, false],
        ];
    }

    #[Test]
    #[DataProvider('saveBoundaryCases')]
    public function saveBranchOnlyInsertsAfterTheRecordAndRevalidationBothPass(bool $validated, string $validation, int $inserts, bool $rejected): void
    {
        [$stop, $calls] = $this->runSaveBranch($validated, $validation);

        self::assertSame($inserts, $calls, 'insertUser() call count');
        if ($rejected) {
            self::assertStringContainsString(_US_REGISTERNG, $stop);
        }
        if ($inserts === 0) {
            self::assertNotSame('', $stop, 'a refused save must carry a message for the form');
        }
    }

    /**
     * Executes the new-user save branch of register.php with stubbed request,
     * validator and handlers, returning the resulting $stop and the number of
     * insertUser() calls.
     *
     * @return array{string, int}
     */
    private function runSaveBranch(bool $validated, string $validation): array
    {
        $branch = $this->between('$isNew = $newuser->isNew();', '// User inserted! Now insert custom profile fields') . "}\n";

        $namespace = __NAMESPACE__ . '\\SaveBoundary';
        if (!class_exists($namespace . '\\Request', false)) {
            eval('namespace ' . $namespace . ';'
                . ' class Request {'
                . '   public const MASK_ALLOW_RAW = 1; public const MASK_NO_TRIM = 2;'
                . '   private static function read($n, $d) { return isset($_POST[$n]) ? (string) $_POST[$n] : $d; }'
                . '   public static function getString($n, $d = "", $h = "POST") { return self::read($n, $d); }'
                . '   public static function getEmail($n, $d = "", $h = "POST") { return self::read($n, $d); }'
                . '   public static function getUrl($n, $d = "", $h = "POST") { return self::read($n, $d); }'
                . '   public static function getVar($n, $d = "", $h = "POST", $t = "string", $m = 0) { return self::read($n, $d); }'
                . ' }'
                . ' class XoopsUserUtility { public static function validate($u, $p, $v) { return $GLOBALS["saveBoundaryValidation"]; } }');
        }
        if (!defined('_US_REGISTERNG')) {
            define('_US_REGISTERNG', 'Registration failed');
        }

        $_POST = ['uname' => 'newbie', 'email' => 'newbie@example.com', 'url' => '', 'pass' => 'secret123', 'vpass' => 'secret123'];
        $_SESSION['profile_register_validated'] = $validated;
        $GLOBALS['saveBoundaryValidation'] = $validation;
        $GLOBALS['xoopsConfig']     = ['com_order' => 0, 'com_mode' => 'flat', 'theme_set' => 'default'];
        $GLOBALS['xoopsConfigUser'] = ['activation_type' => 0, 'new_user_notify' => 0];

        $newuser = new class {
            public function isNew(): bool
            {
                return true;
            }

            public function setVar(string $k, mixed $v, bool $n = false): void
            {
            }

            public function getVar(string $k): mixed
            {
                return 'uid' === $k ? 5 : null;
            }

            /** @return string[] */
            public function getErrors(): array
            {
                return [];
            }
        };
        $member_handler = new class {
            public int $inserts = 0;

            public function insertUser(object $u): bool
            {
                ++$this->inserts;

                return true;
            }
        };
        $profile = new class {
            public function setVar(string $k, mixed $v): void
            {
            }
        };
        $profile_handler = new class {
            public function insert(object $p): bool
            {
                return true;
            }
        };
        $stop = '';

        eval('namespace ' . $namespace . ";\n" . $branch);

        return [$stop, $member_handler->inserts];
    }

    private function between(string $from, string $to): string
    {
        $start = strpos($this->sourceContent, $from);
        self::assertNotFalse($start, "start marker not found: $from");
        $end = strpos($this->sourceContent, $to, $start);
        self::assertNotFalse($end, "end marker not found after start: $to");

        return substr($this->sourceContent, $start, $end - $start);
    }
}
