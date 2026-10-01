<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * xoops_confirm() field rendering regression (Snyk CWE-79).
 *
 * include/functions.php cannot be loaded under the test bootstrap (it redefines
 * the stubbed redirect_header()), so the fix extracts the field rendering that
 * xoops_confirm() duplicates across its template and fallback branches into a
 * pure helper:
 *
 *     include/html_safety.php
 *     function xoops_confirm_fields(array $hiddens): string
 *
 * Contract pinned here (backward compatible with today's xoops_confirm()):
 *  - scalar value  -> <input type="hidden" name="N" value="V" />
 *  - array value   -> one <input type="radio"> per caption => value, then <br>
 *  - every NAME, VALUE and radio CAPTION is HTML-escaped exactly once
 *  - null/bool are rendered without PHP 8.1+ deprecations or "Array" leaks
 *
 * Flagged sinks this covers (the xoops_confirm() taint flows Snyk reported):
 *  - notifications.php:192            del_not array keys -> radio captions (raw)
 *  - modules/system/admin/modulesadmin/main.php:337/401/466  'module' => $module
 *  - class/xoopseditor/tinymce{5,7}/.../xoopsimagemanager.php:309/358  'target'
 *  - modules/pm/viewpmsg.php:166, readpmsg.php:36  hidden scalars
 */
final class ConfirmFieldsEscapingTest extends TestCase
{
    private const HELPER = XOOPS_ROOT_PATH . '/include/html_safety.php';

    protected function setUp(): void
    {
        if (!is_file(self::HELPER)) {
            self::fail('include/html_safety.php is missing (fix not applied yet).');
        }
        require_once self::HELPER;
        if (!function_exists('xoops_confirm_fields') || !function_exists('xoops_confirm_submit_label')) {
            self::fail('xoops_confirm_fields() or xoops_confirm_submit_label() is not defined in include/html_safety.php.');
        }
    }

    // ---------------------------------------------------------------------
    // Backward-compatible shape
    // ---------------------------------------------------------------------

    #[Test]
    public function scalarRendersExactHiddenInput(): void
    {
        self::assertSame(
            '<input type="hidden" name="op" value="delete_ok" />',
            xoops_confirm_fields(['op' => 'delete_ok'])
        );
    }

    #[Test]
    public function arrayStillRendersAsRadioGroup(): void
    {
        $html = xoops_confirm_fields(['choice' => ['Yes' => '1', 'No' => '0']]);

        self::assertSame(2, substr_count($html, 'type="radio"'));
        self::assertStringContainsString('name="choice" value="1" /> Yes', $html);
        self::assertStringContainsString('name="choice" value="0" /> No', $html);
        self::assertStringEndsWith('<br>', $html);
    }

    // ---------------------------------------------------------------------
    // Snyk-flagged flows, pinned with concrete payloads
    // ---------------------------------------------------------------------

    /**
     * notifications.php:181-192 passes Request::getArray('del_not') straight in;
     * the array KEY becomes the radio caption. POST body used for the repro:
     *   delete_ok=1&del_not[<img src=x onerror=alert(document.domain)>]=1
     */
    #[Test]
    public function notificationsRadioCaptionPayloadIsEscaped(): void
    {
        $html = xoops_confirm_fields([
            'uid'       => 1,
            'delete_ok' => 1,
            'del_not'   => ['<img src=x onerror=alert(document.domain)>' => '1'],
        ]);

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(document.domain)&gt;', $html);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function hostileFieldPayloads(): array
    {
        return [
            'name breaks out of attribute' => [['x"><svg onload=alert(1)>' => '1'], '<svg'],
            'module dirname (modulesadmin:337)' => [
                ['module' => '"><script>alert(1)</script>', 'op' => 'install_ok', 'fct' => 'modulesadmin'],
                '<script>',
            ],
            'single-quote attribute break' => [['q' => "' autofocus onfocus=alert(1) x='"], "' autofocus"],
            'radio value break' => [['c' => ['ok' => '"><svg onload=alert(1)>']], '<svg'],
        ];
    }

    /** @param array<string, mixed> $hiddens */
    #[Test]
    #[DataProvider('hostileFieldPayloads')]
    public function hostileNamesValuesAndCaptionsAreEscaped(array $hiddens, string $mustNotAppear): void
    {
        self::assertStringNotContainsString($mustNotAppear, xoops_confirm_fields($hiddens));
    }

    /**
     * tinymce{5,7} xoopsimagemanager.php pre-escaped $target and xoops_confirm()
     * escaped it again ("&" -> "&amp;amp;"). The helper must encode exactly once;
     * the call site must stop pre-escaping (see source pin below).
     */
    #[Test]
    public function valuesAreEncodedExactlyOnce(): void
    {
        $html = xoops_confirm_fields(['target' => 'a&b']);

        self::assertStringContainsString('value="a&amp;b"', $html);
        self::assertStringNotContainsString('&amp;amp;', $html);
    }

    // ---------------------------------------------------------------------
    // PHP 8.2 / 8.6 hygiene
    // ---------------------------------------------------------------------

    #[Test]
    public function nullBoolAndNumericScalarsRenderWithoutDeprecations(): void
    {
        set_error_handler(static function (int $errno, string $errstr): bool {
            throw new \ErrorException($errstr, 0, $errno);
        });
        try {
            $html = xoops_confirm_fields(['n' => null, 't' => true, 'f' => false, 'i' => 7, 'd' => 1.5]);
        } finally {
            restore_error_handler();
        }

        self::assertStringContainsString('name="n" value=""', $html);
        self::assertStringContainsString('name="t" value="1"', $html);
        self::assertStringContainsString('name="f" value=""', $html);
        self::assertStringContainsString('name="i" value="7"', $html);
        self::assertStringContainsString('name="d" value="1.5"', $html);
    }

    /**
     * The real notifications form posts del_not[<modid>][]=<id>, so the radio
     * branch receives an ARRAY as the radio value. Today that reaches
     * htmlspecialchars(array) -> TypeError. It must be skipped, never stringified.
     */
    #[Test]
    public function nestedArrayRadioValueIsSkippedNotStringified(): void
    {
        set_error_handler(static function (int $errno, string $errstr): bool {
            throw new \ErrorException($errstr, 0, $errno);
        });
        try {
            $html = xoops_confirm_fields(['del_not' => ['3' => ['10', '11']]]);
        } finally {
            restore_error_handler();
        }

        self::assertStringNotContainsString('Array', $html);
        self::assertStringNotContainsString('value="10"', $html);
    }

    // ---------------------------------------------------------------------
    // Submit label (value="" and title="" in system_confirm.tpl and the
    // xbootstrap5 / xswatch5 copies, and in the fallback output)
    // ---------------------------------------------------------------------

    /** @return array<string, array{mixed, string}> */
    public static function hostileSubmitLabels(): array
    {
        return [
            'attribute break'     => ['"><svg onload=alert(1)>', '<svg'],
            'single-quote break'  => ["' autofocus onfocus=alert(1) x='", "' autofocus"],
            'raw angle brackets'  => ['<b>Delete</b>', '<b>'],
        ];
    }

    #[Test]
    #[DataProvider('hostileSubmitLabels')]
    public function submitLabelIsEscapedForAttributes(mixed $label, string $mustNotAppear): void
    {
        $html = xoops_confirm_submit_label($label, 'Submit');

        self::assertStringNotContainsString($mustNotAppear, $html);
        self::assertStringNotContainsString('"', $html);
    }

    #[Test]
    public function submitLabelKeepsEntitiesFromLanguageConstants(): void
    {
        self::assertSame('L&ouml;schen', xoops_confirm_submit_label('L&ouml;schen', 'Submit'));
    }

    /** @return array<string, array{mixed}> */
    public static function emptySubmitLabels(): array
    {
        return [
            'empty string' => [''],
            'whitespace'   => ["  \t "],
            'null'         => [null],
            'array'        => [['Delete']],
        ];
    }

    #[Test]
    #[DataProvider('emptySubmitLabels')]
    public function emptySubmitLabelUsesTheEscapedDefault(mixed $label): void
    {
        self::assertSame('Sub&quot;mit', xoops_confirm_submit_label($label, 'Sub"mit'));
    }

    #[Test]
    public function submitLabelIsTrimmedAndKeepsTextAroundInvalidUtf8(): void
    {
        self::assertSame('Delete', xoops_confirm_submit_label('  Delete ', 'Submit'));
        self::assertSame("Delete \u{FFFD} now", xoops_confirm_submit_label("Delete \xFF now", 'Submit'));
    }

    #[Test]
    public function xoopsConfirmEscapesTheSubmitLabelForTemplateAndFallback(): void
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/include/functions.php');
        self::assertNotFalse($src);

        self::assertSame(
            1,
            preg_match('/\$submit\s*=\s*xoops_confirm_submit_label\(\s*\$submit\s*,\s*_SUBMIT\s*\);\s*\$confirmTpl->assign\(\s*\'submit\'\s*,\s*\$submit\s*\)/', $src),
            'xoops_confirm() must escape the submit label before assigning it to the template.'
        );
        self::assertSame(
            0,
            preg_match('/trim\(\s*\$submit\s*\)\s*:\s*_SUBMIT/', $src),
            'A second, separate submit-label computation remains in xoops_confirm().'
        );
    }

    /**
     * Every confirm template shipped with core.
     *
     * @return array<string, array{string}>
     */
    public static function confirmTemplates(): array
    {
        $templates = array_merge(
            glob(XOOPS_ROOT_PATH . '/modules/system/templates/system_confirm.tpl') ?: [],
            glob(XOOPS_ROOT_PATH . '/themes/*/modules/system/system_confirm.tpl') ?: []
        );
        $cases = [];
        foreach ($templates as $path) {
            $relPath         = substr($path, strlen(XOOPS_ROOT_PATH) + 1);
            $cases[$relPath] = [$relPath];
        }

        return $cases;
    }

    #[Test]
    public function coreShipsTheExpectedConfirmTemplates(): void
    {
        // system plus the xbootstrap5 and xswatch5 theme copies
        self::assertGreaterThanOrEqual(3, count(self::confirmTemplates()));
    }

    /**
     * xoops_confirm() hands the template an already escaped label, so the
     * template must print it as is: an |escape modifier would encode it a
     * second time (an apostrophe would show as &apos;).
     */
    #[Test]
    #[DataProvider('confirmTemplates')]
    public function confirmTemplatePrintsTheEscapedSubmitLabelUnchanged(string $relPath): void
    {
        $tpl = file_get_contents(XOOPS_ROOT_PATH . '/' . $relPath);
        self::assertNotFalse($tpl);

        self::assertGreaterThanOrEqual(1, substr_count($tpl, '<{$submit}>'), "$relPath does not print \$submit.");
        self::assertSame(
            0,
            preg_match('/\$submit\s*\|/', $tpl),
            "$relPath applies a modifier to \$submit, which xoops_confirm() has already escaped."
        );
    }

    // ---------------------------------------------------------------------
    // Source pins: the flagged call sites use the helper
    // ---------------------------------------------------------------------

    #[Test]
    public function xoopsConfirmDelegatesBothBranchesToHelper(): void
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/include/functions.php');
        self::assertNotFalse($src);

        self::assertTrue(str_contains($src, 'xoops_confirm_fields('), 'xoops_confirm() does not use xoops_confirm_fields().');
        self::assertSame(
            0,
            preg_match('/\/>\s*\'\s*\.\s*\$caption\b/', $src),
            'xoops_confirm() still concatenates a raw $caption after a radio input.'
        );
        self::assertSame(
            0,
            preg_match('/name="\'\s*\.\s*\$name\s*\./', $src),
            'xoops_confirm() still concatenates a raw $name into an attribute.'
        );
    }

    /**
     * The selection parsing and the confirmation round trip are executed in
     * NotificationSelectionTest; these pins check that notifications.php
     * actually uses those helpers for both steps.
     */
    #[Test]
    public function notificationsNormalisesDelNotBeforeConfirm(): void
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/notifications.php');
        self::assertNotFalse($src);

        self::assertSame(
            0,
            preg_match('/\$del_not\s*=\s*Request::getArray\(/', $src),
            'notifications.php still reads del_not raw.'
        );
        self::assertSame(
            2,
            preg_match_all('/\$del_not\s*=\s*xoops_notification_selection\(\s*Request::getArray\(\s*\'del_not\'/', $src),
            'Both the confirm and the delete step must read del_not through xoops_notification_selection().'
        );
        self::assertSame(
            0,
            preg_match('/\'del_not\'\s*=>/', $src),
            'A nested del_not array must not reach xoops_confirm(), which renders arrays as radio groups.'
        );
    }

    #[Test]
    public function notificationsConfirmSubmitsToDeleteStep(): void
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/notifications.php');
        self::assertNotFalse($src);

        // A hidden "delete_ok" would send the confirmation back to itself.
        self::assertSame(
            1,
            preg_match('/case\s+\'delete_ok\'.*?\$hidden_vars\s*=\s*\[\s*\'delete\'\s*=>\s*1\s*\]\s*\+\s*xoops_notification_confirm_fields\(\s*\$del_not\s*\).*?xoops_confirm\(\s*\$hidden_vars\b/s', $src)
        );
    }

    #[Test]
    public function notificationsDeleteStepToleratesMissingNotification(): void
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/notifications.php');
        self::assertNotFalse($src);

        self::assertSame(
            1,
            preg_match('/case\s+\'delete\'.*?\$notification\s*=\s*\$notification_handler->get\(.*?is_object\(\$notification\)/s', $src),
            'An unknown notification id must not call getVar() on a non-object.'
        );
    }

    /** @return array<string, array{string}> */
    public static function imageManagerFiles(): array
    {
        return [
            'tinymce5' => ['class/xoopseditor/tinymce5/js/tinymce/plugins/xoopsimagemanager/xoopsimagemanager.php'],
            'tinymce7' => ['class/xoopseditor/tinymce7/js/tinymce/plugins/xoopsimagemanager/xoopsimagemanager.php'],
        ];
    }

    /**
     * $target stays raw for xoops_confirm() (which escapes it once) and is
     * URL-encoded for the ?target= links; an up-front htmlspecialchars() was
     * what xoops_confirm() double-encoded.
     */
    #[Test]
    #[DataProvider('imageManagerFiles')]
    public function imageManagerDoesNotPreEscapeTarget(string $relPath): void
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/' . $relPath);
        self::assertNotFalse($src);

        self::assertSame(
            0,
            preg_match('/\$target\s*=\s*htmlspecialchars\(\s*\$target\b/', $src),
            "$relPath pre-escapes \$target; xoops_confirm() escapes it again."
        );
        self::assertSame(
            0,
            preg_match('/\?target=\'\s*\.\s*\$target\b/', $src),
            "$relPath puts the raw \$target into a ?target= link; use the URL-encoded value."
        );
    }
}
