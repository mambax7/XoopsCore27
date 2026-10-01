<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Behaviour of the notification-deletion selection helpers in
 * include/notification_functions.php, used by notifications.php:
 *
 *  - xoops_notification_selection($raw): the posted
 *    del_not[<module id>][] = <notification id> selection, reduced to
 *    positive integers grouped by module id. A key or value that is not a
 *    canonical positive integer is dropped, not coerced: PHP's (int) cast
 *    would turn "12junk" into 12, "1.9" into 1 and "1e3" into 1000.
 *  - xoops_notification_confirm_fields($selection): the selection as
 *    del_not[<module id>][<n>] hidden fields for the confirmation form.
 *
 * The round-trip test plays the confirmation step end to end: the hidden
 * fields are rendered, posted back the way a browser submits them and
 * decoded by PHP's own query parser, and the delete step's collector must
 * recover exactly the selection that was confirmed.
 */
final class NotificationSelectionTest extends TestCase
{
    protected function setUp(): void
    {
        require_once XOOPS_ROOT_PATH . '/include/notification_functions.php';
        require_once XOOPS_ROOT_PATH . '/include/html_safety.php';
        foreach (['xoops_notification_selection', 'xoops_notification_confirm_fields'] as $function) {
            if (!function_exists($function)) {
                self::fail("$function() is missing from include/notification_functions.php.");
            }
        }
    }

    #[Test]
    public function validSelectionIsGroupedByModule(): void
    {
        self::assertSame(
            [3 => [10, 11], 7 => [42]],
            xoops_notification_selection([3 => ['10', 11], '7' => ['42']])
        );
    }

    /** @return array<string, array{mixed}> */
    public static function malformedIds(): array
    {
        return [
            'trailing junk'    => ['12junk'],
            'decimal'          => ['1.9'],
            'exponent'         => ['1e3'],
            'leading space'    => [' 12'],
            'trailing newline' => ["12\n"],
            'leading zero'     => ['012'],
            'plus sign'        => ['+5'],
            'hex'              => ['0x1A'],
            'zero'             => ['0'],
            'negative'         => ['-3'],
            'empty'            => [''],
            'too long'         => ['1234567890123456789'],
            'float'            => [12.0],
            'bool'             => [true],
            'null'             => [null],
            'nested array'     => [['12']],
            'zero int'         => [0],
            'negative int'     => [-4],
        ];
    }

    #[Test]
    #[DataProvider('malformedIds')]
    public function malformedNotificationIdIsDropped(mixed $id): void
    {
        self::assertSame([3 => [10]], xoops_notification_selection([3 => [$id, '10']]));
    }

    /** @return array<string, array{array<int|string, mixed>}> */
    public static function malformedModuleKeys(): array
    {
        return [
            'non-numeric key' => [['mod' => ['10']]],
            'junk key'        => [['3junk' => ['10']]],
            'leading zero'    => [['03' => ['10']]],
            'zero'            => [[0 => ['10']]],
            'negative'        => [[-1 => ['10']]],
            'scalar list'     => [[3 => '10']],
        ];
    }

    /** @param array<int|string, mixed> $raw */
    #[Test]
    #[DataProvider('malformedModuleKeys')]
    public function malformedModuleEntryIsDropped(array $raw): void
    {
        self::assertSame([], xoops_notification_selection($raw));
    }

    #[Test]
    public function nonArraySelectionIsEmpty(): void
    {
        self::assertSame([], xoops_notification_selection('10'));
        self::assertSame([], xoops_notification_selection(null));
    }

    #[Test]
    public function confirmFieldsUseBracketedNames(): void
    {
        self::assertSame(
            ['del_not[3][0]' => 10, 'del_not[3][1]' => 11, 'del_not[7][0]' => 42],
            xoops_notification_confirm_fields([3 => [10, 11], 7 => [42]])
        );
    }

    #[Test]
    public function confirmedSelectionSurvivesTheRoundTripToTheDeleteStep(): void
    {
        $posted    = [3 => ['10', '11'], '7' => ['42'], 'x' => ['9'], 5 => ['12junk']];
        $selection = xoops_notification_selection($posted);
        self::assertSame([3 => [10, 11], 7 => [42]], $selection);

        // Render the confirmation fields, then submit them the way a browser
        // does (name=value pairs) and let PHP decode them into $_POST.
        $fields = xoops_notification_confirm_fields($selection);
        $html   = xoops_confirm_fields(['delete' => 1] + $fields);
        preg_match_all('/<input type="hidden" name="([^"]*)" value="([^"]*)" \/>/', $html, $m, PREG_SET_ORDER);
        $pairs = [];
        foreach ($m as [, $name, $value]) {
            $pairs[] = rawurlencode(html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
                . '=' . rawurlencode(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        parse_str(implode('&', $pairs), $submitted);

        self::assertSame('1', $submitted['delete'] ?? null, 'the confirmation must submit to the delete step');
        self::assertSame($selection, xoops_notification_selection($submitted['del_not'] ?? null));
    }
}
