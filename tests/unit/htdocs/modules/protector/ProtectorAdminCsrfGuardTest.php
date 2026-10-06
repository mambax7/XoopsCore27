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

namespace modulesprotector;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Protector's admin pages check their forms with the core CSRF token since
 * 2.7.4. They are procedural and cannot run in isolation, so the wiring is
 * asserted at the source level, as hardening/CsrfTokenGuardTest does for the
 * system module: in every mutating branch the token check precedes the first
 * database call, and every POST form carries the core token field.
 *
 * @category  Xoops
 * @package   Tests
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversNothing]
final class ProtectorAdminCsrfGuardTest extends TestCase
{
    private const GUARD = "xoopsSecurity']->check()";
    private const FIELD = "xoopsSecurity']->getTokenHTML()";

    private static function source(string $file): string
    {
        $src = file_get_contents(XOOPS_PATH . '/modules/protector/admin/' . $file);
        self::assertNotFalse($src, $file);

        return $src;
    }

    /** The block from $start up to the next branch marker, or to the end of the file. */
    private static function block(string $src, string $start, string $nextMarker): string
    {
        $from = strpos($src, $start);
        self::assertNotFalse($from, "branch '{$start}' not found");
        $to = strpos($src, $nextMarker, $from + strlen($start));

        return false === $to ? substr($src, $from) : substr($src, $from, $to - $from);
    }

    private static function assertGuardBeforeFirstDatabaseCall(string $block, string $label): void
    {
        $guard = strpos($block, self::GUARD);
        self::assertNotFalse($guard, "{$label}: missing token check");
        self::assertSame(1, preg_match('/\$db->(query|exec)\(/', $block, $m, PREG_OFFSET_CAPTURE), "{$label}: no database call");
        self::assertLessThan($m[0][1], $guard, "{$label}: token check must precede the first database call");
        self::assertStringContainsString("redirect_header(", substr($block, $guard, 200), "{$label}: a failed check must redirect");
    }

    /** @return array<string, array{string, string, string}> */
    public static function mutatingBranches(): array
    {
        return [
            'center.php action block'    => ['center.php', "if (\$action !== '') {", "\n}\n"],
            'prefix_manager.php copy'    => ['prefix_manager.php', "Request::hasVar('copy', 'POST')", '} elseif ('],
            'prefix_manager.php backup'  => ['prefix_manager.php', "Request::hasVar('backup', 'POST')", '} elseif ('],
            'prefix_manager.php delete'  => ['prefix_manager.php', "Request::hasVar('delete', 'POST')", "\n}\n"],
        ];
    }

    #[Test]
    #[DataProvider('mutatingBranches')]
    public function tokenCheckPrecedesTheFirstDatabaseCall(string $file, string $start, string $next): void
    {
        self::assertGuardBeforeFirstDatabaseCall(self::block(self::source($file), $start, $next), $file . ' ' . $start);
    }

    #[Test]
    public function centerPostFormsCarryTheCoreTokenField(): void
    {
        $src   = self::source('center.php');
        $forms = preg_split("/<form[^>]*method='POST'[^>]*>/i", $src);
        self::assertNotFalse($forms);
        self::assertGreaterThanOrEqual(3, count($forms), 'center.php renders at least two POST forms');
        foreach (array_slice($forms, 1) as $i => $after) {
            $end = strpos($after, '</form>');
            self::assertStringContainsString(self::FIELD, substr($after, 0, false === $end ? null : $end), "POST form " . ($i + 1) . " lacks the core token field");
        }
    }

    #[Test]
    public function prefixManagerRowFormsCarryTheCoreTokenField(): void
    {
        $src = self::source('prefix_manager.php');
        self::assertStringContainsString('$ticket_input = $GLOBALS[\'' . self::FIELD . ';', $src);
        self::assertGreaterThanOrEqual(2, substr_count($src, '$ticket_input'), 'the token field is rendered into the row forms');
    }

    #[Test]
    #[DataProvider('pages')]
    public function noGTicketLeft(string $file): void
    {
        self::assertStringNotContainsString('GTicket', self::source($file));
        self::assertStringNotContainsString('gtickets.php', self::source($file));
    }

    /** @return array<string, array{string}> */
    public static function pages(): array
    {
        return ['center' => ['center.php'], 'prefix_manager' => ['prefix_manager.php']];
    }
}
